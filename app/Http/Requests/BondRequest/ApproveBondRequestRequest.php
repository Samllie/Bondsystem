<?php

namespace App\Http\Requests\BondRequest;

use App\Enums\CertificateType;
use App\Models\BondRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'notary_id' => [Rule::requiredIf($this->notaryRequired()), 'nullable', 'integer', 'exists:notaries,id'],
            'doc_no' => ['nullable', 'string', 'max:100'],
            'page_no' => ['nullable', 'string', 'max:100'],
            'book_no' => ['nullable', 'string', 'max:100'],
            'series_year' => ['nullable', 'string', 'max:4'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $bondRequest = $this->route('bond_request');

            if (! $bondRequest instanceof BondRequest) {
                return;
            }

            $bondRequest->loadMissing('creator.branch');
            $creator = $bondRequest->creator;

            if ($creator === null) {
                $validator->errors()->add('signatory_id', 'Bond request creator not found.');

                return;
            }

            $notaryFee = $creator->branch?->notary_price;

            if ($notaryFee === null || (float) $notaryFee <= 0) {
                $validator->errors()->add(
                    'signatory_id',
                    'Notary price is not configured for the requester\'s branch.',
                );
            } elseif ((float) $creator->balance < (float) $notaryFee) {
                $validator->errors()->add(
                    'signatory_id',
                    'Requester has insufficient balance to cover the notary fee of PHP '.number_format((float) $notaryFee, 2).'.',
                );
            }
        });
    }

    /**
     * The notary is only required for Bond certificates; CAR certificates have
     * no notary section.
     */
    private function notaryRequired(): bool
    {
        $bondRequest = $this->route('bond_request');

        return ! ($bondRequest instanceof BondRequest)
            || $bondRequest->certificate_type === CertificateType::BondCertificate;
    }
}
