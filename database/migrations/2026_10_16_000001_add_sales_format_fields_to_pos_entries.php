<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_entries', function (Blueprint $table) {
            $table->string('guest_name', 255)->nullable()->after('notes');
            $table->string('address', 1000)->nullable()->after('guest_name');
            $table->string('tin', 50)->nullable()->after('address');
            $table->string('business_source', 50)->nullable()->after('tin');
            $table->string('vat_classification', 50)->nullable()->after('business_source');
            $table->string('discount_type', 50)->nullable()->after('vat_classification');
            $table->dateTime('arrival')->nullable()->after('discount_type');
            $table->dateTime('departure')->nullable()->after('arrival');
            $table->decimal('withholding_tax_rate', 5, 2)->nullable()->after('departure');
            $table->decimal('basic_room_rate_amount', 15, 2)->default(0)->after('withholding_tax_rate');
            $table->decimal('extra_bed_amount', 15, 2)->default(0)->after('basic_room_rate_amount');
            $table->decimal('room_upgrade_amount', 15, 2)->default(0)->after('extra_bed_amount');
            $table->decimal('no_show_amount', 15, 2)->default(0)->after('room_upgrade_amount');
            $table->decimal('early_check_in_out_amount', 15, 2)->default(0)->after('no_show_amount');
            $table->decimal('spa_amount', 15, 2)->default(0)->after('early_check_in_out_amount');
            $table->decimal('laundry_amount', 15, 2)->default(0)->after('spa_amount');
            $table->decimal('shuttle_amount', 15, 2)->default(0)->after('laundry_amount');
            $table->decimal('smoking_fee_amount', 15, 2)->default(0)->after('shuttle_amount');
            $table->decimal('key_card_amount', 15, 2)->default(0)->after('smoking_fee_amount');
            $table->decimal('amenities_amount', 15, 2)->default(0)->after('key_card_amount');
            $table->decimal('avl_amount', 15, 2)->default(0)->after('amenities_amount');
            $table->decimal('other_income_amount', 15, 2)->default(0)->after('avl_amount');
            $table->decimal('online_booking_amount', 15, 2)->default(0)->after('other_income_amount');
            $table->decimal('withholding_tax_amount', 15, 2)->default(0)->after('online_booking_amount');
        });
    }

    public function down(): void
    {
        Schema::table('pos_entries', function (Blueprint $table) {
            $table->dropColumn([
                'guest_name',
                'address',
                'tin',
                'business_source',
                'vat_classification',
                'discount_type',
                'arrival',
                'departure',
                'withholding_tax_rate',
                'basic_room_rate_amount',
                'extra_bed_amount',
                'room_upgrade_amount',
                'no_show_amount',
                'early_check_in_out_amount',
                'spa_amount',
                'laundry_amount',
                'shuttle_amount',
                'smoking_fee_amount',
                'key_card_amount',
                'amenities_amount',
                'avl_amount',
                'other_income_amount',
                'online_booking_amount',
                'withholding_tax_amount',
            ]);
        });
    }
};