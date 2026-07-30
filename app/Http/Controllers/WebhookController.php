<?php

namespace App\Http\Controllers;

use App\Payments\Contracts\HandlesWebhooks;
use App\Payments\Exceptions\PaymentException;
use App\Payments\PaymentGatewayManager;
use App\Payments\PaymentSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly PaymentSettlementService $settlement,
    ) {}

    /**
     * Receive an online gateway's server-to-server notification.
     *
     * The driver verifies the signature and tells us which payment the event is
     * about; we then ask the gateway for the authoritative status and settle.
     * The webhook body is never trusted for the outcome — only for identity.
     */
    public function handle(Request $request, string $gateway): JsonResponse
    {
        if (! in_array($gateway, $this->gateways->available(), true)) {
            return response()->json(['message' => 'Unknown gateway.'], 404);
        }

        $driver = $this->gateways->driver($gateway);

        if (! $driver instanceof HandlesWebhooks) {
            return response()->json(['message' => 'Gateway does not accept webhooks.'], 404);
        }

        try {
            $payment = $driver->handleWebhook($request);
        } catch (PaymentException $e) {
            Log::warning('Rejected webhook', ['gateway' => $gateway, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        // No matching payment, or an event we do not act on — acknowledge so the
        // gateway stops retrying.
        if (! $payment || $payment->isSettled()) {
            return response()->json(['message' => 'Acknowledged.']);
        }

        try {
            $this->settlement->settle($payment, $driver->verify($payment));
        } catch (PaymentException $e) {
            Log::error('Webhook settlement failed', ['gateway' => $gateway, 'payment_id' => $payment->id, 'error' => $e->getMessage()]);

            // Tell the gateway to retry; a transient verify() failure is recoverable.
            return response()->json(['message' => 'Retry later.'], 500);
        }

        return response()->json(['message' => 'Processed.']);
    }
}
