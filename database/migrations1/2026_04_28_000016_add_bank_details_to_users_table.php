<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('avatar_path');
            $table->string('account_holder')->nullable()->after('bank_name');
            $table->string('account_number')->nullable()->after('account_holder');
            $table->string('ifsc_code')->nullable()->after('account_number');
            $table->string('branch')->nullable()->after('ifsc_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'bank_name',
                'account_holder',
                'account_number',
                'ifsc_code',
                'branch',
            ]);
        });
    }
};
