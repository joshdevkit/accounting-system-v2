<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FiscalYear;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalYearService
{
    /**
     * Create a fiscal year and, unless disabled, its accounting periods.
     *
     * @param  array{year: int|string, start_date: string, end_date: string, status?: string}  $attributes
     */
    public function create(Company $company, array $attributes): FiscalYear
    {
        $start = $this->date($attributes['start_date']);
        $end = $this->date($attributes['end_date']);

        $this->ensureNoOverlap($company->id, $start, $end);

        return DB::transaction(function () use ($company, $attributes, $start, $end) {
            /** @var FiscalYear $fiscalYear */
            $fiscalYear = $company->fiscalYears()->create([
                'year' => (int) $attributes['year'],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => $attributes['status'] ?? 'open',
            ]);

            if (config('accounting.fiscal_years.auto_generate_periods', true)) {
                $this->generatePeriods($fiscalYear);
            }

            return $fiscalYear;
        });
    }

    /**
     * @param  array{year?: int|string, start_date?: string, end_date?: string, status?: string}  $attributes
     */
    public function update(FiscalYear $fiscalYear, array $attributes): FiscalYear
    {
        $start = $this->date($attributes['start_date'] ?? $fiscalYear->start_date);
        $end = $this->date($attributes['end_date'] ?? $fiscalYear->end_date);

        $this->ensureNoOverlap($fiscalYear->company_id, $start, $end, $fiscalYear->id);

        $datesChanged = $start->toDateString() !== $fiscalYear->start_date->toDateString()
            || $end->toDateString() !== $fiscalYear->end_date->toDateString();

        if ($datesChanged && $fiscalYear->journalEntries()->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'Journal entries are already assigned to this fiscal year, so its dates can no longer be changed.',
            ]);
        }

        $fiscalYear->fill([
            'year' => isset($attributes['year']) ? (int) $attributes['year'] : $fiscalYear->year,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => $attributes['status'] ?? $fiscalYear->status,
        ])->save();

        if ($datesChanged) {
            $this->resetPeriods($fiscalYear);
        }

        return $fiscalYear;
    }

    /**
     * Fill in any missing periods for the fiscal year.
     *
     * Periods are anniversary based on the fiscal year start date, so a
     * Jan 1 start produces calendar months. Returns the number created.
     */
    public function generatePeriods(FiscalYear $fiscalYear): int
    {
        $total = max(1, (int) config('accounting.fiscal_years.periods', 12));

        $fiscalYear->loadMissing('periods');

        $existing = $fiscalYear->periods
            ->map(fn (AccountingPeriod $period): int => (int) $period->period_number)
            ->all();

        $start = $this->date($fiscalYear->start_date);
        $end = $this->date($fiscalYear->end_date);
        $cursor = $start;
        $created = 0;

        for ($number = 1; $number <= $total; $number++) {
            if ($cursor->greaterThan($end)) {
                break;
            }

            $periodEnd = $number === $total
                ? $end
                : $this->earlier($start->addMonths($number)->subDay(), $end);

            if ($periodEnd->lessThan($cursor)) {
                $periodEnd = $cursor;
            }

            if (! in_array($number, $existing, true)) {
                AccountingPeriod::query()->create([
                    'fiscal_year_id' => $fiscalYear->id,
                    'period_number' => $number,
                    'name' => $cursor->format('F Y'),
                    'start_date' => $cursor->toDateString(),
                    'end_date' => $periodEnd->toDateString(),
                    'status' => 'open',
                ]);

                $created++;
            }

            $cursor = $periodEnd->addDay();
        }

        if ($created > 0) {
            $fiscalYear->unsetRelation('periods');
        }

        return $created;
    }

    /**
     * Rebuild the periods that hold no journal entries (used when the
     * fiscal year dates change).
     */
    public function resetPeriods(FiscalYear $fiscalYear): int
    {
        $fiscalYear->periods()
            ->whereDoesntHave('journalEntries')
            ->delete();

        return $this->generatePeriods($fiscalYear);
    }

    public function setStatus(FiscalYear $fiscalYear, string $status): FiscalYear
    {
        if ($status === 'closed') {
            // Closing the year closes every open period too: nothing new may
            // be posted into a year that has been signed off.
            $fiscalYear->periods()->where('status', 'open')->update(['status' => 'closed']);
        }

        $fiscalYear->fill(['status' => $status])->save();

        return $fiscalYear;
    }

    public function setPeriodStatus(AccountingPeriod $period, string $status): AccountingPeriod
    {
        $period->fill(['status' => $status])->save();

        return $period;
    }

    public function delete(FiscalYear $fiscalYear): void
    {
        if ($fiscalYear->journalEntries()->exists()) {
            throw ValidationException::withMessages([
                'fiscal_year' => 'This fiscal year already has journal entries and cannot be removed.',
            ]);
        }

        $fiscalYear->delete();
    }

    /**
     * The accounting period a date belongs to, if the company has one.
     */
    public function periodFor(Company|int $company, mixed $date): ?AccountingPeriod
    {
        $companyId = $company instanceof Company ? $company->id : $company;
        $date = $this->date($date);

        return AccountingPeriod::query()
            ->whereHas('fiscalYear', function ($query) use ($companyId): void {
                $query->where('company_id', $companyId);
            })
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->with('fiscalYear')
            ->orderBy('start_date')
            ->first();
    }

    /**
     * The period new entries should default to: today's period, falling back
     * to the most recent period the company has.
     */
    public function currentPeriod(Company $company): ?AccountingPeriod
    {
        return $this->periodFor($company, CarbonImmutable::now())
            ?? AccountingPeriod::query()
                ->whereHas('fiscalYear', fn ($query) => $query->where('company_id', $company->id))
                ->orderByDesc('start_date')
                ->with('fiscalYear')
                ->first();
    }

    /**
     * @throws ValidationException when the period (or its fiscal year) is closed
     */
    public function assertOpen(AccountingPeriod $period): void
    {
        $fiscalYear = $period->fiscalYear ?? FiscalYear::query()->find($period->fiscal_year_id);

        if ($fiscalYear && $fiscalYear->status !== 'open') {
            throw ValidationException::withMessages([
                'transaction_date' => "The {$fiscalYear->year} fiscal year is closed.",
            ]);
        }

        if ($period->status !== 'open') {
            throw ValidationException::withMessages([
                'transaction_date' => "The accounting period \"{$period->name}\" is closed.",
            ]);
        }
    }

    protected function ensureNoOverlap(int $companyId, CarbonImmutable $start, CarbonImmutable $end, ?int $ignoreId = null): void
    {
        $overlap = FiscalYear::query()
            ->where('company_id', $companyId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->orderBy('year')
            ->first();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => "These dates overlap the {$overlap->year} fiscal year ({$overlap->start_date->format('M j, Y')} - {$overlap->end_date->format('M j, Y')}).",
            ]);
        }
    }

    public function date(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value->startOfDay();
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        return CarbonImmutable::parse((string) $value)->startOfDay();
    }

    protected function earlier(CarbonImmutable $first, CarbonImmutable $second): CarbonImmutable
    {
        return $first->lessThan($second) ? $first : $second;
    }
}
