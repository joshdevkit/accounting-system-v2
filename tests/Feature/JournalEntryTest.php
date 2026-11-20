<?php

use App\Models\JournalEntry;
use App\Services\FiscalYearService;
use App\Services\JournalEntryService;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AccountingFixtures;

uses(AccountingFixtures::class);

it('saves a balanced entry as a draft until it is posted', function () {
    $fixture = $this->journalFixture();
    $company = $fixture['company'];

    $entry = app(JournalEntryService::class)->store($company, [
        'transaction_date' => now()->toDateString(),
        'description' => 'Float issued to the front desk',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => '1500.00', 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => '1500.00'],
    ]);

    expect($entry->status)->toBe('draft')
        ->and($entry->posted_at)->toBeNull()
        ->and($entry->lines)->toHaveCount(2)
        ->and($entry->reference_no)->toMatch('/^JE-'.now()->format('Y').'-\d{5}$/');

    expect($entry->accountingPeriod)->not->toBeNull()
        ->and($entry->fiscalYear)->not->toBeNull()
        ->and(Money::cents($entry->lines->firstWhere('debit', '>', 0)?->debit))->toBe(150000);
});

it('refuses to post an entry whose debits and credits do not agree', function () {
    $fixture = $this->journalFixture();

    $service = app(JournalEntryService::class);

    $entry = $service->store($fixture['company'], [
        'transaction_date' => now()->toDateString(),
        'description' => 'Out by a peso',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 1000, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 999],
    ]);

    $exception = $this->expectValidationError(fn () => $service->post($entry), 'lines');

    expect($exception->errors()['lines'][0])->toContain('out by 1.00')
        ->and($entry->refresh()->status)->toBe('draft');
});

it('will not post a line into a summary or an inactive account', function () {
    $fixture = $this->journalFixture();
    $company = $fixture['company'];

    $summary = $this->makeSummaryAccount($company, '1100', 'Cash and equivalents');
    $this->makeAccount($company, '1101', 'Petty cash', 'asset', $summary);

    $inactive = $this->makeAccount($company, '1120', 'Dormant bank account', 'asset');
    $inactive->forceFill(['is_active' => false])->save();

    $service = app(JournalEntryService::class);

    $this->expectValidationError(
        fn () => $service->store($company, [
            'transaction_date' => now()->toDateString(),
            'description' => 'Posted against a summary account',
        ], [
            ['account_id' => $summary->id, 'debit' => 500, 'credit' => null],
            ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 500],
        ]),
        'lines.0.account_id',
    );

    $this->expectValidationError(
        fn () => $service->store($company, [
            'transaction_date' => now()->toDateString(),
            'description' => 'Posted against an inactive account',
        ], [
            ['account_id' => $inactive->id, 'debit' => 500, 'credit' => null],
            ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 500],
        ]),
        'lines.0.account_id',
    );
});

it('refuses to post once the accounting period is closed', function () {
    $fixture = $this->journalFixture();

    $service = app(JournalEntryService::class);
    $fiscalYears = app(FiscalYearService::class);

    $entry = $service->store($fixture['company'], [
        'transaction_date' => now()->toDateString(),
        'description' => 'Month end accrual',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 250, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 250],
    ]);

    $period = $entry->accountingPeriod;

    $fiscalYears->setPeriodStatus($period, 'closed');

    expect($period->refresh()->allowsPosting())->toBeFalse();

    $this->expectValidationError(
        fn () => $service->post($entry->fresh()),
        'transaction_date',
    );

    $fiscalYears->setPeriodStatus($period, 'open');

    expect($service->post($entry->fresh())->status)->toBe('posted');
});

it('reverses a posted entry with a swapped copy instead of editing it', function () {
    $fixture = $this->journalFixture();

    $service = app(JournalEntryService::class);

    $entry = $service->store($fixture['company'], [
        'transaction_date' => now()->toDateString(),
        'description' => 'Posted then reversed',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 700, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 700],
    ], post: true);

    expect($entry->status)->toBe('posted');

    $this->makeUser();

    $reversal = $service->reverse($entry->fresh());
    $entry->refresh();

    expect($entry->status)->toBe('reversed')
        ->and($entry->reversed_by_id)->toBe($reversal->id)
        ->and($reversal->reverses_id)->toBe($entry->id)
        ->and($reversal->status)->toBe('posted')
        ->and($reversal->description)->toContain('Reversal of '.$entry->reference_no)
        ->and($reversal->lines)->toHaveCount(2);

    $sideOf = fn (\Illuminate\Support\Collection $lines, bool $swap): array => $lines
        ->map(fn ($line): array => [
            'account_id' => $line->account_id,
            'debit' => Money::cents($swap ? $line->credit : $line->debit),
            'credit' => Money::cents($swap ? $line->debit : $line->credit),
        ])
        ->all();

    // The reversal mirrors the original: same accounts, opposite sides.
    expect($sideOf($reversal->lines, true))->toBe($sideOf($entry->lines, false))
        ->and($entry->totalCents())->toBe(70000)
        ->and($reversal->totalCents())->toBe(70000);

    $this->expectValidationError(
        fn () => $service->reverse($entry->fresh()),
        'reversal',
    );
});

