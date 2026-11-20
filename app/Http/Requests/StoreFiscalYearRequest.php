<?php

namespace App\Http\Requests;

use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreFiscalYearRequest extends FormRequest
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
        $companyId = CurrentCompany::get()?->id;

        return [
            'year' => [
                'required',
                'integer',
                'between:1990,2200',
                Rule::unique('fiscal_years', 'year')->where('company_id', $companyId),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(['open', 'closed'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'year' => 'fiscal year',
            'start_date' => 'start date',
            'end_date' => 'end date',
        ];
    }

    protected function prepareForValidation(): void
    {
        // The form only asks for the dates; the year is derived from the start
        // date so the two can never disagree.
        if (! $this->filled('year') && $this->filled('start_date')) {
            try {
                $this->merge([
                    'year' => Carbon::parse((string) $this->input('start_date'))->year,
                ]);
            } catch (\Throwable) {
                // The date itself will fail validation below.
            }
        }

        if (! $this->filled('status')) {
            $this->merge(['status' => 'open']);
        }
    }
}
