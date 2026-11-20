<?php

use App\Services\PurchasePostingService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AccountingFixtures;

uses(AccountingFixtures::class);

it('uses the selected credit account for posting and payment clearing', function () {
    $company = $this->makeCompany();
    $this->makeOpenFiscalYear($company);
    $branch = $this->makeBranch($company, ['code' => 'MAXI']);
    $user = $this->makeUser();

    $expense = $this->makeAccount($company, '6100-01', 'Purchase expense', 'expense');
    $selectedPayable = $this->makeAccount($company, '4104-01', 'Accounts Payable - Others', 'liability');
    $this->makeAccount($company, '4107-01', 'A/P Clearing', 'liability');
    $this->makeAccount($company, '1101-01', 'Cash', 'asset');

    $purchase = $company->purchases()->create([
        'branch_id' => $branch->id,
        'debit_account_id' => $expense->id,
        'purchase_date' => now()->toDateString(),
        'taxable_amount' => 100,
        'vendor_name' => 'Example vendor',
        'status' => 'draft',
    ]);
    $purchase->lines()->create([
        'account_id' => $expense->id,
        'particulars' => 'Supplies',
        'quantity' => 1,
        'unit_price' => 100,
        'amount' => 100,
        'tax_type' => 'non_vat',
        'vat_amount' => 0,
        'withholding_tax_rate' => 0,
        'withholding_tax_amount' => 0,
        'sort_order' => 0,
    ]);

    $this->get("/purchases/{$purchase->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchases/show')
            ->has('draftJournalLines', 2)
            ->where('draftJournalLines.1.credit_cents', 10000));

    $this->post("/purchases/{$purchase->id}/post", [])->assertSessionHasErrors('payable_account_id');
    $this->post("/purchases/{$purchase->id}/post", [
        'payable_account_id' => $expense->id,
    ])->assertSessionHasErrors('payable_account_id');

    $this->post("/purchases/{$purchase->id}/post", [
        'payable_account_id' => $selectedPayable->id,
    ])->assertSessionHasNoErrors();

    $purchase->refresh();
    expect($purchase->payable_account_id)->toBe($selectedPayable->id)
        ->and($purchase->journalEntry->lines->firstWhere('account_id', $selectedPayable->id)->credit)->toBe('100.00');

    app(PurchasePostingService::class)->pay($purchase, 'cash', $user);

    expect($purchase->refresh()->clearingJournalEntry->lines->firstWhere('account_id', $selectedPayable->id)->debit)->toBe('100.00');
});

it('keeps Gusto purchases credited to Due to Affiliates out of outgoing payments', function () {
    $company = $this->makeCompany();
    $this->makeOpenFiscalYear($company);
    $branch = $this->makeBranch($company, ['code' => 'GUSTO']);
    $this->makeUser();

    $expense = $this->makeAccount($company, '6100-02', 'Purchase expense', 'expense');
    $affiliate = $this->makeAccount($company, '4201-02', 'Due To Affiliates - Maximilian', 'liability');

    $purchase = $company->purchases()->create([
        'branch_id' => $branch->id,
        'debit_account_id' => $expense->id,
        'purchase_date' => now()->toDateString(),
        'taxable_amount' => 100,
        'vendor_name' => 'Maxi-paid vendor',
        'status' => 'draft',
    ]);
    $purchase->lines()->create([
        'account_id' => $expense->id,
        'particulars' => 'Supplies',
        'quantity' => 1,
        'unit_price' => 100,
        'amount' => 100,
        'tax_type' => 'non_vat',
        'vat_amount' => 0,
        'withholding_tax_rate' => 0,
        'withholding_tax_amount' => 0,
        'sort_order' => 0,
    ]);

    $this->post("/purchases/{$purchase->id}/post", [
        'payable_account_id' => $affiliate->id,
    ])->assertSessionHasNoErrors();

    expect($purchase->refresh()->isAffiliateSettlement())->toBeTrue()
        ->and($purchase->journalEntry->lines->firstWhere('account_id', $affiliate->id)->credit)->toBe('100.00');

    $this->get("/purchases/{$purchase->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('purchase.is_affiliate_settlement', true)
            ->where('actions.can_mark_for_voucher', false));

    $this->patch("/purchases/{$purchase->id}/processing-status", [
        'processing_status' => 'for_check',
    ])->assertStatus(422);

    $this->get('/outgoing-payments')
        ->assertInertia(fn (Assert $page) => $page->has('entries', 0));
});
