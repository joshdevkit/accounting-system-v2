<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->date('business_date');
            $table->string('pos_reference', 100);

            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('exempted_amount', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('local_tax_amount', 15, 2)->default(0);
            $table->decimal('service_charge_amount', 15, 2)->default(0);
            $table->decimal('net_sales', 15, 2)->default(0);

            $table->decimal('cash_amount', 15, 2)->default(0);
            $table->decimal('card_amount', 15, 2)->default(0);
            $table->decimal('gcash_qrph_amount', 15, 2)->default(0);
            $table->decimal('bank_transfer_amount', 15, 2)->default(0);
            $table->decimal('cheque_amount', 15, 2)->default(0);
            $table->decimal('accounts_receivable_amount', 15, 2)->default(0);
            $table->decimal('room_post_amount', 15, 2)->default(0);
            $table->decimal('gift_voucher_amount', 15, 2)->default(0);
            $table->decimal('other_payment_amount', 15, 2)->default(0);

            $table->enum('status', ['draft', 'posted', 'voided'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'business_date', 'pos_reference']);
            $table->index(['company_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_entries');
    }
};
