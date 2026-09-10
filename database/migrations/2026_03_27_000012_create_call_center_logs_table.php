<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_center_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('call_code')->nullable()->unique();
            $table->string('agent_name');
            $table->string('call_type')->default('outgoing');
            $table->string('priority')->default('normal');
            $table->string('result')->default('no_answer');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('call_time');
            $table->timestamps();

            $table->index(['seller_id', 'result']);
            $table->index(['seller_id', 'call_time']);
            $table->index(['seller_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_center_logs');
    }
};
