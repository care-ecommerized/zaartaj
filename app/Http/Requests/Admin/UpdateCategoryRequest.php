<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends StoreCategoryRequest
{
    /**
     * Ignore the category being edited when checking the slug is unique.
     */
    protected function uniqueSlug(): object
    {
        return Rule::unique('categories', 'slug')->ignore($this->route('category'));
    }
}
