<?php

namespace App\Http\Requests\BondRequest;

use App\Enums\CertificateType;
use App\Models\BondRequest;
use App\Rules\ValidKycObligee;
use App\Support\BondNumberGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBondRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BondRequest::class);
    }

    public function rules(): array
    {
        return [
            'bond_type_id' => ['required', 'exists:bond_type_masters,id'],
            'principal_id' => ['required', 'exists:principals,id'],
            'obligee_id' => ['required', 'integer', 'min:1', new ValidKycObligee],
            'obligee_name' => ['nullable', 'string', 'max:255'],
            'address_1' => ['nullable', 'string', 'max:500'],
            'address_2' => ['nullable', 'string', 'max:500'],
            'address_3' => ['nullable', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'amount_in_words' => ['nullable', 'string', 'max:1000'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'date_issued' => ['nullable', 'date'],
            'inception_date' => ['required', 'date'],
            'attention' => ['nullable', 'string', 'max:255'],
            'supporting_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'certificate_type' => ['required', Rule::enum(CertificateType::class)],
            'request_date' => ['required', 'date'],
            'expiry_date' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->user()->loadMissing('branch');

            if (! BondNumberGenerator::userHasBranchCode($this->user())) {
                $validator->errors()->add(
                    'bond_type_id',
                    'Set your branch (with a branch code) in your profile before submitting a bond request.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'obligee_id.required' => 'Please select an obligee from the KYC search results.',
            'obligee_id.min' => 'Please select an obligee from the KYC search results.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'attention' => $this->filled('attention') ? $this->input('attention') : null,
        ]);
    }
}
