<?php

namespace App\Delivery;

use App\Delivery\Enums\DeliveryType;
use App\Delivery\Exceptions\DeliveryException;
use App\Models\Shipment;
use Illuminate\Support\Str;

/**
 * One parcel, in our vocabulary. Couriers translate it on the way out.
 */
class ShipmentRequest
{
    public function __construct(
        public readonly string $invoice,
        public readonly string $recipientName,
        public readonly string $recipientPhone,
        public readonly string $recipientAddress,
        public readonly float $codAmount,
        public readonly ?string $note = null,
        public readonly DeliveryType $deliveryType = DeliveryType::Home,
        public readonly ?string $recipientEmail = null,
        public readonly ?string $recipientCity = null,
        public readonly ?string $recipientCountry = null,
        public readonly ?string $recipientPostcode = null,
    ) {}

    public static function fromShipment(Shipment $shipment): self
    {
        $order = $shipment->order;

        return new self(
            invoice: $shipment->invoice,
            recipientName: $order->customer_name,
            // Carry the number as the customer typed it. Each courier normalises
            // at its own wire boundary — the BD-only rule lives in Steadfast's
            // payload, not here, so international recipients are not rejected.
            recipientPhone: $order->customer_phone,
            recipientAddress: $order->customer_address,
            codAmount: (float) $shipment->cod_amount,
            note: $shipment->note,
            deliveryType: $shipment->delivery_type ?? DeliveryType::Home,
            recipientEmail: $order->customer_email,
            recipientCity: $order->customer_district,
            recipientCountry: $order->customer_country,
            recipientPostcode: $order->customer_postcode,
        );
    }

    /**
     * Reduce a Bangladeshi mobile number to the bare 11 digits couriers expect.
     *
     * @throws DeliveryException
     */
    public static function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        // Strip the country code in either of the forms customers type it.
        if (str_starts_with($digits, '880')) {
            $digits = '0'.substr($digits, 3);
        }

        if (! preg_match('/^01[3-9]\d{8}$/', $digits)) {
            throw new DeliveryException("\"{$phone}\" is not a valid Bangladeshi mobile number.");
        }

        return $digits;
    }

    /**
     * Steadfast's own field names. Optional keys are dropped rather than sent
     * as null, which their validator rejects.
     *
     * @return array<string, scalar>
     */
    public function toSteadfastPayload(): array
    {
        return array_filter([
            'invoice' => $this->invoice,
            'recipient_name' => Str::limit($this->recipientName, 100, ''),
            'recipient_phone' => self::normalisePhone($this->recipientPhone),
            'recipient_address' => Str::limit($this->recipientAddress, 250, ''),
            'cod_amount' => round($this->codAmount, 2),
            'note' => $this->note,
            'delivery_type' => $this->deliveryType->value,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
