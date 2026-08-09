<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * Coerce is_visible to a real boolean so the checkbox's "on"/1/true/absent
     * forms all validate the same. Absent means the category defaults to visible.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_visible' => $this->boolean('is_visible', ! $this->has('is_visible')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', $this->uniqueSlug()],
            'description' => ['nullable', 'string'],
            // An admin-uploaded category photo. Optional on both create and edit;
            // on edit, `remove_image` clears the existing one without replacing it.
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.(int) config('catalog.image_max_kb')],
            'remove_image' => ['nullable', 'boolean'],
            'is_visible' => ['boolean'],
        ];
    }

    /**
     * The uniqueness rule for the slug, overridden by the update request so a
     * category can be saved without tripping over its own slug.
     */
    protected function uniqueSlug(): object
    {
        return Rule::unique('categories', 'slug');
    }
}
