<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Payments\Exceptions\PaymentException;
use App\Payments\PaymentGatewayManager;
use App\Payments\PaymentInitiator;
use App\Payments\PaymentResult;
use App\Payments\PaymentSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly PaymentInitiator $initiator,
        private readonly PaymentSettlementService $settlement,
    ) {}

    public function create(Request $request): Response
    {
        // The storefront cart hands the total over as a query string.
        $amount = $request->query('amount');
        $base = is_numeric($amount) ? round((float) $amount, 2) : null;

        // Only card gateways work here; BNPL providers need full order context.
        $gateways = collect($this->gateways->standaloneCapable())
            ->map(fn (string $name) => [
                'value' => $name,
                'currency' => $this->gateways->currencyFor($name),
                'amount' => $base === null ? null : $this->gateways->convert($base, $this->gateways->currencyFor($name)),
            ])
            ->values();

        return Inertia::render('payments/checkout', [
            'gateways' => $gateways,
            'baseCurrency' => config('payment.currency'),
            'baseAmount' => $base,
        ]);
    }

    /**
     * Create the local payment record and hand the customer off to the gateway.
     */
    public function store(Request $request): SymfonyResponse
    {
        $validated = $request->validate([
            'gateway' => ['required', Rule::in($this->gateways->standaloneCapable())],
            'amount' => ['required', 'numeric', 'min:1', 'max:500000'],
            'payer_reference' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $redirectUrl = $this->initiator->startStandalone(
                (float) $validated['amount'],
                $validated['gateway'],
                $request->user()?->id,
                $validated['payer_reference'] ?? null,
            );
        } catch (PaymentException $e) {
            Log::error('Payment initiation failed', ['gateway' => $validated['gateway'], 'error' => $e->getMessage()]);

            return back()->withErrors(['gateway' => 'Could not start the payment. Please try again.']);
        }

        // Inertia needs an explicit external redirect (409 + X-Inertia-Location).
        return Inertia::location($redirectUrl);
    }

    /**
     * Where the gateway sends the customer back to. Treated as untrusted input:
     * the driver re-confirms the outcome with the gateway before we settle.
     */
    public function callback(Request $request, Payment $payment): RedirectResponse
    {
        if ($payment->isSettled()) {
            return $this->afterSettle($payment);
        }

        try {
            $result = $this->gateways->driver($payment->gateway)
                ->finalize($payment, $request->all());
        } catch (PaymentException $e) {
            Log::error('Payment finalization failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);

            $result = PaymentResult::failure('We could not confirm this payment.');
        }

        $this->settlement->settle($payment, $result);

        return $this->afterSettle($payment);
    }

    public function show(Payment $payment): Response
    {
        return Inertia::render('payments/status', [
            'payment' => $payment->only([
                'reference', 'gateway', 'amount', 'currency', 'status',
                'gateway_transaction_id', 'failure_reason', 'paid_at',
            ]),
        ]);
    }

    /**
     * Re-check an abandoned payment against the gateway (callback never arrived).
     */
    public function reconcile(Payment $payment): RedirectResponse
    {
        if (! $payment->isSettled()) {
            $this->settlement->settle($payment, $this->gateways->driver($payment->gateway)->verify($payment));
        }

        return $this->afterSettle($payment);
    }

    /**
     * A payment made through checkout returns the customer to their order; a
     * standalone one lands on its own status page.
     */
    private function afterSettle(Payment $payment): RedirectResponse
    {
        if ($payment->order_id && $payment->order) {
            return redirect()->route('checkout.confirmation', $payment->order);
        }

        return redirect()->route('payments.show', $payment);
    }
}
