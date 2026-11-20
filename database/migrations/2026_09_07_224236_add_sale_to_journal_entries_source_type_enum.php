<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE journal_entries MODIFY source_type ENUM('manual', 'pos', 'expense', 'purchase', 'sale') NOT NULL DEFAULT 'manual'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE journal_entries MODIFY source_type ENUM('manual', 'pos', 'expense', 'purchase') NOT NULL DEFAULT 'manual'");
    }
};