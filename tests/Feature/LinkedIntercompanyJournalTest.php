<?php

use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AccountingFixtures;

uses(AccountingFixtures::class);

it('posts a balanced intercompany journal from an inserted purchase and prevents a second entry', function () {
    $company = $this->makeCompany();
    $this->makeOpenFiscalYear($company);
    $gusto = $this->makeBranch($company, ['code' => 'GUSTO']);
    $maxi = $this->makeBranch($company, ['code' => 'MAXI']);
    $this->makeUser();
    $dueFrom = $this->makeAccount($company, '1404-01', 'Due from Affiliates - Gusto', 'asset');
    $clearing = $this->makeAccount($company, '4107-01', 'A/P Clearing - Maxi', 'liability');

    $purchase = $company->purchases()->create([
        'branch_id' => $gusto->id,
        'purchase_date' => now()->toDateString(),
        'vendor_name' => 'Example vendor',
        'status' => 'posted',
    ]);

    $payload = [
        'transaction_date' => now()->toDateString(),
        'branch_id' => $maxi->id,
        'description' => 'Maxi clears Gusto purchase',
        'lines' => [
            ['account_id' => $dueFrom->id, 'debit' => '150.00', 'credit' => '0'],
            ['account_id' => $clearing->id, 'debit' => '0', 'credit' => '150.00'],
        ],
    ];

    $this->post("/purchases/{$purchase->id}/intercompany-journal", $payload)->assertSessionHasNoErrors();

    $entry = $purchase->refresh()->intercompanyJournalEntry;
    expect($entry)->not->toBeNull()
        ->and($entry->status)->toBe('posted')
        ->and($entry->branch_id)->toBe($maxi->id)
        ->and($entry->lines->count())->toBe(2);

    $this->get("/purchases/{$purchase->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchases/show')
            ->where('intercompanyJournalEntry.id', $entry->id));

    $this->post("/purchases/{$purchase->id}/intercompany-journal", $payload)->assertSessionHasErrors('lines');
    expect($purchase->refresh()->intercompany_journal_entry_id)->toBe($entry->id);
});

it('links a debit and credit journal to an inserted expense', function () {
    $company = $this->makeCompany();
    $this->makeOpenFiscalYear($company);
    $branch = $this->makeBranch($company, ['code' => 'MAXI']);
    $this->makeUser();
    $debit = $this->makeAccount($company, '1404-01', 'Due from Affiliates', 'asset');
    $credit = $this->makeAccount($company, '4107-01', 'A/P Clearing', 'liability');

    $expense = $company->expenses()->create([
        'branch_id' => $branch->id,
        'expense_date' => now()->toDateString(),
        'payee' => 'Example payee',
        'status' => 'posted',
    ]);

    $this->post("/expenses/{$expense->id}/intercompany-journal", [
        'transaction_date' => now()->toDateString(),
        'branch_id' => $branch->id,
        'description' => 'Expense clearing',
        'lines' => [
            ['account_id' => $debit->id, 'debit' => 25, 'credit' => 0],
            ['account_id' => $credit->id, 'debit' => 0, 'credit' => 25],
        ],
    ])->assertSessionHasNoErrors();

    expect($expense->refresh()->intercompanyJournalEntry->status)->toBe('posted');
});
