<?php

namespace App\Http\Requests;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch_id' => $this->input('branch_id') ?: null,
            'phone' => $this->filled('phone') ? $this->input('phone') : null,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
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
                'unique:users,email',
                'regex:/^[a-z0-9._%+-]+@sterling-insurance\.com\.ph$/',
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->user()->hasRole(RoleSlug::SuperAdmin)) {
                        $role = Role::query()->find($value);

                        if ($role?->slug === RoleSlug::SuperAdmin->value) {
                            $fail('You are not allowed to assign the Super Admin account level.');
                        }
                    }
                },
            ],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'branch_code' => ['nullable', 'string', 'size:3', 'alpha', 'uppercase'],
            'branch_city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.regex' => 'The email must use the @sterling-insurance.com.ph domain.',
        ];
    }
}
