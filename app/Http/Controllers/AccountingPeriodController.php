<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Services\FiscalYearService;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountingPeriodController extends Controller
{
    public function __construct(
        protected FiscalYearService $fiscalYears,
    ) {}

    /**
     * Opening or closing a period decides where new postings may land.
     */
    public function updateStatus(Request $request, AccountingPeriod $accountingPeriod): RedirectResponse
    {
        $company = CurrentCompany::find();

        abort_unless($accountingPeriod->fiscalYear?->company_id === $company->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'closed'])],
        ]);

        if ($validated['status'] === 'closed') {
            $drafts = $accountingPeriod->journalEntries()->where('status', 'draft')->count();

            if ($drafts > 0) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        '%s %d draft journal %s still unposted. Post or remove %s before closing the period.',
                        $accountingPeriod->name,
                        $drafts,
                        $drafts === 1 ? 'entry is' : 'entries are',
                        $drafts === 1 ? 'it' : 'them',
                    ),
                ]);
            }
        }

        $this->fiscalYears->setPeriodStatus($accountingPeriod, $validated['status']);

        return back()->with(
            'success',
            $accountingPeriod->status === 'closed'
                ? "{$accountingPeriod->name} is closed to new postings."
                : "{$accountingPeriod->name} is open again.",
        );
    }
}
