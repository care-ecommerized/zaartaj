<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    /**
     * List the customer's saved addresses.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('settings/addresses', [
            'addresses' => $request->user()->addresses()
                ->orderByDesc('is_default')
                ->latest('id')
                ->get()
                ->map(fn (Address $address) => $this->present($address))
                ->all(),
            'countries' => $this->countries(),
        ]);
    }

    /**
     * Save a new address for the customer.
     */
    public function store(StoreAddressRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $address = $request->user()->addresses()->create($request->safe()->except('is_default'));

            if ($request->boolean('is_default')) {
                $this->makeDefault($request, $address);
            }
        });

        return to_route('addresses.index');
    }

    /**
     * Update one of the customer's addresses.
     */
    public function update(UpdateAddressRequest $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        DB::transaction(function () use ($request, $address) {
            $address->update($request->safe()->except('is_default'));

            if ($request->boolean('is_default')) {
                $this->makeDefault($request, $address);
            }
        });

        return to_route('addresses.index');
    }

    /**
     * Remove one of the customer's addresses.
     */
    public function destroy(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $address->delete();

        return to_route('addresses.index');
    }

    /**
     * Flag $address as the sole default, clearing the flag on its siblings.
     */
    private function makeDefault(Request $request, Address $address): void
    {
        $request->user()->addresses()
            ->whereKeyNot($address->getKey())
            ->update(['is_default' => false]);

        $address->forceFill(['is_default' => true])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Address $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'address_line' => $address->address_line,
            'city' => $address->city,
            'state' => $address->state,
            'postcode' => $address->postcode,
            'country' => $address->country,
            'is_default' => $address->is_default,
        ];
    }

    /**
     * The supported destination countries, name-sorted, for the address form.
     *
     * @return array<int, array{code: string, name: string}>
     */
    private function countries(): array
    {
        return collect(config('countries', []))
            ->map(fn (string $name, string $code) => ['code' => $code, 'name' => $name])
            ->sortBy('name')
            ->values()
            ->all();
    }
}
