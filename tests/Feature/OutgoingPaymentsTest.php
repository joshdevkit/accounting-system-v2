<?php

use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AccountingFixtures;

uses(AccountingFixtures::class);

it('shows a posted purchase in outgoing payments only after For Voucher is selected', function () {
    $company = $this->makeCompany();
    $this->makeUser();
    $account = $this->makeAccount($company, '5110', 'Purchases', 'expense');

    $purchase = $company->purchases()->create([
        'debit_account_id' => $account->id,
        'purchase_date' => now()->toDateString(),
        'vendor_name' => 'Voucher Vendor',
        'taxable_amount' => 100,
        'status' => 'posted',
        'payment_status' => 'unpaid',
    ]);

    $this->get('/outgoing-payments')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('outgoing/Index')
            ->has('entries', 0));

    $this->patch("/purchases/{$purchase->id}/processing-status", [
        'processing_status' => 'for_check',
    ])->assertSessionHasNoErrors();

    $this->get('/outgoing-payments')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('outgoing/Index')
            ->has('entries', 1)
            ->where('entries.0.id', $purchase->id)
            ->where('entries.0.processing_status', 'for_cheque'));
});
