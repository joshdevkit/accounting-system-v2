<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('business_source', 50)->nullable()->after('tax_type');
            $table->string('discount_type', 50)->nullable()->default('none')->after('business_source');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['business_source', 'discount_type']);
        });
    }
};
