<?php

namespace App\Http\Requests\BondRequest;

use App\Rules\ValidKycObligee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBondRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('bond_request'));
    }

    public function rules(): array
    {
        $bondRequest = $this->route('bond_request');

        return [
            'bond_number' => ['required', 'string', 'max:50', Rule::unique('bond_requests', 'bond_number')->ignore($bondRequest)],
            'bond_type_id' => ['required', 'exists:bond_type_masters,id'],
            'principal_id' => ['required', 'exists:principals,id'],
            'obligee_id' => ['required', 'integer', new ValidKycObligee],
            'obligee_name' => ['nullable', 'string', 'max:255'],
            'address_1' => ['nullable', 'string', 'max:500'],
            'address_2' => ['nullable', 'string', 'max:500'],
            'address_3' => ['nullable', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'amount_in_words' => ['nullable', 'string', 'max:1000'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'request_date' => ['required', 'date'],
            'expiry_date' => ['required', 'date', 'after_or_equal:request_date'],
            'signatory_id' => ['required', 'exists:signatories,id'],
            'signatory_position' => ['nullable', 'string', 'max:255'],
            'notary_id' => ['required', 'exists:notaries,id'],
            'doc_no' => ['nullable', 'string', 'max:100'],
            'page_no' => ['nullable', 'string', 'max:100'],
            'book_no' => ['nullable', 'string', 'max:100'],
            'series_year' => ['nullable', 'string', 'max:4'],
            'description' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
