<?php

namespace App\Http\Requests;

use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $company = CurrentCompany::get();

        return [
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(
                    fn ($query) => $query->where('company_id', $company?->id)
                ),
            ],
            'sale_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:sale_date'],
            'credit_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(
                    fn ($query) => $query->where('company_id', $company?->id)->where('is_active', true)
                ),
            ],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'taxable_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999999.99'],
            'tax_type' => ['required', Rule::in(['vatable', 'non_vat', 'vat_exempt'])],
            'business_source' => [
                'nullable',
                Rule::in(array_keys((array) config('accounting.sales.business_sources'))),
            ],
            'discount_type' => [
                'nullable',
                Rule::in(array_keys((array) config('accounting.sales.discounts'))),
            ],
            'withholding_tax_rate' => [
                'required',
                Rule::in(array_map('floatval', array_keys((array) config('accounting.sales.withholding_tax_rates')))),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
            'post_now' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'credit_account_id' => 'revenue account',
            'taxable_amount' => 'amount',
            'business_source' => 'business source',
            'discount_type' => 'discount',
        ];
    }
}
