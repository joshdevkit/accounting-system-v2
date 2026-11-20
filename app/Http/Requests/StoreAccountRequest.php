<?php

namespace App\Http\Requests;

use App\Support\CurrentCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('accounts', 'code')->where('company_id', $companyId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'account_type_id' => [
                'required',
                'integer',
                Rule::exists('account_types', 'id')->where('is_active', true),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('company_id', $companyId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_control_account' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'This account code is already used in the chart of accounts.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'account code',
            'account_type_id' => 'account type',
            'parent_id' => 'parent account',
            'is_control_account' => 'control account',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->input('code')) ? trim((string) $this->input('code')) : $this->input('code'),
            'parent_id' => $this->input('parent_id') ?: null,
            'is_control_account' => $this->boolean('is_control_account'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}