it('keeps posted entries immutable but lets drafts be corrected and removed', function () {
    $fixture = $this->journalFixture();
    $company = $fixture['company'];

    $service = app(JournalEntryService::class);

    $posted = $service->store($company, [
        'transaction_date' => now()->toDateString(),
        'description' => 'Already posted',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 100, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 100],
    ], post: true);

    $this->expectValidationError(
        fn () => $service->update($posted->fresh(), [
            'transaction_date' => now()->toDateString(),
            'description' => 'Trying to rewrite history',
        ], []),
        'status',
    );

    $this->expectValidationError(fn () => $service->delete($posted->fresh()), 'status');

    $draft = $service->store($company, [
        'transaction_date' => now()->toDateString(),
        'description' => 'Draft to be corrected',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 100, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 100],
    ]);

    $updated = $service->update($draft->fresh(), [
        'transaction_date' => now()->toDateString(),
        'description' => 'Corrected amount',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 120, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 120],
    ]);

    expect($updated->description)->toBe('Corrected amount')
        ->and($updated->lines)->toHaveCount(2)
        ->and(Money::cents($updated->lines->firstWhere('debit', '>', 0)?->debit))->toBe(12000);

    $service->delete($updated->fresh());

    expect(JournalEntry::query()->whereKey($updated->id)->exists())->toBeFalse()
        ->and(JournalEntry::query()->whereKey($posted->id)->exists())->toBeTrue();
});

it('creates a draft through the UI and posts it from the entry page', function () {
    $fixture = $this->journalFixture();
    $user = $this->makeUser();

    $this->post('/journal-entries', [
        'transaction_date' => now()->toDateString(),
        'description' => 'Front desk float',
        'post_now' => false,
        'lines' => [
            ['account_id' => $fixture['cash']->id, 'debit' => '1500.00', 'credit' => '', 'description' => 'Issued'],
            ['account_id' => $fixture['revenue']->id, 'debit' => '', 'credit' => '1500.00'],
        ],
    ])->assertSessionHasNoErrors();

    $entry = JournalEntry::query()->firstWhere('description', 'Front desk float');

    expect($entry)->not->toBeNull()
        ->and($entry->status)->toBe('draft')
        ->and($entry->created_by)->toBe($user->id)
        ->and($entry->lines()->count())->toBe(2);

    $this->post(route('journal-entries.post', $entry))
        ->assertRedirect(route('journal-entries.show', $entry));

    expect($entry->refresh()->status)->toBe('posted')
        ->and($entry->posted_by)->toBe($user->id)
        ->and($entry->posted_at)->not->toBeNull();
});

it('reports validation errors instead of half saving an entry', function () {
    $fixture = $this->journalFixture();
    $this->makeUser();

    $this->post('/journal-entries', [
        'transaction_date' => now()->toDateString(),
        'description' => '',
        'lines' => [
            ['account_id' => $fixture['cash']->id, 'debit' => 100, 'credit' => 50],
            ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 100],
        ],
    ])->assertSessionHasErrors(['description', 'lines']);

    expect(JournalEntry::query()->count())->toBe(0);
});

it('shows the entry with its balance and the actions it still allows', function () {
    $fixture = $this->journalFixture();
    $this->makeUser();

    $entry = app(JournalEntryService::class)->store($fixture['company'], [
        'transaction_date' => now()->toDateString(),
        'description' => 'Balanced draft',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 900, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 900],
    ]);

    $this->get(route('journal-entries.show', $entry))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('journal-entries/show')
            ->where('entry.reference_no', $entry->reference_no)
            ->where('totals.debits', 90000)
            ->where('totals.credits', 90000)
            ->where('totals.difference', 0)
            ->where('actions.can_edit', true)
            ->where('actions.can_post', true)
            ->where('actions.can_reverse', false)
            ->where('currency', $fixture['company']->base_currency_code)
            ->has('entry.lines', 2)
        );
});

it('sends a posted entry back to its page instead of the edit form', function () {
    $fixture = $this->journalFixture();
    $this->makeUser();

    $entry = app(JournalEntryService::class)->store($fixture['company'], [
        'transaction_date' => now()->toDateString(),
        'description' => 'Posted',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 10, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 10],
    ], post: true);

    $this->get(route('journal-entries.edit', $entry))
        ->assertRedirect(route('journal-entries.show', $entry));

    $this->put(route('journal-entries.update', $entry), [
        'transaction_date' => now()->toDateString(),
        'description' => 'nope',
        'lines' => [
            ['account_id' => $fixture['cash']->id, 'debit' => 1, 'credit' => null],
            ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 1],
        ],
    ])->assertRedirect()->assertSessionHasErrors('status');

    expect($entry->refresh()->description)->toBe('Posted');
});

it('rejects a reference that another entry in the company already uses', function () {
    $fixture = $this->journalFixture();
    $this->makeUser();

    $payload = function (string $reference) use ($fixture): array {
        return [
            'transaction_date' => now()->toDateString(),
            'description' => 'Duplicate reference '.$reference,
            'reference_no' => $reference,
            'lines' => [
                ['account_id' => $fixture['cash']->id, 'debit' => 5, 'credit' => null],
                ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 5],
            ],
        ];
    };

    $this->post('/journal-entries', $payload('JE-DUP-1'))->assertSessionHasNoErrors();

    $this->post('/journal-entries', $payload('JE-DUP-1'))
        ->assertSessionHasErrors('reference_no');

    expect(JournalEntry::query()->where('reference_no', 'JE-DUP-1')->count())->toBe(1);
});
