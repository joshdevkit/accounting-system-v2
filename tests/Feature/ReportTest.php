<?php

use App\Services\JournalEntryService;
use App\Services\TrialBalance;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AccountingFixtures;

uses(AccountingFixtures::class);

/**
 * Post a balanced entry and return it, so report tests read like a day in the
 * ledger rather than a pile of fixtures.
 *
 * @param  array<int, array{0: int, 1: float, 2: float}>  $lines  [account id, debit, credit]
 */
function postEntry(\App\Models\Company $company, string $date, string $description, array $lines, bool $post = true): \App\Models\JournalEntry
{
    $entry = app(JournalEntryService::class)->store($company, [
        'transaction_date' => $date,
        'description' => $description,
    ], array_map(fn (array $line): array => [
        'account_id' => $line[0],
        'debit' => $line[1],
        'credit' => $line[2],
    ], $lines), post: $post);

    return $entry;
}

it('rolls sub-account balances up into their parents', function () {
    $fixture = $this->journalFixture();
    $company = $fixture['company'];

    $parent = $this->makeSummaryAccount($company, '1100', 'Cash and equivalents', 'asset');
    $bank = $this->makeAccount($company, '1120', 'Cash in Bank', 'asset', $parent);

    // Move the cash KPI account under the same parent for the roll-up.
    $fixture['cash']->forceFill(['parent_id' => $parent->id])->save();

    postEntry($company, now()->toDateString(), 'Two cash accounts', [
        [$fixture['cash']->id, 600, 0],
        [$bank->id, 400, 0],
        [$fixture['revenue']->id, 0, 1000],
    ]);

    $rows = collect(app(TrialBalance::class)->rows($company))->keyBy('code');

    expect($rows['1110']['debit'])->toBe(60000)
        ->and($rows['1120']['debit'])->toBe(40000)
        ->and($rows['1100']['is_summary'])->toBeTrue()
        ->and($rows['1100']['own_debit'])->toBe(0)
        ->and($rows['1100']['debit'])->toBe(100000)
        ->and($rows['1100']['depth'])->toBe(0)
        ->and($rows['1110']['depth'])->toBe(1)
        ->and($rows['4130']['credit'])->toBe(100000)
        ->and($rows['4130']['balance'])->toBe(100000);
});

it('totals debits and credits so the trial balance balances', function () {
    $fixture = $this->journalFixture();
    $company = $fixture['company'];

    postEntry($company, now()->toDateString(), 'Sales day', [
        [$fixture['cash']->id, 1120, 0],
        [$fixture['revenue']->id, 0, 1120],
    ]);

    postEntry($company, now()->addDay()->toDateString(), 'Supplier payment', [
        [$fixture['revenue']->id, 50, 0],
        [$fixture['cash']->id, 0, 50],
    ]);

    $totals = app(TrialBalance::class)->totals($company);

    expect($totals)->toBe(['debits' => 117000, 'credits' => 117000, 'difference' => 0])
        ->and($totals['difference'])->toBe(0);
});

it('leaves drafts out of the reports', function () {
    $fixture = $this->journalFixture();
    $company = $fixture['company'];

    postEntry($company, now()->toDateString(), 'Still a draft', [
        [$fixture['cash']->id, 900, 0],
        [$fixture['revenue']->id, 0, 900],
    ], post: false);

    expect(app(TrialBalance::class)->totals($company))->toBe([
        'debits' => 0,
        'credits' => 0,
        'difference' => 0,
    ]);

    $rows = collect(app(TrialBalance::class)->rows($company))->keyBy('code');

    expect($rows['1110']['debit'])->toBe(0)
        ->and($rows['1110']['credit'])->toBe(0);

    // A reviewer can still pull drafts into the picture when they need to.
    $withDrafts = app(TrialBalance::class)->totals($company, [
        'statuses' => ['posted', 'draft'],
    ]);

    expect($withDrafts['debits'])->toBe(90000);
});

it('filters by period, by branch and by date', function () {
    $fixture = $this->journalFixture();
    $company = $fixture['company'];
    $branch = $this->makeBranch($company, ['name' => 'Bayfront']);
    $period = $company->fiscalYears()->firstOrFail()->currentPeriod();

    postEntry($company, now()->toDateString(), 'This month, this branch', [
        [$fixture['cash']->id, 100, 0],
        [$fixture['revenue']->id, 0, 100],
    ]);

    $service = app(JournalEntryService::class);
    $nextMonth = $period->end_date->copy()->addDay();

    $service->store($company, [
        'transaction_date' => $nextMonth->toDateString(),
        'branch_id' => $branch->id,
        'description' => 'Next month, other branch',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 200, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 200],
    ], post: true);

    $byPeriod = app(TrialBalance::class)->totals($company, [
        'accounting_period_id' => $period->id,
    ]);

    expect($byPeriod['debits'])->toBe(10000);

    $byBranch = app(TrialBalance::class)->totals($company, [
        'branch_id' => $branch->id,
    ]);

    expect($byBranch['debits'])->toBe(20000);

    $byDate = app(TrialBalance::class)->totals($company, [
        'from' => $nextMonth->toDateString(),
        'to' => $nextMonth->toDateString(),
    ]);

    expect($byDate['debits'])->toBe(20000);
});

