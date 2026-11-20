<?php

namespace App\Http\Controllers\Traits;

use App\Models\Expense;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait JETraits
{
    /**
     * Maps journal entry id => vendor, cheque number and released flag, for the
     * purchases and expenses that created those entries.
     *
     * @return array<int, array{vendor: ?string, cheque_number: ?string, released: bool}>
     */
    protected function entrySources($company, Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        $sources = [];

        $purchases = Purchase::query()
            ->where('company_id', $company->id)
            ->where(fn($q) => $q->whereIn('journal_entry_id', $ids)
                ->orWhereIn('clearing_journal_entry_id', $ids)
                ->orWhereIn('payment_journal_entry_id', $ids))
            ->with('contact:id,name')
            ->get();

        foreach ($purchases as $purchase) {
            $this->addSource($sources, $purchase, $purchase->contact?->name ?? $purchase->vendor_name);
        }

        $expenses = Expense::query()
            ->where('company_id', $company->id)
            ->where(fn($q) => $q->whereIn('journal_entry_id', $ids)->orWhereIn('payment_journal_entry_id', $ids))
            ->with('contact:id,name')
            ->get();

        foreach ($expenses as $expense) {
            $this->addSource($sources, $expense, $expense->contact?->name ?? $expense->payee);
        }

        return $sources;
    }


    protected function addSource(array &$sources, Model $document, ?string $name): void
    {
        $viaCheque = $document->settlement_account === 'cheque';

        // The entry created when the bill was posted.
        if ($document->journal_entry_id) {
            $sources[$document->journal_entry_id] = [
                'vendor' => $name,
                'cheque_number' => null,
                'released' => false,
            ];
        }

        // The entry created when the payment was made.
        if ($document->payment_journal_entry_id) {
            $sources[$document->payment_journal_entry_id] = [
                'vendor' => $name,
                'cheque_number' => $viaCheque ? $document->check_number : null,
                'released' => $viaCheque && $document->payment_status === 'paid',
            ];
        }

        if ($document instanceof Purchase && $document->clearing_journal_entry_id) {
            $sources[$document->clearing_journal_entry_id] = [
                'vendor' => $name,
                'cheque_number' => null,
                'released' => false,
            ];
        }
    }
}
