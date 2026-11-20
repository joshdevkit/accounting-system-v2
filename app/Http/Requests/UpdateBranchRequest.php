<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
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
        /** @var Branch $branch */
        $branch = $this->route('branch');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('branches', 'code')
                    ->where('company_id', $branch->company_id)
                    ->ignore($branch->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        /** @var Branch $branch */
        $branch = $this->route('branch');

        // An unchecked checkbox posts nothing, so fall back to the stored value
        // rather than silently keeping the branch as it was.
        $this->merge([
            'code' => is_string($this->input('code')) ? trim((string) $this->input('code')) : $this->input('code'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : (bool) $branch->is_active,
        ]);
    }
}