<?php

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Services\FiscalYearService;
use App\Services\JournalEntryService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AccountingFixtures;

uses(AccountingFixtures::class);

it('creates a fiscal year with one accounting period per month', function () {
    $company = $this->makeCompany();

    $fiscalYear = app(FiscalYearService::class)->create($company, [
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    expect($fiscalYear->periods)->toHaveCount(12)
        ->and($fiscalYear->periods->first()->start_date->toDateString())->toBe('2026-01-01')
        ->and($fiscalYear->periods->first()->end_date->toDateString())->toBe('2026-01-31')
        ->and($fiscalYear->periods->last()->period_number)->toBe(12)
        ->and($fiscalYear->periods->every(fn (AccountingPeriod $period): bool => $period->status === 'open'))->toBeTrue();
});

it('splits a year that does not start in january on anniversary months', function () {
    $company = $this->makeCompany();

    $fiscalYear = app(FiscalYearService::class)->create($company, [
        'year' => 2026,
        'start_date' => '2026-06-01',
        'end_date' => '2027-05-31',
    ]);

    $periods = $fiscalYear->load('periods')->periods;

    expect($periods)->toHaveCount(12)
        ->and($periods->first()->start_date->toDateString())->toBe('2026-06-01')
        ->and($periods->first()->end_date->toDateString())->toBe('2026-06-30')
        ->and($periods->last()->start_date->toDateString())->toBe('2027-05-01')
        ->and($periods->last()->end_date->toDateString())->toBe('2027-05-31');

    // Every day of the year lands in exactly one period.
    $covers = $periods->sum(fn (AccountingPeriod $period): int => $period->start_date->diffInDays($period->end_date) + 1);

    expect($covers)->toBe(365);
});

it('refuses two fiscal years that overlap', function () {
    $company = $this->makeCompany();
    $year = (int) now()->format('Y');

    $this->makeOpenFiscalYear($company, $year);

    // A June to May year labelled 2027 still overlaps January to December.
    $this->expectValidationError(
        fn () => app(FiscalYearService::class)->create($company, [
            'year' => $year + 1,
            'start_date' => sprintf('%d-06-01', $year),
            'end_date' => sprintf('%d-05-31', $year + 1),
        ]),
        'start_date',
    );

    expect(FiscalYear::query()->where('company_id', $company->id)->count())->toBe(1);
});

it('creates a second year back to back with the first', function () {
    $company = $this->makeCompany();
    $year = (int) now()->format('Y');

    $this->makeOpenFiscalYear($company, $year);

    $next = app(FiscalYearService::class)->create($company, [
        'year' => $year + 1,
        'start_date' => sprintf('%d-01-01', $year + 1),
        'end_date' => sprintf('%d-12-31', $year + 1),
    ]);

    expect($next->periods)->toHaveCount(12)
        ->and(FiscalYear::query()->where('company_id', $company->id)->count())->toBe(2);
});

it('will not delete a year that already carries entries', function () {
    $fixture = $this->journalFixture();
    $fiscalYear = $fixture['company']->fiscalYears()->firstOrFail();

    app(JournalEntryService::class)->store($fixture['company'], [
        'transaction_date' => now()->toDateString(),
        'description' => 'Keeps the year in place',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 5, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 5],
    ]);

    $this->expectValidationError(
        fn () => app(FiscalYearService::class)->delete($fiscalYear),
        'fiscal_year',
    );

    expect(FiscalYear::query()->whereKey($fiscalYear->id)->exists())->toBeTrue();

    $empty = app(FiscalYearService::class)->create($fixture['company'], [
        'year' => 2050,
        'start_date' => '2050-01-01',
        'end_date' => '2050-12-31',
    ]);

    app(FiscalYearService::class)->delete($empty);

    expect(AccountingPeriod::query()->where('fiscal_year_id', $empty->id)->exists())->toBeFalse();
});

it('lets two companies run the same year', function () {
    $this->makeOpenFiscalYear($this->makeCompany(), 2031);
    $this->makeOpenFiscalYear($this->makeCompany(), 2031);

    expect(FiscalYear::query()->where('year', 2031)->count())->toBe(2);
});

it('closes a period so nothing new can be posted into it', function () {
    $fixture = $this->journalFixture();
    $this->makeUser();

    $period = $fixture['company']->fiscalYears()->firstOrFail()->currentPeriod();

    expect($period->allowsPosting())->toBeTrue();

    $this->patch(route('accounting-periods.status', $period), [
        'status' => 'closed',
    ])->assertSessionHasNoErrors();

    expect($period->refresh()->status)->toBe('closed')
        ->and($period->allowsPosting())->toBeFalse();

    $this->patch(route('accounting-periods.status', $period), [
        'status' => 'open',
    ])->assertSessionHasNoErrors();

    expect($period->refresh()->status)->toBe('open');
});

it('will not close a period that still holds drafts', function () {
    $fixture = $this->journalFixture();
    $this->makeUser();

    $entry = app(JournalEntryService::class)->store($fixture['company'], [
        'transaction_date' => now()->toDateString(),
        'description' => 'Left in draft',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 10, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 10],
    ]);

    $period = $entry->accountingPeriod;

    $this->patch(route('accounting-periods.status', $period), ['status' => 'closed'])
        ->assertSessionHasErrors('status');

    expect($period->refresh()->status)->toBe('open');
});

it('closes the whole year and blocks posting into every period below it', function () {
    $fixture = $this->journalFixture();
    $this->makeUser();

    $service = app(JournalEntryService::class);

    $entry = $service->store($fixture['company'], [
        'transaction_date' => now()->toDateString(),
        'description' => 'Waiting for the year to reopen',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 20, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 20],
    ]);

    $fiscalYear = $entry->fiscalYear;

    $this->patch(route('fiscal-years.status', $fiscalYear), ['status' => 'closed'])
        ->assertSessionHasNoErrors();

    expect($fiscalYear->refresh()->status)->toBe('closed');

    $this->expectValidationError(fn () => $service->post($entry->fresh()), 'transaction_date');

    $this->patch(route('fiscal-years.status', $fiscalYear), ['status' => 'open'])
        ->assertSessionHasNoErrors();

    expect($service->post($entry->fresh())->status)->toBe('posted');
});

it('will not change the dates of a year that already has entries', function () {
    $fixture = $this->journalFixture();
    $this->makeUser();

    $fiscalYear = $fixture['company']->fiscalYears()->firstOrFail();

    app(JournalEntryService::class)->store($fixture['company'], [
        'transaction_date' => now()->toDateString(),
        'description' => 'Filed in this year',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 1, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 1],
    ]);

    $this->put(route('fiscal-years.update', $fiscalYear), [
        'year' => $fiscalYear->year,
        'start_date' => $fiscalYear->start_date->copy()->subWeek()->toDateString(),
        'end_date' => $fiscalYear->end_date->toDateString(),
    ])->assertSessionHasErrors('start_date');

    expect($fiscalYear->refresh()->start_date->toDateString())->toBe(now()->format('Y-01-01'));
});

it('lists years with their period grid', function () {
    $fixture = $this->journalFixture();
    $this->makeUser();

    $this->get('/fiscal-years')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('fiscal-years/index')
            ->has('fiscalYears', 1)
            ->where('fiscalYears.0.year', (int) now()->format('Y'))
            ->has('fiscalYears.0.periods', 12)
            ->where('stats.years', 1)
            ->where('stats.open', 1)
            ->where('stats.periods', 12)
        );
});
