<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'company_name')) {
                $table->string('company_name')->nullable()->after('avatar_path');
            }
            if (!Schema::hasColumn('users', 'brand_name')) {
                $table->string('brand_name')->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('users', 'support_phone')) {
                $table->string('support_phone')->nullable()->after('brand_name');
            }
            if (!Schema::hasColumn('users', 'business_type')) {
                $table->string('business_type')->nullable()->after('support_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'brand_name',
                'support_phone',
                'business_type',
            ]);
        });
    }
};
