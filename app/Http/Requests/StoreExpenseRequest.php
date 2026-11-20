<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\Branch;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $company = CurrentCompany::get();
        $branch = Branch::whereKey($this->input('branch_id'))
            ->where('company_id', $company?->id)
            ->first();

        $suffix = $branch ? config("accounting.branch_suffixes.{$branch->code}") : null;

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(
                    fn($query) => $query->where('company_id', $company?->id)->where('is_active', true)
                ),
            ],
            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where(
                    fn($query) => $query->where('company_id', $company?->id)
                ),
            ],
            'expense_date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'payee' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'post_now' => ['nullable', 'boolean'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(
                    fn($query) => $query->where('company_id', $company?->id)->where('is_active', true)
                ),
                function ($attribute, $value, $fail) use ($suffix) {
                    if (! $suffix) {
                        return;
                    }

                    $code = Account::whereKey($value)->value('code');

                    if ($code && str_contains($code, '-') && ! str_ends_with($code, '-' . $suffix)) {
                        $fail("{$code} belongs to a different branch. Pick one from the selected branch.");
                    }
                },
            ],
            'lines.*.particulars' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.uom' => ['nullable', 'string', 'max:10'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
            'lines.*.tax_type' => ['required', Rule::in(['vatable', 'non_vat', 'vat_exempt'])],
            'lines.*.withholding_tax_rate' => [
                'required',
                Rule::in(array_map('floatval', array_keys((array) config('accounting.expenses.withholding_tax_rates')))),
            ],
            'lines.*.description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'branch_id' => 'branch',
            'contact_id' => 'payee',
            'expense_date' => 'expense date',
            'reference_no' => 'reference number',
            'lines' => 'line items',
            'lines.*.account_id' => 'account',
            'lines.*.particulars' => 'particulars',
            'lines.*.quantity' => 'quantity',
            'lines.*.unit_price' => 'unit price',
            'lines.*.amount' => 'amount',
            'lines.*.tax_type' => 'tax type',
            'lines.*.withholding_tax_rate' => 'withholding rate',
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'Select a branch.',
            'branch_id.exists' => 'That branch is not available.',
            'expense_date.required' => 'Enter the expense date.',

            'lines.required' => 'Add at least one line item.',
            'lines.min' => 'Add at least one line item.',

            'lines.*.account_id.required' => 'Choose an account.',
            'lines.*.account_id.exists' => 'That account isn’t available.',
            'lines.*.particulars.required' => 'Describe what was spent.',
            'lines.*.quantity.required' => 'Enter a quantity.',
            'lines.*.quantity.min' => 'Quantity must be more than zero.',
            'lines.*.unit_price.required' => 'Enter a unit price.',
            'lines.*.unit_price.min' => 'Unit price cannot be negative.',
            'lines.*.particulars.max' => 'Keep this under 255 characters.',
            'lines.*.amount.required' => 'Enter an amount.',
            'lines.*.amount.numeric' => 'Amount must be a number.',
            'lines.*.amount.min' => 'Amount must be more than zero.',
            'lines.*.tax_type.required' => 'Choose a tax type.',
            'lines.*.tax_type.in' => 'Choose a valid tax type.',
            'lines.*.withholding_tax_rate.required' => 'Choose a withholding rate.',
            'lines.*.withholding_tax_rate.in' => 'Choose a valid withholding rate.',
        ];
    }
}
