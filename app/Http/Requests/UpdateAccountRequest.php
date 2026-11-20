<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends StoreAccountRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        /** @var Account $account */
        $account = $this->route('account');

        $rules['code'] = [
            'required',
            'string',
            'max:50',
            Rule::unique('accounts', 'code')
                ->where('company_id', $account->company_id)
                ->ignore($account->id),
        ];

        $rules['parent_id'] = [
            'nullable',
            'integer',
            Rule::exists('accounts', 'id')->where('company_id', $account->company_id),
            Rule::notIn([$account->id]),
        ];

        return $rules;
    }
}
