<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->decimal('withholding_tax_rate', 5, 2)->default(0)->after('vat_amount');
            $table->decimal('withholding_tax_amount', 15, 2)->default(0)->after('withholding_tax_rate');
        });

        // Header-level fields stay, but become nullable/unused going forward —
        // not dropped outright, so existing single-rate purchases keep their
        // historical data intact rather than silently losing it.
        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('withholding_tax_rate', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropColumn(['withholding_tax_rate', 'withholding_tax_amount']);
        });
    }
};