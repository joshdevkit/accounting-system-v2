<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE purchases MODIFY payment_status ENUM('unpaid', 'for_check', 'for_release', 'paid') NOT NULL DEFAULT 'unpaid'");
    }

    public function down(): void
    {
        DB::table('purchases')->where('payment_status', 'for_release')->update(['payment_status' => 'unpaid']);
        DB::statement("ALTER TABLE purchases MODIFY payment_status ENUM('unpaid', 'for_check', 'paid') NOT NULL DEFAULT 'unpaid'");
    }
};
