<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\PosEntry;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a POS day summary (a Z report) into a balanced journal entry.
 *
 * A POS entry is aggregated: it carries tendered amounts per payment method,
 * taxes, service charge and discounts, but no line items. So the journal
 * entry is derived from the account codes in `config/accounting.php`:
 *
 *   Dr  each settlement account (cash, card, GCash, receivables, ...)
 *   Dr  sales discounts (contra revenue)
 *       Cr  revenue (net sales)
 *       Cr  VAT payable / local tax payable / service charge payable
 *
 * Anything left over between what was tendered and what should have been
 * tendered is posted to the over/short account, so the entry always balances.
 */
class PosPostingService
{
    public function __construct(
        protected JournalEntryService $journalEntries,
    ) {}

    public function post(PosEntry $pos, ?User $user = null): JournalEntry
    {
        if ($pos->hasIncomeDetails()) {
            throw ValidationException::withMessages([
                'status' => 'This entry has a workbook journal preview. Review its difference and account mappings before enabling ledger posting.',
            ]);
        }
        if ($pos->status === 'posted') {
            throw ValidationException::withMessages([
                'status' => 'This POS summary has already been posted.',
            ]);
        }

        if ($pos->journalEntry && $pos->journalEntry->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'This POS summary is already linked to a posted journal entry.',
            ]);
        }

        $lines = $this->lines($pos);

        return DB::transaction(function () use ($pos, $lines, $user) {
            $entry = $this->journalEntries->store(
                $pos->company,
                [
                    'branch_id' => $pos->branch_id,
                    'transaction_date' => $pos->business_date->toDateString(),
                    'description' => "POS summary {$pos->pos_reference}",
                    'source_type' => 'pos',
                    'source_reference' => $pos->pos_reference,
                ],
                $lines,
                $user,
                post: true,
            );

            $pos->fill([
                'status' => 'posted',
                'journal_entry_id' => $entry->id,
                'posted_by' => $user?->id,
                'posted_at' => now(),
            ])->save();

            return $entry;
        });
    }

    /**
     * Voiding a day summary reverses its journal entry when there is one, and
     * flags the POS row voided either way.
     */
    public function void(PosEntry $pos, mixed $date = null, ?User $user = null): void
    {
        if ($pos->status === 'voided') {
            throw ValidationException::withMessages([
                'status' => 'This POS summary is already voided.',
            ]);
        }

        $entry = $pos->journalEntry;

        if ($entry && $entry->status === 'posted') {
            // JournalEntryService::reverse() flags the POS row voided as well.
            $this->journalEntries->reverse($entry, $date ?? now(), $user);

            $pos->refresh()->fill([
                'voided_by' => $user?->id,
                'voided_at' => now(),
            ])->save();

            return;
        }

        $pos->fill([
            'status' => 'voided',
            'voided_by' => $user?->id,
            'voided_at' => now(),
        ])->save();
    }

    /**
     * The derived figures of a day summary: what was tendered against what
     * should have been tendered.
     *
     * @return array<string, int|string>
     */
    public function summary(PosEntry $pos): array
    {
        $payments = $this->paymentFields();

        $tendered = Money::sum(array_map(
            fn (string $field) => $pos->{$field},
            $payments,
        ));

        $expected = $pos->expectedTenderCents();

        return [
            'tendered' => $tendered,
            'expected' => $expected,
            'variance' => $tendered - $expected,
        ];
    }

    /**
     * @return list<string>
     */
    public function paymentFields(): array
    {
        return (array) config('accounting.pos.payment_fields');
    }

    /**
     * The accounts a given summary will be posted through, so the UI can show
     * the mapping before anything is saved.
     *
     * @return array<string, array{field: string, label: string, code: string|null, name: string|null}>
     */
    public function mapping(Company $company, ?Branch $branch = null): array
    {
        $config = $this->resolvedConfig($branch);
        $settlement = (array) $config['settlement_accounts'];
        $labels = (array) $config['payment_labels'];

        $codes = array_values(array_unique(array_filter(array_merge(
            array_values($settlement),
            [
                $config['discount_account'] ?? null,
                $config['revenue_account'] ?? null,
                $config['vat_payable_account'] ?? null,
                $config['local_tax_payable_account'] ?? null,
                $config['service_charge_payable_account'] ?? null,
                $config['over_short_account'] ?? null,
            ],
        ))));

        $accounts = Account::query()
            ->where('company_id', $company->id)
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        $mapping = [];

        foreach ($settlement as $field => $code) {
            $mapping[] = [
                'field' => (string) $field,
                'label' => (string) ($labels[$field] ?? $field),
                'side' => 'debit',
                'code' => $code ? (string) $code : null,
                'name' => $accounts->get($code)?->name,
            ];
        }

        foreach (
            [
                ['net_sales', 'Sales revenue', 'credit', 'revenue_account'],
                ['vat_amount', 'Output VAT', 'credit', 'vat_payable_account'],
                ['local_tax_amount', 'Local tax', 'credit', 'local_tax_payable_account'],
                ['service_charge_amount', 'Service charge', 'credit', 'service_charge_payable_account'],
            ] as [$field, $label, $side, $configKey]
        ) {
            $code = $config[$configKey] ?? null;

            $mapping[] = [
                'field' => $field,
                'label' => $label,
                'side' => $side,
                'code' => $code ? (string) $code : null,
                'name' => $code ? $accounts->get($code)?->name : null,
            ];
        }

        $shortCode = $config['over_short_account'] ?? null;

        $mapping[] = [
            'field' => 'variance',
            'label' => 'Cash over / short',
            'side' => 'either',
            'code' => $shortCode ? (string) $shortCode : null,
            'name' => $shortCode ? $accounts->get($shortCode)?->name : null,
        ];

        return $mapping;
    }

    /**
     * @return list<array{account_id: int, debit: string, credit: string, description: string}>
     */
    public function lines(PosEntry $pos): array
    {
        $config = $this->resolvedConfig($pos->branch);
        $settlement = (array) $config['settlement_accounts'];
        $labels = (array) $config['payment_labels'];

        $settlementAmounts = [];

        foreach ($settlement as $field => $code) {
            $cents = Money::cents($pos->{$field});

            if ($cents !== 0) {
                if (! $code) {
                    throw ValidationException::withMessages([$field => 'No account is configured for this payment method and branch.']);
                }
                $settlementAmounts[(string) $field] = $cents;
            }
        }

        // Revenue must exclude VAT — net_sales is VAT-inclusive and would
        // double-count the VAT that's also credited separately below.
        $revenue = Money::cents($pos->taxable_amount) + Money::cents($pos->exempted_amount);
        if ($revenue !== 0 && empty($config['revenue_account'])) {
            throw ValidationException::withMessages(['status' => 'No revenue account is configured for this branch.']);
        }

        $taxAmounts = [];

        foreach ($this->taxFields() as $field => $configKey) {
            $cents = Money::cents($pos->{$field});
            $code = $config[$configKey] ?? null;

            if ($cents !== 0 && ! $code) {
                throw ValidationException::withMessages([$field => 'No account is configured for this tax or charge and branch.']);
            }

            if ($cents !== 0 && $code) {
                $taxAmounts[(string) $field] = $cents;
            }
        }

        $codes = array_values(array_unique(array_filter(array_merge(
            array_map(fn (string $field): string => (string) $settlement[$field], array_keys($settlementAmounts)),
            $revenue !== 0 && ! empty($config['revenue_account']) ? [(string) $config['revenue_account']] : [],
            array_map(fn (string $field): string => (string) $config[$this->taxFields()[$field]], array_keys($taxAmounts)),
            ! empty($config['over_short_account']) ? [(string) $config['over_short_account']] : [],
        ))));

        /** @var Collection<string, Account> $accounts */
        $accounts = Account::query()
            ->where('company_id', $pos->company_id)
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        $missing = array_values(array_filter($codes, fn (string $code): bool => ! $accounts->has($code)));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'status' => 'The chart of accounts is missing '.implode(', ', $missing).', which this POS summary needs before it can be posted.',
            ]);
        }

        $lines = [];
        $debitCents = 0;
        $creditCents = 0;

        // Settlement accounts (cash, card, GCash, etc.) are debit-normal assets.
        foreach ($settlementAmounts as $field => $cents) {
            $lines[] = $this->line(
                $accounts->get((string) $settlement[$field])->id,
                max($cents, 0),
                min($cents, 0) * -1,
                (string) ($labels[$field] ?? $field),
            );

            $debitCents += $cents;
        }

        // Revenue is credit-normal.
        if ($revenue !== 0) {
            $lines[] = $this->line(
                $accounts->get((string) $config['revenue_account'])->id,
                min($revenue, 0) * -1,
                max($revenue, 0),
                'Sales revenue',
            );

            $creditCents += $revenue;
        }

        // VAT/local tax/service charge payable are liabilities, credit-normal.
        foreach ($taxAmounts as $field => $cents) {
            $code = (string) $config[$this->taxFields()[$field]];

            $lines[] = $this->line(
                $accounts->get($code)->id,
                min($cents, 0) * -1,
                max($cents, 0),
                $this->taxLabel((string) $field),
            );

            $creditCents += $cents;
        }

        $variance = $debitCents - $creditCents;

        if ($variance !== 0) {
            $code = $config['over_short_account'] ?? null;

            if (! $code || ! $accounts->has((string) $code)) {
                throw ValidationException::withMessages([
                    'other_payment_amount' => sprintf(
                        'Tendered amounts are out by %s against net sales plus taxes and service charge. Fix the amounts, or map accounting.pos.over_short_account to an over/short account.',
                        Money::format(abs($variance)),
                    ),
                ]);
            }

            // A shortage needs an extra debit to balance, an overage an extra
            // credit, so the sign of the variance decides the side.
            $lines[] = $this->line(
                $accounts->get((string) $code)->id,
                max(-$variance, 0),
                max($variance, 0),
                'Cash over / short',
            );
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'net_sales' => 'There is nothing to post: record net sales or at least one tendered amount.',
            ]);
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    protected function taxFields(): array
    {
        return [
            'vat_amount' => 'vat_payable_account',
            'local_tax_amount' => 'local_tax_payable_account',
            'service_charge_amount' => 'service_charge_payable_account',
        ];
    }

    public function resolvedConfig(?Branch $branch): array
    {
        $config = (array) config('accounting.pos');
        $suffix = $branch ? config('accounting.branch_suffixes.'.$branch->code) : null;
        $resolve = fn (mixed $code) => is_array($code) ? ($code[$suffix ?? ''] ?? null) : $code;
        $config['settlement_accounts'] = array_map($resolve, $config['settlement_accounts']);
        foreach (array_keys($config) as $key) {
            if (str_ends_with($key, '_account')) {
                $config[$key] = $resolve($config[$key]);
            }
        }

        return $config;
    }

    public function workbookPreview(PosEntry $pos): array
    {
        if (in_array($pos->calculation_version, ['sales_format_3', 'sales_format_4'], true)) {
            return $pos->journalEntry?->workbook_snapshot ?? app(PosWorkbookService::class)->preview($pos);
        }
        $calculated = app(PosSalesCalculationService::class)->calculate($pos->getAttributes());
        $config = $this->resolvedConfig($pos->branch);
        $suffix = config('accounting.branch_suffixes.'.$pos->branch?->code);
        $resolve = fn (mixed $code) => is_array($code) ? ($code[$suffix ?? ''] ?? null) : $code;
        $amount = fn (string $field): int => Money::cents($pos->{$field});
        $rows = [];
        $append = function (string $label, string $side, ?int $cents, mixed $code, string $basis) use (&$rows, $resolve): void {
            $rows[] = [
                'label' => $label,
                'side' => $side,
                'amount' => $cents,
                'code' => $resolve($code),
                'basis' => $basis,
            ];
        };
        $append('Cash on Hand', 'debit', $amount('cash_amount'), $config['settlement_accounts']['cash_amount'], 'Total cash payment');
        $append('Debit/Credit Card Sales', 'debit', $amount('card_amount'), $config['settlement_accounts']['card_amount'], 'Total card payment');
        $append('Cash in Bank', 'debit', $amount('bank_transfer_amount') + $amount('gcash_qrph_amount') + $amount('online_booking_amount'), $config['settlement_accounts']['bank_transfer_amount'], 'Bank transfer / QR Ph / online booking');
        $append('Accounts Receivable', 'debit', $amount('accounts_receivable_amount'), $config['settlement_accounts']['accounts_receivable_amount'], 'Total Send bill');
        $accounts = config('accounting.pos.workbook_accounts');
        $append('Room Revenue - Complementary Room', 'debit', $amount('complimentary_room_amount'), $accounts['complimentary_room'], 'Column N; excluded from gross sales');
        $append('Creditable Withholding Tax', 'debit', $calculated['withholding_tax_amount'], $config['withholding_tax_account'], 'VATable sales × withholding percentage');
        $append('Output Tax', 'credit', $calculated['vat_amount'], $config['vat_payable_account'], 'VATable sales × 12% when classified VATable');
        foreach ([
            'senior_20' => 'Discount - Senior Citizen',
            'employee_25' => 'Discount - Employee',
            'pwd_20' => 'Discount - PWD',
            'promo' => 'Promo Discount',
            'special_open' => 'Special Open Discount',
            'corporate_government' => "Discount - Corporate & Gov't",
        ] as $type => $label) {
            $selected = $pos->discount_type === $type;
            $cents = $selected ? ($calculated['discount_rate'] === null ? null : $calculated['discount_amount']) : 0;
            $append($label, 'credit', $cents, config('accounting.pos.discount_accounts.'.$type), $selected && $cents === null ? 'Enter discount percentage' : 'Selected discount amount; credit side from workbook');
        }
        foreach ([
            'spa_amount' => 'SPA',
            'laundry_amount' => 'Laundry Bag & Laundry Services',
            'shuttle_amount' => 'Shuttle Services',
            'smoking_fee_amount' => 'Smoking Fee',
            'key_card_amount' => 'Key Card',
            'amenities_amount' => 'Amenities',
            'avl_amount' => 'AVL',
        ] as $field => $label) {
            $append('Other Income - '.$label, 'credit', $amount($field), $accounts[$field], 'Imported income amount');
        }
        $append('Unearned Revenue - Gift Voucher', 'credit', $amount('gift_voucher_amount'), $accounts['gift_voucher'], 'Total gift vouchers; credit side from workbook');
        $append('Room Revenue', 'credit', $calculated['room_total'], $config['revenue_account'], 'Basic room rates + extra bed + upgrade + no show + early CI/CO');
        foreach ([
            ['avl_increment_amount', 'avl_payable', 'Accounts Payable - AVL'],
            ['other_suppliers_increment_amount', 'other_suppliers_payable', 'Accounts Payable - Other Suppliers'],
            ['massage_increment_amount', 'massage_payable', 'Accounts Payable - Massage Services'],
        ] as [$field, $key, $label]) {
            $cents = $amount('avl_amount') === 0 ? 0 : ($pos->{$field} === null ? null : $amount('avl_amount') - $amount($field));
            $append($label, 'credit', $cents, $accounts[$key], $cents === null ? 'Enter increment: workbook specifies AVL gross less increment' : 'AVL gross less increment, as written in workbook');
        }
        $debits = array_sum(array_map(fn (array $row): int => $row['side'] === 'debit' ? ($row['amount'] ?? 0) : 0, $rows));
        $credits = array_sum(array_map(fn (array $row): int => $row['side'] === 'credit' ? ($row['amount'] ?? 0) : 0, $rows));

        return [
            'sales' => $calculated,
            'rows' => $rows,
            'debits' => $debits,
            'credits' => $credits,
            'difference' => $debits - $credits,
            'complete' => ! in_array(null, array_column($rows, 'amount'), true),
        ];
    }

    protected function taxLabel(string $field): string
    {
        return match ($field) {
            'vat_amount' => 'Output VAT',
            'local_tax_amount' => 'Local tax',
            default => 'Service charge',
        };
    }

    /**
     * @return array{account_id: int, debit: string, credit: string, description: string}
     */
    protected function line(int $accountId, int $debitCents, int $creditCents, string $description): array
    {
        return [
            'account_id' => $accountId,
            'debit' => Money::decimal($debitCents),
            'credit' => Money::decimal($creditCents),
            'description' => $description,
        ];
    }
}
