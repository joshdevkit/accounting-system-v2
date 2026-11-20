<?php

namespace App\Http\Middleware;

use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyIsConfigured
{
    /**
     * Accounting data only makes sense once a company profile exists, so
     * send the user to settings instead of failing with a 500 downstream.
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if (CurrentCompany::get() !== null) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            abort(428, 'Set up the company profile before using the accounting features.');
        }

        return redirect()
            ->route('company.edit')
            ->with('error', 'Set up your company details before using this feature.');
    }
}
