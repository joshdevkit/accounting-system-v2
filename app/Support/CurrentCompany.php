<?php

namespace App\Support;

use App\Models\Company;

/**
 * Resolves the company the current request is working on.
 *
 * The application is single-tenant for now: the first row of `companies` is
 * treated as "the" company. Every scoped resource (branches, fiscal years,
 * the chart of accounts, journal entries, POS entries) goes through this
 * helper, so adding real multi-company support only means changing here.
 */
final class CurrentCompany
{
    public static function get(): ?Company
    {
        return Company::query()->orderBy('id')->first();
    }

    public static function find(): Company
    {
        $company = self::get();

        if (! $company) {
            abort(404, 'No company has been configured yet.');
        }

        return $company;
    }
}
