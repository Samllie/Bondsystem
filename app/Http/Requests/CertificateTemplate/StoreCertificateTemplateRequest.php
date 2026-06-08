<?php

namespace App\Http\Requests\CertificateTemplate;

use App\Enums\CertificateTemplateType;
use App\Models\CertificateTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreCertificateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CertificateTemplate::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'template_name' => ['required', 'string', 'max:255'],
            'template_type' => ['required', Rule::enum(CertificateTemplateType::class)],
            'template' => [
                'required',
                File::default()
                    ->extensions(['docx'])
                    ->max(10 * 1024),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'template.required' => 'Please upload a DOCX template file.',
            'template.max' => 'The template may not be larger than 10 MB.',
            'template.extensions' => 'Only .docx files are allowed.',
            'template.mimes' => 'Only .docx files are allowed.',
        ];
    }
}
