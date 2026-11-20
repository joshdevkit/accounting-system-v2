<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'code', 'name', 'address', 'phone', 'email', 'is_active'])]
class Branch extends Model
{
    use RecordsAuditTrail;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function accountSuffix(): string
    {
        return config("accounting.branch_suffixes.{$this->code}")
            ?? throw new \DomainException("No account suffix configured for branch code {$this->code}.");
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function posEntries(): HasMany
    {
        return $this->hasMany(PosEntry::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function hasActivity(): bool
    {
        return $this->journalEntries()->exists() || $this->posEntries()->exists();
    }
}
