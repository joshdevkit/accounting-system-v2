<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Branch;
use DomainException;
use LogicException;

class BranchAccounts
{
    public static function resolve(string|array|null $config, Branch $branch): ?string
    {
        if ($config === null) {
            return null;
        }

        $suffix = $branch->accountSuffix();

        if (is_array($config)) {
            return $config[$suffix] ?? null;
        }

        if (str_contains($config, '-')) {
            throw new LogicException("Account '{$config}' is already branch-suffixed; use a base code or a per-branch array.");
        }

        return "{$config}-{$suffix}";
    }

    public static function account(string|array|null $config, Branch $branch): Account
    {
        $code = self::resolve($config, $branch)
            ?? throw new DomainException("No account configured for branch {$branch->name}.");

        return Account::where('company_id', $branch->company_id)
            ->where('code', $code)
            ->firstOr(fn() => throw new DomainException("Account {$code} not found for branch {$branch->name}."));
    }
}
