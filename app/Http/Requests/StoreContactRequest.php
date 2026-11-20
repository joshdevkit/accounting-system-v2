<?php

namespace App\Http\Requests;

use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $company = CurrentCompany::get();

        return [
            'type' => ['required', Rule::in(['vendor', 'customer', 'both'])],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('contacts', 'name')->where(
                    fn ($query) => $query->where('company_id', $company?->id)
                ),
            ],
            'tax_identification_number' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'default_payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tax_identification_number' => 'TIN',
            'default_payment_terms_days' => 'payment terms',
        ];
    }
}