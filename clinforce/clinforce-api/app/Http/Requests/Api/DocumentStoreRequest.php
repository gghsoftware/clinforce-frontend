<?php
/// app/Http/Requests/Api/DocumentStoreRequest.php
namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DocumentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'doc_type' => ['bail','required','string','max:60'],
            'file' => ['bail','required','file','max:10240','mimes:pdf,doc,docx,png,jpg,jpeg'],
        ];
    }

    public function messages(): array
    {
        return [
            'doc_type.required' => 'doc_type is required.',
            'file.required' => 'Please choose a file to upload.',
            'file.file' => 'The file field must be a file.',
            'file.mimes' => 'Allowed: pdf, doc, docx, png, jpg, jpeg.',
            'file.max' => 'Max file size is 10MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('doc_type')) {
            $this->merge([
                'doc_type' => strtolower(trim((string)$this->input('doc_type'))),
            ]);
        }
    }
}

