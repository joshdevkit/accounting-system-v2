<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['fiscal_year_id', 'period_number', 'name', 'start_date', 'end_date', 'status'])]
class AccountingPeriod extends Model
{
    use RecordsAuditTrail;

    protected function casts(): array
    {
        return [
            'period_number' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function covers(string $date): bool
    {
        return $this->start_date->toDateString() <= $date && $this->end_date->toDateString() >= $date;
    }

    /**
     * Closing a period only blocks new postings; drafts already inside it stay
     * visible so that the period can still be finished off.
     */
    public function allowsPosting(): bool
    {
        return $this->isOpen() && ($this->fiscalYear?->isOpen() ?? true);
    }
}
