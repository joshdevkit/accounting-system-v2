<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Support\CurrentCompany;
use App\Support\InertiaLinks;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VendorLedgerController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CurrentCompany::find();

        $filters = [
            'contact_id' => $request->filled('contact_id') ? $request->integer('contact_id') : null,
            'from' => (string) $request->input('from', ''),
            'to' => (string) $request->input('to', ''),
        ];

        // Released = posted and fully paid. Voided purchases are excluded.
        $base = fn () => $company->purchases()
            ->where('status', 'posted')
            ->where('payment_status', 'paid')
            ->when($filters['contact_id'], fn ($q, $id) => $q->where('contact_id', $id))
            ->when($filters['from'], fn ($q, $d) => $q->whereDate('paid_at', '>=', $d))
            ->when($filters['to'], fn ($q, $d) => $q->whereDate('paid_at', '<=', $d));

        $entries = $base()
            ->with(['contact:id,name', 'lines'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Total across every page of the filtered result, not just this one.
        $total = DB::table('purchase_lines')
            ->whereIn('purchase_id', $base()->select('purchases.id'))
            ->selectRaw('coalesce(sum(amount + vat_amount - withholding_tax_amount), 0) as total')
            ->value('total');

        return Inertia::render('accounts-payable/vendorledger', [
            'entries' => $entries->through(fn (Purchase $purchase): array => [
                'id' => $purchase->id,
                'purchase_date' => $purchase->purchase_date->toDateString(),
                'reference_no' => $purchase->reference_no,
                'amount_cents' => Money::cents($purchase->netPayable()),
                'check_number' => $purchase->check_number,
                'released_at' => $purchase->paid_at?->toDateString(),
            ])->all(),
            'pagination' => InertiaLinks::from($entries),
            'total_cents' => Money::cents($total),
            'filters' => $filters,
            'vendors' => $company->contacts()
                ->whereIn('type', ['vendor', 'both'])
                ->orderBy('name')
                ->get(['id', 'name']),
            'currency' => $company->base_currency_code,
        ]);
    }
}