<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SellerController;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect()->route('seller.index');
});

Route::get('/privacy-policy', function () {
    return view('privacy-policy');
});


Route::get('/clear-cache', function () {
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    \Illuminate\Support\Facades\Artisan::call('view:clear');
    \Illuminate\Support\Facades\Artisan::call('route:clear');
    return 'Cache and OPcache cleared successfully!';
});

Route::get('/run-migrate', function () {
    \Illuminate\Support\Facades\Artisan::call('migrate');
    return '<pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
});


Route::get('/view-logs', function () {
    $logPath = storage_path('logs/laravel.log');
    if (!file_exists($logPath)) {
        return 'No log file found.';
    }
    $file = file($logPath);
    $lines = array_slice($file, -150);
    return '<pre>' . implode("", $lines) . '</pre>';
});





// Fallback auth route name used by Laravel auth middleware.
Route::get('/login', fn () => redirect()->route('seller.index'))->name('login');

Route::post('/shopify/webhooks/products/update', [SellerController::class, 'shopifyProductUpdatedWebhook'])
    ->name('shopify.webhooks.products.update');

Route::post('/shopify/webhooks/products/create', [SellerController::class, 'shopifyProductCreatedWebhook'])
    ->name('shopify.webhooks.products.create');

Route::post('/shopify/webhooks/products/delete', [SellerController::class, 'shopifyProductDeletedWebhook'])
    ->name('shopify.webhooks.products.delete');

Route::post('/shopify/webhooks/customers/data_request', [SellerController::class, 'shopifyCustomersDataRequest'])
    ->name('shopify.webhooks.customers.data_request');

Route::post('/shopify/webhooks/customers/redact', [SellerController::class, 'shopifyCustomersRedact'])
    ->name('shopify.webhooks.customers.redact');

Route::post('/shopify/webhooks/shop/redact', [SellerController::class, 'shopifyShopRedact'])
    ->name('shopify.webhooks.shop.redact');

Route::post('/shopify/webhooks/orders/create', [SellerController::class, 'shopifyOrderCreatedWebhook'])
    ->name('shopify.webhooks.orders.create');

Route::post('/shopify/webhooks/orders/updated', [SellerController::class, 'shopifyOrderUpdatedWebhook'])
    ->name('shopify.webhooks.orders.updated');

Route::post('/shopify/webhooks/orders/cancelled', [SellerController::class, 'shopifyOrderCancelledWebhook'])
    ->name('shopify.webhooks.orders.cancelled');

Route::post('/shopify/webhooks/orders/fulfilled', [SellerController::class, 'shopifyOrderFulfilledWebhook'])
    ->name('shopify.webhooks.orders.fulfilled');

Route::post('/shopify/webhooks/orders/partially_fulfilled', [SellerController::class, 'shopifyOrderPartiallyFulfilledWebhook'])
    ->name('shopify.webhooks.orders.partially_fulfilled');

Route::get('/language/{locale}', function (string $locale) {
    $supportedLocales = ['en', 'fr', 'es', 'it'];

    if (! in_array($locale, $supportedLocales, true)) {
        $locale = config('app.locale', 'en');
    }

    SystemSetting::query()->updateOrCreate(
        ['setting_key' => 'language'],
        ['setting_value' => $locale]
    );

    session(['locale' => $locale]);
    app()->setLocale($locale);

    return redirect()->back();
})->name('language.switch');

Route::prefix('seller')->name('seller.')->controller(SellerController::class)->group(function () {
    Route::get('/', fn () => redirect()->route('seller.index'));
    Route::get('/index', 'index')->name('index');
    Route::post('/login', 'authenticate')->name('authenticate');
    Route::get('/forgot-password', 'forgotPassword')->name('password.request');
    Route::post('/forgot-password', 'sendResetLink')->name('password.email');
    Route::get('/reset-password/{token}', 'resetPasswordForm')->name('password.reset');
    Route::post('/reset-password', 'resetPassword')->name('password.update');
    Route::get('/login', fn () => redirect()->route('seller.index'))->name('login');
});

