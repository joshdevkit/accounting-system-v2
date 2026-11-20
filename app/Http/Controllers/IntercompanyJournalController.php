<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIntercompanyJournalRequest;
use App\Models\Expense;
use App\Models\Purchase;
use App\Services\JournalEntryService;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IntercompanyJournalController extends Controller
{
    public function purchase(StoreIntercompanyJournalRequest $request, Purchase $purchase, JournalEntryService $journals): RedirectResponse
    {
        abort_unless((int) $purchase->company_id === (int) CurrentCompany::find()->id, 404);
        $this->store($purchase, $request, $journals);

        return to_route('purchases.show', $purchase)->with('success', 'Intercompany clearing entry posted.');
    }

    public function expense(StoreIntercompanyJournalRequest $request, Expense $expense, JournalEntryService $journals): RedirectResponse
    {
        abort_unless((int) $expense->company_id === (int) CurrentCompany::find()->id, 404);
        $this->store($expense, $request, $journals);

        return to_route('expenses.show', $expense)->with('success', 'Intercompany clearing entry posted.');
    }

    private function store(Purchase|Expense $source, StoreIntercompanyJournalRequest $request, JournalEntryService $journals): void
    {
        $data = $request->validated();

        DB::transaction(function () use ($source, $request, $journals, $data): void {
            $locked = $source::query()->lockForUpdate()->findOrFail($source->id);

            if ($locked->status !== 'posted' || $locked->payment_status !== 'unpaid'
                || $locked->intercompany_journal_entry_id !== null) {
                throw ValidationException::withMessages([
                    'lines' => 'This record must be posted, unpaid, and have no intercompany clearing entry yet.',
                ]);
            }

            $entry = $journals->store(
                $locked->company,
                [
                    'branch_id' => $data['branch_id'],
                    'transaction_date' => $data['transaction_date'],
                    'description' => $data['description'],
                    'source_type' => 'manual',
                    'source_reference' => ($locked instanceof Purchase ? 'Purchase #' : 'Expense #').$locked->id,
                ],
                $data['lines'],
                $request->user(),
                post: true,
            );

            $locked->update(['intercompany_journal_entry_id' => $entry->id]);
        });
    }
}
