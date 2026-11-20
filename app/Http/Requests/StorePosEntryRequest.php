<?php

namespace App\Http\Requests;

use App\Services\PosSalesCalculationService;
use App\Support\CurrentCompany;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StorePosEntryRequest extends FormRequest
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
        $company = CurrentCompany::get();

        $rules = [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(
                    fn ($query) => $query->where('company_id', $company?->id)->where('is_active', true)
                ),
            ],
            'business_date' => ['required', 'date'],
            'pos_reference' => [
                'required',
                'string',
                'max:100',
                Rule::unique('pos_entries', 'pos_reference')->where(
                    'branch_id',
                    (int) $this->input('branch_id')
                )->where('business_date', (string) $this->input('business_date')),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
            'guest_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tin' => ['nullable', 'string', 'max:50'],
            'arrival' => ['nullable', 'date'],
            'departure' => $this->filled('arrival')
                ? ['nullable', 'date', 'after_or_equal:arrival']
                : ['nullable', 'date'],
            'business_source' => ['nullable', Rule::in(array_keys(config('accounting.pos.business_sources')))],
            'vat_classification' => ['nullable', Rule::in(array_keys(config('accounting.pos.vat_classifications')))],
            'discount_type' => ['nullable', Rule::in(array_keys(config('accounting.pos.discount_types')))],
            'withholding_tax_rate' => ['nullable', 'numeric', Rule::in(config('accounting.pos.withholding_tax_rates'))],
            'discount_rate' => [
                in_array($this->input('discount_type'), ['promo', 'special_open', 'corporate_government'], true)
                    ? 'required'
                    : 'nullable',
                'numeric', 'min:0', 'max:100',
            ],
            'calculation_version' => ['nullable', Rule::in(['sales_format_2', 'sales_format_3', 'sales_format_4', 'sales_format_5'])],
            'avl_increment_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999999.99'],
            'other_suppliers_increment_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999999.99'],
            'massage_increment_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999999.99'],
            'post_now' => ['nullable', 'boolean'],
        ];

        foreach ($this->moneyFields() as $field) {
            $rules[$field] = ['nullable', 'numeric', 'min:0', 'max:99999999999.99'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'branch_id' => 'branch',
            'business_date' => 'business date',
            'pos_reference' => 'POS reference',
            'gross_sales' => 'gross sales',
            'net_sales' => 'net sales',
            'vat_amount' => 'VAT amount',
            'local_tax_amount' => 'local tax amount',
            'service_charge_amount' => 'service charge amount',
            'gcash_qrph_amount' => 'GCash / QRPh amount',
            'accounts_receivable_amount' => 'accounts receivable amount',
            'room_post_amount' => 'room post amount',
            'gift_voucher_amount' => 'gift voucher amount',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalised = [
            'pos_reference' => trim((string) $this->input('pos_reference')),
            'post_now' => $this->boolean('post_now'),
        ];

        foreach ($this->moneyFields() as $field) {
            // Blank inputs become 0 and every amount is rounded to the
            // centavo, in pesos, which is the unit the service expects.
            $value = $this->input($field);
            $normalised[$field] = $value === null || $value === ''
                ? 0
                : (is_numeric($value) && $value >= 0 && $value <= 99999999999.99
                    ? Money::decimal(Money::cents($value))
                    : $value);
        }

        $this->merge($normalised);
    }

    protected function passedValidation(): void
    {
        $income = array_intersect_key($this->validated(), config('accounting.pos.income_fields'));
        $gross = Money::sum($income);
        if ($gross !== 0 || in_array($this->input('calculation_version'), ['sales_format_2', 'sales_format_3', 'sales_format_4', 'sales_format_5'], true) || Money::cents($this->input('complimentary_room_amount')) !== 0) {
            if ($this->boolean('post_now')) {
                throw ValidationException::withMessages([
                    'post_now' => 'Save the entry to review its calculated workbook journal before ledger posting.',
                ]);
            }
            $this->validator->setData([
                ...$this->validator->getData(),
                ...app(PosSalesCalculationService::class)->attributes($this->validated()),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    protected function moneyFields(): array
    {
        return array_values(array_unique(array_merge(
            (array) config('accounting.pos.amount_fields'),
            (array) config('accounting.pos.payment_fields'),
        )));
    }
}
