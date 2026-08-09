<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ProductMediaRules;
use Illuminate\Foundation\Http\FormRequest;

class UploadProductVideoRequest extends FormRequest
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
            'video' => ['required', ...$this->videoFileRules()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mediaMessages();
    }
}
