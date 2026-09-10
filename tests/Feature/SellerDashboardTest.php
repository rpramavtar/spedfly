<?php

namespace Tests\Feature;

use App\Http\Controllers\SellerController;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SellerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_live_metrics_and_recent_shipments(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15 12:00:00'));

        try {
            $seller = User::factory()->create([
                'type' => 'seller',
                'status' => 'active',
            ]);

            $customer = Customer::create([
                'seller_id' => $seller->id,
                'name' => 'Neha Kapoor',
                'email' => 'neha@example.com',
                'phone' => '9988776655',
                'address' => 'Indiranagar, Bengaluru',
            ]);

            $order = Order::create([
                'seller_id' => $seller->id,
                'customer_id' => $customer->id,
                'external_order_id' => 'ORD-9001',
                'amount' => 2599.50,
                'payment_type' => 'cod',
                'status' => 'processing',
                'ordered_at' => now()->subDay(),
            ]);

            Shipment::create([
                'seller_id' => $seller->id,
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'shipment_code' => 'SHP-LIVE01',
                'shipment_date' => now()->subDays(20)->toDateString(),
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'pickup_name' => 'Main Hub',
                'pickup_address' => 'Warehouse Road 3',
                'pickup_city' => 'Bengaluru',
                'pickup_postal_code' => '560001',
                'delivery_name' => $customer->name,
                'delivery_address' => $customer->address,
                'delivery_city' => 'Bengaluru',
                'delivery_postal_code' => '560001',
                'courier_name' => 'Delhivery',
                'weight' => 1.10,
                'dimensions' => '20x15x10',
                'payment_type' => 'cod',
                'status' => 'delivered',
            ]);

            Auth::login($seller);
            app('view')->share('errors', new \Illuminate\Support\ViewErrorBag());

            $view = app(SellerController::class)->dashboard(Request::create('/seller/dashboard', 'GET'));
            $html = $view->render();
            $data = $view->getData();

            $this->assertSame(1, $data['metrics']['total_customers']);
            $this->assertSame(1, $data['metrics']['total_orders']);
            $this->assertSame(1, $data['metrics']['delivered_shipments']);
            $this->assertSame(7, count($data['chartData']['labels']));
            $this->assertSame(1, array_sum($data['chartData']['delivered']));
            $this->assertStringContainsString('Seller Dashboard', $html);
            $this->assertStringContainsString('SHP-LIVE01', $html);
            $this->assertStringContainsString('Recent Shipments', $html);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_seller_returns_page_lists_only_their_returns(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15 12:00:00'));

        try {
            $seller = User::factory()->create([
                'type' => 'seller',
                'status' => 'active',
            ]);

            $customer = Customer::create([
                'seller_id' => $seller->id,
                'name' => 'Ravi Mehta',
                'email' => 'ravi@example.com',
                'phone' => '9090909090',
                'address' => 'Pune',
            ]);

            $order = Order::create([
                'seller_id' => $seller->id,
                'customer_id' => $customer->id,
                'external_order_id' => 'ORD-RET-1001',
                'amount' => 1499.00,
                'payment_type' => 'prepaid',
                'status' => 'returned',
                'ordered_at' => now()->subDays(2),
            ]);

            OrderReturn::create([
                'return_code' => 'RTN202604151200000001',
                'order_id' => $order->id,
                'seller_id' => $seller->id,
                'customer_id' => $customer->id,
                'return_reason' => 'Damaged In Transit',
                'refund_amount' => 1499.00,
                'notes' => 'Box damaged at delivery.',
                'status' => 'refunded',
                'returned_at' => now()->subDay(),
            ]);

            Auth::login($seller);
            app('view')->share('errors', new \Illuminate\Support\ViewErrorBag());

            $view = app(SellerController::class)->returns(Request::create('/seller/returns', 'GET'));
            $html = $view->render();
            $data = $view->getData();

            $this->assertSame(1, $data['stats']['total']);
            $this->assertSame(0, $data['stats']['today']);
            $this->assertStringContainsString('Returns', $html);
            $this->assertStringContainsString('RTN202604151200000001', $html);
            $this->assertStringContainsString('ORD-RET-1001', $html);
            $this->assertStringContainsString('Damaged In Transit', $html);
        } finally {
            Carbon::setTestNow();
        }
    }
}
