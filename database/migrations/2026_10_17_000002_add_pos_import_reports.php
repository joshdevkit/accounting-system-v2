<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_entries', function (Blueprint $table) {
            $table->uuid('import_batch_id')->nullable()->index();
            $table->decimal('key_card_increment_amount', 15, 2)->default(0);
            $table->decimal('amenities_increment_amount', 15, 2)->default(0);
        });
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->json('workbook_snapshot')->nullable();
        });
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('journal_entry_lines')->whereNull('account_id')->exists()) {
            throw new RuntimeException('Resolve unmapped draft journal accounts before rolling back.');
        }
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable(false)->change();
        });
        Schema::table('journal_entries', fn (Blueprint $table) => $table->dropColumn('workbook_snapshot'));
        Schema::table('pos_entries', fn (Blueprint $table) => $table->dropColumn([
            'import_batch_id', 'key_card_increment_amount', 'amenities_increment_amount',
        ]));
    }
};
