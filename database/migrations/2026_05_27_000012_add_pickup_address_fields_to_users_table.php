<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'pickup_name')) {
                $table->string('pickup_name')->nullable()->after('business_type');
            }
            if (!Schema::hasColumn('users', 'pickup_address')) {
                $table->text('pickup_address')->nullable()->after('pickup_name');
            }
            if (!Schema::hasColumn('users', 'pickup_city')) {
                $table->string('pickup_city')->nullable()->after('pickup_address');
            }
            if (!Schema::hasColumn('users', 'pickup_postal_code')) {
                $table->string('pickup_postal_code', 20)->nullable()->after('pickup_city');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_name',
                'pickup_address',
                'pickup_city',
                'pickup_postal_code',
            ]);
        });
    }
};
