<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditTrail;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'company_id',
    'workbook_snapshot',
    'branch_id',
    'contact_id',
    'fiscal_year_id',
    'accounting_period_id',
    'reference_no',
    'transaction_date',
    'description',
    'source_type',
    'source_reference',
    'status',
    'created_by',
    'posted_by',
    'posted_at',
    'reverses_id',
    'reversed_by_id',
    'confirmed_by',
    'confirmed_at',
])]
class JournalEntry extends Model
{
    use RecordsAuditTrail;

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'workbook_snapshot' => 'array',
            'posted_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function posEntry(): HasOne
    {
        return $this->hasOne(PosEntry::class);
    }

    public function purchase()
    {
        return $this->hasOne(Purchase::class, 'journal_entry_id');
    }

    /**
     * The entry that was created to reverse this one.
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    /**
     * The original entry when this entry is a reversal.
     */
    public function reversesEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function paymentPurchase(): HasOne
{
    return $this->hasOne(Purchase::class, 'payment_journal_entry_id');
}

public function clearingPurchase(): HasOne
{
    return $this->hasOne(Purchase::class, 'clearing_journal_entry_id');
}

    
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    public function isManual(): bool
    {
        return $this->source_type === 'manual';
    }

    public function isEditable(): bool
    {
        return $this->isDraft() && $this->isManual();
    }

    // for new features of confirming
    // public function isEditable(): bool
    // {
    //     return $this->isDraft() && $this->isManual() && ! $this->isConfirmed();
    // }

    public function isReversible(): bool
    {
        return $this->isPosted() && $this->reversed_by_id === null;
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    /**
     * Total debits, which equal total credits as soon as the entry balances.
     */
    public function totalCents(): int
    {
        $this->loadMissing('lines');

        return Money::sum($this->lines->pluck('debit')->all());
    }

    public function totalsCents(): array
    {
        if ($this->relationLoaded('lines')) {
            $lines = $this->lines;
        } else {
            $lines = $this->lines()->get(['debit', 'credit']);
        }

        return [
            'debits' => Money::sum($lines->pluck('debit')->all()),
            'credits' => Money::sum($lines->pluck('credit')->all()),
        ];
    }

    /**
     * A draft that will be refused by the posting rules (unbalanced, or with
     * a single side) is worth flagging on the list screen.
     */
    public function isUnbalanced(): bool
    {
        if ($this->isPosted() || $this->isReversed()) {
            return false;
        }

        ['debits' => $debits, 'credits' => $credits] = $this->totalsCents();

        return $debits === 0 || $credits === 0 || $debits !== $credits;
    }


    public function scopeWithoutReleasedPurchases(Builder $query): Builder
    {
        $released = Purchase::query()->where('payment_status', 'paid');

        return $query
            ->whereNotIn('id', (clone $released)->whereNotNull('journal_entry_id')->select('journal_entry_id'))
            ->whereNotIn('id', (clone $released)->whereNotNull('clearing_journal_entry_id')->select('clearing_journal_entry_id'))
            ->whereNotIn('id', (clone $released)->whereNotNull('payment_journal_entry_id')->select('payment_journal_entry_id'));
    }

    public function scopeDisplayStatus(Builder $query, string $status): Builder
    {
        // Payment entries of cheques that were released (supplier collected).
        $purchases = Purchase::query()
            ->where('payment_status', 'paid')
            ->where('settlement_account', 'cheque')
            ->whereNotNull('payment_journal_entry_id')
            ->select('payment_journal_entry_id');

        $expenses = Expense::query()
            ->where('payment_status', 'paid')
            ->where('settlement_account', 'cheque')
            ->whereNotNull('payment_journal_entry_id')
            ->select('payment_journal_entry_id');

        if ($status === 'released') {
            return $query->where('status', 'posted')->where(
                fn($q) => $q->whereIn('id', $purchases)->orWhereIn('id', $expenses)
            );
        }

        if ($status === 'posted') {
            return $query->where('status', 'posted')
                ->whereNotIn('id', $purchases)
                ->whereNotIn('id', $expenses);
        }

        return $query->where('status', $status);
    }
}
