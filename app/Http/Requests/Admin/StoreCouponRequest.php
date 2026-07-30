<?php

namespace App\Http\Requests\Admin;

use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * Normalise the code to a canonical uppercase form before validating its
     * uniqueness, so "SAVE10" and "save10" are the same coupon.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isPercent = $this->input('type') === Coupon::TYPE_PERCENT;

        return [
            'code' => ['required', 'string', 'max:60', $this->uniqueCode()],
            'type' => ['required', Rule::in([Coupon::TYPE_PERCENT, Coupon::TYPE_FIXED])],
            'value' => ['required', 'numeric', 'gt:0', ...($isPercent ? ['max:100'] : [])],
            // Only fixed coupons carry a currency; null means the AED base.
            'currency' => ['nullable', 'string', 'size:3'],
            'min_subtotal' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * The uniqueness rule for the code, overridable by the update request.
     */
    protected function uniqueCode(): object
    {
        return Rule::unique('coupons', 'code');
    }
}
