<?php

use App\Models\JournalEntry;
use App\Models\PosEntry;
use App\Services\PosPostingService;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AccountingFixtures;

uses(AccountingFixtures::class);

it('posts a day summary straight into the ledger', function () {
    $fixture = $this->posFixture();
    $user = $this->makeUser();

    $pos = $this->makePosEntry($fixture['company'], $fixture['branch'], [
        'business_date' => now()->toDateString(),
        'pos_reference' => 'Z-001',
        'gross_sales' => 11200,
        'discount_amount' => 0,
        'taxable_amount' => 10000,
        'exempted_amount' => 0,
        'net_sales' => 10000,
        'vat_amount' => 1200,
        'local_tax_amount' => 0,
        'service_charge_amount' => 0,
        'cash_amount' => 6000,
        'card_amount' => 3000,
        'gcash_qrph_amount' => 2200,
        'notes' => 'Tied out to the peso',
    ]);

    expect($pos->varianceCents())->toBe(0);

    $entry = app(PosPostingService::class)->post($pos, $user);

    $pos->refresh();

    expect($pos->status)->toBe('posted')
        ->and($pos->journal_entry_id)->toBe($entry->id)
        ->and($pos->posted_by)->toBe($user->id)
        ->and($pos->posted_at)->not->toBeNull()
        ->and($entry->status)->toBe('posted')
        ->and($entry->source_type)->toBe('pos')
        ->and($entry->source_reference)->toBe('Z-001')
        ->and($entry->branch_id)->toBe($fixture['branch']->id)
        ->and($entry->description)->toContain('POS summary Z-001');

    // Dr cash / card / GCash, Cr revenue + output VAT.
    expect($this->postedLines($entry))->toBe([
        '1110' => ['debit' => 600000, 'credit' => 0],
        '1140' => ['debit' => 300000, 'credit' => 0],
        '1130' => ['debit' => 220000, 'credit' => 0],
        '4130' => ['debit' => 0, 'credit' => 1000000],
        '2210' => ['debit' => 0, 'credit' => 120000],
    ]);

    expect($entry->isUnbalanced())->toBeFalse()
        ->and($entry->totalCents())->toBe(1120000);
});

it('pushes the difference onto the over and short account so the entry still balances', function () {
    $fixture = $this->posFixture();

    $pos = $this->makePosEntry($fixture['company'], $fixture['branch'], [
        'net_sales' => 1000,
        'vat_amount' => 120,
        'cash_amount' => 1115.50,
        'card_amount' => 0,
        'gcash_qrph_amount' => 0,
    ]);

    expect($pos->varianceCents())->toBe(-450);

    $entry = app(PosPostingService::class)->post($pos);

    expect($this->postedLines($entry))->toBe([
        '1110' => ['debit' => 111550, 'credit' => 0],
        '4130' => ['debit' => 0, 'credit' => 100000],
        '2210' => ['debit' => 0, 'credit' => 12000],
        // Short by 4.50, so the shortfall is debited to cash over/short.
        '1150' => ['debit' => 450, 'credit' => 0],
    ]);

    expect($entry->totalsCents())->toBe(['debits' => 112000, 'credits' => 112000]);
});

it('will not post a summary twice, or one that is not tied out when over and short is disabled', function () {
    $fixture = $this->posFixture();

    $pos = $this->makePosEntry($fixture['company'], $fixture['branch'], [
        'net_sales' => 500,
        'vat_amount' => 0,
        'cash_amount' => 500,
        'card_amount' => 0,
        'gcash_qrph_amount' => 0,
    ]);

    $service = app(PosPostingService::class);

    $service->post($pos);

    $this->expectValidationError(fn () => $service->post($pos->fresh()), 'status');

    config()->set('accounting.pos.over_short_account', null);

    $short = $this->makePosEntry($fixture['company'], $fixture['branch'], [
        'pos_reference' => 'Z-SHORT',
        'net_sales' => 500,
        'vat_amount' => 0,
        'cash_amount' => 480,
        'card_amount' => 0,
        'gcash_qrph_amount' => 0,
    ]);

    $this->expectValidationError(fn () => $service->post($short));

    expect($short->refresh()->status)->toBe('draft');
});

it('voids a posted summary with a reversing entry, never by deleting it', function () {
    $fixture = $this->posFixture();
    $user = $this->makeUser();

    $pos = $this->makePosEntry($fixture['company'], $fixture['branch'], [
        'net_sales' => 1000,
        'vat_amount' => 120,
        'cash_amount' => 1120,
        'card_amount' => 0,
        'gcash_qrph_amount' => 0,
    ]);

    $entry = app(PosPostingService::class)->post($pos, $user);
    $pos->refresh();

    expect($entry->totalCents())->toBe(112000);

    app(PosPostingService::class)->void($pos->fresh(), now(), $user);

    $pos->refresh();
    $entry->refresh();

    expect($pos->status)->toBe('voided')
        ->and($pos->voided_by)->toBe($user->id)
        ->and($pos->voided_at)->not->toBeNull()
        ->and($entry->status)->toBe('reversed');

    $reversal = $entry->reversal()->firstOrFail();

    expect($reversal->status)->toBe('posted')
        ->and($reversal->reverses_id)->toBe($entry->id)
        ->and($this->postedLines($reversal))->toBe([
            '1110' => ['debit' => 0, 'credit' => 112000],
            '4130' => ['debit' => 100000, 'credit' => 0],
            '2210' => ['debit' => 12000, 'credit' => 0],
        ]);

    // Original plus reversal net to nothing.
    expect($entry->totalCents() + $reversal->totalCents())->toBe(112000 + 112000)
        ->and($reversal->totalsCents())->toBe(['debits' => 112000, 'credits' => 112000]);

    $this->expectValidationError(
        fn () => app(PosPostingService::class)->void($pos->fresh()),
        'status',
    );
});

