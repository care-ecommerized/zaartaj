<?php

namespace App\Http\Controllers;

use App\Delivery\ShipmentDispatcher;
use App\Delivery\SteadfastStatusMap;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives Steadfast's delivery status push.
 *
 * Steadfast retries on any non-2xx, so anything we cannot act on is answered
 * with a 2xx and logged instead of being left to retry forever.
 */
class SteadfastWebhookController extends Controller
{
    public function __construct(private readonly ShipmentDispatcher $dispatcher) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->authentic($request)) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'consignment_id' => ['required'],
            'invoice' => ['nullable', 'string'],
            'status' => ['required', 'string'],
            'cod_amount' => ['nullable', 'numeric'],
            'updated_at' => ['nullable', 'string'],
        ]);

        $shipment = Shipment::where('courier', 'steadfast')
            ->where(function ($query) use ($validated) {
                $query->where('consignment_id', (string) $validated['consignment_id']);

                if (! empty($validated['invoice'])) {
                    $query->orWhere('invoice', $validated['invoice']);
                }
            })
            ->first();

        if (! $shipment) {
            Log::warning('Steadfast webhook for an unknown shipment', $validated);

            return response()->json(['status' => 200, 'message' => 'Unknown consignment, ignored.']);
        }

        $this->dispatcher->applyStatus(
            $shipment,
            SteadfastStatusMap::toShipmentStatus($validated['status']),
            $validated['status'],
            $validated,
        );

        return response()->json(['status' => 200, 'message' => 'Processed']);
    }

    /**
     * Steadfast signs the push with a static bearer token we generate in their
     * portal. Compared in constant time so the endpoint is not an oracle.
     */
    private function authentic(Request $request): bool
    {
        $expected = config('delivery.couriers.steadfast.webhook_token');

        if (blank($expected)) {
            Log::error('Steadfast webhook received but STEADFAST_WEBHOOK_TOKEN is not set.');

            return false;
        }

        return hash_equals($expected, (string) $request->bearerToken());
    }
}
