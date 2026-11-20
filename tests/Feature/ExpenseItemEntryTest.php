<?php

use App\Models\Expense;
use Tests\Support\AccountingFixtures;

uses(AccountingFixtures::class);

it('saves General and Administrative expenses with purchase-style item details', function () {
    $company = $this->makeCompany();
    $branch = $this->makeBranch($company, ['code' => 'MAXI']);
    $account = $this->makeAccount($company, '6100-01', 'Office supplies', 'expense');
    $this->makeUser();

    $this->post('/expenses', [
        'branch_id' => $branch->id,
        'expense_date' => now()->toDateString(),
        'payee' => 'Office supplier',
        'lines' => [[
            'account_id' => $account->id,
            'particulars' => 'Pens',
            'quantity' => '2',
            'uom' => 'pcs',
            'unit_price' => '125.00',
            'amount' => '250.00',
            'tax_type' => 'non_vat',
            'withholding_tax_rate' => '0',
        ]],
    ])->assertSessionHasNoErrors();

    $line = Expense::query()->firstOrFail()->lines()->firstOrFail();

    expect($line->quantity)->toBe('2.00')
        ->and($line->uom)->toBe('pcs')
        ->and($line->unit_price)->toBe('125.0000')
        ->and($line->amount)->toBe('250.00');
});
