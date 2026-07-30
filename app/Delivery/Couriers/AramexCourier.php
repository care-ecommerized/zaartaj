<?php

namespace App\Delivery\Couriers;

use App\Delivery\Contracts\Courier;
use App\Delivery\Exceptions\DeliveryException;
use App\Delivery\ShipmentRequest;
use App\Delivery\ShipmentResult;
use App\Delivery\TrackingResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Aramex Shipping Services (JSON REST, ShippingAPI.V2).
 *
 * Flow: create a shipment -> Aramex returns an AWB number that is both the
 * booking id and the tracking number -> we poll TrackShipments (via the
 * scheduled shipments:reconcile command) for status, since Aramex has no
 * status-push webhook.
 *
 * Every request carries a ClientInfo credential block in its body rather than
 * headers. Two things the shared Courier contract asks for have no Aramex
 * equivalent — a COD account balance and tracking by our own invoice — so both
 * throw a clear "unsupported" exception.
 */
class AramexCourier implements Courier
{
    private const CREATE_PATH = '/ShippingAPI.V2/Shipping/Service_1_0.svc/json/CreateShipments';

    private const TRACK_PATH = '/ShippingAPI.V2/Tracking/Service_1_0.svc/json/TrackShipments';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'aramex';
    }

    public function createShipment(ShipmentRequest $request): ShipmentResult
    {
        return $this->createShipments([$request])[0]
            ?? ShipmentResult::failure($request->invoice, 'Aramex returned no result for this shipment.');
    }

    public function createShipments(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        // Don't throw on the envelope's HasErrors here: Aramex flags the whole
        // response when *any* shipment fails, yet still returns the ones that
        // succeeded. Per-shipment errors are handled row by row below.
        $body = $this->request(self::CREATE_PATH, [
            'Shipments' => array_map(fn (ShipmentRequest $r) => $this->buildShipmentPayload($r), $requests),
            'LabelInfo' => null,
        ], throwOnErrors: false);

        // The request key is `Shipments`; the response returns `ProcessedShipments`.
        $rows = array_values($body['ProcessedShipments'] ?? []);

        // No processed rows at all with a top-level error means the whole call
        // failed (bad credentials, malformed request) — surface it.
        if ($rows === [] && ($body['HasErrors'] ?? false)) {
            throw DeliveryException::rejected($this->name(), 200, [
                'message' => $this->notificationText($body['Notifications'] ?? []) ?: 'request failed',
            ]);
        }

        return array_values(array_map(function (ShipmentRequest $request, int $index) use ($rows) {
            $row = $rows[$index] ?? null;

            if (! is_array($row)) {
                return ShipmentResult::failure($request->invoice, 'Aramex returned no result for this shipment.');
            }

            // A per-shipment failure carries its own HasErrors flag / Notifications,
            // even when the overall request succeeded.
            if (($row['HasErrors'] ?? false) || ! isset($row['ID']) || blank($row['ID'])) {
                return ShipmentResult::failure(
                    $request->invoice,
                    $this->notificationText($row['Notifications'] ?? []) ?: 'Aramex rejected this shipment.',
                    $row
                );
            }

            return ShipmentResult::fromAramexShipment($row, $request->invoice);
        }, $requests, array_keys($requests)));
    }

    public function trackByConsignmentId(string $consignmentId): TrackingResult
    {
        $body = $this->request(self::TRACK_PATH, [
            'Shipments' => [$consignmentId],
            'GetLastTrackingUpdateOnly' => false,
        ]);

        // TrackingResults is keyed by AWB; each value is that AWB's update rows.
        $results = $body['TrackingResults'] ?? [];
        $updates = [];

        foreach ($results as $entry) {
            // Aramex serialises the map as [{Key: awb, Value: [updates]}, ...].
            $value = $entry['Value'] ?? $entry;
            if (is_array($value)) {
                $updates = $value;
            }
        }

        return TrackingResult::fromAramex($updates);
    }

    public function trackByInvoice(string $invoice): TrackingResult
    {
        throw DeliveryException::unsupported($this->name(), 'tracking by invoice (track by the AWB number instead)');
    }

    public function balance(): float
    {
        throw DeliveryException::unsupported($this->name(), 'a COD balance endpoint');
    }

    /**
     * Assemble one Aramex Shipment object from our request plus config defaults.
     *
     * @return array<string, mixed>
     */
    private function buildShipmentPayload(ShipmentRequest $request): array
    {
        $originCountry = strtoupper((string) ($this->config['origin_country'] ?? 'BD'));
        $destinationCountry = strtoupper($request->recipientCountry ?: $originCountry);

        // Domestic when the parcel stays in the origin country, else international.
        $isDomestic = $destinationCountry === $originCountry;
        $productGroup = $isDomestic ? 'DOM' : 'EXP';
        $productType = $isDomestic
            ? ($this->config['default_product_type_dom'] ?? 'OND')
            : ($this->config['default_product_type_exp'] ?? 'PPX');

        // Collect from the recipient only when there is money to collect.
        $isCod = $request->codAmount > 0;

        $details = [
            'ActualWeight' => ['Value' => (float) ($this->config['default_weight'] ?? 0.5), 'Unit' => 'KG'],
            'NumberOfPieces' => (int) ($this->config['default_pieces'] ?? 1),
            'ProductGroup' => $productGroup,
            'ProductType' => $productType,
            'PaymentType' => $isCod ? 'C' : 'P',
            'DescriptionOfGoods' => $request->note ?: ($this->config['default_description'] ?? 'Goods'),
            'GoodsOriginCountry' => $originCountry,
            'CashOnDeliveryAmount' => $isCod
                ? ['Value' => round($request->codAmount, 2), 'CurrencyCode' => config('delivery.currency', 'BDT')]
                : null,
        ];

        // International express must declare a customs value.
        if (! $isDomestic) {
            $details['CustomsValueAmount'] = [
                'Value' => (float) ($this->config['default_customs_value'] ?? 0),
                'CurrencyCode' => config('delivery.currency', 'BDT'),
            ];
        }

        return [
            'Reference1' => $request->invoice,
            'ForeignHAWBNumber' => $request->invoice,
            'Shipper' => $this->shipper(),
            'Consignee' => [
                'Reference1' => $request->invoice,
                'PartyAddress' => [
                    'Line1' => $request->recipientAddress,
                    'City' => $request->recipientCity ?: '',
                    'PostCode' => $request->recipientPostcode ?: '',
                    'CountryCode' => $destinationCountry,
                ],
                'Contact' => [
                    'PersonName' => $request->recipientName,
                    'CompanyName' => $request->recipientName,
                    'PhoneNumber1' => $request->recipientPhone,
                    'CellPhone' => $request->recipientPhone,
                    'EmailAddress' => $request->recipientEmail ?: '',
                ],
            ],
            'ShippingDateTime' => '/Date('.(time() * 1000).')/',
            'Details' => array_filter($details, fn ($value) => $value !== null),
        ];
    }

    /**
     * The fixed origin / warehouse party, from config.
     *
     * @return array<string, mixed>
     */
    private function shipper(): array
    {
        $shipper = $this->config['shipper'] ?? [];

        return [
            'Reference1' => $shipper['reference'] ?? '',
            'AccountNumber' => $this->config['client_info']['AccountNumber'] ?? '',
            'PartyAddress' => [
                'Line1' => $shipper['line1'] ?? '',
                'City' => $shipper['city'] ?? '',
                'PostCode' => $shipper['postcode'] ?? '',
                'CountryCode' => strtoupper((string) ($shipper['country_code'] ?? $this->config['origin_country'] ?? 'BD')),
            ],
            'Contact' => [
                'PersonName' => $shipper['name'] ?? '',
                'CompanyName' => $shipper['company'] ?? ($shipper['name'] ?? ''),
                'PhoneNumber1' => $shipper['phone'] ?? '',
                'CellPhone' => $shipper['phone'] ?? '',
                'EmailAddress' => $shipper['email'] ?? '',
            ],
        ];
    }

    /**
     * The ClientInfo credential block Aramex expects in every request body.
     *
     * @return array<string, mixed>
     */
    private function clientInfo(): array
    {
        $info = $this->config['client_info'] ?? [];

        return [
            'UserName' => $info['UserName'] ?? null,
            'Password' => $info['Password'] ?? null,
            'Version' => $info['Version'] ?? 'v1.0',
            'AccountNumber' => $info['AccountNumber'] ?? null,
            'AccountPin' => $info['AccountPin'] ?? null,
            'AccountEntity' => $info['AccountEntity'] ?? null,
            'AccountCountryCode' => $info['AccountCountryCode'] ?? null,
            'Source' => $info['Source'] ?? 24,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $path, array $payload, bool $throwOnErrors = true): array
    {
        $payload = ['ClientInfo' => $this->clientInfo(), 'Transaction' => null] + $payload;

        try {
            $response = $this->client()->post($path, $payload);
        } catch (ConnectionException $e) {
            throw DeliveryException::fromCourier($this->name(), "could not be reached: {$e->getMessage()}");
        }

        $body = $response->json();

        if (! is_array($body)) {
            $body = ['message' => $response->body()];
        }

        // A transport failure is always fatal. Aramex's own HasErrors flag is
        // fatal only when the caller asked us to enforce it — createShipments
        // resolves partial failures per shipment instead.
        if ($response->failed() || ($throwOnErrors && ($body['HasErrors'] ?? false))) {
            Log::error('Aramex request failed', ['path' => $path, 'body' => $response->body()]);

            throw DeliveryException::rejected(
                $this->name(),
                $response->failed() ? $response->status() : 200,
                ['message' => $this->notificationText($body['Notifications'] ?? []) ?: 'request failed'],
            );
        }

        return $body;
    }

    /**
     * Flatten Aramex's Notifications array into a single readable message.
     *
     * @param  array<int, array<string, mixed>>  $notifications
     */
    private function notificationText(array $notifications): string
    {
        return collect($notifications)
            ->map(fn ($n) => trim((string) ($n['Code'] ?? '').' '.($n['Message'] ?? '')))
            ->filter()
            ->implode('; ');
    }

    private function client(): PendingRequest
    {
        $info = $this->config['client_info'] ?? [];

        if (blank($info['UserName'] ?? null) || blank($info['Password'] ?? null) || blank($info['AccountNumber'] ?? null)) {
            throw DeliveryException::notConfigured($this->name());
        }

        return Http::asJson()
            ->acceptJson()
            ->baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->timeout((int) ($this->config['timeout'] ?? 20))
            // Only connection-level failures are worth retrying; a rejected
            // shipment fails identically on the next attempt.
            ->retry(
                (int) ($this->config['retries'] ?? 2),
                200,
                fn ($exception) => $exception instanceof ConnectionException,
                throw: false
            );
    }
}
