<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/profile', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'job_title' => $user->job_title,
                'employee_id' => $user->employee_id,
                'phone' => $user->phone,
                'signature_name' => $user->signature_name,
                'avatar_url' => $user->avatar_path ? asset('storage/' . $user->avatar_path) : null,
                'signature_url' => $user->signature_path ? asset('storage/' . $user->signature_path) : null,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }

    public function editPassword(): Response
    {
        return Inertia::render('settings/password');
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->safe()->only([
            'name',
            'email',
            'job_title',
            'employee_id',
            'phone',
            'signature_name',
        ]));

        if ($request->hasFile('avatar')) {
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        if ($request->hasFile('signature')) {
            $user->signature_path = $request->file('signature')->store('signatures', 'public');
        }

        $user->save();

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->fill([
            'password' => Hash::make($request->validated('password')),
        ])->save();

        return back()->with('success', 'Password updated.');
    }
}
