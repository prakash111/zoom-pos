<?php

namespace App\Http\Requests\Tenant;

use App\Models\Company;
use App\Services\Module\ModuleManagerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the registration request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(ModuleManagerService $moduleService): array
    {
        $allowedKeys = $moduleService->getAllowedKeys();

        return [
            'store_name'     => ['required', 'string', 'min:2', 'max:150'],
            'email'          => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'       => ['required', 'string', 'min:6', 'confirmed'],
            'operating_mode' => ['required', 'string', Rule::in($allowedKeys)],
            'subdomain'      => [
                'nullable',
                'string',
                'min:2',
                'max:60',
                'regex:/^[a-z0-9-]+$/',
                Rule::notIn(Company::RESERVED_SLUGS),
                Rule::unique('companies', 'slug'),
            ],
            'plan_name'       => ['nullable', 'string', 'in:trial,starter,professional'],
            'activation_code' => ['nullable', 'string'],
        ];
    }
}
