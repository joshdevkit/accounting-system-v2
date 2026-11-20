<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE purchases MODIFY status ENUM('draft', 'posted', 'voided', 'for_voucher') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE purchases MODIFY status ENUM('draft', 'posted', 'voided') NOT NULL DEFAULT 'draft'");
    }
};