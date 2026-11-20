<?php

namespace App\Http\Requests;

use App\Models\FiscalYear;
use Illuminate\Validation\Rule;

class UpdateFiscalYearRequest extends StoreFiscalYearRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var FiscalYear $fiscalYear */
        $fiscalYear = $this->route('fiscal_year');

        return [
            'year' => [
                'required',
                'integer',
                'between:1990,2200',
                Rule::unique('fiscal_years', 'year')
                    ->where('company_id', $fiscalYear->company_id)
                    ->ignore($fiscalYear->id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(['open', 'closed'])],
        ];
    }
}
