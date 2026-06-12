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
            'bond_type_id' => [
                Rule::requiredIf(fn (): bool => $this->certificateType() === CertificateType::BondCertificate),
                'nullable',
                'integer',
                'exists:bond_type_masters,id',
            ],
            'car' => [
                Rule::requiredIf(fn (): bool => $this->certificateType() === CertificateType::CarCertificate),
                'nullable',
                'string',
                'max:50',
            ],
            'authorized_representative' => [
                Rule::requiredIf(fn (): bool => $this->certificateType() === CertificateType::CarCertificate),
                'nullable',
                'string',
                'max:255',
            ],
            'has_endorsement' => ['sometimes', 'boolean'],
            'endorsement_number' => [
                Rule::requiredIf(fn (): bool => $this->boolean('has_endorsement')),
                'nullable',
                'string',
                'max:100',
            ],
            'principal_id' => ['nullable', 'integer', 'exists:principals,id'],
            'principal_name' => ['required', 'string', 'max:255'],
            'obligee_id' => ['nullable', 'integer', 'min:1', new ValidKycObligee],
            'obligee_name' => ['required', 'string', 'max:255'],
            'address_1' => ['nullable', 'string', 'max:500'],
            'address_2' => ['nullable', 'string', 'max:500'],
            'address_3' => ['nullable', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'amount_in_words' => ['nullable', 'string', 'max:1000'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'date_issued' => ['nullable', 'date'],
            'inception_date' => [
                Rule::requiredIf(fn (): bool => $this->certificateType() === CertificateType::BondCertificate),
                'nullable',
                'date',
            ],
            'attention' => ['nullable', 'string', 'max:255'],
            'supporting_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
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
                $field = $this->certificateType() === CertificateType::CarCertificate
                    ? 'car'
                    : 'bond_type_id';

                $validator->errors()->add(
                    $field,
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
            'obligee_name.required' => 'The obligee name is required.',
            'principal_name.required' => 'The principal name is required.',
            'car.required' => 'The CAR field is required for CAR certificate requests.',
            'authorized_representative.required' => 'The authorized representative is required for CAR certificate requests.',
            'endorsement_number.required' => 'The endorsement number is required when endorsement is enabled.',
            'bond_type_id.required' => 'Please select a bond type for bond certificate requests.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'attention' => $this->filled('attention') ? $this->input('attention') : null,
            'obligee_id' => $this->filled('obligee_id') ? $this->input('obligee_id') : null,
            'principal_id' => $this->filled('principal_id') ? $this->input('principal_id') : null,
            'date_issued' => $this->filled('date_issued') ? $this->input('date_issued') : now()->toDateString(),
        ]);
    }

    private function certificateType(): ?CertificateType
    {
        $value = $this->input('certificate_type');

        return is_string($value) ? CertificateType::tryFrom($value) : null;
    }
}
