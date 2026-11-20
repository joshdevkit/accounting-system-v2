<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $company = CurrentCompany::get();

        if (! $company) {
            return to_route('company.edit')
                ->with('error', 'Set up your company details before adding branches.');
        }

        $branches = $company->branches()
            ->withCount('journalEntries')
            ->withCount('posEntries')
            ->orderBy('name')
            ->get();

        return Inertia::render('branches/index', [
            'company' => ['name' => $company->name, 'code' => $company->code],
            'branches' => $branches->map(fn (Branch $branch): array => [
                'id' => $branch->id,
                'company_id' => $branch->company_id,
                'code' => $branch->code,
                'name' => $branch->name,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'email' => $branch->email,
                'is_active' => $branch->is_active,
                'journal_entries' => (int) $branch->journal_entries_count,
                'pos_entries' => (int) $branch->pos_entries_count,
            ]),
            'stats' => [
                'total' => $branches->count(),
                'active' => $branches->where('is_active', true)->count(),
            ],
        ]);
    }

    public function create(): Response|RedirectResponse
    {
        if (! CurrentCompany::get()) {
            return to_route('company.edit')
                ->with('error', 'Set up your company details before adding branches.');
        }

        return Inertia::render('branches/create');
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $company = CurrentCompany::find();

        $branch = $company->branches()->create($request->validated());

        return to_route('branches.index')->with('success', "Branch {$branch->name} added.");
    }

    public function edit(Branch $branch): Response
    {
        $this->guardCompany($branch);

        return Inertia::render('branches/edit', [
            'branch' => $branch,
            'usage' => [
                'journal_entries' => $branch->journalEntries()->count(),
                'pos_entries' => $branch->posEntries()->count(),
            ],
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->guardCompany($branch);

        $branch->update($request->validated());

        return to_route('branches.index')->with('success', "Branch {$branch->name} updated.");
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->guardCompany($branch);

        if ($branch->hasActivity()) {
            throw ValidationException::withMessages([
                'branch' => 'This branch already has journal or POS entries. Deactivate it instead of removing it.',
            ]);
        }

        $branch->delete();

        return to_route('branches.index')->with('success', 'Branch removed.');
    }

    protected function guardCompany(Branch $branch): void
    {
        abort_unless($branch->company_id === CurrentCompany::find()->id, 404);
    }
}
