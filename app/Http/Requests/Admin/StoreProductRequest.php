<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use App\Http\Requests\Admin\Concerns\ProductMediaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    use ProductMediaRules;

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
            'title' => ['required', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'handle' => ['nullable', 'string', 'max:255', $this->uniqueHandle()],
            'body_html' => ['nullable', 'string'],
            'body_html_ar' => ['nullable', 'string'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'product_type' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_title_ar' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:2000'],
            'seo_description_ar' => ['nullable', 'string', 'max:2000'],
            'option_name' => ['nullable', 'string', 'max:60'],

            'variants' => ['required', 'array', 'min:1'],
            'variants.*.option_value' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.sku' => ['nullable', 'string', 'max:255'],
            'variants.*.weight' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['required', 'integer', 'min:0'],

            // Media picked on the form itself, attached once the product exists.
            'images' => ['nullable', 'array', 'max:'.$this->imageBatchMax()],
            'images.*' => $this->imageFileRules(),
            'video' => ['nullable', ...$this->videoFileRules()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mediaMessages();
    }

    /**
     * The handle uniqueness rule, overridden on update to ignore the edited row.
     */
    protected function uniqueHandle(): object
    {
        return Rule::unique('products', 'handle');
    }
}
