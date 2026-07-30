<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateCouponRequest extends StoreCouponRequest
{
    /**
     * Ignore the coupon being edited when checking the code is unique.
     */
    protected function uniqueCode(): object
    {
        return Rule::unique('coupons', 'code')->ignore($this->route('coupon'));
    }
}
