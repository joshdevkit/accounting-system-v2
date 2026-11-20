<?php

namespace App\Http\Requests;

use App\Models\PosEntry;
use Illuminate\Validation\Rule;

class UpdatePosEntryRequest extends StorePosEntryRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        /** @var PosEntry $posEntry */
        $posEntry = $this->route('pos_entry');

        if ($posEntry->journalEntry?->workbook_snapshot !== null) {
            $rules['calculation_version'] = ['required', Rule::in([$posEntry->calculation_version])];
        }

        $rules['pos_reference'] = [
            'required',
            'string',
            'max:100',
            Rule::unique('pos_entries', 'pos_reference')
                ->where('branch_id', (int) $this->input('branch_id', $posEntry->branch_id))
                ->where('business_date', (string) $this->input('business_date', $posEntry->business_date->toDateString()))
                ->ignore($posEntry->id),
        ];

        return $rules;
    }
}
