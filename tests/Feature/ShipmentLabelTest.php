<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ShipmentLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_open_a_printable_shipment_label(): void
    {
        $seller = User::factory()->create([
            'type' => 'seller',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'seller_id' => $seller->id,
            'name' => 'Asha Verma',
            'email' => 'asha@example.com',
            'phone' => '9876543210',
            'address' => 'MG Road, Indore',
        ]);

        $order = Order::create([
            'seller_id' => $seller->id,
            'customer_id' => $customer->id,
            'external_order_id' => 'ORD-1001',
            'amount' => 1499.00,
            'payment_type' => 'cod',
            'status' => 'new',
            'ordered_at' => now(),
        ]);

        $shipment = Shipment::create([
            'seller_id' => $seller->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'shipment_code' => 'SHP-ABC123',
            'shipment_date' => now()->toDateString(),
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'pickup_name' => 'Spedfly Warehouse',
            'pickup_address' => 'Warehouse Street 1',
            'pickup_city' => 'Indore',
            'pickup_postal_code' => '452001',
            'delivery_name' => $customer->name,
            'delivery_address' => $customer->address,
            'delivery_city' => 'Indore',
            'delivery_postal_code' => '452001',
            'courier_name' => 'Delhivery',
            'weight' => 1.25,
            'dimensions' => '20x15x10',
            'payment_type' => 'cod',
            'status' => 'pending',
        ]);

        Auth::login($seller);

        $response = app(\App\Http\Controllers\SellerController::class)->shipmentLabel($shipment);

        $this->assertSame('spedfly.shipments.label', $response->name());
        $this->assertStringContainsString('SHP-ABC123', $response->render());
        $this->assertStringContainsString('Print Label', $response->render());
    }

    public function test_admin_can_open_any_shipment_label(): void
    {
        $admin = User::factory()->create([
            'type' => 'admin',
            'status' => 'active',
        ]);

        $seller = User::factory()->create([
            'type' => 'seller',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'seller_id' => $seller->id,
            'name' => 'Ravi Sharma',
            'email' => 'ravi@example.com',
            'phone' => '9998887776',
            'address' => 'Civil Lines, Jaipur',
        ]);

        $order = Order::create([
            'seller_id' => $seller->id,
            'customer_id' => $customer->id,
            'external_order_id' => 'ORD-2002',
            'amount' => 899.00,
            'payment_type' => 'prepaid',
            'status' => 'processing',
            'ordered_at' => now(),
        ]);

        $shipment = Shipment::create([
            'seller_id' => $seller->id,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'shipment_code' => 'SHP-ZYX987',
            'shipment_date' => now()->toDateString(),
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'pickup_name' => 'Main Hub',
            'pickup_address' => 'Hub Road 12',
            'pickup_city' => 'Jaipur',
            'pickup_postal_code' => '302001',
            'delivery_name' => $customer->name,
            'delivery_address' => $customer->address,
            'delivery_city' => 'Jaipur',
            'delivery_postal_code' => '302001',
            'courier_name' => 'FedEx',
            'weight' => 2.00,
            'dimensions' => '25x20x15',
            'payment_type' => 'prepaid',
            'status' => 'in_transit',
        ]);

        Auth::login($admin);

        $response = app(\App\Http\Controllers\AdminController::class)->shipmentLabel($shipment);

        $this->assertSame('spedfly.shipments.label', $response->name());
        $this->assertStringContainsString('SHP-ZYX987', $response->render());
        $this->assertStringContainsString('Courier-ready shipment label', $response->render());
    }
}
