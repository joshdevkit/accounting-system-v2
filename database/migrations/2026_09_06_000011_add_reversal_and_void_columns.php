<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reversals are recorded as a pair: the original entry points at the
     * reversing entry, and the reversing entry points back at the original.
     * A voided POS summary keeps a record of who voided it and when.
     */
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('reverses_id')
                ->nullable()
                ->after('status')
                ->constrained('journal_entries')
                ->nullOnDelete();

            $table->foreignId('reversed_by_id')
                ->nullable()
                ->after('reverses_id')
                ->constrained('journal_entries')
                ->nullOnDelete();

            $table->index(['company_id', 'status']);
        });

        Schema::table('pos_entries', function (Blueprint $table) {
            $table->foreignId('voided_by')
                ->nullable()
                ->after('posted_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('voided_at')->nullable()->after('voided_by');
        });
    }

    public function down(): void
    {
        Schema::table('pos_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn('voided_at');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'status']);
            $table->dropConstrainedForeignId('reversed_by_id');
            $table->dropConstrainedForeignId('reverses_id');
        });
    }
};
