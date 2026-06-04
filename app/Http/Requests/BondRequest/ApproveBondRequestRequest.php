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
            'signatory_id' => ['required', 'integer', 'exists:signatories,id'],
            'notary_id' => ['required', 'integer', 'exists:notaries,id'],
            'doc_no' => ['required', 'string', 'max:100'],
            'page_no' => ['required', 'string', 'max:100'],
            'book_no' => ['required', 'string', 'max:100'],
            'series_year' => ['required', 'string', 'max:4'],
        ];
    }
}
