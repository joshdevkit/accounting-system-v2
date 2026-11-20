<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            // Unit code from the UI list (resources/js/libs/uom.ts), e.g. "KG".
            // Nullable so existing lines stay valid.
            $table->string('uom', 10)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropColumn('uom');
        });
    }
};