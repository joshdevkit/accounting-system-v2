<?php

namespace App\Models;

use App\Support\BranchAccounts;
use DomainException;
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
    'clearing_journal_entry_id',
    'intercompany_journal_entry_id',
    'debit_account_id',
    'payable_account_id',
    'purchase_date',
    'due_date',
    'reference_no',
    'vendor_name',
    'taxable_amount',
    'tax_type',
    'vat_amount',
    'withholding_tax_rate',
    'withholding_tax_amount',
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
    'paid_by',
    'paid_at',
    'voided_by',
    'voided_at',
])]
class Purchase extends Model
{
    use RecordsAuditTrail;

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'taxable_amount' => 'decimal:2',
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

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'debit_account_id');
    }

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payable_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function paymentJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'payment_journal_entry_id');
    }

    public function clearingJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'clearing_journal_entry_id');
    }

    public function intercompanyJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'intercompany_journal_entry_id');
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

    public function lines()
    {
        return $this->hasMany(PurchaseLine::class)->orderBy('sort_order');
    }

    /**
     * The contact's name if one is linked, otherwise the free-text vendor
     * name typed on this record.
     */
    public function vendorDisplayName(): ?string
    {
        return $this->contact?->name ?? $this->vendor_name;
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

    public function isAwaitingPayment(): bool
    {
        return in_array($this->payment_status, ['unpaid', 'for_check', 'for_release'], true);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function canMarkPaid(): bool
    {
        return $this->status === 'posted'
            && ! $this->isAffiliateSettlement()
            && $this->intercompanyJournalEntry?->status !== 'posted'
            && in_array($this->payment_status, [null, 'unpaid', 'for_check', 'for_release'], true);
    }

    public function isAffiliateSettlement(): bool
    {
        if (! $this->payable_account_id || ! $this->branch) {
            return false;
        }

        try {
            return $this->payableAccount?->code === BranchAccounts::resolve(
                config('accounting.purchases.affiliate_due_to_account'),
                $this->branch,
            );
        } catch (DomainException) {
            return false;
        }
    }

    public function taxableAmount(): float
    {
        return (float) $this->lines->sum('amount');
    }

    public function vatAmount(): float
    {
        return (float) $this->lines->sum('vat_amount');
    }

    public function grossAmount(): float
    {
        return $this->taxableAmount() + $this->vatAmount();
    }

    // public function netPayable(): float
    // {
    //     return $this->grossAmount() - (float) $this->withholding_tax_amount;
    // }

    public function isOverdue(): bool
    {
        return $this->isAwaitingPayment()
            && $this->due_date !== null
            && $this->due_date->isPast();
    }
    public function withholdingTaxAmount(): float
    {
        return (float) $this->lines->sum('withholding_tax_amount');
    }

    public function netPayable(): float
    {
        return $this->grossAmount() - $this->withholdingTaxAmount();
    }
}
