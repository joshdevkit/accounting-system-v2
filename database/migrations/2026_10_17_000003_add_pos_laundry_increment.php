<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_entries', function (Blueprint $table) {
            $table->decimal('laundry_increment_amount', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('pos_entries', fn (Blueprint $table) => $table->dropColumn('laundry_increment_amount'));
    }
};
