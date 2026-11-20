<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_account_id')->constrained('accounts');

            $table->date('expense_date');
            $table->string('reference_no', 100)->nullable();
            $table->string('payee', 255)->nullable();
            $table->decimal('amount', 15, 2);

            // 'paid' settles immediately from a settlement account; 'unpaid'
            // credits accounts payable and is settled later.
            $table->enum('payment_status', ['paid', 'unpaid'])->default('paid');
            $table->string('settlement_account', 50)->nullable();

            $table->text('notes')->nullable();

            $table->enum('status', ['draft', 'posted', 'voided'])->default('draft');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'expense_date']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};