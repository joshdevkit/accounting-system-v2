<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'contact_id',
    'branch_id',
    'journal_entry_id',
    'payment_journal_entry_id',
    'intercompany_journal_entry_id',
    'paid_at',
    'paid_by',
    'expense_date',
    'reference_no',
    'payee',
    'description',
    'payment_status',
    'settlement_account',
    'bank_name',
    'check_number',
    'notes',
    'status',
    'created_by',
    'posted_by',
    'posted_at',
    'voided_by',
    'voided_at',
])]
class Expense extends Model
{
    use RecordsAuditTrail;

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
            'paid_at' => 'datetime',
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

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class)->orderBy('sort_order');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voider_by');
    }

    public function paymentJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'payment_journal_entry_id');
    }

    public function intercompanyJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'intercompany_journal_entry_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function canMarkPaid(): bool
    {
        return $this->isPosted()
            && $this->payment_status === 'unpaid'
            && $this->intercompanyJournalEntry?->status !== 'posted';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function taxableAmount(): float
    {
        return (float) $this->lines->sum('amount');
    }

    public function vatAmount(): float
    {
        return (float) $this->lines->sum('vat_amount');
    }

    public function withholdingTaxAmount(): float
    {
        return (float) $this->lines->sum('withholding_tax_amount');
    }

    public function grossAmount(): float
    {
        return $this->taxableAmount() + $this->vatAmount();
    }

    public function netPayable(): float
    {
        return $this->grossAmount() - $this->withholdingTaxAmount();
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function payeeDisplayName(): ?string
    {
        return $this->contact?->name ?? $this->payee;
    }
}
