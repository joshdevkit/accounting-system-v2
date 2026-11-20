<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditTrail;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'import_batch_id',
    'laundry_increment_amount',
    'key_card_increment_amount',
    'amenities_increment_amount',
    'branch_id',
    'journal_entry_id',
    'business_date',
    'pos_reference',
    'calculation_version',
    'discount_rate',
    'complimentary_room_amount',
    'amount_due',
    'avl_increment_amount',
    'other_suppliers_increment_amount',
    'massage_increment_amount',
    'guest_name',
    'address',
    'tin',
    'business_source',
    'vat_classification',
    'discount_type',
    'arrival',
    'departure',
    'withholding_tax_rate',
    'basic_room_rate_amount',
    'extra_bed_amount',
    'room_upgrade_amount',
    'no_show_amount',
    'early_check_in_out_amount',
    'spa_amount',
    'laundry_amount',
    'shuttle_amount',
    'smoking_fee_amount',
    'key_card_amount',
    'amenities_amount',
    'avl_amount',
    'other_income_amount',
    'online_booking_amount',
    'withholding_tax_amount',
    'gross_sales',
    'discount_amount',
    'taxable_amount',
    'exempted_amount',
    'vat_amount',
    'local_tax_amount',
    'service_charge_amount',
    'net_sales',
    'cash_amount',
    'card_amount',
    'gcash_qrph_amount',
    'bank_transfer_amount',
    'cheque_amount',
    'accounts_receivable_amount',
    'room_post_amount',
    'gift_voucher_amount',
    'other_payment_amount',
    'status',
    'notes',
    'created_by',
    'posted_by',
    'posted_at',
    'voided_by',
    'voided_at',
])]

class PosEntry extends Model
{
    use RecordsAuditTrail;

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'laundry_increment_amount' => 'decimal:2',
            'key_card_increment_amount' => 'decimal:2',
            'amenities_increment_amount' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'complimentary_room_amount' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'avl_increment_amount' => 'decimal:2',
            'other_suppliers_increment_amount' => 'decimal:2',
            'massage_increment_amount' => 'decimal:2',
            'arrival' => 'datetime',
            'departure' => 'datetime',
            'withholding_tax_rate' => 'decimal:2',
            'basic_room_rate_amount' => 'decimal:2',
            'extra_bed_amount' => 'decimal:2',
            'room_upgrade_amount' => 'decimal:2',
            'no_show_amount' => 'decimal:2',
            'early_check_in_out_amount' => 'decimal:2',
            'spa_amount' => 'decimal:2',
            'laundry_amount' => 'decimal:2',
            'shuttle_amount' => 'decimal:2',
            'smoking_fee_amount' => 'decimal:2',
            'key_card_amount' => 'decimal:2',
            'amenities_amount' => 'decimal:2',
            'avl_amount' => 'decimal:2',
            'other_income_amount' => 'decimal:2',
            'online_booking_amount' => 'decimal:2',
            'withholding_tax_amount' => 'decimal:2',
            'gross_sales' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'exempted_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'local_tax_amount' => 'decimal:2',
            'service_charge_amount' => 'decimal:2',
            'net_sales' => 'decimal:2',
            'cash_amount' => 'decimal:2',
            'card_amount' => 'decimal:2',
            'gcash_qrph_amount' => 'decimal:2',
            'bank_transfer_amount' => 'decimal:2',
            'cheque_amount' => 'decimal:2',
            'accounts_receivable_amount' => 'decimal:2',
            'room_post_amount' => 'decimal:2',
            'gift_voucher_amount' => 'decimal:2',
            'other_payment_amount' => 'decimal:2',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
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
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function hasIncomeDetails(): bool
    {
        return in_array($this->calculation_version, ['sales_format_2', 'sales_format_3', 'sales_format_4'], true)
            || Money::cents($this->complimentary_room_amount) !== 0
            || Money::sum(array_map(
            fn (string $field) => $this->{$field},
            array_keys(config('accounting.pos.income_fields')),
        )) !== 0;
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    /**
     * Once posted, the numbers are part of the ledger and can only be
     * corrected by voiding the summary and recording a new one.
     */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    /**
     * Everything the till actually took in.
     */
    public function tenderedCents(): int
    {
        return Money::sum(array_map(
            fn (string $field) => $this->{$field},
            (array) config('accounting.pos.payment_fields'),
        ));
    }

    /**
     * Everything the till should have taken in for these sales.
     */
    public function expectedTenderCents(): int
    {
        if (in_array($this->calculation_version, ['sales_format_2', 'sales_format_3', 'sales_format_4'], true)) {
            return Money::cents($this->amount_due);
        }
        if ($this->hasIncomeDetails()) {
            return Money::cents($this->gross_sales)
                - Money::cents($this->discount_amount)
                - Money::cents($this->withholding_tax_amount);
        }

        return Money::cents($this->taxable_amount)
            + Money::cents($this->exempted_amount)
            + Money::cents($this->vat_amount)
            + Money::cents($this->local_tax_amount)
            + Money::cents($this->service_charge_amount);
    }

    public function varianceCents(): int
    {
        return $this->tenderedCents() - $this->expectedTenderCents();
    }
}
