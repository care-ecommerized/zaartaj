<?php

namespace App\Http\Requests\Admin;

use App\Models\ShippingRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShippingZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],

            // An empty list marks the catch-all zone.
            'countries' => ['nullable', 'array'],
            'countries.*' => ['string', 'size:2'],

            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['required', 'boolean'],

            'rates' => ['nullable', 'array', 'max:50'],
            'rates.*.method' => ['required', Rule::in([
                ShippingRate::METHOD_FLAT,
                ShippingRate::METHOD_WEIGHT,
                ShippingRate::METHOD_ORDER_VALUE,
            ])],
            'rates.*.amount' => ['required', 'numeric', 'min:0'],
            'rates.*.min_threshold' => ['nullable', 'numeric', 'min:0'],
            'rates.*.max_threshold' => ['nullable', 'numeric', 'min:0'],
            'rates.*.free_over' => ['nullable', 'numeric', 'min:0'],
            'rates.*.priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