it('builds a ledger with a running balance per account', function () {
    $fixture = $this->journalFixture();
    $company = $fixture['company'];

    postEntry($company, now()->startOfMonth()->toDateString(), 'Opening float', [
        [$fixture['cash']->id, 5000, 0],
        [$fixture['revenue']->id, 0, 5000],
    ]);

    postEntry($company, now()->endOfMonth()->toDateString(), 'Payout', [
        [$fixture['cash']->id, 0, 1500],
        [$fixture['revenue']->id, 1500, 0],
    ]);

    $ledger = app(TrialBalance::class)->ledger($company, $fixture['cash']->fresh());

    expect($ledger['lines'])->toHaveCount(2)
        ->and($ledger['debit_cents'])->toBe(500000)
        ->and($ledger['credit_cents'])->toBe(150000)
        ->and($ledger['balance_cents'])->toBe(350000)
        ->and($ledger['lines'][0]['balance'])->toBe(500000)
        ->and($ledger['lines'][1]['balance'])->toBe(350000)
        ->and($ledger['lines'][0]['reference_no'])->not->toBeNull()
        ->and($ledger['lines'][0]['date'])->toBe(now()->startOfMonth()->toDateString());

    // The credit-normal account runs the other way.
    $revenue = app(TrialBalance::class)->ledger($company, $fixture['revenue']->fresh());

    expect($revenue['balance_cents'])->toBe(350000)
        ->and($revenue['lines'][0]['balance'])->toBe(500000)
        ->and($revenue['lines'][1]['balance'])->toBe(350000);
});

it('shows the account screen for a chosen account', function () {
    $fixture = $this->journalFixture();
    $company = $fixture['company'];
    $this->makeUser();

    postEntry($company, now()->toDateString(), 'For the ledger page', [
        [$fixture['cash']->id, 75.25, 0],
        [$fixture['revenue']->id, 0, 75.25],
    ]);

    $this->get('/reports/ledger?account='.$fixture['cash']->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/ledger')
            ->where('account.code', '1110')
            ->where('account.normal_balance', 'debit')
            ->has('ledger.lines', 1)
            ->where('ledger.debit_cents', 7525)
            ->where('ledger.credit_cents', 0)
            ->where('filters.include_sub_accounts', false)
            ->has('accounts', 2)
        );
});

it('renders the trial balance page with its totals', function () {
    $fixture = $this->journalFixture();
    $company = $fixture['company'];
    $this->makeUser();

    postEntry($company, now()->toDateString(), 'For the trial balance page', [
        [$fixture['cash']->id, 1000, 0],
        [$fixture['revenue']->id, 0, 1000],
    ]);

    $this->get('/reports/trial-balance')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/trial-balance')
            ->has('rows', 2)
            ->where('totals.debits', 100000)
            ->where('totals.credits', 100000)
            ->where('totals.difference', 0)
            ->where('unbalanced', false)
            ->where('company.currency', 'PHP')
            ->where('filters.summary', true)
            ->has('pickers.fiscalYears', 1)
            ->has('pickers.branches')
        );
});

it('flags a report range that does not balance', function () {
    $fixture = $this->journalFixture();
    $company = $fixture['company'];
    $this->makeUser();

    // A posted entry is always balanced, so force the imbalance directly to
    // prove the report surfaces it instead of papering over it.
    $entry = postEntry($company, now()->toDateString(), 'Broken by hand', [
        [$fixture['cash']->id, 100, 0],
        [$fixture['revenue']->id, 0, 100],
    ]);

    $line = $entry->lines()->firstWhere('credit', '>', 0);
    $line->forceFill(['credit' => Money::decimal(Money::cents($line->credit) - 2500)])->save();

    $this->get('/reports/trial-balance')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.difference', 2500)
            ->where('unbalanced', true)
        );
});

it('opens the ledger for whichever account the report can show', function () {
    $fixture = $this->journalFixture();
    $this->makeUser();

    // The sidebar links here with no selection at all: the first account is
    // shown rather than an empty or broken page.
    $this->get('/reports/ledger')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('account.code', '1110'));

    // Another company's account id must not open that company's ledger.
    $foreign = $this->makeAccount($this->makeCompany(), '1110', 'Other cash', 'asset');

    $this->get('/reports/ledger?account='.$foreign->id)
        ->assertRedirect('/reports/ledger')
        ->assertSessionHas('error');

    $this->get('/reports/ledger?account='.$fixture['revenue']->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('account.code', '4130'));
});

it('sends an empty chart of accounts to the screen that fixes it', function () {
    $this->makeCompany();
    $this->makeUser();

    $this->get('/reports/ledger')
        ->assertRedirect('/accounts')
        ->assertSessionHas('error');

    $this->get('/reports/trial-balance')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('rows', 0));
});
