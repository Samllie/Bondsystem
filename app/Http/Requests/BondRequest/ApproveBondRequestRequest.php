<?php

namespace App\Http\Requests\BondRequest;

use Illuminate\Foundation\Http\FormRequest;

class ApproveBondRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('bond-requests.approve');
    }

    public function rules(): array
    {
        return [
            'signatory_id' => ['nullable', 'integer', 'exists:signatories,id'],
            'include_signatory_signature' => ['sometimes', 'boolean'],
            'notary_id' => ['nullable', 'integer', 'exists:notaries,id'],
            'doc_no' => ['nullable', 'string', 'max:100'],
            'page_no' => ['nullable', 'string', 'max:100'],
            'book_no' => ['nullable', 'string', 'max:100'],
            'series_year' => ['nullable', 'string', 'max:4'],
        ];
    }
}
