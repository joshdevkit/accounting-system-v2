<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'source_type', 'source_id', 'intercompany_journal_entry_id',
    'branch_id', 'clearing_account_id', 'payment_journal_entry_id', 'payee',
    'source_reference', 'amount', 'status', 'settlement_account', 'bank_name',
    'check_number', 'created_by', 'paid_by', 'paid_at',
])]
class IntercompanyVoucher extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function clearingAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'clearing_account_id');
    }

    public function intercompanyJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'intercompany_journal_entry_id');
    }

    public function paymentJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'payment_journal_entry_id');
    }
}
