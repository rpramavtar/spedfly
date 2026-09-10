<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('shipment_code', 50);
            $table->date('shipment_date');
            $table->string('customer_name');
            $table->string('customer_phone', 30);
            $table->string('pickup_name');
            $table->text('pickup_address');
            $table->string('pickup_city');
            $table->string('pickup_postal_code', 20);
            $table->string('delivery_name');
            $table->text('delivery_address');
            $table->string('delivery_city');
            $table->string('delivery_postal_code', 20);
            $table->string('courier_name');
            $table->decimal('weight', 8, 2)->default(0);
            $table->string('dimensions');
            $table->enum('payment_type', ['prepaid', 'cod']);
            $table->enum('status', ['pending', 'in_transit', 'delivered', 'delayed']);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['seller_id', 'shipment_code']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('shipments');
    }
};
