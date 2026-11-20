<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('intercompany_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('intercompany_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchases', fn (Blueprint $table) => $table->dropConstrainedForeignId('intercompany_journal_entry_id'));
        Schema::table('expenses', fn (Blueprint $table) => $table->dropConstrainedForeignId('intercompany_journal_entry_id'));
    }
};