it('records a day summary from the UI and shows the mapping it will post through', function () {
    $fixture = $this->posFixture();
    $this->makeUser();

    $this->post('/pos-entries', [
        'branch_id' => $fixture['branch']->id,
        'business_date' => now()->toDateString(),
        'pos_reference' => 'Z-UI',
        'gross_sales' => '1120.00',
        'discount_amount' => '',
        'taxable_amount' => '1000.00',
        'exempted_amount' => '',
        'net_sales' => '1000.00',
        'vat_amount' => '120.00',
        'local_tax_amount' => '',
        'service_charge_amount' => '',
        'cash_amount' => '700.00',
        'gcash_qrph_amount' => '420.00',
        'notes' => 'Evening shift',
        'post_now' => true,
    ])->assertSessionHasNoErrors();

    $pos = PosEntry::query()->firstWhere('pos_reference', 'Z-UI');

    expect($pos)->not->toBeNull()
        ->and($pos->status)->toBe('posted')
        ->and($pos->journal_entry_id)->not->toBeNull()
        ->and(Money::cents($pos->cash_amount))->toBe(70000)
        ->and($pos->varianceCents())->toBe(0);

    $this->get(route('pos-entries.show', $pos))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pos-entries/show')
            ->where('pos.pos_reference', 'Z-UI')
            ->where('pos.tendered_cents', 112000)
            ->where('pos.expected_cents', 112000)
            ->where('pos.variance_cents', 0)
            ->where('actions.can_edit', false)
            ->where('actions.can_void', true)
            ->where('journalEntry.status', 'posted')
            ->has('journalEntry.lines', 4)
            ->has('mapping')
        );
});

it('rejects a duplicate terminal reference for the same branch and date', function () {
    $fixture = $this->posFixture();
    $this->makeUser();

    $payload = function (string $reference) use ($fixture): array {
        return [
            'branch_id' => $fixture['branch']->id,
            'business_date' => now()->toDateString(),
            'pos_reference' => $reference,
            'net_sales' => 100,
            'cash_amount' => 100,
        ];
    };

    $this->post('/pos-entries', $payload('Z-DUP'))->assertSessionHasNoErrors();

    $this->post('/pos-entries', $payload('Z-DUP'))->assertSessionHasErrors('pos_reference');

    expect(PosEntry::query()->where('pos_reference', 'Z-DUP')->count())->toBe(1);
});

it('keeps a draft summary editable in the UI but blocks changes once posted', function () {
    $fixture = $this->posFixture();
    $this->makeUser();

    $draft = $this->makePosEntry($fixture['company'], $fixture['branch'], [
        'pos_reference' => 'Z-DRAFT',
        'net_sales' => 100,
        'vat_amount' => 0,
        'cash_amount' => 100,
        'card_amount' => 0,
        'gcash_qrph_amount' => 0,
    ]);

    $this->get(route('pos-entries.edit', $draft))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pos-entries/edit')
            ->where('pos.pos_reference', 'Z-DRAFT')
            ->where('currency', $fixture['company']->base_currency_code)
        );

    $this->put(route('pos-entries.update', $draft), [
        'branch_id' => $fixture['branch']->id,
        'business_date' => now()->toDateString(),
        'pos_reference' => 'Z-DRAFT',
        'net_sales' => 250,
        'gross_sales' => 250,
        'taxable_amount' => 250,
        'vat_amount' => 0,
        'cash_amount' => 250,
        'post_now' => false,
    ])->assertRedirect();

    expect(Money::cents($draft->refresh()->net_sales))->toBe(25000)
        ->and($draft->status)->toBe('draft');

    $this->post(route('pos-entries.post', $draft))->assertSessionHasNoErrors();

    $this->get(route('pos-entries.edit', $draft))
        ->assertRedirect(route('pos-entries.show', $draft));
});

it('lists summaries with what was tendered against what was expected', function () {
    $fixture = $this->posFixture();
    $this->makeUser();

    $this->makePosEntry($fixture['company'], $fixture['branch'], [
        'pos_reference' => 'Z-LIST',
        'business_date' => now()->toDateString(),
        'net_sales' => 1000,
        'vat_amount' => 120,
        'cash_amount' => 1000,
        'card_amount' => 0,
        'gcash_qrph_amount' => 0,
    ]);

    $this->get('/pos-entries')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pos-entries/index')
            ->has('entries', 1)
            ->where('entries.0.pos_reference', 'Z-LIST')
            ->where('entries.0.amounts.net_sales', 100000)
            ->where('entries.0.tendered_cents', 100000)
            ->where('entries.0.variance_cents', -12000)
            ->where('totals.net_sales', 100000)
            ->where('currency', 'PHP')
        );
});

it('writes nothing to the ledger while a summary stays a draft', function () {
    $fixture = $this->posFixture();

    $this->makePosEntry($fixture['company'], $fixture['branch']);

    expect(JournalEntry::query()->count())->toBe(0)
        ->and(PosEntry::query()->where('status', 'draft')->count())->toBe(1);
});
