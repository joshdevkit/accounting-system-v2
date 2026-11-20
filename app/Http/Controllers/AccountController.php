<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Company;
use App\Services\TrialBalance;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(
        protected TrialBalance $trialBalance,
    ) {}

    public function index(Request $request): Response
    {
        $company = CurrentCompany::find();
        /*
     * Accounts:
     * -01 = Maximilian
     * -02 = Gusto
     *
     * Group/summary accounts don't have a suffix, so they are always
     * included to preserve the chart-of-accounts hierarchy.
     */
        $unit = $request->string('unit')->toString();

        if (! in_array($unit, ['maxi', 'gusto'], true)) {
            $unit = 'maxi';
        }

        $unitSuffix = $unit === 'gusto' ? '-02' : '-01';

        $query = $company->accounts()
            ->with([
                'accountType:id,code,name,category,normal_balance',
                'parent:id,code,name',
            ])
            ->withCount('children')
            ->withCount('journalEntryLines')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = (string) $request->string('q');

                $query->where(function ($inner) use ($term): void {
                    $inner
                        ->where('code', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%");
                });
            })
            ->when(
                $request->filled('category'),
                fn($query) => $query->whereHas(
                    'accountType',
                    fn($type) => $type->where(
                        'category',
                        $request->string('category')->toString()
                    )
                )
            )
            ->when(
                $request->input('status') === 'inactive',
                fn($query) => $query->where('is_active', false)
            )
            ->where(function ($query) use ($unitSuffix): void {
                // Include the selected business-unit accounts.
                $query
                    ->where('code', 'like', "%{$unitSuffix}")

                    // Keep summary/group accounts that don't have a
                    // -01 / -02 business-unit suffix.
                    ->orWhere(function ($inner): void {
                        $inner
                            ->where('code', 'not like', '%-01')
                            ->where('code', 'not like', '%-02');
                    });
            })
            ->orderBy('code');

        $accounts = $query->get();

        $balances = collect($this->trialBalance->rows($company, ['unit' => $unit]))->keyBy('id');

        return Inertia::render('accounts/index', [
            'accounts' => $accounts->map(fn(Account $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'description' => $account->description,
                'depth' => $this->depthOf($accounts, $account),
                'is_control_account' => $account->is_control_account,
                'is_active' => $account->is_active,
                'children' => (int) $account->children_count,
                'lines' => (int) $account->journal_entry_lines_count,
                'is_postable' => $account->isPostable(),
                'parent' => $account->parent
                    ? [
                        'id' => $account->parent->id,
                        'code' => $account->parent->code,
                        'name' => $account->parent->name,
                    ]
                    : null,
                'account_type' => $account->accountType
                    ? [
                        'id' => $account->accountType->id,
                        'code' => $account->accountType->code,
                        'name' => $account->accountType->name,
                        'category' => $account->accountType->category,
                        'normal_balance' => $account->accountType->normal_balance,
                    ]
                    : null,
                'debit_cents' => (int) ($balances[$account->id]['debit'] ?? 0),
                'credit_cents' => (int) ($balances[$account->id]['credit'] ?? 0),
                'balance_cents' => (int) ($balances[$account->id]['balance'] ?? 0),
            ])->values(),

            'categories' => AccountType::query()
                ->orderBy('id')
                ->get([
                    'id',
                    'code',
                    'name',
                    'category',
                    'normal_balance',
                ]),

            'stats' => [
                'total' => $accounts->count(),
                'postable' => $accounts
                    ->filter(fn(Account $account) => $account->isPostable())
                    ->count(),
                'inactive' => $accounts
                    ->where('is_active', false)
                    ->count(),
            ],

            'filters' => [
                'q' => $request->string('q')->toString(),
                'category' => $request->string('category')->toString(),
                'status' => $request->string('status')->toString(),
                'unit' => $unit,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $company = CurrentCompany::find();

        return Inertia::render('accounts/create', [
            'accountTypes' => $this->accountTypes(),
            'parentOptions' => $this->parentOptions($company),
            'defaults' => [
                'parent_id' => $request->filled('parent') ? $request->integer('parent') : null,
                'account_type_id' => $request->filled('type') ? $request->integer('type') : null,
            ],
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $company = CurrentCompany::find();

        $account = $company->accounts()->create($request->validated());

        return to_route('accounts.index')->with('success', "Account {$account->code} · {$account->name} added.");
    }

    public function edit(Account $account): Response
    {
        $this->guardCompany($account);

        return Inertia::render('accounts/edit', [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'description' => $account->description,
                'account_type_id' => $account->account_type_id,
                'parent_id' => $account->parent_id,
                'is_control_account' => $account->is_control_account,
                'is_active' => $account->is_active,
            ],
            'accountTypes' => $this->accountTypes(),
            'parentOptions' => $this->parentOptions($account->company, $account),
            'usage' => [
                'children' => $account->children()->count(),
                'lines' => $account->journalEntryLines()->count(),
            ],
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->guardCompany($account);

        $validated = $request->validated();

        $this->assertNoCycle($account, $validated['parent_id'] ?? null);

        // The normal balance of an account follows its type, so retyping an
        // account that already carries postings needs a deliberate look.
        if (
            $account->account_type_id !== (int) $validated['account_type_id']
            && $account->journalEntryLines()->exists()
        ) {
            throw ValidationException::withMessages([
                'account_type_id' => 'This account already has journal lines, so its type can no longer be changed.',
            ]);
        }

        $account->fill($validated)->save();

        return to_route('accounts.index')->with('success', "Account {$account->code} updated.");
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->guardCompany($account);

        if ($account->children()->exists()) {
            throw ValidationException::withMessages([
                'account' => 'Remove or reparent its sub-accounts first.',
            ]);
        }

        if ($account->journalEntryLines()->exists()) {
            throw ValidationException::withMessages([
                'account' => 'This account has journal lines. Deactivate it instead of deleting it.',
            ]);
        }

        $account->delete();

        return to_route('accounts.index')->with('success', 'Account removed.');
    }

    /**
     * Toggling availability is the everyday correction, so it gets its own
     * lightweight endpoint instead of a full form.
     */
    public function toggleActive(Request $request, Account $account): RedirectResponse
    {
        $this->guardCompany($account);

        $request->validate(['is_active' => ['required', 'boolean']]);

        $account->fill(['is_active' => $request->boolean('is_active')])->save();

        return back()->with('success', $account->is_active
            ? "Account {$account->code} is available for postings."
            : "Account {$account->code} is now inactive.");
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function accountTypes(): array
    {
        return AccountType::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn(AccountType $type): array => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'category' => $type->category,
                'normal_balance' => $type->normal_balance,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parentOptions(Company $company, ?Account $exclude = null): array
    {
        $accounts = $company->accounts()->orderBy('code')->get(['id', 'code', 'name', 'parent_id']);

        if ($exclude) {
            $blocked = $this->descendants($accounts, $exclude->id);
            $blocked[] = $exclude->id;

            $accounts = $accounts->reject(fn(Account $account): bool => in_array($account->id, $blocked, true));
        }

        return $accounts
            ->map(fn(Account $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'label' => "{$account->code} · {$account->name}",
            ])
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Account>  $accounts
     * @return list<int>
     */
    protected function descendants($accounts, int $parentId): array
    {
        $found = [];
        $frontier = [$parentId];
        $guard = 0;

        while ($frontier !== [] && $guard++ < 25) {
            $next = [];

            foreach ($accounts as $account) {
                if ($account->parent_id !== null && in_array($account->parent_id, $frontier, true)) {
                    $found[] = $account->id;
                    $next[] = $account->id;
                }
            }

            $frontier = $next;
        }

        return $found;
    }

    protected function depthOf($accounts, Account $account): int
    {
        $depth = 0;
        $parentId = $account->parent_id;
        $guard = 0;

        while ($parentId !== null && $guard++ < 10) {
            $depth++;
            $parentId = $accounts->firstWhere('id', $parentId)?->parent_id;
        }

        return $depth;
    }

    protected function assertNoCycle(Account $account, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $cursor = $parentId;
        $guard = 0;

        while ($cursor !== null && $guard++ < 25) {
            if ($cursor === $account->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'An account cannot sit underneath one of its own sub-accounts.',
                ]);
            }

            $cursor = DB::table('accounts')->where('id', $cursor)->value('parent_id');
            $cursor = $cursor === null ? null : (int) $cursor;
        }
    }

    protected function guardCompany(Account $account): void
    {
        abort_unless($account->company_id === CurrentCompany::find()->id, 404);
    }
}
