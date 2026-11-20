<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = Company::first()?->id;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('companies', 'code')->ignore($companyId)],
            'name' => ['required', 'string', 'max:255'],
            'tax_identification_number' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'base_currency_code' => ['required', 'string', 'size:3'],
            'is_active' => ['boolean'],
        ];
    }
}