<?php

namespace App\Imports;

use App\Http\Requests\StorePosEntryRequest;
use App\Models\Company;
use App\Models\PosEntry;
use App\Models\User;
use App\Services\JournalEntryService;
use App\Services\PosSalesCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class PosEntryImport implements ToCollection, WithCalculatedFormulas
{
    protected int $imported = 0;

    public readonly string $batchId;

    public function __construct(
        protected Company $company,
        protected int $branchId,
        protected ?User $user,
    ) {
        $this->batchId = (string) Str::uuid();
    }

    public function collection(Collection $rows): void
    {
        $this->company->branches()->active()->findOrFail($this->branchId);
        $headerIndex = null;
        $columns = [];

        foreach ($rows->take(20) as $index => $row) {
            $candidate = $this->headers($row);
            if (isset($candidate['business_date'], $candidate['pos_reference'])) {
                $headerIndex = $index;
                $columns = $candidate;
                break;
            }
        }

        if ($headerIndex === null) {
            throw new \RuntimeException('Expected a sales header containing Date and Reference No.');
        }

        $detailHeaders = $this->headers($rows->get($headerIndex + 1, collect()));
        $hasDetailHeader = isset($detailHeaders['cash_amount']) || isset($detailHeaders['spa_amount']);
        if ($hasDetailHeader) {
            $columns = array_merge($columns, $detailHeaders);
        }

        $imported = DB::transaction(function () use ($rows, $headerIndex, $columns, $hasDetailHeader): int {
            $count = 0;
            foreach ($rows->slice($headerIndex + ($hasDetailHeader ? 2 : 1)) as $index => $row) {
                $dateCell = $row->get($columns['business_date']);
                $reference = trim((string) $row->get($columns['pos_reference']));
                if (str_starts_with(trim((string) $dateCell), '***')) {
                    break;
                }
                if (($dateCell === null || $dateCell === '') && $reference === '') {
                    continue;
                }
                if (in_array($this->normalize((string) $dateCell), ['grand total', 'total', 'subtotal'], true)) {
                    continue;
                }

                try {
                    $data = [
                        'branch_id' => $this->branchId,
                        'calculation_version' => isset($columns['ar_employee_amount']) ? 'sales_format_5' : (isset($columns['laundry_increment_amount']) ? 'sales_format_4' : (isset($columns['key_card_increment_amount']) || isset($columns['massage_increment_amount']) ? 'sales_format_3' : 'sales_format_2')),
                    ];
                    foreach ($columns as $field => $column) {
                        $value = $row->get($column);
                        $data[$field] = is_string($value) ? trim($value) : $value;
                    }
                    $data['pos_reference'] = $reference;
                    $data['business_date'] = $this->date($dateCell);
                    foreach (['arrival', 'departure'] as $field) {
                        $data[$field] = $this->date($data[$field] ?? null, true);
                    }
                    foreach ([
                        'business_source' => 'business_sources',
                        'vat_classification' => 'vat_classifications',
                        'discount_type' => 'discount_types',
                    ] as $field => $options) {
                        $data[$field] = $this->option($data[$field] ?? null, $options);
                    }
                    if ($data['calculation_version'] === 'sales_format_5') {
                        $data = $this->noOtherIncomeDiscount($data);
                    }
                    $rate = $data['withholding_tax_rate'] ?? null;
                    if ($this->normalize((string) $rate) === 'none') {
                        $rate = 0;
                    } elseif (is_string($rate) && str_ends_with($rate, '%')) {
                        $rate = trim(substr($rate, 0, -1));
                    } elseif (is_numeric($rate) && $rate > 0 && $rate < 1) {
                        $rate *= 100;
                    }
                    $data['withholding_tax_rate'] = $rate === '' ? null : $rate;
                    foreach (array_merge(config('accounting.pos.amount_fields'), config('accounting.pos.payment_fields')) as $field) {
                        $value = $data[$field] ?? 0;
                        $data[$field] = $value === '' || $value === null ? 0 : (is_string($value) ? str_replace(',', '', $value) : $value);
                    }

                    $request = StorePosEntryRequest::create('/', 'POST', $data);
                    $validated = Validator::make($data, $request->rules())->validate();
                    $validated = array_replace($validated, app(PosSalesCalculationService::class)->attributes($validated));

                    $pos = PosEntry::create([
                        ...$validated,
                        'import_batch_id' => $this->batchId,
                        'company_id' => $this->company->id,
                        'status' => 'draft',
                        'created_by' => $this->user?->id,
                    ]);
                    if (in_array($pos->calculation_version, ['sales_format_3', 'sales_format_4', 'sales_format_5'], true)) {
                        app(JournalEntryService::class)->savePosDraft($pos, $this->user);
                    }
                } catch (ValidationException $exception) {
                    throw new \RuntimeException('Row '.($index + 1).': '.implode(' ', $exception->validator->errors()->all()), previous: $exception);
                } catch (\Throwable $exception) {
                    throw new \RuntimeException('Row '.($index + 1).': '.$exception->getMessage(), previous: $exception);
                }
                $count++;
            }

            return $count;
        });
        $this->imported += $imported;
    }

    protected function headers(Collection $row): array
    {
        $aliases = [
            'spa_amount' => ['spa (actual cost)'],
            'massage_increment_amount' => ['spa (increment)'],
            'laundry_amount' => ['laundry bag & laundy services (actual cost)', 'laundry bag & laundry services (actual cost)'],
            'laundry_increment_amount' => ['laundry bag & laundy services (increment)', 'laundry bag & laundry services (increment)'],
            'key_card_amount' => ['key card (actua cost)', 'key card (actual cost)'],
            'key_card_increment_amount' => ['key card (increment)'],
            'amenities_amount' => ['amenities (pillows, robes, etc) actual cost'],
            'amenities_increment_amount' => ['amenities (pillows, robes, etc) increment'],
            'avl_amount' => ['avl (audio, visual, and lighting) actual cost'],
            'avl_increment_amount' => ['avl (audio, visual, and lighting) increment'],
            'other_income_amount' => ['others (flowers, slippers, cake, chocolates, balloons, etc) actual cost'],
            'other_suppliers_increment_amount' => ['others (flowers, slippers, cake, chocolates, balloons, etc) increment'],
            'business_date' => ['date', 'business date'],
            'pos_reference' => ['reference no', 'reference', 'bill no', 'pos reference'],
            'guest_name' => ['guest name'],
            'address' => ['address'],
            'tin' => ['tin'],
            'business_source' => ['business source'],
            'arrival' => ['arrival'],
            'departure' => ['departure'],
            'vat_classification' => ['vatable', 'vat classification'],
            'withholding_tax_rate' => ['wittholding tax', 'withholding tax', 'withholding tax rate'],
            'discount_type' => ['discounts', 'discount type'],
            'discount_rate' => ['discount rate', 'discount percentage', 'percentage discount'],
            'basic_room_rate_amount' => ['basic room rates', 'basic room rates (exclusive of discounts - corporate/gov\'t dsicount)', 'basic room rates (exclusive of discounts - corporate/gov\'t discount)'],
            'complimentary_room_amount' => ['complementary room (no charge - visitors)', 'complimentary room (no charge - visitors)', 'complementary room', 'complimentary room'],
            'laundry_amount' => [
                'laundry bag & laundy services (actual cost)',
                'laundry bag & laundry services (actual cost)',
                'laundry bag & laundy services',
                'laundry bag & laundry services',
            ],
            'cash_amount' => ['cash'],
            'card_amount' => ['card payment', 'card'],
            'bank_transfer_amount' => ['bank transfer & qr ph', 'bank transfer', 'bank transfer, qr ph, online booking'],
            'online_booking_amount' => ['online booking'],
            'accounts_receivable_amount' => ['send bill'],
            'ar_employee_amount' => ['ar employees', 'ar employee'],
            'gift_voucher_amount' => ['gift voucher'],
        ];
        foreach (config('accounting.pos.income_fields') as $field => $label) {
            $aliases[$field][] = $this->normalize($label);
        }
        $columns = [];
        foreach ($row as $column => $value) {
            foreach ($aliases as $field => $labels) {
                if (in_array($this->normalize((string) $value), $labels, true)) {
                    $columns[$field] = $column;
                    break;
                }
            }
        }

        return $columns;
    }

    /**
     * "Maxi (sample entry) (no OI)": the Discounts column holds a plain description
     * (None / Employee / Senior Citizen / PWD) and the next column the rate as an
     * Excel fraction (0.25 = 25%). A rate with description "None" is a promo.
     */
    protected function noOtherIncomeDiscount(array $data): array
    {
        $rate = $data['discount_rate'] ?? null;
        if (is_numeric($rate) && $rate > 0 && $rate < 1) {
            $rate *= 100;
        }
        $data['discount_rate'] = $rate === '' ? null : $rate;

        $type = $this->normalize((string) ($data['discount_type'] ?? ''));
        $data['discount_type'] = match (true) {
            str_contains($type, 'senior') => 'senior_20',
            str_contains($type, 'pwd') => 'pwd_20',
            str_contains($type, 'employee') => 'employee_25',
            in_array($type, ['', 'none'], true) => is_numeric($rate) && $rate > 0 ? 'promo' : 'none',
            default => $data['discount_type'],
        };

        return $data;
    }

    protected function normalize(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', str_replace('.', '', $value))));
    }

    protected function option(mixed $value, string $options): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        foreach (config('accounting.pos.'.$options) as $key => $label) {
            if (in_array($this->normalize((string) $value), [$this->normalize($key), $this->normalize($label)], true)) {
                return $key;
            }
        }

        return (string) $value;
    }

    protected function date(mixed $value, bool $withTime = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = is_numeric($value)
            ? Date::excelToDateTimeObject($value)
            : Carbon::parse($value);

        return $date->format($withTime ? 'Y-m-d H:i:s' : 'Y-m-d');
    }

    public function imported(): int
    {
        return $this->imported;
    }
}
