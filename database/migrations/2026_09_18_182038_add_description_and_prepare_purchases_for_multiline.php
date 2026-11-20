<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->text('description')->nullable()->after('vendor_name');
            $table->unsignedBigInteger('debit_account_id')->nullable()->change();
            $table->decimal('taxable_amount', 15, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};