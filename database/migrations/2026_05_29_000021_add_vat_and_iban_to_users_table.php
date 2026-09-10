<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'vat_number')) {
                $table->string('vat_number')->nullable()->after('support_phone');
            }
            if (! Schema::hasColumn('users', 'iban_code')) {
                $table->string('iban_code')->nullable()->after('bank_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'vat_number',
                'iban_code',
            ]);
        });
    }
};
