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
 * Steadfast Courier Limited (portal.steadfast.com.bd/api/v1).
 *
 * Flow: create a consignment -> Steadfast pushes status changes to our webhook
 * -> we reconcile anything the webhook missed with a status lookup.
 *
 * There is no sandbox. Against live credentials every created consignment is a
 * real parcel, so cancel what you create while testing.
 */
class SteadfastCourier implements Courier
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'steadfast';
    }

    public function createShipment(ShipmentRequest $request): ShipmentResult
    {
        $body = $this->request('post', '/create_order', $request->toSteadfastPayload());

        $consignment = $body['consignment'] ?? null;

        if (! is_array($consignment)) {
            throw DeliveryException::fromCourier(
                $this->name(),
                'accepted the request but returned no consignment.',
                ['invoice' => $request->invoice]
            );
        }

        return ShipmentResult::fromSteadfastConsignment($consignment, $request->invoice);
    }

    public function createShipments(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        $limit = (int) ($this->config['bulk_limit'] ?? 500);

        if (count($requests) > $limit) {
            throw DeliveryException::fromCourier(
                $this->name(),
                "accepts at most {$limit} consignments per bulk call, ".count($requests).' given.'
            );
        }

        // The bulk endpoint is the one place Steadfast wants the array
        // JSON-encoded into a single `data` field rather than sent as an array.
        $body = $this->request('post', '/create_order/bulk-order', [
            'data' => json_encode(array_map(
                fn (ShipmentRequest $request) => $request->toSteadfastPayload(),
                $requests
            )),
        ]);

        $rows = $body['data'] ?? [];

        return array_values(array_map(function (ShipmentRequest $request, int $index) use ($rows) {
            $row = $rows[$index] ?? null;

            if (! is_array($row)) {
                return ShipmentResult::failure($request->invoice, 'Steadfast returned no result for this consignment.');
            }

            // Per-row `status` here is "success"/"error", not a delivery status.
            if (($row['status'] ?? null) !== 'success') {
                return ShipmentResult::failure(
                    $request->invoice,
                    $row['message'] ?? 'Steadfast rejected this consignment.',
                    $row
                );
            }

            return ShipmentResult::fromSteadfastConsignment($row, $request->invoice);
        }, $requests, array_keys($requests)));
    }

    public function trackByConsignmentId(string $consignmentId): TrackingResult
    {
        return TrackingResult::fromSteadfast(
            $this->request('get', '/status_by_cid/'.rawurlencode($consignmentId))
        );
    }

    public function trackByInvoice(string $invoice): TrackingResult
    {
        return TrackingResult::fromSteadfast(
            $this->request('get', '/status_by_invoice/'.rawurlencode($invoice))
        );
    }

    public function balance(): float
    {
        return (float) ($this->request('get', '/get_balance')['current_balance'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        try {
            $response = $this->client()->{$method}($path, $payload);
        } catch (ConnectionException $e) {
            throw DeliveryException::fromCourier($this->name(), "could not be reached: {$e->getMessage()}");
        }

        $body = $response->json();

        if (! is_array($body)) {
            $body = ['message' => $response->body()];
        }

        // Steadfast answers HTTP 200 with a non-200 `status` field on validation
        // failures, so the envelope has to be checked as well as the HTTP code.
        $status = (int) ($body['status'] ?? 200);

        if ($response->failed() || $status !== 200) {
            Log::error('Steadfast request failed', ['path' => $path, 'body' => $response->body()]);

            throw DeliveryException::rejected(
                $this->name(),
                $response->failed() ? $response->status() : $status,
                $body
            );
        }

        return $body;
    }

    private function client(): PendingRequest
    {
        if (blank($this->config['api_key'] ?? null) || blank($this->config['secret_key'] ?? null)) {
            throw DeliveryException::notConfigured($this->name());
        }

        return Http::asJson()
            ->acceptJson()
            ->baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->withHeaders([
                'Api-Key' => $this->config['api_key'],
                'Secret-Key' => $this->config['secret_key'],
            ])
            ->timeout((int) ($this->config['timeout'] ?? 15))
            // Only connection-level failures are worth retrying. Retrying a
            // rejected consignment would duplicate it or fail identically.
            ->retry(
                (int) ($this->config['retries'] ?? 2),
                200,
                fn ($exception) => $exception instanceof ConnectionException,
                throw: false
            );
    }
}
