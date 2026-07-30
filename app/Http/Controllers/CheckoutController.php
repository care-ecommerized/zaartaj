<?php

namespace App\Http\Controllers;

use App\Checkout\CheckoutService;
use App\Checkout\CheckoutSessionService;
use App\Checkout\Exceptions\CheckoutException;
use App\Discounts\DiscountService;
use App\Enums\PaymentMethod;
use App\Http\Requests\StoreCheckoutRequest;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\ShippingZone;
use App\Orders\OrderPresenter;
use App\Payments\Exceptions\PaymentException;
use App\Payments\PaymentGatewayManager;
use App\Payments\PaymentInitiator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly CheckoutSessionService $sessions,
        private readonly PaymentInitiator $initiator,
        private readonly PaymentGatewayManager $gateways,
        private readonly OrderPresenter $presenter,
    ) {}

    /**
     * The checkout form. The bag itself is read client-side from localStorage and
     * posted with the order, so this only needs to supply the form's options.
     */
    public function show(Request $request): Response
    {
        // Resume an abandoned/open session: rehydrate the bag re-priced from the
        // catalogue (never the stored subtotal) and prefill the captured contact.
        $resume = $this->resumeState($request);

        return Inertia::render('shop/checkout', [
            'districts' => config('checkout.districts'),
            // Supported destination countries, and the active zones (with their
            // rates) so the client can mirror the server's shipping estimate.
            'countries' => $this->supportedCountries(),
            'shippingZones' => $this->shippingZonesForClient(),
            'paymentMethods' => collect(config('checkout.payment_methods'))
                ->map(fn (string $value) => [
                    'value' => $value,
                    'label' => PaymentMethod::from($value)->label(),
                    // Methods that settle abroad show the customer the charged
                    // currency; the frontend converts the cart total at this rate.
                    'currency' => $this->gateways->currencyFor($value),
                ])
                ->values(),
            'baseCurrency' => config('payment.currency'),
            'exchangeRates' => config('payment.exchange_rates'),
            'shipping' => config('checkout.shipping'),
            'dhakaDistricts' => config('checkout.dhaka_districts'),
            'prefill' => $request->user()?->only(['name', 'email']),
            // The signed-in customer's saved addresses, so the form can prefill
            // the delivery details from a chosen one. Empty for guests.
            'addresses' => $this->savedAddresses($request),
            // The promo code held in the session (if any), and the last resolved
            // discount flashed by applyCoupon so the summary can show its value.
            'appliedCouponCode' => $request->session()->get('checkout.coupon'),
            'couponResult' => $request->session()->get('coupon'),
            // A resumed bag (re-priced from the catalogue) and the captured
            // contact to prefill; null on a normal visit.
            'resumeCart' => $resume['cart'],
            'resumeContact' => $resume['contact'],
        ]);
    }

    /**
     * Capture (debounced) the shopper's contact + bag as a recoverable session.
     *
     * Called repeatedly as the form is filled, so it is deliberately cheap and
     * tolerant: loose validation, a browser-scoped `checkout_token` cookie that is
     * minted on first capture, and a no-content response so the form is never
     * disrupted. The cart is upserted, not appended, keyed by the cookie token.
     */
    public function captureSession(Request $request): SymfonyResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'currency' => ['nullable', 'string', 'size:3'],
            'locale' => ['nullable', 'string', 'max:5'],
            'items' => ['nullable', 'array', 'max:100'],
            'items.*.slug' => ['nullable', 'string', 'max:255'],
            'items.*.size' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $token = $request->cookie('checkout_token') ?: (string) Str::uuid();

        $this->sessions->capture($token, [
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'currency' => $validated['currency'] ?? null,
            'locale' => $validated['locale'] ?? null,
            'cart' => $validated['items'] ?? [],
        ], $request->user()?->id);

        // 30-day cookie so a returning shopper (and the recovery link) map back to
        // the same trace. Non-response-breaking: capture never renders anything.
        return response()->noContent()->cookie('checkout_token', $token, 60 * 24 * 30);
    }

    /**
     * Resolve the ?resume= token into a re-priced bag and prefill contact.
     *
     * @return array{cart: ?array<int, array<string, mixed>>, contact: ?array<string, mixed>}
     */
    private function resumeState(Request $request): array
    {
        $token = (string) $request->query('resume', '');

        if ($token === '') {
            return ['cart' => null, 'contact' => null];
        }

        $session = CheckoutSession::where('token', $token)
            ->where('status', '!=', CheckoutSession::STATUS_CONVERTED)
            ->first();

        if ($session === null) {
            return ['cart' => null, 'contact' => null];
        }

        // Mark a reminded session as recovered once its link is followed back.
        if ($session->status === CheckoutSession::STATUS_ABANDONED) {
            $session->forceFill(['status' => CheckoutSession::STATUS_RECOVERED])->save();
        }

        return [
            'cart' => $this->checkout->priceLinesForDisplay(is_array($session->cart) ? $session->cart : []),
            'contact' => [
                'email' => $session->email,
                'phone' => $session->phone,
            ],
        ];
    }

    /**
     * Apply (or re-validate) a promo code against the current bag.
     *
     * The subtotal is re-priced from the database here — a client-sent subtotal is
     * never trusted. A valid code is remembered in the session for place() to
     * redeem; an invalid one clears any held code and returns its reason.
     */
    public function applyCoupon(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:60'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.slug' => ['required', 'string', 'max:255'],
            'items.*.size' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $subtotal = $this->checkout->subtotalFor($validated['items']);
        } catch (CheckoutException $e) {
            $request->session()->forget('checkout.coupon');

            return back()->withErrors(['coupon' => $e->getMessage()]);
        }

        $result = app(DiscountService::class)->resolve(
            $validated['code'],
            $subtotal,
            $request->user()?->id,
            $request->user()?->email,
        );

        if (! $result->valid) {
            $request->session()->forget('checkout.coupon');

            return back()->withErrors(['coupon' => $result->message]);
        }

        $request->session()->put('checkout.coupon', $result->coupon->code);

        return back()->with('coupon', [
            'code' => $result->coupon->code,
            'amount' => $result->amount,
        ]);
    }

    /**
     * Drop any promo code held for this checkout.
     */
    public function removeCoupon(Request $request): RedirectResponse
    {
        $request->session()->forget('checkout.coupon');

        return back();
    }

    /**
     * Place the order. Cash-on-delivery finishes here; an online method hands the
     * customer to the gateway and settles the order when payment confirms.
     */
    public function store(StoreCheckoutRequest $request): SymfonyResponse
    {
        try {
            $order = $this->checkout->place(
                $request->input('items'),
                $request->input('customer'),
                $request->paymentMethod(),
                $request->user()?->id,
                $request->session()->get('checkout.coupon'),
            );
        } catch (CheckoutException $e) {
            return back()->withErrors([
                'checkout' => $e->getMessage(),
                ...$this->lineErrorBag($e),
            ]);
        }

        // Tie the browser's captured session (if any) to the placed order, so the
        // sweep never chases a checkout that actually converted.
        if ($token = $request->cookie('checkout_token')) {
            $this->sessions->markConverted($token, $order);
        }

        // The code has been redeemed onto the order; it must not linger for the next bag.
        $request->session()->forget('checkout.coupon');

        // Lets a guest reach the confirmation page for the order they just placed.
        $request->session()->put('recent_order', $order->order_number);

        if ($request->paymentMethod() === PaymentMethod::CashOnDelivery) {
            return redirect()->route('checkout.confirmation', $order);
        }

        return $this->beginOnlinePayment($request, $order);
    }

    /**
     * The order-received page. A signed-in owner can always see it; a guest may see
     * only the order they placed this session.
     */
    public function confirmation(Request $request, Order $order): Response
    {
        $owned = $request->user() && $order->user_id === $request->user()->id;
        $justPlaced = $request->session()->get('recent_order') === $order->order_number;

        abort_unless($owned || $justPlaced, 403);

        $order->load('items');

        return Inertia::render('shop/order-confirmation', [
            'order' => $this->presenter->present($order),
        ]);
    }

    private function beginOnlinePayment(StoreCheckoutRequest $request, Order $order): SymfonyResponse
    {
        try {
            $redirectUrl = $this->initiator->startForOrder(
                $order,
                $request->paymentMethod()->gateway(),
                $request->user()?->id,
            );
        } catch (PaymentException $e) {
            Log::error('Checkout payment initiation failed', ['order' => $order->order_number, 'error' => $e->getMessage()]);

            // The order stands; the customer can retry payment from its confirmation page.
            return redirect()
                ->route('checkout.confirmation', $order)
                ->with('warning', 'We could not start the online payment. You can retry, or we will collect on delivery.');
        }

        return Inertia::location($redirectUrl);
    }

    /**
     * The countries offered in the checkout picker, name-sorted.
     *
     * @return array<int, array{code: string, name: string}>
     */
    private function supportedCountries(): array
    {
        return collect(config('countries', []))
            ->map(fn (string $name, string $code) => ['code' => $code, 'name' => $name])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * A compact view of the active zones and their rates, so the checkout page
     * can preview the delivery charge the server will apply. Weight-method rates
     * are included but only ever match server-side, where the parcel weight is
     * known.
     *
     * @return array<int, array<string, mixed>>
     */
    private function shippingZonesForClient(): array
    {
        return ShippingZone::query()
            ->active()
            ->with('rates')
            ->orderByDesc('priority')
            ->get()
            ->map(fn (ShippingZone $zone) => [
                'countries' => array_map('strtoupper', $zone->countries ?? []),
                'priority' => $zone->priority,
                'rates' => $zone->rates
                    ->sortByDesc('priority')
                    ->map(fn ($rate) => [
                        'method' => $rate->method,
                        'amount' => (float) $rate->amount,
                        'min_threshold' => $rate->min_threshold !== null ? (float) $rate->min_threshold : null,
                        'max_threshold' => $rate->max_threshold !== null ? (float) $rate->max_threshold : null,
                        'free_over' => $rate->free_over !== null ? (float) $rate->free_over : null,
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function lineErrorBag(CheckoutException $e): array
    {
        $bag = [];

        foreach ($e->lineErrors as $index => $message) {
            $bag["items.{$index}"] = $message;
        }

        return $bag;
    }

    /**
     * The signed-in customer's saved addresses, shaped for the checkout prefill.
     *
     * @return array<int, array<string, mixed>>
     */
    private function savedAddresses(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return $user->addresses()
            ->orderByDesc('is_default')
            ->latest('id')
            ->get()
            ->map(fn ($address) => [
                'id' => $address->id,
                'label' => $address->label,
                'recipient' => $address->recipient_name,
                'phone' => $address->phone,
                'address_line' => $address->address_line,
                'city' => $address->city,
                'state' => $address->state,
                'postcode' => $address->postcode,
                'country' => $address->country,
                'is_default' => $address->is_default,
            ])
            ->all();
    }
}
