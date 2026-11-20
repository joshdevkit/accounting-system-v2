<?php

namespace App\Http\Requests;

use App\Models\JournalEntry;
use Illuminate\Validation\Rule;

class UpdateJournalEntryRequest extends StoreJournalEntryRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        /** @var JournalEntry $entry */
        $entry = $this->route('journal_entry');

        $rules['reference_no'] = [
            'nullable',
            'string',
            'max:100',
            Rule::unique('journal_entries', 'reference_no')
                ->where('company_id', $entry->company_id)
                ->ignore($entry->id),
        ];

        return $rules;
    }
}
