<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditTrail;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'account_type_id',
    'parent_id',
    'code',
    'name',
    'description',
    'is_control_account',
    'is_active',
])]
class Account extends Model
{
    use RecordsAuditTrail;

    protected function casts(): array
    {
        return [
            'is_control_account' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Summary accounts exist for reporting only; balances are kept on the
     * accounts below them.
     */
    public function hasChildren(): bool
    {
        if (array_key_exists('children_count', $this->attributes)) {
            return (int) $this->children_count > 0;
        }

        return $this->children()->exists();
    }

    public function isPostable(): bool
    {
        return $this->is_active && ! $this->hasChildren();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLeaves(Builder $query): Builder
    {
        return $query->whereDoesntHave('children');
    }

    /**
     * Full "1110 · Cash on Hand" style label used in pickers.
     */
    public function label(): string
    {
        return "{$this->code} · {$this->name}";
    }

    /**
     * Posted balance of this account and everything below it, signed to its
     * normal side. Used on the chart of accounts screen.
     */
    public function balanceCents(): int
    {
        $debits = 0;
        $credits = 0;

        $this->collectBalances($debits, $credits);

        return $this->normalBalanceIsCredit() ? $credits - $debits : $debits - $credits;
    }

    public function normalBalanceIsCredit(): bool
    {
        $this->loadMissing('accountType');

        return $this->accountType?->normal_balance === 'credit';
    }

    protected function collectBalances(int &$debits, int &$credits): void
    {
        $rows = $this->journalEntryLines()
            ->selectRaw('COALESCE(SUM(debit), 0) as summed_debit, COALESCE(SUM(credit), 0) as summed_credit')
            ->whereHas('journalEntry', fn(Builder $query) => $query->where('journal_entries.status', 'posted'))
            ->first();

        $debits += Money::cents($rows?->summed_debit);
        $credits += Money::cents($rows?->summed_credit);

        foreach ($this->children as $child) {
            $child->collectBalances($debits, $credits);
        }
    }
}
