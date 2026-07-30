<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Orders\OrderPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(private readonly OrderPresenter $presenter) {}

    /**
     * The signed-in customer's order history.
     */
    public function index(Request $request): Response
    {
        $orders = $request->user()->orders()
            ->paginate(10)
            ->through(fn (Order $order) => [
                'order_number' => $order->order_number,
                'placed_at' => $order->placed_at?->toIso8601String(),
                'status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
                'payment_status' => $order->payment_status,
                'total' => (float) $order->total,
                'presentment_total' => $this->presentmentTotal($order),
                'currency' => $order->currency,
            ]);

        return Inertia::render('account/orders/index', [
            'orders' => $orders,
        ]);
    }

    /**
     * A single order the customer owns.
     */
    public function show(Request $request, Order $order): Response
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->load(['items', 'shipment']);

        return Inertia::render('account/orders/show', [
            'order' => $this->presenter->present($order),
        ]);
    }

    private function presentmentTotal(Order $order): float
    {
        try {
            return $order->presentmentTotal();
        } catch (\Throwable) {
            return (float) $order->total;
        }
    }
}
