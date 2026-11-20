<?php

namespace App\Http\Controllers;

use App\Support\CurrentCompany;
use Inertia\Inertia;
use Inertia\Response;

class CityLedgerController extends Controller
{
    public function index(): Response
    {
        $company = CurrentCompany::find();

        return Inertia::render('city-ledger/index', [
            // TODO: replace with the accounts receivable computation.
            // Each entry should match the `LedgerEntry` type in the page:
            // id, date, description, payment_type, user,
            // credit_cents, debit_cents, cumulative_balance_cents
            'entries' => [],
            'currency' => $company->base_currency_code,
        ]);
    }
}