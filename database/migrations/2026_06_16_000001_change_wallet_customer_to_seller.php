<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        \Illuminate\Support\Facades\DB::table('wallet_transactions')->truncate();
        \Illuminate\Support\Facades\DB::table('wallets')->truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $hasCustomerForeignKey = \Illuminate\Support\Facades\DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'wallets' 
              AND CONSTRAINT_NAME = 'wallets_customer_id_foreign'
        ");

        $hasSellerForeignKey = \Illuminate\Support\Facades\DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'wallets' 
              AND CONSTRAINT_NAME = 'wallets_seller_id_foreign'
        ");

        Schema::table('wallets', function (Blueprint $table) use ($hasCustomerForeignKey, $hasSellerForeignKey) {
            if (!empty($hasCustomerForeignKey)) {
                $table->dropForeign('wallets_customer_id_foreign');
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('wallets', 'customer_id')) {
                $table->dropColumn('customer_id');
            }
            if (!empty($hasSellerForeignKey)) {
                $table->dropForeign('wallets_seller_id_foreign');
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('wallets', 'seller_id')) {
                $table->dropColumn('seller_id');
            }
        });

        Schema::table('wallets', function (Blueprint $table) {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('wallets', 'seller_id')) {
                $table->foreignId('seller_id')->unique()->after('id')->constrained('users')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        \Illuminate\Support\Facades\DB::table('wallet_transactions')->truncate();
        \Illuminate\Support\Facades\DB::table('wallets')->truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropColumn('seller_id');
            $table->foreignId('customer_id')->unique()->after('id')->constrained('customers')->cascadeOnDelete();
        });
    }
};
