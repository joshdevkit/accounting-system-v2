<?php

namespace App\Http\Requests;

use App\Support\CurrentCompany;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends StoreExpenseRequest
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
            'expense_date' => ['required', 'date'],
            'expense_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(
                    fn ($query) => $query->where('company_id', $company?->id)->where('is_active', true)
                ),
            ],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'payee' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999999.99'],
            'payment_status' => ['required', Rule::in(['paid', 'unpaid'])],
            'settlement_account' => [
                'nullable',
                'required_if:payment_status,paid',
                Rule::in(array_keys((array) config('accounting.expenses.settlement_accounts'))),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
            'post_now' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'expense_account_id' => 'expense category',
            'settlement_account' => 'payment source',
        ];
    }
}