Route::prefix('seller')
    ->name('seller.')
    ->middleware(['auth', 'user.type:seller'])
    ->controller(SellerController::class)
    ->group(function () {
    Route::get('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/leads', 'leads')->name('leads');
    Route::patch('/leads/{order}/confirm', 'confirmLead')->name('leads.confirm');
    Route::get('/orders', 'orders')->name('orders');
    Route::get('/orders/feed', 'ordersFeed')->name('orders.feed');
    Route::post('/orders/sync-shopify', 'syncOrdersFromShopify')->name('orders.sync-shopify');
    Route::get('/returns', 'returns')->name('returns');
    Route::post('/returns', 'storeReturn')->name('returns.store');
    Route::get('/call-center', 'callCenter')->name('call-center');
    Route::get('/shipments', 'shipments')->name('shipments');
    Route::get('/shipments/feed', 'shipmentsFeed')->name('shipments.feed');
    Route::get('/analytics', 'analytics')->name('analytics');
    Route::get('/reports', 'reports')->name('reports');
    Route::get('/csv-import', 'csvImport')->name('csv-import');
    Route::post('/csv-import', 'uploadCsvImport')->name('csv-import.upload');
    Route::get('/csv-import/template', 'csvImportTemplate')->name('csv-import.template');
    Route::get('/csv-import/shopify-sample', 'csvImportShopifySample')->name('csv-import.shopify-sample');
    Route::get('/settings', 'settings')->name('settings');
    Route::put('/settings', 'updateSettings')->name('settings.update');
    Route::post('/shopify/connect', 'shopifyConnect')->name('shopify.connect');
    Route::get('/shopify/callback', 'shopifyCallback')->name('shopify.callback');
    Route::post('/shopify/disconnect', 'shopifyDisconnect')->name('shopify.disconnect');
    Route::get('/add-company', 'addCompany')->name('add-company');
    Route::get('/add-driver', 'addDriver')->name('add-driver');
    Route::get('/add-trip', 'addTrip')->name('add-trip');
    Route::get('/company', 'company')->name('company');
    Route::get('/create-shipment', 'createShipment')->name('create-shipment');
    Route::post('/create-shipment', 'storeShipment')->name('create-shipment.store');
    Route::get('/shipments/{shipment}/label', 'shipmentLabel')->name('shipments.label');
    Route::get('/shipments/{shipment}/edit', 'editShipment')->name('shipments.edit');
    Route::match(['put', 'patch'], '/shipments/{shipment}', 'updateShipment')->name('shipments.update');
    Route::delete('/shipments/{shipment}', 'destroyShipment')->name('shipments.destroy');
    Route::get('/order-details/{order}', 'orderDetails')->name('order-details');
    Route::patch('/orders/{order}/status', 'updateOrderStatus')->name('orders.status');
    Route::get('/products', 'products')->name('products');
    Route::get('/products/feed', 'productsFeed')->name('products.feed');
    Route::get('/products/{product}/feed', 'productFeed')->name('products.single.feed');
    Route::post('/products/sync-shopify', 'syncProductsToShopify')->name('products.sync-shopify');
    Route::get('/add-product', 'addProduct')->name('add-product');
    Route::post('/add-product', 'storeProduct')->name('add-product.store');
    Route::get('/products/{product}/edit', 'editProduct')->name('products.edit');
    Route::match(['put', 'patch'], '/products/{product}', 'updateProduct')->name('products.update');
    Route::delete('/products/{product}', 'destroyProduct')->name('products.destroy');

    Route::get('/profile', 'profile')->name('profile');
    Route::put('/profile', 'updateProfile')->name('profile.update');
    Route::get('/driver', fn () => redirect()->route('seller.add-driver'))->name('driver');
    Route::get('/trips', fn () => redirect()->route('seller.add-trip'))->name('trips');
    Route::get('/customers', fn () => redirect()->route('seller.orders'))->name('customers');
    Route::get('/email', fn () => redirect()->route('seller.dashboard'))->name('email');
    Route::get('/chat', fn () => redirect()->route('seller.dashboard'))->name('chat');
    Route::get('/notifications', fn () => redirect()->route('seller.dashboard'))->name('notifications');
    Route::get('/sendsms', fn () => redirect()->route('seller.dashboard'))->name('sendsms');
    Route::get('/availabledrivermap', fn () => redirect()->route('seller.dashboard'))->name('availabledrivermap');
    Route::get('/wallet', 'wallet')->name('wallet');
    Route::post('/wallet/withdraw', 'requestWithdrawal')->name('wallet.withdraw');
    Route::post('/wallet/topup', 'topupWallet')->name('wallet.topup');
    Route::get('/wallet/success', 'topupSuccess')->name('wallet.success');
});

