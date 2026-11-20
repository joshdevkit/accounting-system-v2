<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->text('description')->nullable()->after('payee');

            // No longer required now that lines carry their own account/amount/tax.
            $table->unsignedBigInteger('expense_account_id')->nullable()->change();
            $table->decimal('amount', 15, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};