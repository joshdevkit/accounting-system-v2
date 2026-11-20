<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_entries', function (Blueprint $table) {
            $table->string('calculation_version', 50)->nullable();
            $table->decimal('discount_rate', 5, 2)->nullable();
            $table->decimal('complimentary_room_amount', 15, 2)->default(0);
            $table->decimal('amount_due', 15, 2)->default(0);
            $table->decimal('avl_increment_amount', 15, 2)->nullable();
            $table->decimal('other_suppliers_increment_amount', 15, 2)->nullable();
            $table->decimal('massage_increment_amount', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pos_entries', function (Blueprint $table) {
            $table->dropColumn([
                'calculation_version', 'discount_rate', 'complimentary_room_amount',
                'amount_due', 'avl_increment_amount', 'other_suppliers_increment_amount',
                'massage_increment_amount',
            ]);
        });
    }
};
