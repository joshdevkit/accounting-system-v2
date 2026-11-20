<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'contact_id',
    'branch_id',
    'journal_entry_id',
    'payment_journal_entry_id',
    'credit_account_id',
    'sale_date',
    'due_date',
    'reference_no',
    'customer_name',
    'taxable_amount',
    'tax_type',
    'business_source',
    'discount_type',
    'vat_amount',
    'withholding_tax_rate',
    'withholding_tax_amount',
    'payment_status',
    'settlement_account',
    'notes',
    'status',
    'created_by',
    'posted_by',
    'posted_at',
    'paid_by',
    'paid_at',
    'voided_by',
    'voided_at',
])]
class Sale extends Model
{
    use RecordsAuditTrail;

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'taxable_amount' => 'decimal:2',
            'business_source' => 'string',
            'discount_type' => 'string',
            'vat_amount' => 'decimal:2',
            'withholding_tax_rate' => 'decimal:2',
            'withholding_tax_amount' => 'decimal:2',
            'posted_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'due_date' => 'date',
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

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function paymentJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'payment_journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function customerDisplayName(): ?string
    {
        return $this->contact?->name ?? $this->customer_name;
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

    public function canMarkPaid(): bool
    {
        return $this->isPosted() && $this->payment_status === 'unpaid';
    }

    /**
     * Gross amount before withholding — taxable + VAT.
     */
    public function grossAmount(): float
    {
        return (float) $this->taxable_amount + (float) $this->vat_amount;
    }

    /**
     * What you actually collect from the customer — gross minus what they withheld.
     */
    public function netReceivable(): float
    {
        return $this->grossAmount() - (float) $this->withholding_tax_amount;
    }

    public function isOverdue(): bool
    {
        return $this->payment_status === 'unpaid'
            && $this->due_date !== null
            && $this->due_date->isPast();
    }
}
