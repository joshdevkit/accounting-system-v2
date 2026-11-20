<?php

namespace App\Http\Requests;

use App\Support\CurrentCompany;
use App\Support\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the shape of a manual journal entry. Whether it balances and
 * whether its period is still open is decided by JournalEntryService, which
 * is also what the "save and post" button goes through.
 */
class StoreJournalEntryRequest extends FormRequest
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
            'reference_no' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('journal_entries', 'reference_no')->where('company_id', $companyId),
            ],
            'transaction_date' => ['required', 'date'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('company_id', $companyId),
            ],
            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where(
                    fn($query) => $query->where('company_id', $companyId)
                ),
            ],
            'description' => ['required', 'string', 'max:255'],
            'post_now' => ['nullable', 'boolean'],
            'lines' => [
                'required',
                'array',
                'min:1',
                'max:' . (int) config('accounting.journal_entries.max_lines', 50),
                $this->eachLineUsesOneSideOnly(),
            ],
            'lines.*.account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(
                    fn($query) => $query->where('company_id', $companyId)->where('is_active', true)
                ),
            ],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:99999999999.99'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:99999999999.99'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'transaction_date' => 'date',
            'branch_id' => 'branch',
            'lines' => 'journal entry lines',
            'lines.*.account_id' => 'account',
        ];
    }

    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('lines', []))
            ->filter(fn(mixed $line): bool => is_array($line))
            ->map(fn(array $line): array => [
                'account_id' => $line['account_id'] ?? null,
                // Normalised back to pesos (rounded to the centavo) so the
                // validated payload and the service speak the same unit.
                'debit' => round(Money::cents($line['debit'] ?? null) / 100, 2),
                'credit' => round(Money::cents($line['credit'] ?? null) / 100, 2),
                'description' => filled($line['description'] ?? null) ? trim((string) $line['description']) : null,
            ])
            ->values()
            ->all();

        $this->merge([
            'lines' => $lines,
            'reference_no' => filled($this->input('reference_no')) ? trim((string) $this->input('reference_no')) : null,
            'branch_id' => $this->input('branch_id') ?: null,
            'post_now' => $this->boolean('post_now'),
        ]);
    }

    /**
     * Blank rows are fine (they are dropped later), a row carrying both a
     * debit and a credit is not.
     */
    protected function eachLineUsesOneSideOnly(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            $meaningful = 0;

            foreach ($value as $line) {
                if (! is_array($line)) {
                    continue;
                }

                $debit = Money::cents($line['debit'] ?? null);
                $credit = Money::cents($line['credit'] ?? null);

                if ($debit > 0 && $credit > 0) {
                    $fail('A line can carry a debit or a credit, never both.');

                    return;
                }

                if ($debit > 0 || $credit > 0) {
                    $meaningful++;
                }
            }

            if ($meaningful < 2) {
                $fail('An entry needs at least two lines with amounts: one debit and one credit.');
            }
        };
    }
}
