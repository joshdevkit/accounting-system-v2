<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_lines', function (Blueprint $table) {
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('uom', 10)->nullable();
            $table->decimal('unit_price', 15, 4)->nullable();
        });

        // Existing amount-only lines become one item at their original amount.
        DB::table('expense_lines')->whereNull('unit_price')->update(['unit_price' => DB::raw('amount')]);
    }

    public function down(): void
    {
        Schema::table('expense_lines', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'uom', 'unit_price']);
        });
    }
};
