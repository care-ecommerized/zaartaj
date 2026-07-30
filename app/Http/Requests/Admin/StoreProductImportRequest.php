<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductImportRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                /*
                 * Exports saved from Excel are often sniffed as text/plain, and some
                 * browsers send application/vnd.ms-excel for a .csv, so the extension
                 * is checked separately rather than trusting the client's MIME type.
                 */
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel',
                'extensions:csv,txt',
                'max:51200',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimetypes' => 'Upload the Shopify export as a CSV file.',
            'file.extensions' => 'Upload the Shopify export as a CSV file.',
            'file.max' => 'The export may not be larger than 50 MB.',
        ];
    }
}
