<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'payment_type')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('payment_type', 20)->default('COD')->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'payment_type')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('payment_type');
            });
        }
    }
};
