<?php

namespace Tests\Support;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\PosEntry;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\FiscalYearService;
use App\Support\Money;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Fixtures for the accounting tests.
 *
 * Everything is built through the same services and models the HTTP layer
 * uses, so a failure here means a real flow is broken rather than a fixture
 * that drifted away from the schema.
 */
trait AccountingFixtures
{
    protected function makeCompany(array $attributes = []): Company
    {
        return Company::factory()->create($attributes);
    }

    /**
     * Sign the current request in, since the audit trail records who did what.
     */
    protected function makeUser(): User
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        return $user;
    }

    /**
     * A leaf account, ready to take postings.
     */
    protected function makeAccount(
        Company $company,
        string $code,
        string $name,
        string $category = 'asset',
        ?Account $parent = null,
    ): Account {
        $type = AccountType::query()->firstOrCreate(
            ['code' => Str::upper($category)],
            [
                'name' => Str::headline($category),
                'category' => $category,
                'normal_balance' => in_array($category, ['asset', 'expense'], true) ? 'debit' : 'credit',
                'is_active' => true,
            ],
        );

        return Account::query()->create([
            'company_id' => $company->id,
            'account_type_id' => $type->id,
            'parent_id' => $parent?->id,
            'code' => $code,
            'name' => $name,
            'description' => null,
            'is_control_account' => false,
            'is_active' => true,
        ]);
    }

    protected function makeSummaryAccount(Company $company, string $code, string $name, string $category = 'asset'): Account
    {
        $account = $this->makeAccount($company, $code, $name, $category);

        $account->forceFill(['is_control_account' => true])->save();

        return $account;
    }

    /**
     * A fiscal year covering today, with one accounting period per month, so
     * that posting rules are satisfied by default.
     */
    protected function makeOpenFiscalYear(Company $company, ?int $year = null): FiscalYear
    {
        $year ??= (int) now()->format('Y');

        return app(FiscalYearService::class)->create($company, [
            'year' => $year,
            'start_date' => sprintf('%d-01-01', $year),
            'end_date' => sprintf('%d-12-31', $year),
        ]);
    }

    /**
     * Create every account the POS posting config points at. The codes come
     * from the config so a test fails if somebody edits the mapping without
     * seeding a chart of accounts that can support it.
     *
     * @return array<string, Account> keyed by account code
     */
    protected function makePosChart(Company $company): array
    {
        $config = (array) config('accounting.pos');

        $codes = collect($config['settlement_accounts'] ?? [])
            ->values()
            ->merge(array_map(
                fn (string $key): mixed => $config[$key] ?? null,
                [
                    'revenue_account',
                    'discount_account',
                    'vat_payable_account',
                    'local_tax_payable_account',
                    'service_charge_payable_account',
                    'over_short_account',
                ],
            ))
            ->filter()
            ->map(fn (mixed $code): string => (string) $code)
            ->unique()
            ->values();

        $accounts = [];

        foreach ($codes as $code) {
            $accounts[$code] = $this->makeAccount(
                $company,
                $code,
                'Account '.$code,
                $this->categoryForAccountCode($code),
            );
        }

        return $accounts;
    }

    /**
     * Philippine-style chart of accounts numbering: the leading digit is the
     * category (1 asset, 2 liability, 3 equity, 4 revenue, 5 expense).
     */
    protected function categoryForAccountCode(string $code): string
    {
        return match (substr($code, 0, 1)) {
            '1' => 'asset',
            '2' => 'liability',
            '3' => 'equity',
            '4' => 'revenue',
            default => 'expense',
        };
    }

    /**
     * A company with a chart of accounts and an open fiscal year, which is the
     * minimum needed to record anything.
     *
     * @return array{company: Company, cash: Account, revenue: Account}
     */
    protected function journalFixture(): array
    {
        $company = $this->makeCompany();
        $this->makeOpenFiscalYear($company);

        return [
            'company' => $company,
            'cash' => $this->makeAccount($company, '1110', 'Cash on Hand', 'asset'),
            'revenue' => $this->makeAccount($company, '4130', 'Room and other revenue', 'revenue'),
        ];
    }

    /**
     * Run a callback that is expected to fail validation, and assert which
     * field the message was keyed on.
     */
    protected function expectValidationError(callable $callback, ?string $errorKey = null): ValidationException
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            if ($errorKey !== null) {
                test()->assertArrayHasKey($errorKey, $exception->errors());
            }

            return $exception;
        }

        test()->fail('A ValidationException was expected but nothing was thrown.');
    }

    protected function makeBranch(Company $company, array $attributes = []): Branch
    {
        return Branch::factory()->create([
            'company_id' => $company->id,
            ...$attributes,
        ]);
    }

    /**
     * A day summary whose amounts can be overridden per test. The factory keeps
     * the tenders tied out, so a test only states what it cares about.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makePosEntry(Company $company, Branch $branch, array $attributes = []): PosEntry
    {
        return PosEntry::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            ...$attributes,
        ]);
    }

    /**
     * A company that is ready for POS posting: an open fiscal year, a branch,
     * and every account the configured POS mapping points at.
     *
     * @return array{company: Company, branch: Branch, accounts: array<string, Account>}
     */
    protected function posFixture(): array
    {
        $company = $this->makeCompany();
        $this->makeOpenFiscalYear($company);

        return [
            'company' => $company,
            'branch' => $this->makeBranch($company),
            'accounts' => $this->makePosChart($company),
        ];
    }

    /**
     * The derived journal entry, keyed by account code, so a test can state the
     * whole posting in the same shape the services produce it.
     *
     * @return array<string, array{debit: int, credit: int}>
     */
    protected function postedLines(\App\Models\JournalEntry $entry): array
    {
        $lines = [];

        foreach ($entry->load('lines.account')->lines as $line) {
            $code = (string) $line->account?->code;

            $lines[$code] = [
                'debit' => ($lines[$code]['debit'] ?? 0) + Money::cents($line->debit),
                'credit' => ($lines[$code]['credit'] ?? 0) + Money::cents($line->credit),
            ];
        }

        return $lines;
    }

    /**
     * The id of the shared account type for a category, creating it when a
     * test needs it directly (the chart of accounts form asks for a type).
     */
    protected function accountTypeId(string $category): int
    {
        return AccountType::query()->firstOrCreate(
            ['code' => Str::upper($category)],
            [
                'name' => Str::headline($category),
                'category' => $category,
                'normal_balance' => in_array($category, ['asset', 'expense'], true) ? 'debit' : 'credit',
                'is_active' => true,
            ],
        )->id;
    }
}
