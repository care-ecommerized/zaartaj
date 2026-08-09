<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the store settings form. Fields arrive flat, named by their dotted
 * canonical key (e.g. input name `store.name`), which Laravel exposes to the
 * validator as nested `store.name` rules.
 */
class UpdateSettingsRequest extends FormRequest
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
            // Store identity & contact.
            'store.name' => ['required', 'string', 'max:255'],
            'store.email' => ['nullable', 'email', 'max:255'],
            'store.phone' => ['nullable', 'string', 'max:255'],
            'store.address' => ['nullable', 'string', 'max:255'],
            'store.city' => ['nullable', 'string', 'max:255'],
            'store.country' => ['nullable', 'string', 'size:2'],

            // Social links.
            'social.facebook' => ['nullable', 'string', 'max:255'],
            'social.instagram' => ['nullable', 'string', 'max:255'],
            'social.youtube' => ['nullable', 'string', 'max:255'],
            'social.linkedin' => ['nullable', 'string', 'max:255'],

            // Store policies (EN/AR bodies).
            'policy.privacy.en' => ['nullable', 'string', 'max:2000'],
            'policy.privacy.ar' => ['nullable', 'string', 'max:2000'],
            'policy.terms.en' => ['nullable', 'string', 'max:2000'],
            'policy.terms.ar' => ['nullable', 'string', 'max:2000'],
            'policy.shipping.en' => ['nullable', 'string', 'max:2000'],
            'policy.shipping.ar' => ['nullable', 'string', 'max:2000'],
            'policy.returns.en' => ['nullable', 'string', 'max:2000'],
            'policy.returns.ar' => ['nullable', 'string', 'max:2000'],

            // Where new-order notifications are sent.
            'notifications.order_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
