<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('payment_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('credit_account_id')->constrained('accounts');

            $table->date('sale_date');
            $table->string('reference_no', 100)->nullable();
            $table->string('customer_name', 255)->nullable();

            $table->decimal('taxable_amount', 15, 2);

            $table->enum('tax_type', ['vatable', 'non_vat', 'vat_exempt'])->default('non_vat');
            $table->decimal('vat_amount', 15, 2)->default(0);

            $table->decimal('withholding_tax_rate', 5, 2)->default(0);
            $table->decimal('withholding_tax_amount', 15, 2)->default(0);

            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
            $table->string('settlement_account', 50)->nullable();

            $table->text('notes')->nullable();

            $table->enum('status', ['draft', 'posted', 'voided'])->default('draft');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'sale_date']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};