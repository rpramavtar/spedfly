<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'shopify_product_id')) {
                $table->unsignedBigInteger('shopify_product_id')->nullable()->after('status');
            }
            if (! Schema::hasColumn('products', 'shopify_sync_status')) {
                $table->string('shopify_sync_status')->default('pending')->after('shopify_product_id');
            }
            if (! Schema::hasColumn('products', 'shopify_synced_at')) {
                $table->timestamp('shopify_synced_at')->nullable()->after('shopify_sync_status');
            }
            if (! Schema::hasColumn('products', 'shopify_sync_error')) {
                $table->text('shopify_sync_error')->nullable()->after('shopify_synced_at');
            }
            $table->index(['seller_id', 'shopify_product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            try {
                $table->dropIndex(['seller_id', 'shopify_product_id']);
            } catch (\Throwable $exception) {
                //
            }

            $columns = [];
            foreach ([
                'shopify_product_id',
                'shopify_sync_status',
                'shopify_synced_at',
                'shopify_sync_error',
            ] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $columns[] = $column;
                }
            }

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
