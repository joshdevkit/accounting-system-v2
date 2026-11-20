<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        $company = CurrentCompany::find();

        $companyName = $company
            ? Cache::rememberForever(
                "company:{$company->id}:name",
                fn () => $company->name
            )
            : null;

        return Inertia::render('auth/login', [
            'companyName' => $companyName,
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->ensureNotRateLimited();

        $credentials = $request->validated();

        $remember = (bool) ($credentials['remember'] ?? false);

        unset($credentials['remember']);

        if (! Auth::attempt($credentials, $remember)) {
            $request->hitRateLimiter();

            return back()
                ->withErrors([
                    'email' => 'Account not found or Incorrect email.',
                    'password' => 'Password is incorrect'
                ])
                ->onlyInput('email');
        }

        $request->clearRateLimiter();

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}