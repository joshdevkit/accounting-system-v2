<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'year', 'start_date', 'end_date', 'status'])]
class FiscalYear extends Model
{
    use RecordsAuditTrail;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class)->orderBy('period_number');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * The period covering today, or the latest period the year has.
     */
    public function currentPeriod(): ?AccountingPeriod
    {
        $today = now()->toDateString();
        $periods = $this->periods;

        return $periods->first(fn (AccountingPeriod $period): bool => $period->covers($today))
            ?? $periods->sortByDesc('period_number')->first();
    }

    public function covers(string $date): bool
    {
        return $this->start_date->toDateString() <= $date && $this->end_date->toDateString() >= $date;
    }
}
