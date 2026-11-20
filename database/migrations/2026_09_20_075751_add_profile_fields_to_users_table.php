<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('email');
            $table->string('employee_id', 50)->nullable()->after('job_title');
            $table->string('phone', 50)->nullable()->after('employee_id');
            $table->string('signature_name')->nullable()->after('phone');
            $table->string('signature_path')->nullable()->after('signature_name');
            $table->string('avatar_path')->nullable()->after('signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'job_title',
                'employee_id',
                'phone',
                'signature_name',
                'signature_path',
                'avatar_path',
            ]);
        });
    }
};