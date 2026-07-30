<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Models\Coupon;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff management of discount coupons. Codes are stored uppercase (the form
 * requests normalise them) so lookups at checkout are case-insensitive.
 */
class CouponController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/coupons/index', [
            'coupons' => Coupon::query()
                ->orderByDesc('is_active')
                ->orderBy('code')
                ->get()
                ->map(fn (Coupon $coupon) => $this->present($coupon)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/coupons/create', $this->formOptions());
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        Coupon::create($this->attributes($request->validated()));

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon created.');
    }

    public function edit(Coupon $coupon): Response
    {
        return Inertia::render('admin/coupons/edit', [
            ...$this->formOptions(),
            'coupon' => [
                ...$this->present($coupon),
                'starts_at' => $coupon->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $coupon->ends_at?->format('Y-m-d\TH:i'),
                'min_subtotal' => $coupon->min_subtotal !== null ? (float) $coupon->min_subtotal : null,
            ],
        ]);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->attributes($request->validated()));

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon deleted.');
    }

    /**
     * Shape the validated input into persistable attributes. A percent coupon
     * never carries a currency; a fixed one keeps its uppercased code (null = base).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $isPercent = $data['type'] === Coupon::TYPE_PERCENT;

        return [
            'code' => $data['code'],
            'type' => $data['type'],
            'value' => $data['value'],
            'currency' => $isPercent ? null : ($data['currency'] ? strtoupper($data['currency']) : null),
            'min_subtotal' => $data['min_subtotal'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'per_user_limit' => $data['per_user_limit'] ?? null,
            'is_active' => $data['is_active'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => (float) $coupon->value,
            'currency' => $coupon->currency,
            'min_subtotal' => $coupon->min_subtotal !== null ? (float) $coupon->min_subtotal : null,
            'starts_at' => $coupon->starts_at?->toIso8601String(),
            'ends_at' => $coupon->ends_at?->toIso8601String(),
            'usage_limit' => $coupon->usage_limit,
            'per_user_limit' => $coupon->per_user_limit,
            'times_used' => $coupon->times_used,
            'is_active' => $coupon->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'types' => [Coupon::TYPE_PERCENT, Coupon::TYPE_FIXED],
            'baseCurrency' => config('payment.currency'),
            'currencyOptions' => Currency::query()
                ->active()
                ->orderByDesc('is_base')
                ->orderBy('code')
                ->pluck('code')
                ->all(),
        ];
    }
}
