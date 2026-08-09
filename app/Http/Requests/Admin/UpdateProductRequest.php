<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateProductRequest extends StoreProductRequest
{
    /**
     * Ignore the product being edited when checking the handle is unique.
     */
    protected function uniqueHandle(): object
    {
        return Rule::unique('products', 'handle')->ignore($this->route('product'));
    }
}
