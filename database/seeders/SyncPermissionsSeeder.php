<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class SyncPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_keys(config('permissions')) as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}