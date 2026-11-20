<?php

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Services\JournalEntryService;
use App\Services\PosPostingService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AccountingFixtures;

uses(AccountingFixtures::class);

it('records who posted an entry and what changed', function () {
    $fixture = $this->journalFixture();
    $user = $this->makeUser();

    $entry = app(JournalEntryService::class)->store($fixture['company'], [
        'transaction_date' => now()->toDateString(),
        'description' => 'Trail me',
    ], [
        ['account_id' => $fixture['cash']->id, 'debit' => 100, 'credit' => null],
        ['account_id' => $fixture['revenue']->id, 'debit' => null, 'credit' => 100],
    ], $user);

    $created = AuditLog::query()
        ->where('auditable_type', JournalEntry::class)
        ->where('auditable_id', $entry->id)
        ->where('action', 'created')
        ->first();

    expect($created)->not->toBeNull()
        ->and($created->user_id)->toBe($user->id)
        ->and($created->new_values['description'])->toBe('Trail me')
        ->and($created->new_values['status'])->toBe('draft')
        ->and($created->old_values)->toBe([])
        ->and($created->subjectLabel())->toBe('Journal Entry #'.$entry->id);

    app(JournalEntryService::class)->post($entry->fresh(), $user);

    $posted = AuditLog::query()
        ->where('auditable_type', JournalEntry::class)
        ->where('auditable_id', $entry->id)
        ->where('action', 'updated')
        ->first();

    expect($posted)->not->toBeNull()
        ->and($posted->new_values['status'])->toBe('posted')
        ->and($posted->old_values['status'])->toBe('draft')
        ->and($posted->changes())->toBe([
            ['field' => 'posted_at', 'old' => null, 'new' => $entry->fresh()->posted_at->toDateTimeString()],
            ['field' => 'posted_by', 'old' => null, 'new' => $user->id],
            ['field' => 'status', 'old' => 'draft', 'new' => 'posted'],
        ]);

    // Passwords and timestamps are never copied into the trail.
    expect($posted->new_values)->not->toHaveKey('updated_at');
});

it('follows a posted day summary and its reversal', function () {
    $fixture = $this->posFixture();
    $user = $this->makeUser();

    $pos = $this->makePosEntry($fixture['company'], $fixture['branch'], [
        'net_sales' => 100,
        'vat_amount' => 0,
        'cash_amount' => 100,
        'card_amount' => 0,
        'gcash_qrph_amount' => 0,
    ]);

    $entry = app(PosPostingService::class)->post($pos, $user);
    app(PosPostingService::class)->void($pos->fresh(), now(), $user);

    expect(AuditLog::query()->where('auditable_type', $pos::class)->count())->toBeGreaterThan(1)
        ->and(AuditLog::query()
            ->where('auditable_type', JournalEntry::class)
            ->where('auditable_id', $entry->id)
            ->where('action', 'updated')
            ->exists())->toBeTrue();

    $voided = AuditLog::query()
        ->where('auditable_type', $pos::class)
        ->where('auditable_id', $pos->id)
        ->where('action', 'updated')
        ->latest('id')
        ->first();

    expect($voided->new_values['status'])->toBe('voided')
        ->and($voided->user_id)->toBe($user->id);
});

it('lists the trail with the filters the screen offers', function () {
    $company = $this->makeCompany();
    $user = $this->makeUser();

    $account = $this->makeAccount($company, '1110', 'Cash on Hand', 'asset');
    $account->forceFill(['name' => 'Cash on hand'])->save();

    $this->get('/audit-log')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audit-log/index')
            ->where('stats.total', 2)
            ->where('stats.today', 2)
            ->has('logs', 2)
            ->where('logs.0.action', 'updated')
            ->where('logs.0.subject', 'Account #'.$account->id)
            ->where('logs.0.user.name', $user->name)
            ->where('logs.0.changes.0.field', 'name')
            ->where('logs.0.changes.0.old', 'Cash on Hand')
            ->where('logs.0.changes.0.new', 'Cash on hand')
            ->has('subjects')
            ->has('actions')
            ->has('users', 1)
            ->has('pagination')
        );

    $this->get('/audit-log?action=created')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.action', 'created')
            ->has('logs', 1)
            ->where('logs.0.action', 'created')
        );

    $this->get('/audit-log?subject='.urlencode(Account::class).'&user_id='.$user->id)
        ->assertInertia(fn (Assert $page) => $page->has('logs', 2));

    $this->get('/audit-log?q=nothing-matches-this')
        ->assertInertia(fn (Assert $page) => $page->has('logs', 0));
});

it('exposes no way to write the trail from the outside', function () {
    $routes = app('router')->getRoutes();

    expect($routes->getByName('audit-log.index'))->not->toBeNull()
        ->and($routes->getByName('audit-log.store'))->toBeNull()
        ->and($routes->getByName('audit-log.update'))->toBeNull()
        ->and($routes->getByName('audit-log.destroy'))->toBeNull();
});
