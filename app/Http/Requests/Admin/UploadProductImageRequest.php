<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ProductMediaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UploadProductImageRequest extends FormRequest
{
    use ProductMediaRules;

    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * Accepts either a batch (`images[]`, what the admin screen sends) or a
     * single `image`, which keeps existing one-at-a-time callers working.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'images' => ['required_without:image', 'array', 'min:1', 'max:'.$this->imageBatchMax()],
            'images.*' => $this->imageFileRules(),
            'image' => ['required_without:images', ...$this->imageFileRules()],
        ];
    }

    /**
     * The uploaded files, whichever field carried them.
     *
     * @return list<UploadedFile>
     */
    public function images(): array
    {
        $files = array_filter((array) $this->file('images', []));

        if ($single = $this->file('image')) {
            $files[] = $single;
        }

        return array_values($files);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mediaMessages();
    }
}
