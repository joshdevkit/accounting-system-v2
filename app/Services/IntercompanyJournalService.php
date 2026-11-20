<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;

class IntercompanyJournalService
{
    public function accountOptions(Company $company): array
    {
        return $company->accounts()
            ->where('is_active', true)
            ->whereDoesntHave('children')
            ->with(['accountType:id,name,category,normal_balance', 'parent:id,code,name'])
            ->orderBy('code')
            ->get()
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'label' => "{$account->code} · {$account->name}",
                'category' => $account->accountType?->category,
                'normal_balance' => $account->accountType?->normal_balance,
                'group' => $account->parent?->name,
                'is_active' => true,
                'is_summary' => false,
            ])->all();
    }

    public function branchOptions(Company $company): array
    {
        return $company->branches()->active()->orderBy('name')->get(['id', 'name', 'code'])
            ->map(fn ($branch): array => ['id' => $branch->id, 'name' => $branch->name, 'code' => $branch->code])
            ->all();
    }
}
