<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'shopify_access_token_expires_at')) {
                $table->timestamp('shopify_access_token_expires_at')->nullable()->after('shopify_access_token');
            }

            if (! Schema::hasColumn('users', 'shopify_refresh_token')) {
                $table->text('shopify_refresh_token')->nullable()->after('shopify_access_token_expires_at');
            }

            if (! Schema::hasColumn('users', 'shopify_refresh_token_expires_at')) {
                $table->timestamp('shopify_refresh_token_expires_at')->nullable()->after('shopify_refresh_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'shopify_refresh_token_expires_at')) {
                $table->dropColumn('shopify_refresh_token_expires_at');
            }

            if (Schema::hasColumn('users', 'shopify_refresh_token')) {
                $table->dropColumn('shopify_refresh_token');
            }

            if (Schema::hasColumn('users', 'shopify_access_token_expires_at')) {
                $table->dropColumn('shopify_access_token_expires_at');
            }
        });
    }
};
