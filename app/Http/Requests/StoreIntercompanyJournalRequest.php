<?php

namespace App\Http\Requests;

use App\Support\CurrentCompany;
use Illuminate\Validation\Rule;

class StoreIntercompanyJournalRequest extends StoreJournalEntryRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['branch_id'] = [
            'required',
            'integer',
            Rule::exists('branches', 'id')
                ->where('company_id', CurrentCompany::find()->id)
                ->where('is_active', true),
        ];

        return $rules;
    }
}
