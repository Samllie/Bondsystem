<?php

namespace App\Http\Requests\BondRequest;

use App\Enums\CertificateType;
use App\Rules\ValidKycObligee;
use App\Support\BondNumberGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBondRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('bond_request'));
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
            'tin' => [
                Rule::requiredIf(fn (): bool => $this->certificateType() === CertificateType::CarCertificate),
                'nullable',
                'string',
                'regex:/^\d{3}-\d{3}-\d{3}-0000$/',
            ],
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
            'supporting_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'certificate_type' => ['required', Rule::enum(CertificateType::class)],
            'request_date' => ['required', 'date'],
            'expiry_date' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'obligee_id.required' => 'Please select an obligee from the KYC search results.',
            'obligee_id.min' => 'Please select an obligee from the KYC search results.',
            'car.required' => 'The CAR field is required for CAR certificate requests.',
            'authorized_representative.required' => 'The authorized representative is required for CAR certificate requests.',
            'tin.required' => 'The TIN is required for CAR certificate requests.',
            'tin.regex' => 'Enter a valid TIN in the format 000-000-000-0000.',
            'bond_type_id.required' => 'Please select a bond type for bond certificate requests.',
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'attention' => $this->filled('attention') ? $this->input('attention') : null,
        ]);
    }

    private function certificateType(): ?CertificateType
    {
        $value = $this->input('certificate_type');

        return is_string($value) ? CertificateType::tryFrom($value) : null;
    }
}