Route::prefix('admin')->name('admin.')->controller(AdminController::class)->group(function () {
    Route::get('/', fn () => redirect()->route('admin.login'));
    Route::get('/login', 'login')->name('login');
    Route::post('/login', 'authenticate')->name('authenticate');
    Route::get('/forgot-password', 'forgotPassword')->name('password.request');
    Route::post('/forgot-password', 'sendResetLink')->name('password.email');
    Route::get('/reset-password/{token}', 'resetPasswordForm')->name('password.reset');
    Route::post('/reset-password', 'resetPassword')->name('password.update');
    Route::post('/logout', 'logout')->name('logout');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'user.type:admin'])
    ->controller(AdminController::class)
    ->group(function () {
    Route::get('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/sellers', 'sellers')->name('sellers');
    Route::get('/sellers/{seller}', 'showSeller')->name('sellers.show');
    Route::post('/sellers', 'storeSeller')->name('sellers.store');
    Route::patch('/sellers/{seller}', 'updateSeller')->name('sellers.update');
    Route::put('/sellers/{seller}/fees', 'updateSellerFees')->name('sellers.fees.update');
    Route::patch('/sellers/{seller}/status', 'updateSellerStatus')->name('sellers.status');
    Route::delete('/sellers/{seller}', 'destroySeller')->name('sellers.destroy');
    Route::get('/customers', 'customers')->name('customers');
    Route::post('/customers', 'storeCustomer')->name('customers.store');
    Route::patch('/customers/{customer}', 'updateCustomer')->name('customers.update');
    Route::patch('/customers/{customer}/status', 'updateCustomerStatus')->name('customers.status');
    Route::delete('/customers/{customer}', 'destroyCustomer')->name('customers.destroy');
    Route::get('/sellers/{seller}/wallet', 'sellerWallet')->name('sellers.wallet');
    Route::get('/withdrawals', 'withdrawals')->name('withdrawals');
    Route::post('/withdrawals/{request}/approve', 'approveWithdrawal')->name('withdrawals.approve');
    Route::post('/withdrawals/{request}/reject', 'rejectWithdrawal')->name('withdrawals.reject');
    Route::get('/products', 'products')->name('products');
    Route::get('/orders', 'orders')->name('orders');
    Route::get('/orders/feed', 'ordersFeed')->name('orders.feed');
    Route::post('/orders', 'storeOrder')->name('orders.store');
    Route::post('/returns', 'storeReturn')->name('returns.store');
    Route::patch('/orders/{order}', 'updateOrder')->name('orders.update');
    Route::patch('/orders/{order}/status', 'updateOrderStatus')->name('orders.status');
    Route::get('/orders/{order}/details', 'orderDetails')->name('orders.details');
    Route::get('/leads', 'leads')->name('leads');
    Route::patch('/leads/{order}/confirm', 'confirmLead')->name('leads.confirm');
    Route::get('/shipments', 'shipments')->name('shipments');
    Route::get('/shipments/feed', 'shipmentsFeed')->name('shipments.feed');
    Route::get('/shipments/{shipment}/label', 'shipmentLabel')->name('shipments.label');
    Route::patch('/shipments/{shipment}', 'updateShipment')->name('shipments.update');
    Route::get('/returns', 'returns')->name('returns');
    Route::post('/returns', 'storeReturn')->name('returns.store');
    Route::patch('/returns/{orderReturn}/status', 'updateReturnStatus')->name('returns.status');
    Route::get('/payments', 'payments')->name('payments');
    Route::get('/call-center', 'callCenter')->name('call-center');
    Route::post('/call-center', 'storeCallCenter')->name('call-center.store');
    Route::get('/analytics', 'analytics')->name('analytics');
    Route::get('/marketing', 'marketing')->name('marketing');
    Route::get('/reports', 'reports')->name('reports');
    Route::get('/reports/export/{format}', 'exportReports')->name('reports.export');
    Route::get('/system-settings', 'systemSettings')->name('system-settings');
    Route::post('/system-settings', 'updateSystemSettings')->name('system-settings.update');
    Route::get('/admin-management', 'adminManagement')->name('admin-management');
    Route::get('/logs', 'logs')->name('logs');
    Route::get('/admin', 'admin')->name('admin');
    Route::get('/index', fn () => redirect()->route('admin.login'))->name('index');
    Route::get('/profile', 'profile')->name('profile');
    Route::put('/profile', 'updateProfile')->name('profile.update');
    Route::get('/settings', fn () => redirect()->route('admin.system-settings'))->name('settings');

    // Wallet Management Routes
    Route::post('/sellers/{seller}/wallet/topup', 'topupSellerWallet')->name('sellers.wallet.topup');
    Route::post('/sellers/{seller}/wallet/threshold', 'updateSellerWalletThreshold')->name('sellers.wallet.threshold');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
});
