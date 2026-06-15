<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateAttorneyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAttorney() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
                'regex:/^[a-z0-9._%+-]+@sterling-insurance\.com\.ph$/',
            ],
            'signatory_position' => ['required', 'string', 'max:255'],
            'signatory_tin' => ['required', 'string', 'max:50'],
            'signatory_signature' => ['nullable', File::types(['png'])->max(2048)],
            'notary_commission_number' => ['required', 'string', 'max:100'],
            'notary_tin' => ['required', 'string', 'regex:/^\d{3}-\d{3}-\d{3}-0000$/'],
            'notary_signature' => ['nullable', File::types(['png'])->max(10 * 1024)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.regex' => 'The email must use the @sterling-insurance.com.ph domain.',
            'notary_tin.regex' => 'Enter a valid TIN in the format 000-000-000-0000.',
            'notary_signature.max' => 'The notary seal may not be larger than 10 MB.',
        ];
    }
}
