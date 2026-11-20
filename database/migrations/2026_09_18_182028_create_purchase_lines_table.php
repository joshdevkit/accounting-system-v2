<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts');

            $table->string('particulars', 255);
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('amount', 15, 2);

            $table->enum('tax_type', ['vatable', 'non_vat', 'vat_exempt'])->default('non_vat');
            $table->decimal('vat_amount', 15, 2)->default(0);

            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_lines');
    }
};