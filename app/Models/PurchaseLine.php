<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_id',
    'account_id',
    'particulars',
    'quantity',
    'uom',
    'unit_price',
    'amount',
    'tax_type',
    'vat_amount',
    'withholding_tax_rate',
    'withholding_tax_amount',
    'description',
    'sort_order',
])]
class PurchaseLine extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:4',
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'withholding_tax_rate' => 'decimal:2',
            'withholding_tax_amount' => 'decimal:2',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
