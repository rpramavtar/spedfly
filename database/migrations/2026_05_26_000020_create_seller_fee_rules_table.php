<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('seller_fee_rules')) {
            Schema::create('seller_fee_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
                $table->string('label')->nullable();
                $table->string('fee_type');
                $table->string('billing_unit');
                $table->unsignedInteger('min_quantity')->nullable();
                $table->unsignedInteger('max_quantity')->nullable();
                $table->decimal('rate', 12, 2)->default(0);
                $table->string('currency', 10)->default('EUR');
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['seller_id', 'fee_type', 'billing_unit']);
                $table->index(['seller_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_fee_rules');
    }
};
