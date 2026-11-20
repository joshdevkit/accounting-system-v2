<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'type',
    'name',
    'contact_person',
    'tax_identification_number',
    'phone',
    'email',
    'address',
    'default_payment_terms_days',
    'notes',
    'is_active',
    'created_by',
])]
class Contact extends Model
{
    use RecordsAuditTrail;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_payment_terms_days' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isVendor(): bool
    {
        return in_array($this->type, ['vendor', 'both'], true);
    }

    public function isCustomer(): bool
    {
        return in_array($this->type, ['customer', 'both'], true);
    }

    /**
     * Everything currently owed to this vendor across posted, unpaid
     * purchases — net of any withholding.
     */
    public function outstandingPayableCents(): int
    {
        return $this->purchases()
            ->where('status', 'posted')
            ->where('payment_status', 'unpaid')
            ->get()
            ->sum(fn(Purchase $purchase): int => \App\Support\Money::cents($purchase->netPayable()));
    }

    /**
     * Everything currently owed by this customer across posted, unpaid
     * sales — net of any withholding.
     */
    public function outstandingReceivableCents(): int
    {
        return $this->sales()
            ->where('status', 'posted')
            ->where('payment_status', 'unpaid')
            ->get()
            ->sum(fn(Sale $sale): int => \App\Support\Money::cents($sale->netReceivable()));
    }


    /**
     * Debit = everything billed to this customer on posted sales.
     */
    public function totalDebitCents(): int
    {
        return $this->sales()
            ->where('status', 'posted')
            ->get()
            ->sum(fn(Sale $sale): int => \App\Support\Money::cents($sale->netReceivable()));
    }

    /**
     * Credit = the portion of those sales already paid.
     */
    public function totalCreditCents(): int
    {
        return $this->sales()
            ->where('status', 'posted')
            ->where('payment_status', 'paid')
            ->get()
            ->sum(fn(Sale $sale): int => \App\Support\Money::cents($sale->netReceivable()));
    }
}
