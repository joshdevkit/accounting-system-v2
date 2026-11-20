<?php

use App\Services\JournalEntryService;
use Tests\Support\AccountingFixtures;

uses(AccountingFixtures::class);

it('moves a posted Maxi clearing entry into outgoing only after For Voucher and pays its clearing account once', function () {
    $company = $this->makeCompany();
    $this->makeOpenFiscalYear($company);
    $gusto = $this->makeBranch($company, ['code' => 'GUSTO']);
    $maxi = $this->makeBranch($company, ['code' => 'MAXI']);
    $user = $this->makeUser();
    $supplies = $this->makeAccount($company, '1604-02', 'Prepaid supplies - Gusto', 'asset');
    $dueFrom = $this->makeAccount($company, '1404-01', 'Due from Gusto', 'asset');
    $clearing = $this->makeAccount($company, '4107-01', 'A/P Clearing - Maxi', 'liability');
    $bank = $this->makeAccount($company, '1102-01', 'Bank - Maxi', 'asset');

    $purchase = $company->purchases()->create([
        'branch_id' => $gusto->id,
        'debit_account_id' => $supplies->id,
        'purchase_date' => now()->toDateString(),
        'vendor_name' => 'Example vendor',
        'taxable_amount' => 150,
        'status' => 'posted',
        'payment_status' => 'unpaid',
    ]);
    $purchase->lines()->create([
        'account_id' => $supplies->id,
        'particulars' => 'Foil',
        'quantity' => 1,
        'unit_price' => 150,
        'amount' => 150,
        'tax_type' => 'non_vat',
        'vat_amount' => 0,
        'withholding_tax_rate' => 0,
        'withholding_tax_amount' => 0,
        'sort_order' => 0,
    ]);
    $linked = app(JournalEntryService::class)->store($company, [
        'branch_id' => $maxi->id,
        'transaction_date' => now()->toDateString(),
        'description' => 'Maxi receivable for Gusto purchase',
        'source_type' => 'manual',
    ], [
        ['account_id' => $dueFrom->id, 'debit' => '150.00', 'credit' => '0.00'],
        ['account_id' => $clearing->id, 'debit' => '0.00', 'credit' => '150.00'],
    ], $user, post: true);
    $purchase->update(['intercompany_journal_entry_id' => $linked->id]);

    $this->get('/outgoing-payments')->assertInertia(fn ($page) => $page->has('intercompanyVouchers', 0));

    $this->post("/purchases/{$purchase->id}/intercompany-voucher")->assertSessionHasNoErrors();
    $this->get('/outgoing-payments')->assertInertia(fn ($page) => $page
        ->has('intercompanyVouchers', 1)
        ->where('intercompanyVouchers.0.branch', $maxi->name)
        ->where('intercompanyVouchers.0.amount_cents', 15000));

    $voucher = \App\Models\IntercompanyVoucher::query()->sole();
    $this->post("/intercompany-vouchers/{$voucher->id}/pay", [
        'settlement_account' => 'bank_transfer',
        'payment_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $voucher->refresh();
    expect($voucher->status)->toBe('paid')
        ->and($voucher->paymentJournalEntry->branch_id)->toBe($maxi->id)
        ->and($voucher->paymentJournalEntry->lines->firstWhere('account_id', $clearing->id)->debit)->toBe('150.00')
        ->and($voucher->paymentJournalEntry->lines->firstWhere('account_id', $bank->id)->credit)->toBe('150.00')
        ->and($purchase->refresh()->payment_status)->toBe('paid');

    $this->post("/intercompany-vouchers/{$voucher->id}/pay", [
        'settlement_account' => 'bank_transfer',
    ])->assertSessionHasErrors('voucher');
});

it('routes a posted expense clearing entry to an intercompany voucher', function () {
    $company = $this->makeCompany();
    $this->makeOpenFiscalYear($company);
    $gusto = $this->makeBranch($company, ['code' => 'GUSTO']);
    $maxi = $this->makeBranch($company, ['code' => 'MAXI']);
    $user = $this->makeUser();
    $expenseAccount = $this->makeAccount($company, '6100-02', 'Admin expense - Gusto', 'expense');
    $dueFrom = $this->makeAccount($company, '1404-01', 'Due from Gusto', 'asset');
    $clearing = $this->makeAccount($company, '4107-01', 'A/P Clearing - Maxi', 'liability');

    $expense = $company->expenses()->create([
        'branch_id' => $gusto->id,
        'expense_date' => now()->toDateString(),
        'payee' => 'Example payee',
        'status' => 'posted',
        'payment_status' => 'unpaid',
    ]);
    $expense->lines()->create([
        'account_id' => $expenseAccount->id,
        'particulars' => 'Office supplies',
        'quantity' => 1,
        'uom' => 'EA',
        'unit_price' => 25,
        'amount' => 25,
        'tax_type' => 'non_vat',
        'vat_amount' => 0,
        'withholding_tax_rate' => 0,
        'withholding_tax_amount' => 0,
        'sort_order' => 0,
    ]);
    $entry = app(JournalEntryService::class)->store($company, [
        'branch_id' => $maxi->id,
        'transaction_date' => now()->toDateString(),
        'description' => 'Maxi receivable for Gusto expense',
        'source_type' => 'manual',
    ], [
        ['account_id' => $dueFrom->id, 'debit' => 25, 'credit' => 0],
        ['account_id' => $clearing->id, 'debit' => 0, 'credit' => 25],
    ], $user, post: true);
    $expense->update(['intercompany_journal_entry_id' => $entry->id]);

    $this->post("/expenses/{$expense->id}/intercompany-voucher")->assertSessionHasNoErrors();
    $voucher = \App\Models\IntercompanyVoucher::query()->sole();
    expect($voucher->source_type)->toBe('expense')
        ->and($voucher->branch_id)->toBe($maxi->id)
        ->and($voucher->amount)->toBe('25.00')
        ->and($expense->refresh()->canMarkPaid())->toBeFalse();
});
