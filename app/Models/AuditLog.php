<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'action',
    'auditable_type',
    'auditable_id',
    'old_values',
    'new_values',
    'ip_address',
    'user_agent',
])]
class AuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Human-readable subject shown in the audit log.
     */
    public function subjectLabel(): string
    {
        if (! $this->auditable_type) {
            return 'System';
        }

        $subject = match (class_basename($this->auditable_type)) {
            'JournalEntry' => 'Journal Entry',
            'JournalEntryLine' => 'Journal Entry Line',
            'PosEntry' => 'POS Entry',
            'Company' => 'Company',
            'Branch' => 'Branch',
            'Account' => 'Account',
            'User' => 'User',
            'FiscalYear' => 'Fiscal Year',
            'AccountingPeriod' => 'Accounting Period',
            default => Str::headline(class_basename($this->auditable_type)),
        };

        return $this->auditable_id
            ? "{$subject} #{$this->auditable_id}"
            : $subject;
    }

    /**
     * Human-readable field labels.
     *
     * The database column names remain stored in the audit trail,
     * but are never exposed directly by the presentation layer.
     *
     * @return array<string, string>
     */
    protected function fieldLabels(): array
    {
        return [
            'id' => 'ID',

            // General
            'name' => 'Name',
            'code' => 'Code',
            'description' => 'Description',
            'status' => 'Status',
            'type' => 'Type',
            'notes' => 'Notes',

            // References
            'reference_no' => 'Reference Number',
            'source_reference' => 'Source Reference',
            'pos_reference' => 'POS Reference',

            // Dates
            'transaction_date' => 'Transaction Date',
            'business_date' => 'Business Date',
            'posting_date' => 'Posting Date',
            'effective_date' => 'Effective Date',
            'posted_at' => 'Posted At',

            // Company / organization
            'company_id' => 'Company',
            'branch_id' => 'Branch',
            'department_id' => 'Department',

            // Accounting periods
            'fiscal_year_id' => 'Fiscal Year',
            'accounting_period_id' => 'Accounting Period',

            // Accounts
            'account_id' => 'Account',
            'account_code' => 'Account Code',
            'account_name' => 'Account Name',

            // Journal entries
            'journal_entry_id' => 'Journal Entry',
            'journal_entry_line_id' => 'Journal Entry Line',
            'debit' => 'Debit',
            'credit' => 'Credit',

            // POS
            'pos_entry_id' => 'POS Entry',
            'gross_sales' => 'Gross Sales',
            'net_sales' => 'Net Sales',
            'vat' => 'VAT',
            'output_vat' => 'Output VAT',
            'service_charge' => 'Service Charge',

            // Tenders
            'cash' => 'Cash',
            'card' => 'Card',
            'gcash' => 'GCash',
            'qrph' => 'QRPh',
            'room_post' => 'Room Post',

            // Users
            'user_id' => 'User',
            'created_by' => 'Created By',
            'posted_by' => 'Posted By',
            'reversed_by_id' => 'Reversed By',

            // Journal state
            'source_type' => 'Source',
            'reverses_id' => 'Reverses Entry',

            // Common business fields
            'amount' => 'Amount',
            'total' => 'Total',
            'balance' => 'Balance',

            // Authentication / system
            'email' => 'Email',
            'email_address' => 'Email',
            'role' => 'Role',
            'is_active' => 'Active',
            'is_enabled' => 'Enabled',
        ];
    }

    /**
     * Convert a database field name into a human-readable label.
     */
    public function fieldLabel(string $field): string
    {
        return $this->fieldLabels()[$field]
            ?? Str::headline($field);
    }

    /**
     * Field-by-field diff, ready for the audit log screen.
     *
     * Database column names are converted to presentation labels here.
     *
     * @return list<array{
     *     field: string,
     *     old: mixed,
     *     new: mixed
     * }>
     */
    public function changes(): array
    {
        $old = (array) ($this->old_values ?? []);
        $new = (array) ($this->new_values ?? []);

        $fields = array_values(
            array_unique(
                array_merge(
                    array_keys($old),
                    array_keys($new),
                ),
            ),
        );

        sort($fields);

        return array_map(
            fn (string $field): array => [
                'field' => $this->fieldLabel($field),
                'old' => $old[$field] ?? null,
                'new' => $new[$field] ?? null,
            ],
            $fields,
        );
    }
}