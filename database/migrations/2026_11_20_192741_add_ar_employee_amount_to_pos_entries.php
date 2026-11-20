<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_entries', function (Blueprint $table) {
            $table->decimal('ar_employee_amount', 15, 2)->default(0)->after('accounts_receivable_amount');
        });
    }

    public function down(): void
    {
        Schema::table('pos_entries', function (Blueprint $table) {
            $table->dropColumn('ar_employee_amount');
        });
    }
};