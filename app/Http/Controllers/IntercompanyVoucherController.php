<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\IntercompanyVoucher;
use App\Models\Purchase;
use App\Services\IntercompanyVoucherService;
use App\Support\BranchAccounts;
use App\Support\CurrentCompany;
use App\Support\Money;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IntercompanyVoucherController extends Controller
{
    public function purchase(Request $request, Purchase $purchase, IntercompanyVoucherService $service): RedirectResponse
    {
        abort_unless($purchase->company_id === CurrentCompany::find()->id, 404);
        $service->markForVoucher($purchase, $request->user());

        return to_route('purchases.show', $purchase)->with('success', 'Intercompany entry marked For Voucher.');
    }

    public function expense(Request $request, Expense $expense, IntercompanyVoucherService $service): RedirectResponse
    {
        abort_unless($expense->company_id === CurrentCompany::find()->id, 404);
        $service->markForVoucher($expense, $request->user());

        return to_route('expenses.show', $expense)->with('success', 'Intercompany entry marked For Voucher.');
    }

    public function pay(Request $request, IntercompanyVoucher $voucher, IntercompanyVoucherService $service): RedirectResponse
    {
        $this->guardCompany($voucher);
        $data = $request->validate([
            'settlement_account' => ['required', Rule::in(array_keys((array) config('accounting.purchases.settlement_accounts')))],
            'payment_date' => ['nullable', 'date'],
            'bank_name' => ['nullable', 'required_if:settlement_account,cheque', 'string', 'max:255'],
            'check_number' => ['nullable', 'required_if:settlement_account,cheque', 'string', 'max:50'],
        ]);

        $service->pay(
            $voucher,
            $data['settlement_account'],
            $request->user(),
            $data['payment_date'] ?? null,
            $data['bank_name'] ?? null,
            $data['check_number'] ?? null,
        );

        return to_route('banking.outgoing')->with('success', 'Intercompany payment posted.');
    }

    public function payBatch(Request $request, IntercompanyVoucherService $service): RedirectResponse
    {
        $company = CurrentCompany::find();
        $sources = array_keys((array) config('accounting.purchases.settlement_accounts'));
        $data = $request->validate([
            'voucher_ids' => ['required', 'array', 'min:1'],
            'voucher_ids.*' => ['integer', 'distinct'],
            'settlement_account' => ['required', Rule::in($sources)],
            'payment_date' => ['nullable', 'date'],
            // Cheque in batch mode sends vouchers "For Cheque" via status menu
            // — no bank/check needed yet. Those are captured per-row later.
            'bank_name' => ['nullable', 'string', 'max:255'],
            'check_number' => ['nullable', 'string', 'max:50'],
        ]);
        $vouchers = $company->intercompanyVouchers()->whereIn('id', $data['voucher_ids'])->get();
        if ($vouchers->count() !== count($data['voucher_ids'])) {
            abort(404);
        }

        // Check for vouchers already in cheque workflow with cheque numbers
        foreach ($vouchers as $voucher) {
            if ($voucher->status === 'for_release' && $voucher->check_number) {
                throw ValidationException::withMessages([
                    'voucher_ids' => "Voucher #{$voucher->id} already has a cheque number. Release it from its status menu instead.",
                ]);
            }
        }

        // Same process as purchases: cheque goes through send-for-cheque workflow
        if ($data['settlement_account'] === 'cheque') {
            $service->sendForChequeBatch($vouchers->all());
        } else {
            $service->markBatchForRelease(
                $vouchers->all(),
                $data['settlement_account'],
            );
        }

        return back()->with(
            'success',
            $data['settlement_account'] === 'cheque'
                ? 'Vouchers sent for cheque.'
                : 'Selected vouchers marked For Release.',
        );
    }

    public function sendForCheque(Request $request, IntercompanyVoucher $voucher, IntercompanyVoucherService $service): RedirectResponse
    {
        $this->guardCompany($voucher);
        $service->sendForCheque($voucher);

        return back()->with('success', 'Voucher sent for cheque.');
    }

    public function sendForChequeBatch(Request $request, IntercompanyVoucherService $service): RedirectResponse
    {
        $company = CurrentCompany::find();
        $data = $request->validate([
            'voucher_ids' => ['required', 'array', 'min:1'],
            'voucher_ids.*' => ['integer', 'distinct'],
        ]);
        $vouchers = $company->intercompanyVouchers()->whereIn('id', $data['voucher_ids'])->get();
        if ($vouchers->count() !== count($data['voucher_ids'])) {
            abort(404);
        }

        $service->sendForChequeBatch($vouchers->all());

        return back()->with('success', 'Vouchers sent for cheque.');
    }

    public function chequeDetails(Request $request, IntercompanyVoucher $voucher, IntercompanyVoucherService $service): RedirectResponse
    {
        $this->guardCompany($voucher);

        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'check_number' => ['required', 'string', 'max:50'],
        ]);

        $service->setChequeDetails($voucher, $data['bank_name'], $data['check_number']);

        return back()->with('success', 'Cheque saved. The voucher is now For Release.');
    }

    public function release(Request $request, IntercompanyVoucher $voucher, IntercompanyVoucherService $service): RedirectResponse
    {
        $this->guardCompany($voucher);
        $data = $request->validate(['payment_date' => ['nullable', 'date']]);

        $service->release($voucher, $request->user(), $data['payment_date'] ?? null);

        return back()->with('success', 'Payment released and posted.');
    }

    public function cancelPayment(Request $request, IntercompanyVoucher $voucher, IntercompanyVoucherService $service): RedirectResponse
    {
        $this->guardCompany($voucher);
        $service->cancelPayment($voucher);

        return back()->with('success', 'Payment cancelled. Voucher is back to pending.');
    }

    public function voucher(Request $request, IntercompanyVoucher $voucher)
    {
        $this->guardCompany($voucher);
        // Printable at any point after the intercompany clearing entry is
        // posted — including while the row sits For Release awaiting its
        // cheque details, same as a normal purchase's cheque voucher.
        abort_unless(in_array($voucher->status, ['for_voucher', 'send_for_cheque', 'for_release', 'paid'], true), 404);
        abort_unless($voucher->intercompanyJournalEntry?->status === 'posted', 404);

        $data = $request->validate([
            'settlement_account' => ['nullable', Rule::in(array_keys((array) config('accounting.purchases.settlement_accounts')))],
            'payment_date' => ['nullable', 'date'],
            'check_number' => ['nullable', 'string', 'max:50'],
        ]);
        $voucher->loadMissing(['branch', 'clearingAccount']);
        $source = $data['settlement_account'] ?? $voucher->settlement_account ?? 'bank_transfer';
        $settlement = BranchAccounts::account(config("accounting.purchases.settlement_accounts.{$source}"), $voucher->branch);
        $amount = Money::cents($voucher->amount);

        return Pdf::loadView('pdf.batch-check-voucher-overlay', [
            'payee' => $voucher->payee,
            'date' => $data['payment_date'] ?? $voucher->paid_at?->toDateString() ?? now()->toDateString(),
            'particulars' => 'Intercompany payment for '.($voucher->source_reference ?: ucfirst($voucher->source_type).' #'.$voucher->source_id),
            'amount_cents' => $amount,
            'amount_words' => NumberToWords::pesos($amount),
            'check_number' => $data['check_number'] ?? $voucher->check_number,
            'accounts' => [
                ['name' => $voucher->clearingAccount->name, 'debit_cents' => $amount, 'credit_cents' => 0],
                ['name' => $settlement->name, 'debit_cents' => 0, 'credit_cents' => $amount],
            ],
        ])->setOption('defaultFont', 'DejaVu Sans')->setPaper([0, 0, 612, 360])
            ->stream('intercompany-voucher-'.$voucher->id.'.pdf');
    }

    private function guardCompany(IntercompanyVoucher $voucher): void
    {
        abort_unless($voucher->company_id === CurrentCompany::find()->id, 404);
    }
}
