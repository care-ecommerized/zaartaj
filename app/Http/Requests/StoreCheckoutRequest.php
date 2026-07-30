<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Uppercase the destination country so the conditional rules and the ISO-2
     * match against a canonical form.
     */
    protected function prepareForValidation(): void
    {
        $customer = $this->input('customer');

        if (is_array($customer) && isset($customer['country'])) {
            $customer['country'] = strtoupper(trim((string) $customer['country']));
            $this->merge(['customer' => $customer]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isBd = strtoupper((string) $this->input('customer.country')) === 'BD';

        return [
            'customer.name' => ['required', 'string', 'max:120'],

            // Every destination resolves to a zone (a catch-all always exists),
            // so any well-formed ISO-3166-1 alpha-2 code is accepted.
            'customer.country' => ['required', 'string', 'size:2'],

            // Bangladeshi numbers keep the strict mobile rule; everywhere else a
            // permissive international form (E.164-ish) is accepted.
            'customer.phone' => $isBd
                ? ['required', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/']
                : ['required', 'string', 'regex:/^\+?[1-9]\d{6,14}$/'],

            'customer.email' => ['nullable', 'email', 'max:190'],
            'customer.address' => ['required', 'string', 'max:500'],

            // District is a Bangladesh concept; only required, and only validated
            // against the BD list, when the destination is Bangladesh.
            'customer.district' => $isBd
                ? ['required', 'string', Rule::in(config('checkout.districts'))]
                : ['nullable', 'string', 'max:120'],

            // A postcode is needed for international couriers (e.g. Aramex) but BD
            // addresses are not postcoded, so it is optional there.
            'customer.postcode' => $isBd
                ? ['nullable', 'string', 'max:20']
                : ['required', 'string', 'max:20'],

            'customer.note' => ['nullable', 'string', 'max:500'],

            'payment_method' => ['required', Rule::in(config('checkout.payment_methods'))],

            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.slug' => ['required', 'string', 'max:255'],
            'items.*.size' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer.phone.regex' => 'Enter a valid phone number for your country.',
            'customer.district.in' => 'Choose your district from the list.',
            'customer.country.size' => 'Choose your country.',
            'customer.country.required' => 'Choose your country.',
            'customer.postcode.required' => 'A postcode is required for international delivery.',
        ];
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->input('payment_method'));
    }
}
