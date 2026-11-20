<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateContactRequest extends StoreContactRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        /** @var \App\Models\Contact $contact */
        $contact = $this->route('contact');

        $rules['name'] = [
            'required',
            'string',
            'max:255',
            Rule::unique('contacts', 'name')
                ->where(fn ($query) => $query->where('company_id', $contact->company_id))
                ->ignore($contact->id),
        ];

        return $rules;
    }
}