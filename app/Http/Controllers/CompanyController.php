<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/company', [
            'company' => Company::first(),
        ]);
    }

    public function update(UpdateCompanyRequest $request): RedirectResponse
    {
        $company = Company::first() ?? new Company;

        $company->fill($request->validated());
        $company->save();

        Cache::forget("company:{$company->id}:name");

        return back()->with('success', 'Company details saved.');
    }
}