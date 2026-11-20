<?php

use App\Services\JournalEntryService;
use App\Services\PosPostingService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AccountingFixtures;

uses(AccountingFixtures::class);

it('asks for a company before it shows any numbers', function () {
    $this->makeUser();

    $this->get('/dashboard')
        ->assertRedirect(route('company.edit'))
        ->assertSessionHas('error');
});

it('sends a guest to the login screen', function () {
    $this->get('/dashboard')->assertRedirect('/');

    $this->get('/journal-entries')->assertRedirect('/');

    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/login'));
});

it('shows the ledger balances it is supposed to', function () {
    $fixture = $this->journalFixture();
    $this->makeUser();
    $this->makeBranch($fixture['company']);

    app(JournalEntryService::class)->store($fixture['company'], [
        'transaction_date' => now()->toDateString(),
        'description' => 'Cash in',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 1250.50, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 1250.50],
    ], post: true);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('company.name', $fixture['company']->name)
            ->where('company.currency', 'PHP')
            ->where('businessDate', now()->toDateString())
            ->where('fiscalYear.year', (int) now()->format('Y'))
            ->where('period.status', 'open')
            ->where('period.allows_posting', true)
            ->where('kpis.0.cents', 125050)
            ->where('kpis.3.count', 0)
            ->has('recentEntries', 1)
            ->where('recentEntries.0.cents', 125050)
            ->where('alerts', [])
        );
});

it('counts a draft as unposted work', function () {
    $fixture = $this->journalFixture();
    $this->makeUser();

    app(JournalEntryService::class)->store($fixture['company'], [
        'transaction_date' => now()->toDateString(),
        'description' => 'Not posted yet',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 10, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 10],
    ]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.3.count', 1)
            ->where('kpis.0.cents', 0)
            ->has('recentEntries', 1)
            ->where('recentEntries.0.status', 'draft')
        );
});

it('reports what the tills took in today', function () {
    $fixture = $this->posFixture();
    $this->makeUser();

    $pos = $this->makePosEntry($fixture['company'], $fixture['branch'], [
        'business_date' => now()->toDateString(),
        'net_sales' => 1000,
        'vat_amount' => 120,
        'cash_amount' => 620,
        'card_amount' => 500,
        'gcash_qrph_amount' => 0,
    ]);

    app(PosPostingService::class)->post($pos);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('posSummaries.posted', 1)
            ->where('posSummaries.drafts', 0)
            ->where('kpis.1.cents', 100000)
            ->where('tenders.0.label', 'Cash')
            ->where('tenders.0.cents', 62000)
            ->where('tenders.1.cents', 50000)
        );
});

it('warns about the things that stop the books being kept', function () {
    $fixture = $this->journalFixture();
    $this->makeUser();

    // No branches, and a period that is closed for business.
    $period = $fixture['company']->fiscalYears()->firstOrFail()->currentPeriod();
    $period->forceFill(['status' => 'closed'])->save();

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('period.allows_posting', false)
            ->has('alerts', 2)
            ->where('alerts.0.level', 'warning')
            ->where('alerts.0.href', '/fiscal-years')
            ->where('alerts.1.href', '/branches/create')
        );

    // A POS draft is worth a nudge too.
    $this->makePosEntry($fixture['company'], $this->makeBranch($fixture['company']));

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('alerts', 2));
});

it('narrows every figure to the chosen branch', function () {
    $fixture = $this->journalFixture();
    $this->makeUser();

    $branch = $this->makeBranch($fixture['company'], ['name' => 'Banquet hall']);

    app(JournalEntryService::class)->store($fixture['company'], [
        'transaction_date' => now()->toDateString(),
        'branch_id' => $branch->id,
        'description' => 'Banquet deposit',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 800, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 800],
    ], post: true);

    $this->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('kpis.0.cents', 80000));

    $this->get('/dashboard?branch_id='.$branch->id)
        ->assertInertia(fn (Assert $page) => $page
            ->where('branchId', $branch->id)
            ->where('kpis.0.cents', 80000)
            ->has('branches', 1)
        );

    $this->get('/dashboard?branch_id=99999')
        ->assertInertia(fn (Assert $page) => $page->where('branchId', 99999));
});
