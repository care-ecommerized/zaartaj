<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreShippingZoneRequest;
use App\Http\Requests\Admin\UpdateShippingZoneRequest;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff management of the worldwide shipping zones and their AED rates. Rates are
 * edited inline on the zone form and rewritten wholesale on save, which keeps the
 * UI simple and the persisted set an exact mirror of what staff submitted.
 */
class ShippingZoneController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/shipping/index', [
            'zones' => ShippingZone::query()
                ->withCount('rates')
                ->orderByDesc('priority')
                ->orderBy('name')
                ->get()
                ->map(fn (ShippingZone $zone) => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'countries' => array_map('strtoupper', $zone->countries ?? []),
                    'is_catch_all' => $zone->isCatchAll(),
                    'priority' => $zone->priority,
                    'is_active' => $zone->is_active,
                    'rates_count' => $zone->rates_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/shipping/create', $this->formOptions());
    }

    public function store(StoreShippingZoneRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $zone = ShippingZone::create([
                'name' => $data['name'],
                'countries' => array_map('strtoupper', $data['countries'] ?? []),
                'priority' => $data['priority'],
                'is_active' => $data['is_active'],
            ]);

            $this->syncRates($zone, $data['rates'] ?? []);
        });

        return redirect()->route('admin.shipping.index')->with('status', 'Shipping zone created.');
    }

    public function edit(ShippingZone $shipping): Response
    {
        $shipping->load('rates');

        return Inertia::render('admin/shipping/edit', [
            ...$this->formOptions(),
            'zone' => [
                'id' => $shipping->id,
                'name' => $shipping->name,
                'countries' => array_map('strtoupper', $shipping->countries ?? []),
                'priority' => $shipping->priority,
                'is_active' => $shipping->is_active,
                'rates' => $shipping->rates
                    ->sortByDesc('priority')
                    ->map(fn (ShippingRate $rate) => [
                        'method' => $rate->method,
                        'amount' => (float) $rate->amount,
                        'min_threshold' => $rate->min_threshold !== null ? (float) $rate->min_threshold : null,
                        'max_threshold' => $rate->max_threshold !== null ? (float) $rate->max_threshold : null,
                        'free_over' => $rate->free_over !== null ? (float) $rate->free_over : null,
                        'priority' => $rate->priority,
                    ])
                    ->values(),
            ],
        ]);
    }

    public function update(UpdateShippingZoneRequest $request, ShippingZone $shipping): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $shipping) {
            $shipping->update([
                'name' => $data['name'],
                'countries' => array_map('strtoupper', $data['countries'] ?? []),
                'priority' => $data['priority'],
                'is_active' => $data['is_active'],
            ]);

            $shipping->rates()->delete();
            $this->syncRates($shipping, $data['rates'] ?? []);
        });

        return redirect()->route('admin.shipping.index')->with('status', 'Shipping zone updated.');
    }

    public function destroy(ShippingZone $shipping): RedirectResponse
    {
        $shipping->delete();

        return redirect()->route('admin.shipping.index')->with('status', 'Shipping zone deleted.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rates
     */
    private function syncRates(ShippingZone $zone, array $rates): void
    {
        foreach ($rates as $rate) {
            $zone->rates()->create([
                'method' => $rate['method'],
                'amount' => $rate['amount'],
                'min_threshold' => $rate['min_threshold'] ?? null,
                'max_threshold' => $rate['max_threshold'] ?? null,
                'free_over' => $rate['free_over'] ?? null,
                'priority' => $rate['priority'] ?? 0,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'methods' => [
                ShippingRate::METHOD_FLAT,
                ShippingRate::METHOD_ORDER_VALUE,
                ShippingRate::METHOD_WEIGHT,
            ],
            'countryOptions' => collect(config('countries', []))
                ->map(fn (string $name, string $code) => ['code' => $code, 'name' => $name])
                ->sortBy('name')
                ->values()
                ->all(),
        ];
    }
}
