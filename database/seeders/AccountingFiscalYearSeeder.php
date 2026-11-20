<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\FiscalYear;
use App\Services\FiscalYearService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class AccountingFiscalYearSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Make sure the company has a fiscal year covering today, with one
     * accounting period per month, so posting can start immediately.
     */
    public function run(): void
    {
        $company = Company::where('code', env('ACCOUNTING_COMPANY_CODE', 'TMH'))->first();

        if (! $company) {
            return;
        }

        $year = (int) env('ACCOUNTING_FISCAL_YEAR', now()->format('Y'));
        $service = app(FiscalYearService::class);

        $existing = FiscalYear::query()
            ->where('company_id', $company->id)
            ->where('year', $year)
            ->first();

        if ($existing) {
            $service->generatePeriods($existing);

            return;
        }

        try {
            $fiscalYear = $service->create($company, [
                'year' => $year,
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
            ]);
        } catch (\Throwable $exception) {
            // Overlapping years or a disabled period generator should not stop
            // the rest of the seeding.
            Log::warning('Skipped seeding the fiscal year: '.$exception->getMessage());

            $this->command?->warn('Could not seed fiscal year '.$year.'. Review your fiscal years in the app.');

            return;
        }

        $this->command?->info("Fiscal year {$fiscalYear->year} seeded with ".$fiscalYear->periods()->count().' periods.');
    }
}
