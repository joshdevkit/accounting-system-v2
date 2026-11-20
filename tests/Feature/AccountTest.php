<?php

use App\Models\Account;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AccountingFixtures;

uses(AccountingFixtures::class);

it('adds an account to the company chart of accounts', function () {
    $company = $this->makeCompany();
    $this->makeUser();

    $this->post('/accounts', [
        'code' => '1600',
        'name' => 'Right of use asset',
        'account_type_id' => $this->accountTypeId('asset'),
        'description' => 'Leased equipment',
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $account = Account::query()->firstWhere('code', '1600');

    expect($account)->not->toBeNull()
        ->and($account->name)->toBe('Right of use asset')
        ->and($account->company_id)->toBe($company->id)
        ->and($account->isPostable())->toBeTrue();
});

it('keeps account codes unique inside a company, but not across companies', function () {
    $company = $this->makeCompany();
    $this->makeUser();

    $this->makeAccount($company, '1110', 'Cash on Hand', 'asset');

    $this->post('/accounts', [
        'code' => '1110',
        'name' => 'Petty cash',
        'account_type_id' => $this->accountTypeId('asset'),
    ])->assertSessionHasErrors('code');

    // Each company owns its own chart, so the same code is fine elsewhere.
    $this->makeAccount($this->makeCompany(), '1110', 'Cash on Hand', 'asset');

    expect(Account::query()->where('code', '1110')->count())->toBe(2)
        ->and(Account::query()->where('code', '1110')->pluck('company_id')->unique())->toHaveCount(2);
});

it('treats an account with sub-accounts as a summary that cannot be posted to', function () {
    $company = $this->makeCompany();

    $parent = $this->makeSummaryAccount($company, '1100', 'Cash');
    $child = $this->makeAccount($company, '1110', 'Cash on Hand', 'asset', $parent);

    expect($child->isPostable())->toBeTrue()
        ->and($parent->refresh()->isPostable())->toBeFalse()
        ->and($parent->hasChildren())->toBeTrue();
});

it('deactivates an account without deleting its history', function () {
    $company = $this->makeCompany();
    $this->makeUser();

    $account = $this->makeAccount($company, '1110', 'Cash on Hand', 'asset');

    $this->patch(route('accounts.active', $account), [
        'is_active' => false,
    ])->assertSessionHasNoErrors();

    expect($account->refresh()->is_active)->toBeFalse()
        ->and($account->isPostable())->toBeFalse();

    $this->patch(route('accounts.active', $account), ['is_active' => true]);

    expect($account->refresh()->is_active)->toBeTrue();

    $this->get('/accounts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounts/index')
            ->has('accounts', 1)
            ->where('accounts.0.code', '1110')
            ->where('accounts.0.is_postable', true)
            ->where('stats.total', 1)
            ->where('stats.postable', 1)
        );
});

it('protects accounts that other records still point at', function () {
    $company = $this->makeCompany();
    $this->makeOpenFiscalYear($company);
    $this->makeUser();

    $parent = $this->makeSummaryAccount($company, '1100', 'Cash');
    $this->makeAccount($company, '1110', 'Cash on Hand', 'asset', $parent);

    $this->delete(route('accounts.destroy', $parent))->assertSessionHasErrors('account');

    $leaf = Account::query()->firstWhere('code', '1110');

    app(\App\Services\JournalEntryService::class)->store($company, [
        'transaction_date' => now()->toDateString(),
        'description' => 'Blocks the delete',
    ], [
        ['account_id' => $leaf->id, 'debit' => 30, 'credit' => null],
        ['account_id' => $this->makeAccount($company, '4130', 'Revenue', 'revenue')->id, 'debit' => null, 'credit' => 30],
    ], post: true);

    $this->delete(route('accounts.destroy', $leaf))->assertSessionHasErrors('account');

    expect(Account::query()->whereKey($leaf->id)->exists())->toBeTrue();
});

it('removes an unused account', function () {
    $company = $this->makeCompany();
    $this->makeUser();

    $account = $this->makeAccount($company, '1999', 'Scratch account', 'asset');

    $this->delete(route('accounts.destroy', $account))
        ->assertRedirect(route('accounts.index'));

    expect(Account::query()->whereKey($account->id)->exists())->toBeFalse();
});

it('will not let an account become its own parent', function () {
    $company = $this->makeCompany();
    $this->makeUser();

    $parent = $this->makeSummaryAccount($company, '1100', 'Cash');
    $child = $this->makeAccount($company, '1110', 'Cash on Hand', 'asset', $parent);

    $this->put(route('accounts.update', $parent), [
        'code' => '1100',
        'name' => 'Cash',
        'account_type_id' => $parent->account_type_id,
        'parent_id' => $child->id,
        'is_active' => true,
    ])->assertSessionHasErrors('parent_id');

    expect($parent->refresh()->parent_id)->toBeNull();
});

it('retypes an unused account but not one that already carries postings', function () {
    $company = $this->makeCompany();
    $this->makeOpenFiscalYear($company);
    $this->makeUser();

    $cash = $this->makeAccount($company, '1110', 'Cash on Hand', 'asset');

    $this->put(route('accounts.update', $cash), [
        'code' => '1110',
        'name' => 'Cash on hand, front desk',
        'account_type_id' => $this->accountTypeId('liability'),
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($cash->refresh()->name)->toBe('Cash on hand, front desk');

    app(\App\Services\JournalEntryService::class)->store($company, [
        'transaction_date' => now()->toDateString(),
        'description' => 'Posting that pins the type',
    ], [
        ['account_id' => $cash->id, 'debit' => 40, 'credit' => null],
        ['account_id' => $this->makeAccount($company, '4130', 'Revenue', 'revenue')->id, 'debit' => null, 'credit' => 40],
    ], post: true);

    $this->put(route('accounts.update', $cash), [
        'code' => '1110',
        'name' => 'Cash on hand, front desk',
        'account_type_id' => $this->accountTypeId('asset'),
        'is_active' => true,
    ])->assertSessionHasErrors('account_type_id');
});
