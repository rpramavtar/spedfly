<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'shopify_shop_domain')) {
                $table->string('shopify_shop_domain')->nullable()->after('branch');
            }
            if (! Schema::hasColumn('users', 'shopify_access_token')) {
                $table->text('shopify_access_token')->nullable()->after('shopify_shop_domain');
            }
            if (! Schema::hasColumn('users', 'shopify_scope')) {
                $table->string('shopify_scope')->nullable()->after('shopify_access_token');
            }
            if (! Schema::hasColumn('users', 'shopify_connected_at')) {
                $table->timestamp('shopify_connected_at')->nullable()->after('shopify_scope');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [];
            foreach ([
                'shopify_shop_domain',
                'shopify_access_token',
                'shopify_scope',
                'shopify_connected_at',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $columns[] = $column;
                }
            }

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
