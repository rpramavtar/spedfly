<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\CallCenterLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\SellerCsvImport;
use App\Models\Shipment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\SellerFeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Notifications\ResetPassword;

class SellerController extends Controller
{
    public function index()
    {
        if (Auth::check()) {
            return Auth::user()->type === 'admin'
                ? redirect()->route('admin.dashboard')
                : redirect()->route('seller.dashboard');
        }

        return view('spedfly.seller.index');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:Admin,Seller'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
            'type' => $validated['type'],
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Invalid credentials for selected user type.'])
                ->withInput($request->only('type', 'email'));
        }

        $user = Auth::user();
        $status = strtolower((string) ($user?->status ?? 'active'));

        if ($status === 'deactive' || $status === 'inactive' || $status === 'suspended') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Your account is deactive. Please contact admin.'])
                ->withInput($request->only('type', 'email'));
        }

        $request->session()->regenerate();

        return $validated['type'] === 'admin'
            ? redirect()->route('admin.dashboard')
            : redirect()->route('seller.dashboard');
    }

    public function forgotPassword()
    {
        return view('spedfly.seller.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        ResetPassword::createUrlUsing(function (User $user, string $token) {
            return route('seller.password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        $status = Password::broker()->sendResetLink([
            'email' => $validated['email'],
            'type' => 'seller',
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()
                ->withErrors(['email' => 'We could not find a seller account with that email address.'])
                ->withInput($request->only('email'));
        }

        return back()->with('status', 'We sent a password reset link to your email address.');
    }

    public function resetPasswordForm(Request $request, string $token)
    {
        return view('spedfly.seller.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker()->reset([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'password_confirmation' => $request->input('password_confirmation'),
            'token' => $validated['token'],
            'type' => 'seller',
        ], function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'The reset link is invalid or has expired.']);
        }

        return redirect()
            ->route('seller.index')
            ->with('status', 'Password updated successfully. You can log in now.');
    }

    public function dashboard(Request $request)
    {
        $user = Auth::user();
        $sellerId = (int) Auth::id();
        $selectedTab = strtolower((string) $request->input('tab', 'all'));
        $overviewTabs = [
            'all' => __('ui.all'),
            'pending' => __('ui.pending'),
            'in_transit' => __('ui.in_transit'),
            'delivered' => __('ui.delivered'),
            'delayed' => __('ui.delayed'),
            'returned' => __('ui.returned'),
        ];

        if (! array_key_exists($selectedTab, $overviewTabs)) {
            $selectedTab = 'all';
        }

        $today = now();
        $currentWindowStart = $today->copy()->subDays(6)->startOfDay();
        $currentWindowEnd = $today->copy()->endOfDay();
        $previousWindowStart = $today->copy()->subDays(13)->startOfDay();
        $previousWindowEnd = $today->copy()->subDays(7)->endOfDay();
        $currentMonthStart = $today->copy()->startOfMonth();
        $previousMonthStart = $today->copy()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = $today->copy()->startOfMonth()->subSecond();

        $ordersQuery = Order::query()
            ->where('seller_id', $sellerId)
            ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead']);

        $shipmentsQuery = Shipment::query()
            ->where('seller_id', $sellerId);

        $customersQuery = Customer::query()
            ->where('seller_id', $sellerId);

        $totalCustomers = (clone $customersQuery)->count();
        $totalOrders = (clone $ordersQuery)->count();
        $totalShipments = (clone $shipmentsQuery)->count();

        $deliveredShipments = (clone $shipmentsQuery)->where('status', 'delivered')->count();
        $returnedShipments = (clone $shipmentsQuery)->where('status', 'returned')->count();
        $pendingShipments = (clone $shipmentsQuery)->where('status', 'pending')->count();
        $inTransitShipments = (clone $shipmentsQuery)->where('status', 'in_transit')->count();

        $totalRevenue = (float) (clone $ordersQuery)->sum('amount');
        $monthlyRevenue = (float) (clone $ordersQuery)
            ->whereBetween('ordered_at', [$currentMonthStart, $currentWindowEnd])
            ->sum('amount');
        $previousMonthlyRevenue = (float) (clone $ordersQuery)
            ->whereBetween('ordered_at', [$previousMonthStart, $previousMonthEnd])
            ->sum('amount');

        $codOrders = (clone $ordersQuery)->where('payment_type', 'cod')->count();
        $prepaidOrders = (clone $ordersQuery)->where('payment_type', 'prepaid')->count();

        $currentCustomers = (clone $customersQuery)
            ->whereBetween('created_at', [$currentWindowStart, $currentWindowEnd])
            ->count();
        $previousCustomers = (clone $customersQuery)
            ->whereBetween('created_at', [$previousWindowStart, $previousWindowEnd])
            ->count();

        $currentOrders = (clone $ordersQuery)
            ->whereBetween('ordered_at', [$currentWindowStart, $currentWindowEnd])
            ->count();
        $previousOrders = (clone $ordersQuery)
            ->whereBetween('ordered_at', [$previousWindowStart, $previousWindowEnd])
            ->count();

        $currentDelivered = (clone $shipmentsQuery)
            ->whereBetween('updated_at', [$currentWindowStart, $currentWindowEnd])
            ->where('status', 'delivered')
            ->count();
        $previousDelivered = (clone $shipmentsQuery)
            ->whereBetween('updated_at', [$previousWindowStart, $previousWindowEnd])
            ->where('status', 'delivered')
            ->count();

        $currentReturned = (clone $shipmentsQuery)
            ->whereBetween('updated_at', [$currentWindowStart, $currentWindowEnd])
            ->where('status', 'returned')
            ->count();
        $previousReturned = (clone $shipmentsQuery)
            ->whereBetween('updated_at', [$previousWindowStart, $previousWindowEnd])
            ->where('status', 'returned')
            ->count();

        $dailyLabels = [];
        $dailyRevenue = [];
        $dailyOrders = [];
        $dailyDelivered = [];
        $dailyReturned = [];
        $dailyPending = [];
        $dailyInTransit = [];
        $dailyDelayed = [];

        $cursor = $currentWindowStart->copy();
        while ($cursor <= $currentWindowEnd) {
            $label = $cursor->format('d M');
            $date = $cursor->toDateString();
            $dailyLabels[] = $label;

            $dailyRevenue[] = (float) (clone $ordersQuery)
                ->whereDate('ordered_at', $date)
                ->sum('amount');

            $dailyOrders[] = (clone $ordersQuery)
                ->whereDate('ordered_at', $date)
                ->count();

            $dailyDelivered[] = (clone $shipmentsQuery)
                ->whereDate('updated_at', $date)
                ->where('status', 'delivered')
                ->count();

            $dailyReturned[] = (clone $shipmentsQuery)
                ->whereDate('updated_at', $date)
                ->where('status', 'returned')
                ->count();

            $dailyPending[] = (clone $shipmentsQuery)
                ->whereDate('shipment_date', $date)
                ->where('status', 'pending')
                ->count();

            $dailyInTransit[] = (clone $shipmentsQuery)
                ->whereDate('shipment_date', $date)
                ->where('status', 'in_transit')
                ->count();

            $dailyDelayed[] = (clone $shipmentsQuery)
                ->whereDate('shipment_date', $date)
                ->where('status', 'delayed')
                ->count();

            $cursor->addDay();
        }

        $recentShipments = Shipment::query()
            ->with(['order.customer'])
            ->where('seller_id', $sellerId)
            ->orderByDesc('shipment_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $dashboardMetrics = [
            'total_customers' => $totalCustomers,
            'total_orders' => $totalOrders,
            'total_shipments' => $totalShipments,
            'delivered_shipments' => $deliveredShipments,
            'returned_shipments' => $returnedShipments,
            'pending_shipments' => $pendingShipments,
            'in_transit_shipments' => $inTransitShipments,
            'monthly_revenue' => $monthlyRevenue,
            'total_revenue' => $totalRevenue,
            'cod_orders' => $codOrders,
            'prepaid_orders' => $prepaidOrders,
            'customer_growth' => $this->calculateGrowth($currentCustomers, $previousCustomers),
            'order_growth' => $this->calculateGrowth($currentOrders, $previousOrders),
            'delivery_growth' => $this->calculateGrowth($currentDelivered, $previousDelivered),
            'return_growth' => $this->calculateGrowth($currentReturned, $previousReturned),
            'revenue_growth' => $this->calculateGrowth($monthlyRevenue, $previousMonthlyRevenue),
        ];

        $overviewStats = [
            'shipped' => $inTransitShipments + $deliveredShipments,
            'cancelled' => $returnedShipments,
            'delivered' => $deliveredShipments,
            'growth' => $dashboardMetrics['order_growth'],
            'signups' => $totalCustomers,
            'ratings' => round(4.5 + min(0.4, $deliveredShipments > 0 ? 0.1 : 0), 1),
            'earnings' => $monthlyRevenue,
        ];

        $feeSummary = app(SellerFeeService::class)->buildDashboardSummary($user);
        $feeCards = $feeSummary['cards'];

        $overviewChart = match ($selectedTab) {
            'pending' => [
                'primary' => ['label' => __('ui.pending'), 'data' => $dailyPending],
                'secondary' => ['label' => __('ui.returned'), 'data' => $dailyReturned],
            ],
            'in_transit' => [
                'primary' => ['label' => __('ui.in_transit'), 'data' => $dailyInTransit],
                'secondary' => ['label' => __('ui.returned'), 'data' => $dailyReturned],
            ],
            'delivered' => [
                'primary' => ['label' => __('ui.delivered'), 'data' => $dailyDelivered],
                'secondary' => ['label' => __('ui.returned'), 'data' => $dailyReturned],
            ],
            'delayed' => [
                'primary' => ['label' => __('ui.delayed'), 'data' => $dailyDelayed],
                'secondary' => ['label' => __('ui.returned'), 'data' => $dailyReturned],
            ],
            'returned' => [
                'primary' => ['label' => __('ui.returned'), 'data' => $dailyReturned],
                'secondary' => ['label' => __('ui.delivered'), 'data' => $dailyDelivered],
            ],
            default => [
                'primary' => ['label' => __('ui.shipped'), 'data' => array_map(
                    fn (int $delivered, int $inTransit) => $delivered + $inTransit,
                    $dailyDelivered,
                    $dailyInTransit
                )],
                'secondary' => ['label' => __('ui.cancelled'), 'data' => $dailyReturned],
            ],
        };

        return view('spedfly.seller.dashboard', [
            'shopifyConnection' => $this->shopifyConnectionData($user),
            'metrics' => $dashboardMetrics,
            'overviewStats' => $overviewStats,
            'feeCards' => $feeCards,
            'feeSummary' => $feeSummary,
            'chartData' => [
                'labels' => $dailyLabels,
                'revenue' => $dailyRevenue,
                'orders' => $dailyOrders,
                'delivered' => $dailyDelivered,
                'returned' => $dailyReturned,
                'pending' => $dailyPending,
                'in_transit' => $dailyInTransit,
                'delayed' => $dailyDelayed,
            ],
            'recentShipments' => $recentShipments,
            'overviewTabs' => $overviewTabs,
            'selectedTab' => $selectedTab,
            'overviewChart' => $overviewChart,
        ]);
    }

    public function profile()
    {
        $user = Auth::user();

        return view('spedfly.seller.profile', [
            'user' => $user,
            'memberSince' => optional($user?->created_at)->format('M d, Y') ?? '-',
            'avatarUrl' => $user?->avatar_path ? asset($user->avatar_path) : null,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $updates = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $updates['password'] = Hash::make($validated['password']);
        }

        if ($request->hasFile('avatar')) {
            if ($user?->avatar_path) {
                File::delete(public_path($user->avatar_path));
            }

            $avatarFile = $request->file('avatar');
            $avatarDirectory = public_path('uploads/avatars/seller');
            File::ensureDirectoryExists($avatarDirectory);

            $avatarName = 'seller-' . ($user?->id ?? 'profile') . '-' . now()->format('YmdHis') . '.' . $avatarFile->getClientOriginalExtension();
            $avatarFile->move($avatarDirectory, $avatarName);
            $updates['avatar_path'] = 'uploads/avatars/seller/' . $avatarName;
        }

        $user?->update($updates);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function wallet(Request $request)
    {
        $seller = Auth::user();
        $wallet = $seller->getOrCreateWallet();
        $transactions = $wallet->transactions()->latest()->get();
        $withdrawals = $seller->withdrawalRequests()->latest()->get();
        
        return view('spedfly.seller.wallet', compact('wallet', 'transactions', 'withdrawals'));
    }

    public function requestWithdrawal(Request $request): RedirectResponse
    {
        $seller = Auth::user();
        $wallet = $seller->getOrCreateWallet();

        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $amount = (float) $request->input('amount');

        if ($amount > $wallet->balance) {
            return redirect()->back()->with('error', 'Insufficient balance to request this withdrawal.');
        }

        DB::transaction(function () use ($seller, $amount) {
            // Create pending withdrawal request
            $wr = WithdrawalRequest::create([
                'seller_id' => $seller->id,
                'amount' => $amount,
                'status' => 'pending',
            ]);

            // Deduct from wallet immediately
            $seller->chargeWallet($amount, null, "Withdrawal request pending (Request ID: #{$wr->id})");
        });

        return redirect()->back()->with('success', 'Withdrawal request submitted successfully.');
    }

    public function topupWallet(Request $request): RedirectResponse
    {
        $seller = Auth::user();
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $amount = (float) $request->input('amount');
        $stripeSecret = env('STRIPE_SECRET');

        if (empty($stripeSecret)) {
            return redirect()->back()->with('error', 'Stripe secret key is not configured in the server.');
        }

        try {
            \Stripe\Stripe::setApiKey($stripeSecret);

            $checkoutSession = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower(system_currency_code()),
                        'product_data' => [
                            'name' => 'Spedfly Wallet Top-up',
                            'description' => 'Add funds to Spedfly pre-paid wallet balance',
                        ],
                        'unit_amount' => (int) round($amount * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('seller.wallet.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('seller.wallet'),
                'metadata' => [
                    'seller_id' => $seller->id,
                    'amount' => $amount,
                ],
            ]);

            return redirect()->away($checkoutSession->url);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Stripe Checkout error: " . $e->getMessage());
            return redirect()->back()->with('error', 'Stripe error: ' . $e->getMessage());
        }
    }

    public function topupSuccess(Request $request): RedirectResponse
    {
        $seller = Auth::user();
        $sessionId = $request->input('session_id');

        if (empty($sessionId)) {
            return redirect()->route('seller.wallet')->with('error', 'Invalid checkout session.');
        }

        $stripeSecret = env('STRIPE_SECRET');
        if (empty($stripeSecret)) {
            return redirect()->route('seller.wallet')->with('error', 'Stripe secret key is not configured in the server.');
        }

        try {
            \Stripe\Stripe::setApiKey($stripeSecret);

            $session = \Stripe\Checkout\Session::retrieve($sessionId);

            if ($session->payment_status !== 'paid') {
                return redirect()->route('seller.wallet')->with('error', 'Payment was not completed successfully.');
            }

            $metadataSellerId = (int) ($session->metadata->seller_id ?? 0);
            if ($metadataSellerId !== $seller->id) {
                return redirect()->route('seller.wallet')->with('error', 'Unauthorized checkout session.');
            }

            $alreadyCredited = \App\Models\WalletTransaction::query()
                ->where('type', 'topup')
                ->where(function ($query) use ($sessionId) {
                    $query->where('description', 'like', "%Stripe Top-up (Session: {$sessionId})%")
                          ->orWhere('description', 'like', "%recharge (Session: {$sessionId})%");
                })
                ->exists();

            if ($alreadyCredited) {
                return redirect()->route('seller.wallet')->with('error', 'This checkout session has already been credited.');
            }

            $amount = (float) ($session->amount_total / 100);
            $seller->topupWallet($amount, "recharge (Session: {$sessionId})");

            return redirect()->route('seller.wallet')->with('success', 'Wallet topped up successfully via Stripe! Added: ' . system_currency_format($amount));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Stripe verification error: " . $e->getMessage());
            return redirect()->route('seller.wallet')->with('error', 'Payment verification failed: ' . $e->getMessage());
        }
    }

    private function calculateGrowth(int|float $current, int|float $previous): int
    {
        if ((float) $previous === 0.0) {
            return $current > 0 ? 100 : 0;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    private function shopifyConnectionData(?User $user): array
    {
        $shopDomain = trim((string) $user?->shopify_shop_domain);
        $accessToken = $this->shopifyAccessTokenForUser($user);
        $scope = trim((string) $user?->shopify_scope);
        $installedScopes = array_values(array_filter(array_map('trim', explode(',', $scope))));

        return [
            'connected' => $shopDomain !== '' && $accessToken !== '',
            'shop_domain' => $shopDomain,
            'scope' => $scope,
            'has_product_write_scope' => in_array('write_products', $installedScopes, true),
            'has_order_write_scope' => in_array('write_orders', $installedScopes, true),
            'connected_at' => $user?->shopify_connected_at,
        ];
    }

    private function normalizeShopifyDomain(string $shopDomain): string
    {
        $shopDomain = trim(strtolower($shopDomain));
        $shopDomain = preg_replace('#^https?://#', '', $shopDomain) ?? $shopDomain;
        $shopDomain = preg_replace('#/.*$#', '', $shopDomain) ?? $shopDomain;

        if ($shopDomain === '') {
            return '';
        }

        if (! preg_match('/^[a-z0-9][a-z0-9-]*\.[a-z0-9.-]+$/', $shopDomain)) {
            return '';
        }

        return $shopDomain;
    }

    private function buildShopifyAuthorizationUrl(Request $request, string $shopDomain, string $state): string
    {
        $callbackUrl = $this->buildShopifyCallbackUrl($request);

        $params = [
            'client_id' => (string) config('services.shopify.client_id'),
            'scope' => $this->shopifyRequestedScopes(),
            'redirect_uri' => $callbackUrl,
            'state' => $state,
        ];

        return "https://{$shopDomain}/admin/oauth/authorize?" . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function buildShopifyCallbackUrl(Request $request): string
    {
        $appUrl = trim((string) config('app.url'));
        if ($appUrl !== '') {
            return rtrim($appUrl, '/') . '/seller/shopify/callback';
        }

        return rtrim($request->getSchemeAndHttpHost(), '/') . '/seller/shopify/callback';
    }

    private function shopifyRequestedScopes(): string
    {
        $configuredScopes = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('services.shopify.scopes'))
        )));

        $requiredScopes = ['read_products'];

        return implode(',', array_values(array_unique(array_merge($configuredScopes, $requiredScopes))));
    }

private function isValidShopifyCallback(Request $request): bool
{
    $query = $request->server('QUERY_STRING', '');

    parse_str($query, $params);

    $providedHmac = $params['hmac'] ?? '';

    unset($params['hmac']);
    unset($params['signature']);

    ksort($params);

    $pairs = [];

    foreach ($params as $key => $value) {
        $pairs[] = $key . '=' . $value;
    }

    $message = implode('&', $pairs);

    $calculatedHmac = hash_hmac(
        'sha256',
        $message,
        config('services.shopify.client_secret')
    );

    \Log::info('SHOPIFY_HMAC_CHECK', [
        'message' => $message,
        'provided' => $providedHmac,
        'calculated' => $calculatedHmac,
    ]);

    return hash_equals($providedHmac, $calculatedHmac);
}

   /* private function isValidShopifyCallback(Request $request): bool
    {
          return true; 
        
        $params = $request->except(['hmac', 'signature']);
        ksort($params);

        $message = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $calculatedHmac = hash_hmac('sha256', $message, (string) config('services.shopify.client_secret'));
        $providedHmac = (string) $request->query('hmac', '');

        return $providedHmac !== '' && hash_equals($calculatedHmac, $providedHmac);
    }*/

    public function shopifyProductUpdatedWebhook(Request $request): Response
    {
        if (! $this->isValidShopifyWebhook($request)) {
            return response('Invalid webhook signature.', 401);
        }

        $topic = strtolower((string) $request->header('X-Shopify-Topic', ''));
        if ($topic !== 'products/update') {
            return response('Ignored.', 200);
        }

        $shopDomain = $this->normalizeShopifyDomain((string) $request->header('X-Shopify-Shop-Domain', ''));
        if ($shopDomain === '') {
            return response('Missing shop domain.', 200);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (! is_array($payload)) {
            return response('Invalid payload.', 200);
        }

        Log::info('Shopify products/update webhook received.', [
            'shop_domain' => $shopDomain,
            'product_id' => $payload['id'] ?? null,
            'title' => $payload['title'] ?? null,
            'variant_sku' => $payload['variants'][0]['sku'] ?? null,
            'variant_price' => $payload['variants'][0]['price'] ?? null,
        ]);

        $this->syncLocalProductFromShopifyWebhook($shopDomain, $payload);

        return response('OK', 200);
    }

    public function shopifyProductCreatedWebhook(Request $request): Response
    {
        if (! $this->isValidShopifyWebhook($request)) {
            return response('Invalid webhook signature.', 401);
        }

        $topic = strtolower((string) $request->header('X-Shopify-Topic', ''));
        if ($topic !== 'products/create') {
            return response('Ignored.', 200);
        }

        $shopDomain = $this->normalizeShopifyDomain((string) $request->header('X-Shopify-Shop-Domain', ''));
        if ($shopDomain === '') {
            return response('Missing shop domain.', 200);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (! is_array($payload)) {
            return response('Invalid payload.', 200);
        }

        Log::info('Shopify products/create webhook received.', [
            'shop_domain' => $shopDomain,
            'product_id' => $payload['id'] ?? null,
            'title' => $payload['title'] ?? null,
            'variant_sku' => $payload['variants'][0]['sku'] ?? null,
            'variant_price' => $payload['variants'][0]['price'] ?? null,
        ]);

        $this->syncLocalProductFromShopifyWebhook($shopDomain, $payload, true);

        return response('OK', 200);
    }

    public function shopifyProductDeletedWebhook(Request $request): Response
    {
        if (! $this->isValidShopifyWebhook($request)) {
            return response('Invalid webhook signature.', 401);
        }

        $topic = strtolower((string) $request->header('X-Shopify-Topic', ''));
        if ($topic !== 'products/delete') {
            return response('Ignored.', 200);
        }

        $shopDomain = $this->normalizeShopifyDomain((string) $request->header('X-Shopify-Shop-Domain', ''));
        if ($shopDomain === '') {
            return response('Missing shop domain.', 200);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (! is_array($payload)) {
            return response('Invalid payload.', 200);
        }

        Log::info('Shopify products/delete webhook received.', [
            'shop_domain' => $shopDomain,
            'product_id' => $payload['id'] ?? null,
            'title' => $payload['title'] ?? null,
        ]);

        $seller = User::query()
            ->where('shopify_shop_domain', $shopDomain)
            ->first();

        if (! $seller) {
            return response('OK', 200);
        }

        $shopifyProductId = (int) ($payload['id'] ?? 0);
        if ($shopifyProductId <= 0) {
            return response('OK', 200);
        }

        $product = Product::query()
            ->where('seller_id', $seller->id)
            ->where('shopify_product_id', $shopifyProductId)
            ->first();

        if (! $product) {
            Log::info('Shopify product delete webhook did not match a local product.', [
                'shop_domain' => $shopDomain,
                'shopify_product_id' => $shopifyProductId,
                'title' => $payload['title'] ?? null,
            ]);

            return response('OK', 200);
        }

        $product->update([
            'status' => 'deactive',
            'shopify_sync_status' => 'synced',
            'shopify_synced_at' => now(),
            'shopify_sync_error' => null,
        ]);

        return response('OK', 200);
    }

    public function shopifyCustomersDataRequest(Request $request): Response
    {
        if (! $this->isValidShopifyWebhook($request)) {
            return response('Invalid webhook signature.', 401);
        }

        Log::info('Shopify GDPR customers/data_request webhook received.', (array) json_decode((string) $request->getContent(), true));

        return response('OK', 200);
    }

    public function shopifyCustomersRedact(Request $request): Response
    {
        if (! $this->isValidShopifyWebhook($request)) {
            return response('Invalid webhook signature.', 401);
        }

        Log::info('Shopify GDPR customers/redact webhook received.', (array) json_decode((string) $request->getContent(), true));

        return response('OK', 200);
    }

    public function shopifyShopRedact(Request $request): Response
    {
        if (! $this->isValidShopifyWebhook($request)) {
            return response('Invalid webhook signature.', 401);
        }

        $payload = (array) json_decode((string) $request->getContent(), true);
        Log::info('Shopify GDPR shop/redact webhook received.', $payload);

        $shopDomain = $this->normalizeShopifyDomain((string) ($payload['shop_domain'] ?? $request->header('X-Shopify-Shop-Domain', '')));
        if ($shopDomain !== '') {
            $seller = User::query()->where('shopify_shop_domain', $shopDomain)->first();
            if ($seller) {
                $seller->update([
                    'shopify_access_token' => null,
                    'shopify_scope' => null,
                ]);
            }
        }

        return response('OK', 200);
    }

    public function shopifyAppUninstalledWebhook(Request $request): Response
    {
        if (! $this->isValidShopifyWebhook($request)) {
            return response('Invalid webhook signature.', 401);
        }

        $shopDomain = $this->normalizeShopifyDomain((string) $request->header('X-Shopify-Shop-Domain', ''));
        $payload = (array) json_decode((string) $request->getContent(), true);

        Log::info('Shopify app/uninstalled webhook received.', [
            'shop_domain' => $shopDomain,
            'payload' => $payload,
        ]);

        if ($shopDomain !== '') {
            $seller = User::query()->where('shopify_shop_domain', $shopDomain)->first();
            if ($seller) {
                $seller->update([
                    'shopify_access_token' => null,
                    'shopify_scope' => null,
                ]);
            }
        }

        return response('OK', 200);
    }

    public function shopifyOrderCreatedWebhook(Request $request): Response
    {
        if (! $this->isValidShopifyWebhook($request)) {
            return response('Invalid webhook signature.', 401);
        }

        $topic = strtolower((string) $request->header('X-Shopify-Topic', ''));
        if ($topic !== 'orders/create') {
            return response('Ignored.', 200);
        }

        $shopDomain = $this->normalizeShopifyDomain((string) $request->header('X-Shopify-Shop-Domain', ''));
        if ($shopDomain === '') {
            return response('Missing shop domain.', 200);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (! is_array($payload)) {
            return response('Invalid payload.', 200);
        }

        Log::info('Shopify orders/create webhook received.', [
            'shop_domain' => $shopDomain,
            'order_id' => $payload['id'] ?? null,
            'order_number' => $payload['order_number'] ?? null,
            'email' => $payload['email'] ?? null,
        ]);

        $this->syncLocalOrderFromShopifyWebhook($shopDomain, $payload, false);

        return response('OK', 200);
    }

    public function shopifyOrderUpdatedWebhook(Request $request): Response
    {
        if (! $this->isValidShopifyWebhook($request)) {
            return response('Invalid webhook signature.', 401);
        }

        $topic = strtolower((string) $request->header('X-Shopify-Topic', ''));
        if ($topic !== 'orders/updated') {
            return response('Ignored.', 200);
        }

        $shopDomain = $this->normalizeShopifyDomain((string) $request->header('X-Shopify-Shop-Domain', ''));
        if ($shopDomain === '') {
            return response('Missing shop domain.', 200);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (! is_array($payload)) {
            return response('Invalid payload.', 200);
        }

        Log::info('Shopify orders/updated webhook received.', [
            'shop_domain' => $shopDomain,
            'order_id' => $payload['id'] ?? null,
            'order_number' => $payload['order_number'] ?? null,
            'email' => $payload['email'] ?? null,
        ]);

        $this->syncLocalOrderFromShopifyWebhook($shopDomain, $payload, true);

        return response('OK', 200);
    }

    public function shopifyOrderCancelledWebhook(Request $request): Response
    {
        if (! $this->isValidShopifyWebhook($request)) {
            return response('Invalid webhook signature.', 401);
        }

        $topic = strtolower((string) $request->header('X-Shopify-Topic', ''));
        if ($topic !== 'orders/cancelled') {
            return response('Ignored.', 200);
        }

        $shopDomain = $this->normalizeShopifyDomain((string) $request->header('X-Shopify-Shop-Domain', ''));
        if ($shopDomain === '') {
            return response('Missing shop domain.', 200);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (! is_array($payload)) {
            return response('Invalid payload.', 200);
        }

        Log::info('Shopify orders/cancelled webhook received.', [
            'shop_domain' => $shopDomain,
            'order_id' => $payload['id'] ?? null,
            'order_number' => $payload['order_number'] ?? null,
            'email' => $payload['email'] ?? null,
        ]);

        $seller = User::query()
            ->where('shopify_shop_domain', $shopDomain)
            ->first();

        if (! $seller) {
            return response('OK', 200);
        }

        $order = $this->findLocalOrderFromShopifyPayload($seller->id, $payload);

        if (! $order) {
            return response('OK', 200);
        }

        $order->update([
            'status' => 'returned',
        ]);

        return response('OK', 200);
    }

    public function shopifyOrderFulfilledWebhook(Request $request): Response
    {
        if (! $this->isValidShopifyWebhook($request)) {
            return response('Invalid webhook signature.', 401);
        }

        $topic = strtolower((string) $request->header('X-Shopify-Topic', ''));
        if ($topic !== 'orders/fulfilled') {
            return response('Ignored.', 200);
        }

        $shopDomain = $this->normalizeShopifyDomain((string) $request->header('X-Shopify-Shop-Domain', ''));
        if ($shopDomain === '') {
            return response('Missing shop domain.', 200);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (! is_array($payload)) {
            return response('Invalid payload.', 200);
        }

        Log::info('Shopify orders/fulfilled webhook received.', [
            'shop_domain' => $shopDomain,
            'order_id' => $payload['id'] ?? null,
            'order_number' => $payload['order_number'] ?? null,
            'email' => $payload['email'] ?? null,
        ]);

        $this->syncLocalOrderFromShopifyWebhook($shopDomain, $payload, true);

        $seller = User::query()
            ->where('shopify_shop_domain', $shopDomain)
            ->first();

        if (! $seller) {
            return response('OK', 200);
        }

        $order = $this->findLocalOrderFromShopifyPayload($seller->id, $payload);

        if ($order) {
            $order->update([
                'status' => 'delivered',
            ]);
            $this->syncShipmentStatusFromOrder($order, 'delivered');
        }

        return response('OK', 200);
    }

    public function shopifyOrderPartiallyFulfilledWebhook(Request $request): Response
    {
        if (! $this->isValidShopifyWebhook($request)) {
            return response('Invalid webhook signature.', 401);
        }

        $topic = strtolower((string) $request->header('X-Shopify-Topic', ''));
        if ($topic !== 'orders/partially_fulfilled') {
            return response('Ignored.', 200);
        }

        $shopDomain = $this->normalizeShopifyDomain((string) $request->header('X-Shopify-Shop-Domain', ''));
        if ($shopDomain === '') {
            return response('Missing shop domain.', 200);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (! is_array($payload)) {
            return response('Invalid payload.', 200);
        }

        Log::info('Shopify orders/partially_fulfilled webhook received.', [
            'shop_domain' => $shopDomain,
            'order_id' => $payload['id'] ?? null,
            'order_number' => $payload['order_number'] ?? null,
            'email' => $payload['email'] ?? null,
        ]);

        $seller = User::query()
            ->where('shopify_shop_domain', $shopDomain)
            ->first();

        if (! $seller) {
            return response('OK', 200);
        }

        $order = $this->findLocalOrderFromShopifyPayload($seller->id, $payload);

        if ($order) {
            $order->update([
                'status' => 'shipped',
            ]);
            $this->syncShipmentStatusFromOrder($order, 'shipped');
        }

        return response('OK', 200);
    }

    private function isValidShopifyWebhook(Request $request): bool
    {
        $providedHmac = (string) $request->header('X-Shopify-Hmac-Sha256', '');
        if ($providedHmac === '') {
            return false;
        }

        $rawBody = (string) $request->getContent();
        $computedHmac = base64_encode(hash_hmac('sha256', $rawBody, (string) config('services.shopify.client_secret'), true));

        return hash_equals($computedHmac, $providedHmac);
    }

   private function syncLocalProductFromShopifyWebhook(string $shopDomain, array $payload, bool $createIfMissing = false): void
    {
        $seller = User::query()
            ->where('shopify_shop_domain', $shopDomain)
            ->first();

        if (! $seller) {
            return;
        }

        $shopifyProductId = (int) ($payload['id'] ?? 0);
        if ($shopifyProductId <= 0) {
            return;
        }

        $product = Product::query()
            ->where('seller_id', $seller->id)
            ->where('shopify_product_id', $shopifyProductId)
            ->first();

        if (! $product) {
            $variantSku = trim((string) ($payload['variants'][0]['sku'] ?? ''));
            if ($variantSku !== '') {
                $product = Product::query()
                    ->where('seller_id', $seller->id)
                    ->where('sku', $variantSku)
                    ->first();
            }
        }

        if (! $product) {
            Log::info('Shopify product update webhook did not match a local product.', [
                'shop_domain' => $shopDomain,
                'shopify_product_id' => $shopifyProductId,
                'variant_sku' => $payload['variants'][0]['sku'] ?? null,
                'title' => $payload['title'] ?? null,
            ]);

            if (! $createIfMissing) {
                return;
            }

            $variant = (array) ($payload['variants'][0] ?? []);
            $price = $this->extractFloatFromValue($variant['price'] ?? null) ?? 0.0;
            $stock = $this->extractIntFromValue($variant['inventory_quantity'] ?? null) ?? 0;
            $status = strtolower((string) ($payload['status'] ?? 'active'));
            $description = isset($payload['body_html']) ? trim(strip_tags((string) $payload['body_html'])) : '';
            $sku = trim((string) ($variant['sku'] ?? ''));

            if ($sku === '') {
                $sku = 'SHOPIFY-' . $shopifyProductId;
            }

            Product::query()->create([
                'seller_id' => $seller->id,
                'sku' => $sku,
                'name' => isset($payload['title']) ? trim((string) $payload['title']) : 'Shopify Product ' . $shopifyProductId,
                'description' => $description !== '' ? $description : null,
                'category' => isset($payload['product_type']) ? trim((string) $payload['product_type']) : null,
                'image_path' => $this->extractShopifyImageUrl($payload),
                'price' => $price,
                'stock' => $stock,
                'low_stock_alert' => 0,
                'status' => $status === 'active' ? 'active' : 'deactive',
                'shopify_product_id' => $shopifyProductId,
                'shopify_sync_status' => 'synced',
                'shopify_synced_at' => now(),
                'shopify_sync_error' => null,
            ]);

            return;
        }

        $variant = (array) ($payload['variants'][0] ?? []);
        $price = $this->extractFloatFromValue($variant['price'] ?? null);
        $stock = $this->extractIntFromValue($variant['inventory_quantity'] ?? null);
        $status = strtolower((string) ($payload['status'] ?? 'active'));
        $description = isset($payload['body_html']) ? trim(strip_tags((string) $payload['body_html'])) : '';

        $updates = [
            'name' => isset($payload['title']) ? trim((string) $payload['title']) : $product->name,
            'description' => $description !== '' ? $description : $product->description,
            'category' => isset($payload['product_type']) ? trim((string) $payload['product_type']) : $product->category,
            'image_path' => $this->extractShopifyImageUrl($payload) ?? $product->image_path,
            'status' => $status === 'active' ? 'active' : 'deactive',
            'shopify_sync_status' => 'synced',
            'shopify_synced_at' => now(),
            'shopify_sync_error' => null,
        ];

        if ($price !== null) {
            $updates['price'] = $price;
        }

        if ($stock !== null) {
            $updates['stock'] = $stock;
        }

        $product->update($updates);
    }

    private function findLocalOrderFromShopifyPayload(int $sellerId, array $payload): ?Order
    {
        $shopifyId = trim((string) ($payload['id'] ?? ''));
        $shopifyName = trim((string) ($payload['name'] ?? ''));
        $shopifyNumber = trim((string) ($payload['order_number'] ?? ''));

        if ($shopifyId === '' && $shopifyName === '' && $shopifyNumber === '') {
            return null;
        }

        return Order::query()
            ->where('seller_id', $sellerId)
            ->where(function ($q) use ($shopifyId, $shopifyName, $shopifyNumber) {
                if ($shopifyId !== '') {
                    $q->where('external_order_id', $shopifyId);
                }
                if ($shopifyName !== '') {
                    $cleanName = ltrim($shopifyName, '#');
                    $q->orWhere('external_order_id', $shopifyName)
                      ->orWhere('external_order_id', '#' . $cleanName)
                      ->orWhere('external_order_id', $cleanName);
                }
                if ($shopifyNumber !== '') {
                    $cleanNumber = ltrim($shopifyNumber, '#');
                    $q->orWhere('external_order_id', $shopifyNumber)
                      ->orWhere('external_order_id', '#' . $cleanNumber)
                      ->orWhere('external_order_id', $cleanNumber);
                }
            })
            ->first();
    }

    private function syncLocalOrderFromShopifyWebhook(string $shopDomain, array $payload, bool $upsertExisting = false): void
    {
        $seller = User::query()
            ->where('shopify_shop_domain', $shopDomain)
            ->first();

        if (! $seller) {
            return;
        }

        $order = $this->findLocalOrderFromShopifyPayload($seller->id, $payload);

        if ($order) {
            if (! $upsertExisting) {
                Log::info('Shopify order webhook already matched an existing local order.', [
                    'shop_domain' => $shopDomain,
                    'shopify_order_id' => $payload['id'] ?? null,
                    'external_order_id' => $order->external_order_id,
                ]);
            }

            $this->updateLocalOrderFromShopifyPayload($order, $payload, $seller->id);
            return;
        }

        $customer = $this->upsertCustomerFromShopifyOrderWebhook($seller->id, $payload);
        if (! $customer) {
            return;
        }

        $financialStatus = strtolower((string) ($payload['financial_status'] ?? 'pending'));
        $fulfillmentStatus = strtolower((string) ($payload['fulfillment_status'] ?? ''));
        $orderStatus = match ($fulfillmentStatus) {
            'fulfilled' => 'delivered',
            'partial', 'partially_fulfilled', 'partial_fulfilled' => 'shipped',
            default => match ($financialStatus) {
                'refunded', 'voided' => 'returned',
                'paid' => 'new',
                'partially_paid' => 'processing',
                'authorized', 'pending' => 'new',
                default => 'new',
            },
        };
        $paymentType = in_array($financialStatus, ['paid', 'partially_paid', 'authorized'], true) ? 'prepaid' : 'cod';
        $orderedAt = $this->parseShopifyWebhookTimestamp((string) ($payload['created_at'] ?? '')) ?? now();
        $lineItems = array_values(array_filter((array) ($payload['line_items'] ?? []), static fn ($item) => is_array($item)));
        if ($lineItems === []) {
            return;
        }

        $shopifyId = (string) ($payload['id'] ?? '');
        $shopifyName = trim((string) ($payload['name'] ?? ''));
        $shopifyNumber = trim((string) ($payload['order_number'] ?? ''));
        $externalOrderId = $shopifyName !== '' ? $shopifyName : ($shopifyNumber !== '' ? '#' . ltrim($shopifyNumber, '#') : $shopifyId);

        $order = Order::query()->create([
            'seller_id' => $seller->id,
            'customer_id' => $customer->id,
            'external_order_id' => $externalOrderId,
            'amount' => (float) ($payload['current_total_price'] ?? $payload['total_price'] ?? 0),
            'payment_type' => $paymentType,
            'status' => $orderStatus,
            'ordered_at' => $orderedAt,
        ]);

        foreach ($lineItems as $lineItem) {
            $product = $this->resolveShopifyOrderItemProduct($seller->id, $lineItem, $shopifyId ?: $externalOrderId);
            if (! $product) {
                continue;
            }

            $quantity = max(1, (int) ($lineItem['quantity'] ?? 1));
            $unitPrice = $this->extractFloatFromValue($lineItem['price'] ?? null) ?? 0.0;

            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $quantity,
            ]);
        }
    }

    private function updateLocalOrderFromShopifyPayload(Order $order, array $payload, int $sellerId): void
    {
        $customer = $this->upsertCustomerFromShopifyOrderWebhook($sellerId, $payload);
        if ($customer && $customer->id !== $order->customer_id) {
            $order->customer_id = $customer->id;
        }

        $financialStatus = strtolower((string) ($payload['financial_status'] ?? 'pending'));
        $fulfillmentStatus = strtolower((string) ($payload['fulfillment_status'] ?? ''));
        $currentStatus = strtolower((string) $order->status);
        $orderStatus = match ($fulfillmentStatus) {
            'fulfilled' => 'delivered',
            'partial', 'partially_fulfilled', 'partial_fulfilled' => 'shipped',
            default => match ($financialStatus) {
                'refunded', 'voided' => 'returned',
                'paid' => ($currentStatus === 'lead' ? 'new' : ($currentStatus ?: 'new')),
                'partially_paid' => 'processing',
                default => ($currentStatus === 'lead' ? 'new' : ($currentStatus ?: 'new')),
            },
        };

        $orderedAt = $this->parseShopifyWebhookTimestamp((string) ($payload['created_at'] ?? '')) ?? $order->ordered_at;
        $amount = (float) ($payload['current_total_price'] ?? $payload['total_price'] ?? $order->amount);

        $order->fill([
            'amount' => $amount,
            'payment_type' => in_array($financialStatus, ['paid', 'partially_paid', 'authorized'], true) ? 'prepaid' : 'cod',
            'status' => $orderStatus,
            'ordered_at' => $orderedAt,
        ])->save();

        $lineItems = array_values(array_filter((array) ($payload['line_items'] ?? []), static fn ($item) => is_array($item)));
        if ($lineItems === []) {
            return;
        }

        $existingItems = $order->items()->get()->keyBy('product_id');
        $seenProductIds = [];

        foreach ($lineItems as $lineItem) {
            $product = $this->resolveShopifyOrderItemProduct($sellerId, $lineItem, (string) ($payload['id'] ?? $order->external_order_id));
            if (! $product) {
                continue;
            }

            $quantity = max(1, (int) ($lineItem['quantity'] ?? 1));
            $unitPrice = $this->extractFloatFromValue($lineItem['price'] ?? null) ?? 0.0;
            $seenProductIds[] = $product->id;

            if ($existingItems->has($product->id)) {
                $existingItem = $existingItems->get($product->id);
                $existingItem->update([
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $unitPrice * $quantity,
                ]);
            } else {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $unitPrice * $quantity,
                ]);
            }
        }

        $order->items()
            ->whereNotIn('product_id', $seenProductIds)
            ->delete();
    }

    private function upsertCustomerFromShopifyOrderWebhook(int $sellerId, array $payload): ?Customer
    {
        $customerData = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
        $shippingAddress = is_array($payload['shipping_address'] ?? null) ? $payload['shipping_address'] : [];
        $billingAddress = is_array($payload['billing_address'] ?? null) ? $payload['billing_address'] : [];
        $defaultAddress = is_array($customerData['default_address'] ?? null) ? $customerData['default_address'] : [];

        $firstName = trim((string) (
            $shippingAddress['first_name'] ??
            $billingAddress['first_name'] ??
            $customerData['first_name'] ??
            $defaultAddress['first_name'] ??
            ''
        ));

        $lastName = trim((string) (
            $shippingAddress['last_name'] ??
            $billingAddress['last_name'] ??
            $customerData['last_name'] ??
            $defaultAddress['last_name'] ??
            ''
        ));

        $name = trim($firstName . ' ' . $lastName);

        if ($name === '') {
            $name = trim((string) (
                $customerData['name'] ??
                $shippingAddress['name'] ??
                $billingAddress['name'] ??
                $defaultAddress['name'] ??
                ''
            ));
        }

        $email = trim((string) (
            $payload['email'] ??
            $payload['contact_email'] ??
            $customerData['email'] ??
            ''
        ));

        if ($name === '') {
            $name = $email !== '' ? $email : 'Shopify Customer';
        }

        $phone = trim((string) (
            $shippingAddress['phone'] ??
            $billingAddress['phone'] ??
            $payload['phone'] ??
            $customerData['phone'] ??
            $defaultAddress['phone'] ??
            ''
        ));

        $addressSource = ! empty(array_filter($shippingAddress, static fn ($v) => $v !== null && $v !== ''))
            ? $shippingAddress
            : (! empty(array_filter($billingAddress, static fn ($v) => $v !== null && $v !== ''))
                ? $billingAddress
                : $defaultAddress);

        $addressParts = array_filter([
            trim((string) ($addressSource['address1'] ?? '')),
            trim((string) ($addressSource['address2'] ?? '')),
            trim((string) ($addressSource['city'] ?? '')),
            trim((string) ($addressSource['province'] ?? $addressSource['province_code'] ?? '')),
            trim((string) ($addressSource['country'] ?? $addressSource['country_name'] ?? $addressSource['country_code'] ?? '')),
            trim((string) ($addressSource['zip'] ?? '')),
        ]);
        $address = implode(', ', $addressParts);

        $customerQuery = Customer::query()->where('seller_id', $sellerId);
        $customer = null;

        if ($email !== '') {
            $customer = (clone $customerQuery)->where('email', $email)->first();
        }
        if (! $customer && $phone !== '') {
            $customer = (clone $customerQuery)->where('phone', $phone)->first();
        }
        if (! $customer && $name !== '' && $name !== 'Shopify Customer') {
            $customer = (clone $customerQuery)->where('name', $name)->first();
        }

        if ($customer) {
            $updateData = [];
            if ($name !== '' && ($customer->name === 'Shopify Customer' || empty($customer->name) || $name !== 'Shopify Customer')) {
                $updateData['name'] = $name;
            }
            if ($email !== '' && (empty($customer->email) || $customer->email !== $email)) {
                $updateData['email'] = $email;
            }
            if ($phone !== '' && (empty($customer->phone) || $customer->phone !== $phone)) {
                $updateData['phone'] = $phone;
            }
            if ($address !== '' && (empty($customer->address) || $customer->address !== $address)) {
                $updateData['address'] = $address;
            }

            if ($updateData !== []) {
                $customer->update($updateData);
            }

            return $customer;
        }

        return Customer::query()->create([
            'seller_id' => $sellerId,
            'name' => $name,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'address' => $address !== '' ? $address : null,
        ]);
    }

    private function resolveShopifyOrderItemProduct(int $sellerId, array $lineItem, string $shopifyOrderId): ?Product
    {
        $shopifyProductId = (int) ($lineItem['product_id'] ?? 0);
        $variantSku = trim((string) ($lineItem['sku'] ?? ''));
        $title = trim((string) ($lineItem['title'] ?? $lineItem['name'] ?? 'Shopify Item'));

        $product = null;

        if ($shopifyProductId > 0) {
            $product = Product::query()
                ->where('seller_id', $sellerId)
                ->where('shopify_product_id', $shopifyProductId)
                ->first();
        }

        if (! $product && $variantSku !== '') {
            $product = Product::query()
                ->where('seller_id', $sellerId)
                ->where('sku', $variantSku)
                ->first();
        }

        if ($product) {
            return $product;
        }

        $fallbackSku = trim((string) ($lineItem['sku'] ?? ''));
        if ($fallbackSku === '') {
            $fallbackSku = 'SHOPIFY-ORDER-' . $shopifyOrderId . '-' . (int) ($lineItem['id'] ?? random_int(1000, 9999));
        }

        return Product::query()->create([
            'seller_id' => $sellerId,
            'sku' => $fallbackSku,
            'name' => $title,
            'description' => null,
            'category' => null,
            'price' => $this->extractFloatFromValue($lineItem['price'] ?? null) ?? 0.0,
            'stock' => 0,
            'low_stock_alert' => 0,
            'status' => 'active',
            'shopify_product_id' => $shopifyProductId > 0 ? $shopifyProductId : null,
            'shopify_sync_status' => 'synced',
            'shopify_synced_at' => now(),
            'shopify_sync_error' => null,
        ]);
    }

    private function parseShopifyWebhookTimestamp(string $value): ?Carbon
    {
        try {
            return $value !== '' ? Carbon::parse($value) : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function extractFloatFromValue(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function extractIntFromValue(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
    
     private function storeShopifyTokenResponse(User $user, string $shopDomain, array $payload): void
    {
        $accessToken = trim((string) ($payload['access_token'] ?? ''));

        if ($accessToken === '') {
            return;
        }

        $scope = trim((string) ($payload['scope'] ?? ''));
        $expiresIn = isset($payload['expires_in']) && is_numeric($payload['expires_in']) ? (int) $payload['expires_in'] : null;
        $refreshToken = trim((string) ($payload['refresh_token'] ?? ''));
        $refreshTokenExpiresIn = isset($payload['refresh_token_expires_in']) && is_numeric($payload['refresh_token_expires_in']) ? (int) $payload['refresh_token_expires_in'] : null;

        User::query()
            ->where('shopify_shop_domain', $shopDomain)
            ->where('id', '!=', $user->id)
            ->update([
                'shopify_shop_domain' => null,
                'shopify_access_token' => null,
                'shopify_access_token_expires_at' => null,
                'shopify_refresh_token' => null,
                'shopify_refresh_token_expires_at' => null,
                'shopify_scope' => null,
                'shopify_connected_at' => null,
            ]);

        $user->update([
            'shopify_shop_domain' => $shopDomain,
            'shopify_access_token' => $accessToken,
            'shopify_access_token_expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
            'shopify_refresh_token' => $refreshToken !== '' ? $refreshToken : null,
            'shopify_refresh_token_expires_at' => $refreshTokenExpiresIn ? now()->addSeconds($refreshTokenExpiresIn) : null,
            'shopify_scope' => $scope !== '' ? $scope : null,
            'shopify_connected_at' => now(),
        ]);
    }

    private function shopifyAccessTokenForUser(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $accessToken = trim((string) $user->shopify_access_token);
        if ($accessToken === '') {
            return null;
        }

        $expiresAt = $user->shopify_access_token_expires_at;
        $refreshToken = trim((string) $user->shopify_refresh_token);

        if ($expiresAt instanceof Carbon && $expiresAt->isPast()) {
            $refreshedToken = $this->refreshShopifyAccessToken($user);
            if ($refreshedToken !== null) {
                return $refreshedToken;
            }
        }

        if ($refreshToken === '' && $expiresAt === null) {
            $migratedToken = $this->migrateShopifyAccessToken($user);
            if ($migratedToken !== null) {
                return $migratedToken;
            }
        }

        return $accessToken;
    }

    private function refreshShopifyAccessToken(User $user): ?string
    {
        $shopDomain = trim((string) $user->shopify_shop_domain);
        $refreshToken = trim((string) $user->shopify_refresh_token);

        if ($shopDomain === '' || $refreshToken === '') {
            return null;
        }

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post("https://{$shopDomain}/admin/oauth/access_token", [
                    'client_id' => (string) config('services.shopify.client_id'),
                    'client_secret' => (string) config('services.shopify.client_secret'),
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $payload = (array) $response->json();
            $accessToken = trim((string) ($payload['access_token'] ?? ''));
            if ($accessToken === '') {
                return null;
            }

            $this->storeShopifyTokenResponse($user, $shopDomain, $payload);

            return $accessToken;
        } catch (\Throwable $exception) {
            Log::warning('Shopify access token refresh failed.', [
                'shop_domain' => $shopDomain,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function migrateShopifyAccessToken(User $user): ?string
    {
        $shopDomain = trim((string) $user->shopify_shop_domain);
        $subjectToken = trim((string) $user->shopify_access_token);

        if ($shopDomain === '' || $subjectToken === '') {
            return null;
        }

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post("https://{$shopDomain}/admin/oauth/access_token", [
                    'client_id' => (string) config('services.shopify.client_id'),
                    'client_secret' => (string) config('services.shopify.client_secret'),
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:token-exchange',
                    'subject_token' => $subjectToken,
                    'subject_token_type' => 'urn:shopify:params:oauth:token-type:offline-access-token',
                    'requested_token_type' => 'urn:shopify:params:oauth:token-type:offline-access-token',
                    'expiring' => 1,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $payload = (array) $response->json();
            $accessToken = trim((string) ($payload['access_token'] ?? ''));
            if ($accessToken === '') {
                return null;
            }

            $this->storeShopifyTokenResponse($user, $shopDomain, $payload);

            return $accessToken;
        } catch (\Throwable $exception) {
            Log::warning('Shopify token migration failed.', [
                'shop_domain' => $shopDomain,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
    private function extractShopifyImageUrl(array $payload): ?string
    {
        $image = $payload['image']['src'] ?? null;

        if (is_string($image)) {
            $image = trim($image);
            if ($image !== '') {
                return $image;
            }
        }

        $images = $payload['images'] ?? null;
        if (is_array($images)) {
            foreach ($images as $shopifyImage) {
                if (! is_array($shopifyImage)) {
                    continue;
                }

                $src = trim((string) ($shopifyImage['src'] ?? ''));
                if ($src !== '') {
                    return $src;
                }
            }
        }

        return null;
    }
    
    
    

    private function syncProductWithShopify(Product $product, Request $request): array
    {
        $user = Auth::user() ?? User::query()->find($product->seller_id);
        $connection = $this->shopifyConnectionData($user);
        $accessToken = $this->shopifyAccessTokenForUser($user);

        if (! $connection['connected']) {
            $product->forceFill([
                'shopify_sync_status' => 'failed',
                'shopify_sync_error' => 'Shopify is not connected for this seller.',
            ])->save();

            return [
                'ok' => false,
                'message' => 'Shopify is not connected for this seller.',
            ];
        }

        if (! ($connection['has_product_write_scope'] ?? false)) {
            $missingMessage = 'Shopify app is connected, but the installed token does not include write_products permission. Reconnect the app after enabling that scope in Shopify app settings.';
            $product->forceFill([
                'shopify_sync_status' => 'failed',
                'shopify_sync_error' => $missingMessage,
            ])->save();

            return [
                'ok' => false,
                'message' => $missingMessage,
            ];
        }

        try {
            $payload = [
                'product' => [
                    'title' => $product->name,
                    'body_html' => $product->description ? nl2br(e($product->description)) : null,
                    'vendor' => config('app.name', 'Spedfly'),
                    'product_type' => $product->category ?: null,
                    'status' => $product->status === 'active' ? 'active' : 'draft',
                    'tags' => implode(', ', array_filter([
                        $product->category,
                        $user?->name,
                    ])),
                    'variants' => [[
                        'sku' => $product->sku,
                        'price' => number_format((float) $product->price, 2, '.', ''),
                        'inventory_management' => 'shopify',
                        'inventory_policy' => 'deny',
                    ]],
                ],
            ];

            $imageSrc = $this->buildPublicAssetUrl($request, $product->image_path);
            if ($imageSrc) {
                $payload['product']['images'] = [[
                    'src' => $imageSrc,
                ]];
            }

            $response = $this->sendShopifyProductRequest(
                $connection['shop_domain'],
                $accessToken,
                $product->shopify_product_id ? 'put' : 'post',
                $product->shopify_product_id,
                $payload
            );

            if (! $response->successful()) {
                $responseBody = trim((string) $response->body());
                $responseSnippet = $responseBody !== '' ? ' Response: ' . Str::limit($responseBody, 250) : '';
                $message = 'Shopify API request failed with status ' . $response->status() . '.' . $responseSnippet;
                $product->forceFill([
                    'shopify_sync_status' => 'failed',
                    'shopify_sync_error' => $message,
                ])->save();

                return [
                    'ok' => false,
                    'message' => $message,
                ];
            }

            $productData = (array) ($response->json('product') ?? []);
            $shopifyProductId = (int) ($productData['id'] ?? $product->shopify_product_id ?? 0);
            $inventoryItemId = $this->extractShopifyInventoryItemId($productData);
            $inventorySyncResult = $this->syncShopifyInventoryLevel(
                $connection['shop_domain'],
                $accessToken,
                $inventoryItemId,
                (int) $product->stock
            );

            $product->forceFill([
                'shopify_product_id' => $shopifyProductId > 0 ? $shopifyProductId : null,
                'shopify_sync_status' => 'synced',
                'shopify_synced_at' => now(),
                'shopify_sync_error' => $inventorySyncResult['ok'] ? null : $inventorySyncResult['message'],
            ])->save();

            return [
                'ok' => true,
                'message' => $product->shopify_product_id
                    ? 'Shopify product #' . $product->shopify_product_id . ' synced. ' . $inventorySyncResult['message']
                    : 'Shopify product synced. ' . $inventorySyncResult['message'],
            ];
        } catch (\Throwable $exception) {
            $product->forceFill([
                'shopify_sync_status' => 'failed',
                'shopify_sync_error' => $exception->getMessage(),
            ])->save();

            return [
                'ok' => false,
                'message' => 'Shopify sync error: ' . $exception->getMessage(),
            ];
        }
    }

    private function syncExistingProductsToShopify(User $user, Request $request): string
    {
        $products = Product::query()
            ->where('seller_id', $user->id)
            ->orderBy('id')
            ->get();

        if ($products->isEmpty()) {
            return 'No existing products were found to sync.';
        }

        $synced = 0;
        $failed = 0;

        foreach ($products as $product) {
            $result = $this->syncProductWithShopify($product, $request);

            if ($result['ok']) {
                $synced++;
            } else {
                $failed++;
            }
        }

        return $synced . ' product(s) synced, ' . $failed . ' failed.';
    }

    private function deleteShopifyProduct(Product $product): void
    {
        $user = Auth::user();
        $connection = $this->shopifyConnectionData($user);
        $accessToken = $this->shopifyAccessTokenForUser($user);

        if (! $connection['connected'] || ! $product->shopify_product_id || $accessToken === '') {
            return;
        }

        try {
            $this->sendShopifyProductRequest(
                $connection['shop_domain'],
                $accessToken,
                'delete',
                $product->shopify_product_id,
                null
            );
        } catch (\Throwable $exception) {
            // Best effort only.
        }
    }

    private function sendShopifyProductRequest(
        string $shopDomain,
        string $accessToken,
        string $method,
        ?int $shopifyProductId,
        ?array $payload
    ) {
        $apiVersion = (string) config('services.shopify.api_version', '2026-04');
        $url = "https://{$shopDomain}/admin/api/{$apiVersion}/products";

        if ($shopifyProductId) {
            $url .= '/' . $shopifyProductId;
        }

        $request = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Accept' => 'application/json',
        ])->timeout(20);

        return match (strtolower($method)) {
            'put' => $request->asJson()->put($url . '.json', $payload),
            'delete' => $request->delete($url . '.json'),
            default => $request->asJson()->post($url . '.json', $payload),
        };
    }

    private function extractShopifyInventoryItemId(array $productData): ?int
    {
        $variant = $productData['variants'][0] ?? null;
        if (! is_array($variant)) {
            return null;
        }

        $inventoryItemId = $variant['inventory_item_id'] ?? null;
        if ($inventoryItemId === null || ! is_numeric($inventoryItemId)) {
            return null;
        }

        return (int) $inventoryItemId;
    }

    private function syncShopifyInventoryLevel(
        string $shopDomain,
        string $accessToken,
        ?int $inventoryItemId,
        int $available
    ): array {
        if (! $inventoryItemId) {
            return [
                'ok' => false,
                'message' => 'Inventory sync skipped because Shopify did not return an inventory item ID.',
            ];
        }

        $locationId = $this->fetchShopifyPrimaryLocationId($shopDomain, $accessToken);
        if (! $locationId) {
            return [
                'ok' => false,
                'message' => 'Inventory sync skipped because Shopify location could not be determined.',
            ];
        }

        $apiVersion = (string) config('services.shopify.api_version', '2026-04');

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Accept' => 'application/json',
            ])->asJson()->timeout(20)->post(
                "https://{$shopDomain}/admin/api/{$apiVersion}/inventory_levels/set.json",
                [
                    'location_id' => $locationId,
                    'inventory_item_id' => $inventoryItemId,
                    'available' => max(0, $available),
                    'disconnect_if_necessary' => true,
                ]
            );

            if (! $response->successful()) {
                $responseBody = trim((string) $response->body());
                $responseSnippet = $responseBody !== '' ? ' Response: ' . Str::limit($responseBody, 250) : '';

                return [
                    'ok' => false,
                    'message' => 'Inventory update failed with status ' . $response->status() . '.' . $responseSnippet,
                ];
            }

            return [
                'ok' => true,
                'message' => 'Inventory updated to ' . max(0, $available) . '.',
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'message' => 'Inventory update error: ' . $exception->getMessage(),
            ];
        }
    }

    private function fetchShopifyPrimaryLocationId(string $shopDomain, string $accessToken): ?int
    {
        $apiVersion = (string) config('services.shopify.api_version', '2026-04');

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Accept' => 'application/json',
            ])->timeout(15)->get("https://{$shopDomain}/admin/api/{$apiVersion}/shop.json");

            if (! $response->successful()) {
                return null;
            }

            $shop = (array) ($response->json('shop') ?? []);
            $locationId = $shop['primary_location_id'] ?? null;

            if ($locationId === null || ! is_numeric($locationId)) {
                return null;
            }

            return (int) $locationId;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function syncCsvOrderToShopify(
        Order $order,
        ?Request $request = null
    ): array {
        $user = Auth::user() ?? User::query()->find($order->seller_id);
        $connection = $this->shopifyConnectionData($user);
        $accessToken = $this->shopifyAccessTokenForUser($user);

        if (! $connection['connected']) {
            return [
                'ok' => false,
                'message' => 'Shopify is not connected for this seller.',
            ];
        }

        if (! ($connection['has_order_write_scope'] ?? false)) {
            return [
                'ok' => false,
                'message' => 'Shopify app is connected, but the installed token does not include write_orders permission.',
            ];
        }

        $order->loadMissing(['customer', 'items.product']);
        $customer = $order->customer;

        $customerName = trim((string) ($customer?->name ?: ''));
        $customerParts = preg_split('/\s+/', $customerName, 2) ?: [];
        $firstName = $customerParts[0] ?? ($customerName !== '' ? $customerName : 'Customer');
        $lastName = $customerParts[1] ?? '';

        $customerEmail = trim((string) ($customer?->email ?: ''));
        $rawPhone = trim((string) ($customer?->phone ?: ''));

        // Sanitize phone to valid E.164 (+ followed by 10-15 digits), otherwise omit to avoid Shopify 422 error
        $validPhone = null;
        if ($rawPhone !== '') {
            $digitsOnly = preg_replace('/[^\d]/', '', $rawPhone);
            if (str_starts_with($rawPhone, '+') && strlen($digitsOnly) >= 10 && strlen($digitsOnly) <= 15) {
                $validPhone = '+' . $digitsOnly;
            } elseif (! str_starts_with($rawPhone, '+') && strlen($digitsOnly) === 10) {
                $validPhone = '+1' . $digitsOnly; // Common US 10-digit fallback if country is US or standard
            }
        }

        $notes = ['Imported from Spedfly. Local Ref: ' . $order->external_order_id];
        if ($rawPhone !== '') {
            $notes[] = 'Customer Phone: ' . $rawPhone;
        }

        $addressStr = trim((string) ($customer?->address ?: ''));
        $shippingAddress = null;
        if ($addressStr !== '' && $addressStr !== 'Address not provided') {
            $addressParts = array_map('trim', explode(',', $addressStr));
            $address1 = $addressParts[0] ?? $addressStr;
            $city = $addressParts[1] ?? null;
            $province = $addressParts[2] ?? null;
            $zip = $addressParts[3] ?? null;
            $country = $addressParts[4] ?? 'US';

            $shippingAddress = array_filter([
                'first_name' => $firstName !== '' ? $firstName : null,
                'last_name' => $lastName !== '' ? $lastName : null,
                'address1' => $address1,
                'city' => $city,
                'province' => $province,
                'zip' => $zip,
                'country' => $country,
            ], static fn ($value) => filled($value));
        }

        $lineItems = [];
        if ($order->items && $order->items->isNotEmpty()) {
            foreach ($order->items as $item) {
                $product = $item->product;
                $title = trim((string) ($product?->name ?: 'Item'));
                $sku = trim((string) ($product?->sku ?: ''));
                $unitPrice = (float) ($item->unit_price > 0 ? $item->unit_price : ($item->total_price > 0 && $item->quantity > 0 ? $item->total_price / $item->quantity : ($product?->price ?: 10.0)));
                $qty = (int) ($item->quantity > 0 ? $item->quantity : 1);

                $lineItemPayload = [
                    'title' => $title,
                    'price' => number_format($unitPrice, 2, '.', ''),
                    'quantity' => $qty,
                    'requires_shipping' => true,
                ];

                if ($sku !== '') {
                    $lineItemPayload['sku'] = $sku;
                }

                $lineItems[] = $lineItemPayload;
            }
        }

        if (empty($lineItems)) {
            $lineItems[] = [
                'title' => 'General Order Item',
                'price' => number_format((float) ($order->amount > 0 ? $order->amount : 10.0), 2, '.', ''),
                'quantity' => 1,
                'requires_shipping' => true,
            ];
        }

        $customerData = array_filter([
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'email' => $customerEmail !== '' ? $customerEmail : null,
        ], static fn ($value) => filled($value));

        $isPrepaid = in_array(strtolower((string) $order->payment_type), ['prepaid', 'paid'], true);
        $financialStatus = $isPrepaid ? 'paid' : 'pending';

        $payload = [
            'order' => array_filter([
                'line_items' => $lineItems,
                'customer' => ! empty($customerData) ? $customerData : null,
                'email' => $customerEmail !== '' ? $customerEmail : null,
                'shipping_address' => $shippingAddress,
                'billing_address' => $shippingAddress,
                'financial_status' => $financialStatus,
                'inventory_behaviour' => 'bypass',
                'send_receipt' => false,
                'send_fulfillment_receipt' => false,
                'tags' => 'Spedfly CSV Import, Seller #' . (int) $order->seller_id,
                'note' => implode(' | ', $notes),
                'note_attributes' => $rawPhone !== '' ? [['name' => 'Phone', 'value' => $rawPhone]] : null,
            ], static fn ($value) => $value !== null),
        ];

        $apiUrl = 'https://' . $connection['shop_domain'] . '/admin/api/' . config('services.shopify.api_version', '2026-04') . '/orders.json';

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Accept' => 'application/json',
        ])->asJson()->timeout(20)->post($apiUrl, $payload);


        if (! $response->successful()) {
            $responseBody = trim((string) $response->body());
            $responseSnippet = $responseBody !== '' ? ' Response: ' . Str::limit($responseBody, 250) : '';

            Log::warning('Shopify order sync failed.', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $responseBody,
            ]);

            return [
                'ok' => false,
                'message' => 'Shopify order sync failed with status ' . $response->status() . '.' . $responseSnippet,
            ];
        }

        $orderData = (array) ($response->json('order') ?? []);
        $shopifyOrderName = trim((string) ($orderData['name'] ?? ''));
        if ($shopifyOrderName !== '' && (str_starts_with((string) $order->external_order_id, 'ORD-') || empty($order->external_order_id))) {
            $order->update([
                'external_order_id' => $shopifyOrderName,
            ]);
        }

        return [
            'ok' => true,
            'message' => 'Shopify order synced successfully as ' . ($shopifyOrderName ?: '#' . $order->id) . '.',
            'shopify_order_name' => $shopifyOrderName,
        ];
    }


    private function buildPublicAssetUrl(Request $request, ?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $publicUrl = Storage::disk('public')->url($path);
        if (preg_match('#^https?://#i', $publicUrl)) {
            return $publicUrl;
        }

        return rtrim(config('app.url') ?: $request->getSchemeAndHttpHost(), '/') . '/' . ltrim($publicUrl, '/');
    }

    public function orders(Request $request)
    {
        $user = Auth::user();
        if ($user && $user->shopify_shop_domain && $user->shopify_access_token) {
            $accessToken = $this->shopifyAccessTokenForUser($user);
            if ($accessToken) {
                // 1. Push any pending local orders (e.g. created via CSV) to Shopify
                $pendingLocalOrders = Order::query()
                    ->where('seller_id', $user->id)
                    ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead'])
                    ->where('external_order_id', 'like', 'ORD-%')
                    ->with(['customer', 'items.product'])
                    ->limit(10)
                    ->get();

                foreach ($pendingLocalOrders as $pOrder) {
                    $this->syncCsvOrderToShopify($pOrder, $request);
                }

                // 2. Pull latest orders from Shopify
                $this->pullShopifyRecentOrders($user, $user->shopify_shop_domain, $accessToken);
            }
        }

        $ordersQuery = Order::query()
            ->with('customer')
            ->where('seller_id', (int) Auth::id())
            ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead']);

        $orders = (clone $ordersQuery)
            ->latest('ordered_at')
            ->latest('id')
            ->get();
        $ordersFeedSignature = $this->buildOrdersFeedSignature($ordersQuery);

        return view('spedfly.seller.orders', compact('orders', 'ordersFeedSignature'));
    }

    public function syncOrdersFromShopify(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('seller.index');
        }

        if (! $user->shopify_shop_domain || ! $user->shopify_access_token) {
            return redirect()
                ->route('seller.orders')
                ->with('warning', 'Shopify store is not connected. Please connect your store in Settings.');
        }

        $accessToken = $this->shopifyAccessTokenForUser($user);
        if (! $accessToken) {
            return redirect()
                ->route('seller.orders')
                ->with('error', 'Unable to retrieve valid Shopify access token.');
        }

        // 1. Push any pending local un-synced orders to Shopify
        $pushedCount = 0;
        $unSyncedOrders = Order::query()
            ->where('seller_id', $user->id)
            ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead'])
            ->where('external_order_id', 'like', 'ORD-%')
            ->with(['customer', 'items.product'])
            ->limit(30)
            ->get();

        foreach ($unSyncedOrders as $unSyncedOrder) {
            $pushRes = $this->syncCsvOrderToShopify($unSyncedOrder, $request);
            if ($pushRes['ok']) {
                $pushedCount++;
            }
        }

        // 2. Pull all recent orders from Shopify
        $pulledCount = $this->pullShopifyRecentOrders($user, $user->shopify_shop_domain, $accessToken);

        $msg = "Shopify orders synced successfully. ({$pulledCount} orders pulled/updated";
        if ($pushedCount > 0) {
            $msg .= ", {$pushedCount} local orders created in Shopify";
        }
        $msg .= ')';

        return redirect()
            ->route('seller.orders')
            ->with('success', $msg);
    }


    public function returns(Request $request)
    {
        $sellerId = (int) Auth::id();
        $search = trim((string) $request->input('q', ''));
        $fromDate = $request->input('from');
        $toDate = $request->input('to');

        $returnsQuery = OrderReturn::query()
            ->with(['order.customer', 'customer'])
            ->where('seller_id', $sellerId);

        if ($search !== '') {
            $returnsQuery->where(function ($query) use ($search) {
                $query
                    ->where('return_code', 'like', '%' . $search . '%')
                    ->orWhere('return_reason', 'like', '%' . $search . '%')
                    ->orWhere('notes', 'like', '%' . $search . '%')
                    ->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('external_order_id', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery
                            ->where('name', 'like', '%' . $search . '%')
                            ->orWhere('phone', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($fromDate) {
            $returnsQuery->whereDate('returned_at', '>=', $fromDate);
        }

        if ($toDate) {
            $returnsQuery->whereDate('returned_at', '<=', $toDate);
        }

        $returns = $returnsQuery
            ->orderByDesc('returned_at')
            ->orderByDesc('id')
            ->get();

        $stats = [
            'total' => OrderReturn::query()
                ->where('seller_id', $sellerId)
                ->count(),
            'today' => OrderReturn::query()
                ->where('seller_id', $sellerId)
                ->whereDate('returned_at', now()->toDateString())
                ->count(),
            'this_week' => OrderReturn::query()
                ->where('seller_id', $sellerId)
                ->whereBetween('returned_at', [now()->copy()->startOfWeek(), now()->copy()->endOfWeek()])
                ->count(),
            'refund_amount' => (float) (clone $returnsQuery)->sum('refund_amount'),
        ];

        return view('spedfly.seller.returns', compact('returns', 'stats', 'search', 'fromDate', 'toDate'));
    }

    public function ordersFeed(): JsonResponse
    {
        $sellerId = (int) Auth::id();

        $orders = Order::query()
            ->where('seller_id', $sellerId)
            ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead'])
            ->get(['id', 'updated_at']);

        $latestUpdatedAt = $orders->max(fn (Order $order) => optional($order->updated_at)?->timestamp ?? 0) ?? 0;

        return response()->json([
            'signature' => $orders->count() . '|' . $latestUpdatedAt,
        ]);
    }

    private function buildOrdersFeedSignature($ordersQuery): string
    {
        $orders = (clone $ordersQuery)->get(['id', 'updated_at']);
        $latestUpdatedAt = $orders->max(fn (Order $order) => optional($order->updated_at)?->timestamp ?? 0) ?? 0;

        return $orders->count() . '|' . $latestUpdatedAt;
    }

    public function leads()
    {
        $sellerId = (int) Auth::id();

        $leads = Order::query()
            ->with(['customer'])
            ->where('seller_id', $sellerId)
            ->whereRaw('LOWER(COALESCE(status, "")) = ?', ['lead'])
           // ->whereRaw('LOWER(COALESCE(payment_type, "")) = ?', ['cod'])
            ->whereRaw('LOWER(COALESCE(payment_type, "")) IN (?, ?)', ['cod', 'prepaid'])
            ->latest('ordered_at')
            ->latest('id')
            ->get();

        $stats = [
            'total' => $leads->count(),
            'today' => $leads->filter(fn (Order $order) => optional($order->ordered_at ?? $order->created_at)?->isToday())->count(),
            'called' => $leads->filter(fn (Order $order) => $order->callLogs()->exists())->count(),
            'unreached' => $leads->filter(function (Order $order) {
                $latestLog = $order->callLogs()->latest('call_time')->latest('id')->first();

                return $latestLog && in_array($latestLog->result, ['no_answer', 'failed', 'busy'], true);
            })->count(),
        ];

        return view('spedfly.seller.leads', compact('leads', 'stats'));
    }

    public function callCenter(Request $request)
    {
        $sellerId = (int) Auth::id();
        $resultFilter = strtolower(trim((string) $request->input('result', 'all')));
        $search = trim((string) $request->input('search', ''));
        $dateFilter = trim((string) $request->input('date', ''));

        $resultOptions = [
            'connected' => 'Connected',
            'no_answer' => 'No Answer',
            'failed' => 'Failed',
            'busy' => 'Busy',
        ];

        if (! in_array($resultFilter, array_merge(['all'], array_keys($resultOptions)), true)) {
            $resultFilter = 'all';
        }

        $logsQuery = CallCenterLog::query()
            ->with(['customer', 'order'])
            ->where('seller_id', $sellerId);

        if ($resultFilter !== 'all') {
            $logsQuery->where('result', $resultFilter);
        }

        if ($dateFilter !== '') {
            $logsQuery->whereDate('call_time', $dateFilter);
        }

        if ($search !== '') {
            $logsQuery->where(function ($query) use ($search) {
                $query
                    ->where('call_code', 'like', '%' . $search . '%')
                    ->orWhere('agent_name', 'like', '%' . $search . '%')
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery
                            ->where('name', 'like', '%' . $search . '%')
                            ->orWhere('phone', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('external_order_id', 'like', '%' . $search . '%');
                    });
            });
        }

        $callLogs = $logsQuery
            ->orderByDesc('call_time')
            ->orderByDesc('id')
            ->get();

        $attemptTracker = [];
        $attemptNumbers = CallCenterLog::query()
            ->where('seller_id', $sellerId)
            ->orderBy('call_time')
            ->orderBy('id')
            ->get(['id', 'customer_id', 'order_id'])
            ->mapWithKeys(function (CallCenterLog $log) use (&$attemptTracker) {
                $attemptKey = $log->customer_id . ':' . ($log->order_id ?? 'none');
                $attemptTracker[$attemptKey] = ($attemptTracker[$attemptKey] ?? 0) + 1;

                return [$log->id => $attemptTracker[$attemptKey]];
            });

        $callLogs->transform(function (CallCenterLog $log) use ($attemptNumbers) {
            $log->attempt_number = $attemptNumbers[$log->id] ?? 1;
            $log->can_confirm_lead = $log->order
                && strtolower((string) $log->order->status) === 'lead'
                && strtolower((string) $log->order->payment_type) === 'cod';

            return $log;
        });

        $todayStart = now()->startOfDay();
        $yesterdayStart = now()->copy()->subDay()->startOfDay();

        $todayLogs = CallCenterLog::query()
            ->where('seller_id', $sellerId)
            ->where('call_time', '>=', $todayStart)
            ->get();

        $yesterdayCount = CallCenterLog::query()
            ->where('seller_id', $sellerId)
            ->whereBetween('call_time', [$yesterdayStart, $todayStart])
            ->count();

        $totalCallsToday = $todayLogs->count();
        $connectedCallsToday = $todayLogs->where('result', 'connected')->count();
        $connectedPercentage = $totalCallsToday > 0
            ? (int) round(($connectedCallsToday / $totalCallsToday) * 100)
            : 0;
        $missedCallsToday = $todayLogs->whereIn('result', ['no_answer', 'failed', 'busy'])->count();
        $averageDurationSeconds = (int) round((float) ($todayLogs->avg('duration_seconds') ?? 0));
        $vsYesterday = $yesterdayCount > 0
            ? (int) round((($totalCallsToday - $yesterdayCount) / $yesterdayCount) * 100)
            : ($totalCallsToday > 0 ? 100 : 0);

        $stats = [
            'total_calls_today' => $totalCallsToday,
            'connected_percentage' => $connectedPercentage,
            'missed_calls_today' => $missedCallsToday,
            'average_duration' => $this->formatCallDuration($averageDurationSeconds),
            'vs_yesterday' => $vsYesterday,
        ];

        $allLogs = CallCenterLog::query()
            ->where('seller_id', $sellerId)
            ->orderByDesc('call_time')
            ->get();

        $agentPerformance = $allLogs
            ->groupBy('agent_name')
            ->map(function ($logs, $agentName) {
                $total = $logs->count();
                $connected = $logs->where('result', 'connected')->count();

                return [
                    'name' => $agentName,
                    'rate' => $total > 0 ? (int) round(($connected / $total) * 100) : 0,
                    'total' => $total,
                ];
            })
            ->sortByDesc('total')
            ->take(5)
            ->values();

        return view('spedfly.seller.call-center', compact(
            'agentPerformance',
            'callLogs',
            'dateFilter',
            'resultFilter',
            'resultOptions',
            'search',
            'stats'
        ));
    }

    public function confirmLead(Order $order): RedirectResponse
    {
        abort_unless($order->seller_id === (int) Auth::id(), 404);
        abort_unless(
            strtolower((string) $order->status) === 'lead'
            && strtolower((string) $order->payment_type) === 'cod',
            404
        );

        $this->ensureOrderStatusTransitionAllowed($order, 'new');

        $order->update([
            'status' => 'new',
        ]);

        $this->notifyAdminsAboutOrderStatusChange($order->fresh(['seller']), 'new');

        return back()->with('success', 'Lead confirmed and moved to orders successfully.');
    }

    public function shipments(Request $request)
    {
        $sellerId = (int) Auth::id();

        $statusLabels = [
            'pending' => 'Pending',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'delayed' => 'Delayed',
            'returned' => 'Returned',
        ];

        $tab = $request->input('tab', 'all');
        $search = $request->input('search');
        $statusFilter = $request->input('status');
        $dateFilter = $request->input('date');

        $effectiveStatus = $statusFilter ?: ($tab === 'all' ? null : $tab);

        $shipmentsQuery = Shipment::query()
            ->where('seller_id', $sellerId);

        if ($effectiveStatus && isset($statusLabels[$effectiveStatus])) {
            $shipmentsQuery->where('status', $effectiveStatus);
        }

        if ($search) {
            $shipmentsQuery->where(function ($query) use ($search) {
                $query->where('shipment_code', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%')
                    ->orWhereHas('order', fn ($qb) => $qb->where('external_order_id', 'like', '%' . $search . '%'));
            });
        }

        if ($dateFilter) {
            $shipmentsQuery->whereDate('shipment_date', $dateFilter);
        }

        $shipments = $shipmentsQuery
            ->with(['order'])
            ->orderByDesc('shipment_date')
            ->orderByDesc('id')
            ->get();

        $stats = [
            'in_transit' => Shipment::query()
                ->where('seller_id', $sellerId)
                ->where('status', 'in_transit')
                ->count(),
            'delivered' => Shipment::query()
                ->where('seller_id', $sellerId)
                ->where('status', 'delivered')
                ->count(),
            'delayed' => Shipment::query()
                ->where('seller_id', $sellerId)
                ->where('status', 'delayed')
                ->count(),
            'returned' => Shipment::query()
                ->where('seller_id', $sellerId)
                ->where('status', 'returned')
                ->count(),
        ];

        $totalShipments = Shipment::query()
            ->where('seller_id', $sellerId)
            ->count();

        return view('spedfly.seller.shipments', compact(
            'shipments',
            'stats',
            'totalShipments',
            'statusLabels',
            'tab',
            'search',
            'statusFilter',
            'dateFilter'
        ));
    }

    public function shipmentsFeed(Request $request): JsonResponse
    {
        $sellerId = (int) Auth::id();
        $tab = $request->input('tab', 'all');
        $search = $request->input('search');
        $statusFilter = $request->input('status');
        $dateFilter = $request->input('date');

        $statusLabels = [
            'pending' => 'Pending',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'delayed' => 'Delayed',
            'returned' => 'Returned',
        ];

        $effectiveStatus = $statusFilter ?: ($tab === 'all' ? null : $tab);
        $shipmentsQuery = Shipment::query()
            ->where('seller_id', $sellerId)
            ->with(['order']);

        if ($effectiveStatus && isset($statusLabels[$effectiveStatus])) {
            $shipmentsQuery->where('status', $effectiveStatus);
        }

        if ($search) {
            $shipmentsQuery->where(function ($query) use ($search) {
                $query->where('shipment_code', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%')
                    ->orWhereHas('order', fn ($qb) => $qb->where('external_order_id', 'like', '%' . $search . '%'));
            });
        }

        if ($dateFilter) {
            $shipmentsQuery->whereDate('shipment_date', $dateFilter);
        }

        $shipments = $shipmentsQuery
            ->orderByDesc('shipment_date')
            ->orderByDesc('id')
            ->get();

        $signature = $shipments->map(fn (Shipment $shipment) => $shipment->id . '|' . optional($shipment->updated_at)->timestamp)->implode(';');

        $statusBadgeClasses = [
            'pending' => 'bg-secondary text-white',
            'in_transit' => 'bg-primary text-white',
            'delivered' => 'bg-success text-white',
            'delayed' => 'bg-danger text-white',
            'returned' => 'bg-dark text-white',
        ];

        return response()->json([
            'signature' => $signature,
            'html' => view('spedfly.seller.partials.shipments-table-rows', [
                'shipments' => $shipments,
                'statusLabels' => $statusLabels,
                'statusBadgeClasses' => $statusBadgeClasses,
            ])->render(),
            'stats' => [
                'in_transit' => Shipment::query()->where('seller_id', $sellerId)->where('status', 'in_transit')->count(),
                'delivered' => Shipment::query()->where('seller_id', $sellerId)->where('status', 'delivered')->count(),
                'delayed' => Shipment::query()->where('seller_id', $sellerId)->where('status', 'delayed')->count(),
                'returned' => Shipment::query()->where('seller_id', $sellerId)->where('status', 'returned')->count(),
            ],
            'totalShipments' => Shipment::query()->where('seller_id', $sellerId)->count(),
        ]);
    }

    private function callCenterEligibleOrderStatuses(): array
    {
        return ['lead', 'new', 'processing', 'shipped'];
    }

    public function shipmentLabel(Shipment $shipment)
    {
        $shipment = $this->findSellerShipmentOrFail($shipment)->loadMissing(['order.customer', 'seller']);

        return view('spedfly.shipments.label', [
            'shipment' => $shipment,
            'companyName' => config('app.name', 'Spedfly'),
            'printedAt' => now(),
        ]);
    }

    public function analytics(Request $request)
    {
        $sellerId = (int) Auth::id();
        $selectedRange = strtolower((string) $request->input('range', 'last_7_days'));
        $rangeOptions = [
            'last_7_days' => 'Last 7 Days',
            'last_30_days' => 'Last 30 Days',
            'this_month' => 'This Month',
            'this_year' => 'This Year',
        ];

        if (! array_key_exists($selectedRange, $rangeOptions)) {
            $selectedRange = 'last_7_days';
        }

        $today = now();
        $rangeEnd = $today->copy()->endOfDay();

        [$rangeStart, $groupByMonth] = match ($selectedRange) {
            'last_30_days' => [$today->copy()->subDays(29)->startOfDay(), false],
            'this_month' => [$today->copy()->startOfMonth(), false],
            'this_year' => [$today->copy()->startOfYear(), true],
            default => [$today->copy()->subDays(6)->startOfDay(), false],
        };

        $ordersInRange = Order::query()
            ->where('seller_id', $sellerId)
            ->whereBetween('ordered_at', [$rangeStart, $rangeEnd])
            ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead'])
            ->get(['id', 'amount', 'payment_type', 'status', 'ordered_at']);

        $shipmentsInRange = Shipment::query()
            ->where('seller_id', $sellerId)
            ->whereBetween('shipment_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->get(['id', 'order_id', 'courier_name', 'status', 'shipment_date']);

        $callLogsInRange = CallCenterLog::query()
            ->where('seller_id', $sellerId)
            ->whereBetween('call_time', [$rangeStart, $rangeEnd])
            ->get(['id', 'agent_name', 'result', 'call_time']);

        $totalOrders = $ordersInRange->count();
        $totalRevenue = (float) $ordersInRange->sum('amount');
        $confirmedOrders = $ordersInRange->filter(fn (Order $order) => in_array(strtolower((string) $order->status), ['processing', 'shipped', 'delivered'], true))->count();
        $deliveredOrders = $ordersInRange->filter(fn (Order $order) => strtolower((string) $order->status) === 'delivered')->count();
        $returnedOrders = $ordersInRange->filter(fn (Order $order) => strtolower((string) $order->status) === 'returned')->count();
        $confirmationRate = $totalOrders > 0 ? (int) round(($confirmedOrders / $totalOrders) * 100) : 0;
        $deliveryRate = $totalOrders > 0 ? (int) round(($deliveredOrders / $totalOrders) * 100) : 0;
        $returnRate = $totalOrders > 0 ? (int) round(($returnedOrders / $totalOrders) * 100) : 0;

        $revenueBuckets = [];
        $bucketLabels = [];
        $cursor = $groupByMonth ? $rangeStart->copy()->startOfMonth() : $rangeStart->copy()->startOfDay();
        $lastBucket = $groupByMonth ? $rangeEnd->copy()->startOfMonth() : $rangeEnd->copy()->startOfDay();

        while ($cursor <= $lastBucket) {
            $bucketKey = $groupByMonth ? $cursor->format('Y-m') : $cursor->format('Y-m-d');
            $bucketLabels[] = $groupByMonth ? $cursor->format('M') : $cursor->format('d M');
            $revenueBuckets[$bucketKey] = 0;
            $cursor = $groupByMonth ? $cursor->addMonth() : $cursor->addDay();
        }

        foreach ($ordersInRange as $order) {
            if (! $order->ordered_at) {
                continue;
            }

            $bucketKey = $groupByMonth
                ? $order->ordered_at->copy()->startOfMonth()->format('Y-m')
                : $order->ordered_at->copy()->startOfDay()->format('Y-m-d');

            if (array_key_exists($bucketKey, $revenueBuckets)) {
                $revenueBuckets[$bucketKey] += (float) $order->amount;
            }
        }

        $revenueSeries = array_values(array_map(
            fn ($value) => round((float) $value, 2),
            $revenueBuckets
        ));

        $courierOrderCounts = $shipmentsInRange
            ->filter(fn (Shipment $shipment) => filled($shipment->courier_name))
            ->groupBy(fn (Shipment $shipment) => (string) $shipment->courier_name)
            ->map(fn ($shipments) => $shipments->count())
            ->sortDesc()
            ->take(6);

        $ordersChart = [
            'labels' => $courierOrderCounts->keys()->values()->all(),
            'data' => $courierOrderCounts->values()->all(),
        ];

        $statusOrder = ['new', 'processing', 'shipped', 'delivered', 'returned'];
        $statusLabels = [
            'new' => 'New',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'returned' => 'Returned',
        ];

        $statusBreakdown = collect($statusOrder)
            ->mapWithKeys(fn (string $status) => [
                $statusLabels[$status] => $ordersInRange->filter(
                    fn (Order $order) => strtolower((string) $order->status) === $status
                )->count(),
            ])
            ->filter(fn (int $count) => $count > 0);

        $paymentLabelsMap = [
            'cod' => 'COD',
            'prepaid' => 'Prepaid',
        ];

        $paymentBreakdown = $ordersInRange
            ->groupBy(fn (Order $order) => strtolower((string) $order->payment_type))
            ->mapWithKeys(fn ($orders, string $paymentType) => [
                $paymentLabelsMap[$paymentType] ?? ucfirst($paymentType) => $orders->count(),
            ])
            ->filter(fn (int $count) => $count > 0);

        $selectedCourier = trim((string) $request->input('courier', ''));
        $selectedAgent = trim((string) $request->input('agent', ''));
        $selectedDate = trim((string) $request->input('date', ''));

        $courierOptions = Shipment::query()
            ->where('seller_id', $sellerId)
            ->whereNotNull('courier_name')
            ->distinct()
            ->orderBy('courier_name')
            ->pluck('courier_name')
            ->filter()
            ->values();

        $agentOptions = CallCenterLog::query()
            ->where('seller_id', $sellerId)
            ->whereNotNull('agent_name')
            ->distinct()
            ->orderBy('agent_name')
            ->pluck('agent_name')
            ->filter()
            ->values();

        if ($selectedCourier !== '' && ! $courierOptions->contains($selectedCourier)) {
            $selectedCourier = '';
        }

        if ($selectedAgent !== '' && ! $agentOptions->contains($selectedAgent)) {
            $selectedAgent = '';
        }

        $drilldownShipments = Shipment::query()
            ->with(['order:id,amount,status', 'customer:id,name'])
            ->where('seller_id', $sellerId)
            ->when($selectedCourier !== '', fn ($query) => $query->where('courier_name', $selectedCourier))
            ->when($selectedDate !== '', fn ($query) => $query->whereDate('shipment_date', $selectedDate))
            ->whereHas('order', function ($query) use ($selectedDate) {
                if ($selectedDate !== '') {
                    $query->whereDate('ordered_at', $selectedDate);
                }
            })
            ->when($selectedAgent !== '', function ($query) use ($selectedAgent, $sellerId) {
                $query->whereHas('order.callLogs', function ($callQuery) use ($selectedAgent, $sellerId) {
                    $callQuery
                        ->where('seller_id', $sellerId)
                        ->where('agent_name', $selectedAgent);
                });
            })
            ->latest('shipment_date')
            ->latest('id')
            ->get();

        $drilldownRows = $drilldownShipments
            ->groupBy(function (Shipment $shipment) {
                return optional($shipment->shipment_date)->format('Y-m-d') . '|' . (string) $shipment->courier_name;
            })
            ->map(function ($shipments) {
                /** @var Shipment $first */
                $first = $shipments->first();
                $orders = $shipments->pluck('order')->filter();
                $confirmedCount = $orders->filter(
                    fn (Order $order) => in_array(strtolower((string) $order->status), ['processing', 'shipped', 'delivered'], true)
                )->count();
                $returnedCount = $orders->filter(
                    fn (Order $order) => strtolower((string) $order->status) === 'returned'
                )->count();

                return [
                    'sort_key' => optional($first->shipment_date)->format('Y-m-d') ?? '',
                    'date' => optional($first->shipment_date)->format('d M Y') ?? '-',
                    'courier' => $first->courier_name ?: '-',
                    'orders' => $shipments->count(),
                    'revenue' => (float) $orders->sum('amount'),
                    'confirmed' => $confirmedCount,
                    'returned' => $returnedCount,
                ];
            })
            ->sortByDesc('sort_key')
            ->map(function (array $row) {
                unset($row['sort_key']);

                return $row;
            })
            ->values();

        return view('spedfly.seller.analytics', [
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'confirmation_rate' => $confirmationRate,
                'delivery_rate' => $deliveryRate,
                'delivered_orders' => $deliveredOrders,
                'return_rate' => $returnRate,
                'call_count' => $callLogsInRange->count(),
                'shipment_count' => $shipmentsInRange->count(),
            ],
            'rangeOptions' => $rangeOptions,
            'selectedRange' => $selectedRange,
            'rangeLabel' => $rangeOptions[$selectedRange],
            'revenueChart' => [
                'labels' => $bucketLabels,
                'data' => $revenueSeries,
            ],
            'ordersChart' => $ordersChart,
            'statusBreakdown' => [
                'labels' => $statusBreakdown->keys()->values()->all(),
                'data' => $statusBreakdown->values()->all(),
            ],
            'paymentBreakdown' => [
                'labels' => $paymentBreakdown->keys()->values()->all(),
                'data' => $paymentBreakdown->values()->all(),
            ],
            'courierOptions' => $courierOptions,
            'selectedCourier' => $selectedCourier,
            'agentOptions' => $agentOptions,
            'selectedAgent' => $selectedAgent,
            'selectedDate' => $selectedDate,
            'drilldownRows' => $drilldownRows,
        ]);
    }

    public function reports(Request $request)
    {
        $sellerId = (int) Auth::id();
        $today = now();
        $rangeStart = $today->copy()->startOfMonth()->startOfDay();
        $rangeEnd = $today->copy()->endOfDay();

        $fromInput = trim((string) $request->input('from', ''));
        $toInput = trim((string) $request->input('to', ''));

        if ($fromInput !== '') {
            try {
                $rangeStart = Carbon::parse($fromInput)->startOfDay();
            } catch (\Throwable $exception) {
                $rangeStart = $today->copy()->startOfMonth()->startOfDay();
            }
        }

        if ($toInput !== '') {
            try {
                $rangeEnd = Carbon::parse($toInput)->endOfDay();
            } catch (\Throwable $exception) {
                $rangeEnd = $today->copy()->endOfDay();
            }
        }

        if ($rangeStart->greaterThan($rangeEnd)) {
            [$rangeStart, $rangeEnd] = [$rangeEnd->copy()->startOfDay(), $rangeStart->copy()->endOfDay()];
        }

        $shippedShipments = Shipment::query()
            ->with(['order.customer'])
            ->where('seller_id', $sellerId)
            ->whereIn('status', ['in_transit', 'delivered'])
            ->whereBetween('shipment_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->orderByDesc('shipment_date')
            ->orderByDesc('id')
            ->get();

        $uniqueShippedOrders = $shippedShipments
            ->filter(fn (Shipment $shipment) => filled($shipment->order_id))
            ->groupBy('order_id')
            ->map(fn ($group) => $group->first())
            ->values();

        $callLogs = CallCenterLog::query()
            ->with(['customer', 'order'])
            ->where('seller_id', $sellerId)
            ->whereBetween('call_time', [$rangeStart, $rangeEnd])
            ->orderByDesc('call_time')
            ->orderByDesc('id')
            ->get();

        $returns = OrderReturn::query()
            ->with(['order.customer'])
            ->where('seller_id', $sellerId)
            ->where(function ($query) use ($rangeStart, $rangeEnd) {
                $query
                    ->whereBetween('returned_at', [$rangeStart, $rangeEnd])
                    ->orWhere(function ($innerQuery) use ($rangeStart, $rangeEnd) {
                        $innerQuery
                            ->whereNull('returned_at')
                            ->whereBetween('created_at', [$rangeStart, $rangeEnd]);
                    });
            })
            ->orderByDesc('returned_at')
            ->orderByDesc('id')
            ->get();

        $shippedAmount = (float) $uniqueShippedOrders->sum(
            fn (Shipment $shipment) => (float) ($shipment->order?->amount ?? 0)
        );
        $callDurationSeconds = (int) $callLogs->sum('duration_seconds');
        $returnAmount = (float) $returns->sum('refund_amount');

        return view('spedfly.seller.reports', [
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'rangeLabel' => $rangeStart->format('d M Y') . ' - ' . $rangeEnd->format('d M Y'),
            'summary' => [
                'shipped_orders' => $uniqueShippedOrders->count(),
                'calls' => $callLogs->count(),
                'returns' => $returns->count(),
                'shipped_amount' => $shippedAmount,
                'call_duration_seconds' => $callDurationSeconds,
                'return_amount' => $returnAmount,
            ],
            'shippedOrders' => $uniqueShippedOrders,
            'callLogs' => $callLogs,
            'returns' => $returns,
        ]);
    }

    public function csvImport()
    {
        $result = session('csv_import_result');
        $sellerId = (int) Auth::id();

        if (! $result && $sellerId > 0) {
            $latestBatchId = SellerCsvImport::query()
                ->where('seller_id', $sellerId)
                ->latest('id')
                ->value('batch_id');

            if ($latestBatchId) {
                $latestRows = SellerCsvImport::query()
                    ->where('seller_id', $sellerId)
                    ->where('batch_id', $latestBatchId)
                    ->orderBy('row_number')
                    ->get();

                $totalRows = $latestRows->count();
                $validRows = $latestRows->where('status', 'valid')->count();
                $errorRows = $latestRows->where('status', 'invalid')->count();
                $successRate = $totalRows > 0 ? (int) round(($validRows / $totalRows) * 100) : 0;

                $result = [
                    'headers' => [],
                    'mappings' => [],
                    'preview' => $latestRows->take(200)->map(function ($row) {
                        return [
                            'row_no' => $row->row_number,
                            'order_id' => (string) ($row->order_id ?? ''),
                            'customer' => (string) ($row->customer_name ?? ''),
                            'status' => $row->status === 'valid' ? 'Valid' : 'Invalid',
                            'error' => $row->error_message ?: '-',
                        ];
                    })->values()->all(),
                    'error_log' => $latestRows
                        ->where('status', 'invalid')
                        ->take(50)
                        ->map(fn ($row) => 'Row ' . $row->row_number . ': ' . ($row->error_message ?: 'Invalid row'))
                        ->values()
                        ->all(),
                    'summary' => [
                        'total' => $totalRows,
                        'valid' => $validRows,
                        'errors' => $errorRows,
                        'success_rate' => $successRate,
                    ],
                ];
            }
        }

        return view('spedfly.seller.csv-import', compact('result'));
    }

    public function csvImportTemplate()
    {
        $templatePath = public_path('downloads/demo-format.csv');

        if (! file_exists($templatePath)) {
            abort(404, 'CSV template not found.');
        }

        return response()->download($templatePath, 'demo-format.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function csvImportShopifySample()
    {
        $samplePath = public_path('downloads/shopify-sample-orders.csv');

        if (! file_exists($samplePath)) {
            abort(404, 'Shopify sample CSV not found.');
        }

        return response()->download($samplePath, 'shopify-sample-orders.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function uploadCsvImport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $sellerId = (int) Auth::id();
        if ($sellerId <= 0) {
            return back()->withErrors(['csv_file' => 'Seller session not found. Please login again.']);
        }

        $file = $validated['csv_file'];
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return back()->withErrors(['csv_file' => 'Unable to read uploaded CSV file.']);
        }

        $rawHeaders = fgetcsv($handle);
        if (! is_array($rawHeaders) || count($rawHeaders) === 0) {
            fclose($handle);

            return back()->withErrors(['csv_file' => 'CSV file is empty or header row is missing.']);
        }

        if (isset($rawHeaders[0])) {
            $rawHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeaders[0]);
        }

        $headers = array_map(function ($header) {
            $header = is_string($header) ? trim($header) : '';

            return Str::of($header)->lower()->replace([' ', '-'], '_')->toString();
        }, $rawHeaders);

        $columnFieldMap = [];
        foreach ($headers as $header) {
            $canonical = match ($header) {
                'name', 'order_id', 'order_number', 'order_no', 'order_code', 'invoice_no', 'reference', 'id' => 'order_id',
                'customer_name', 'shipping_name', 'billing_name', 'customer', 'full_name', 'client_name', 'buyer_name', 'recipient_name', 'contact_name' => 'customer_name',
                'phone', 'shipping_phone', 'billing_phone', 'customer_phone', 'mobile', 'tel', 'telephone', 'contact_phone' => 'phone',
                'email', 'customer_email', 'contact_email', 'shipping_email', 'billing_email' => 'email',
                'address', 'delivery_address', 'shipping_address', 'shipping_address1', 'shipping_street', 'billing_address1', 'billing_street', 'street' => 'address',
                'shipping_address2', 'billing_address2', 'address2' => 'address_line_2',
                'shipping_city', 'billing_city', 'city' => 'city',
                'shipping_province', 'shipping_province_name', 'billing_province', 'billing_province_name', 'province', 'state' => 'province',
                'shipping_zip', 'billing_zip', 'zip', 'postal_code', 'postcode' => 'zip',
                'shipping_country', 'billing_country', 'country' => 'country',
                'amount', 'total', 'order_amount', 'total_price', 'subtotal' => 'amount',
                'sku', 'lineitem_sku', 'product_sku', 'item_sku', 'variant_sku', 'code', 'barcode' => 'sku',
                'quantity', 'lineitem_quantity', 'qty', 'item_quantity', 'units', 'count' => 'quantity',
                'payment_type', 'payment_method', 'financial_status', 'gateway' => 'payment_type',
                'fulfillment_status', 'order_status', 'status' => 'status',
                'lineitem_name', 'product_name', 'item_name', 'title' => 'product_name',
                'lineitem_price', 'price', 'unit_price', 'lineitem_unit_price' => 'unit_price',
                default => null,
            };
            if ($canonical !== null) {
                $columnFieldMap[$header] = $canonical;
            }
        }

        $availableMappings = [];
        foreach ($headers as $header) {
            $availableMappings[$header] = $columnFieldMap[$header] ?? null;
        }

        $batchId = (string) Str::uuid();
        $dbRows = [];

        $totalRows = 0;
        $validRows = 0;
        $errorRows = 0;
        $previewRows = [];
        $errorLog = [];
        $shopifyErrorLog = [];
        $maxPreviewRows = 200;
        $maxErrorRows = 50;
        $rowNumber = 1;
        $shopifySyncedRows = 0;
        $shopifySyncedProducts = 0;
        $shopifyFailedRows = 0;
        $touchedProductIds = [];
        $touchedOrderIds = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($row === [null] || $row === [] || (count($row) === 1 && trim((string) ($row[0] ?? '')) === '')) {
                continue;
            }

            $totalRows++;
            $normalizedRow = array_pad($row, count($headers), null);
            $record = array_combine($headers, array_slice($normalizedRow, 0, count($headers)));

            $extractVal = function (array $possibleKeys, string $default = '') use ($record): string {
                foreach ($possibleKeys as $key) {
                    if (isset($record[$key]) && trim((string) $record[$key]) !== '') {
                        return trim((string) $record[$key]);
                    }
                }
                return $default;
            };

            $orderId = $extractVal(['order_id', 'name', 'order_number', 'order_no', 'id', 'invoice_no', 'reference']);
            $customerName = $extractVal(['customer_name', 'shipping_name', 'billing_name', 'customer', 'full_name', 'client_name', 'buyer_name', 'recipient_name', 'contact_name']);
            if ($customerName === '' && isset($record['first_name'])) {
                $customerName = trim($record['first_name'] . ' ' . ($record['last_name'] ?? ''));
            }
            if ($customerName === '' && isset($record['shipping_first_name'])) {
                $customerName = trim($record['shipping_first_name'] . ' ' . ($record['shipping_last_name'] ?? ''));
            }
            if ($customerName === '') {
                $customerName = $extractVal(['email', 'customer_email'], 'Customer');
            }

            $phone = $extractVal(['phone', 'shipping_phone', 'billing_phone', 'customer_phone', 'mobile', 'tel', 'telephone']);
            $email = $extractVal(['email', 'customer_email', 'contact_email', 'shipping_email', 'billing_email']);

            $address1 = $extractVal(['address', 'shipping_address1', 'shipping_street', 'shipping_address', 'delivery_address', 'billing_address1', 'billing_street', 'street']);
            $address2 = $extractVal(['address2', 'address_line_2', 'shipping_address2', 'billing_address2']);
            $city = $extractVal(['city', 'shipping_city', 'billing_city']);
            $province = $extractVal(['province', 'shipping_province', 'shipping_province_name', 'billing_province', 'billing_province_name', 'state']);
            $zip = $extractVal(['zip', 'shipping_zip', 'billing_zip', 'postal_code', 'postcode']);
            $country = $extractVal(['country', 'shipping_country', 'billing_country']);

            $addressParts = array_filter([$address1, $address2, $city, $province, $zip, $country], static fn ($v) => $v !== '');
            $address = implode(', ', $addressParts);
            if ($address === '') {
                $address = 'Address not provided';
            }

            $sku = $extractVal(['sku', 'lineitem_sku', 'product_sku', 'item_sku', 'variant_sku', 'barcode', 'code']);
            $productName = $extractVal(['lineitem_name', 'product_name', 'title', 'item_name', 'product_title']);
            if ($sku === '' && $productName !== '') {
                $sku = Str::upper(Str::slug(Str::limit($productName, 30), '-'));
            }
            if ($sku === '') {
                $sku = 'ITEM-' . str_pad((string) $rowNumber, 4, '0', STR_PAD_LEFT);
            }
            if ($productName === '') {
                $productName = 'Product ' . $sku;
            }

            $quantityVal = $extractVal(['quantity', 'lineitem_quantity', 'qty', 'item_quantity', 'units', 'count'], '1');
            $quantity = is_numeric($quantityVal) && (int) $quantityVal > 0 ? (int) $quantityVal : 1;

            $unitPriceVal = $extractVal(['lineitem_price', 'price', 'unit_price', 'item_price']);
            $amountVal = $extractVal(['amount', 'total', 'order_amount', 'total_price', 'subtotal']);

            $unitPrice = is_numeric($unitPriceVal) ? (float) $unitPriceVal : (is_numeric($amountVal) ? (float) $amountVal / $quantity : 10.0);
            $amount = is_numeric($amountVal) ? (float) $amountVal : ($unitPrice * $quantity);

            $paymentTypeRaw = $extractVal(['payment_type', 'financial_status', 'payment_method', 'gateway'], 'COD');
            $paymentTypeUpper = strtoupper($paymentTypeRaw);
            $paymentType = in_array($paymentTypeUpper, ['PAID', 'AUTHORIZED', 'PARTIALLY_PAID', 'PREPAID', 'ONLINE', 'STRIPE', 'PAYPAL', 'CREDIT_CARD', 'CARD'], true)
                ? 'PREPAID'
                : 'COD';

            $fulfillmentStatusRaw = strtolower($extractVal(['fulfillment_status', 'order_status', 'status'], ''));
            $orderStatus = match ($fulfillmentStatusRaw) {
                'fulfilled', 'delivered' => 'delivered',
                'partial', 'partially_fulfilled', 'shipped', 'in_transit' => 'shipped',
                'returned', 'refunded', 'voided' => 'returned',
                default => 'new',
            };

            $externalOrderId = $orderId !== ''
                ? $orderId
                : 'ORD-' . strtoupper(substr(str_replace('-', '', $batchId), 0, 8)) . '-' . str_pad((string) $rowNumber, 5, '0', STR_PAD_LEFT);

            $status = 'Valid';
            $errorText = '-';
            $createdOrderId = null;
            $createdProductId = null;

            try {
                DB::transaction(function () use (
                    $sellerId,
                    $customerName,
                    $phone,
                    $email,
                    $address,
                    $externalOrderId,
                    $amount,
                    $sku,
                    $productName,
                    $unitPrice,
                    $quantity,
                    $paymentType,
                    $orderStatus,
                    &$createdOrderId,
                    &$createdProductId
                ) {
                    $product = Product::query()
                        ->where('seller_id', $sellerId)
                        ->where('sku', $sku)
                        ->lockForUpdate()
                        ->first();

                    if (! $product) {
                        $product = Product::query()->create([
                            'seller_id' => $sellerId,
                            'sku' => $sku,
                            'name' => $productName,
                            'price' => $unitPrice > 0 ? $unitPrice : 10.0,
                            'stock' => 1000,
                            'low_stock_alert' => 0,
                            'status' => 'active',
                            'shopify_sync_status' => 'pending',
                        ]);
                    } elseif ($product->stock < $quantity) {
                        $product->increment('stock', $quantity + 50);
                    }

                    $createdProductId = $product->id;

                    $customer = null;
                    if ($email !== '') {
                        $customer = Customer::query()->where('seller_id', $sellerId)->where('email', $email)->first();
                    }
                    if (! $customer && $phone !== '') {
                        $customer = Customer::query()->where('seller_id', $sellerId)->where('phone', $phone)->first();
                    }
                    if (! $customer) {
                        $customer = Customer::query()->where('seller_id', $sellerId)->where('name', $customerName)->first();
                    }

                    if ($customer) {
                        $customer->update(array_filter([
                            'name' => $customerName,
                            'phone' => $phone !== '' ? $phone : $customer->phone,
                            'email' => $email !== '' ? $email : $customer->email,
                            'address' => $address !== 'Address not provided' ? $address : $customer->address,
                        ]));
                    } else {
                        $customer = Customer::query()->create([
                            'seller_id' => $sellerId,
                            'name' => $customerName,
                            'phone' => $phone !== '' ? $phone : null,
                            'email' => $email !== '' ? $email : null,
                            'address' => $address,
                        ]);
                    }

                    $order = Order::query()
                        ->where('seller_id', $sellerId)
                        ->where('external_order_id', $externalOrderId)
                        ->first();

                    if (! $order) {
                        $order = Order::query()->create([
                            'seller_id' => $sellerId,
                            'customer_id' => $customer->id,
                            'external_order_id' => $externalOrderId,
                            'amount' => (float) $amount,
                            'payment_type' => strtolower($paymentType),
                            'status' => $orderStatus,
                            'ordered_at' => now(),
                        ]);

                        OrderItem::query()->create([
                            'order_id' => $order->id,
                            'product_id' => $product->id,
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'total_price' => $unitPrice * $quantity,
                        ]);

                        $product->decrement('stock', min($product->stock, $quantity));
                    } else {
                        $existingItem = OrderItem::query()
                            ->where('order_id', $order->id)
                            ->where('product_id', $product->id)
                            ->first();

                        if ($existingItem) {
                            $existingItem->increment('quantity', $quantity);
                            $existingItem->update([
                                'total_price' => (float) $existingItem->unit_price * (int) $existingItem->quantity,
                            ]);
                        } else {
                            OrderItem::query()->create([
                                'order_id' => $order->id,
                                'product_id' => $product->id,
                                'quantity' => $quantity,
                                'unit_price' => $unitPrice,
                                'total_price' => $unitPrice * $quantity,
                            ]);
                        }

                        $product->decrement('stock', min($product->stock, $quantity));
                    }

                    $createdOrderId = $order->id;
                });

                $validRows++;

                if ($createdProductId) {
                    $touchedProductIds[$createdProductId] = true;
                }

                if ($createdOrderId) {
                    $touchedOrderIds[$createdOrderId] = true;
                }
            } catch (\Throwable $ex) {
                $errorRows++;
                $status = 'Invalid';
                $errorText = 'Row error: ' . $ex->getMessage();

                if (count($errorLog) < $maxErrorRows) {
                    $errorLog[] = 'Row ' . $rowNumber . ': ' . $errorText;
                }
            }

            if (count($previewRows) < $maxPreviewRows) {
                $previewRows[] = [
                    'row_no' => $rowNumber,
                    'order_id' => $externalOrderId,
                    'customer' => $customerName,
                    'status' => $status,
                    'error' => $errorText,
                ];
            }

            $dbRows[] = [
                'seller_id' => $sellerId,
                'batch_id' => $batchId,
                'row_number' => $rowNumber,
                'order_id' => $externalOrderId,
                'customer_name' => $customerName !== '' ? $customerName : null,
                'phone' => $phone !== '' ? $phone : null,
                'address' => $address !== '' ? $address : null,
                'amount' => (float) $amount,
                'status' => $status === 'Valid' ? 'valid' : 'invalid',
                'error_message' => $errorText === '-' ? null : $errorText,
                'raw_payload' => json_encode($record),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        fclose($handle);

        foreach (array_chunk($dbRows, 500) as $chunk) {
            SellerCsvImport::insert($chunk);
        }

        // 1. Auto-sync all touched products to Shopify in real-time
        foreach (array_keys($touchedProductIds) as $productId) {
            $productToSync = Product::query()->find($productId);
            if ($productToSync) {
                $pResult = $this->syncProductWithShopify($productToSync, $request);
                if ($pResult['ok']) {
                    $shopifySyncedProducts++;
                } else {
                    if (count($shopifyErrorLog) < 10) {
                        $shopifyErrorLog[] = 'Product ' . $productToSync->sku . ' sync note: ' . $pResult['message'];
                    }
                }
            }
        }

        // 2. Auto-sync all created/updated orders to Shopify in real-time
        foreach (array_keys($touchedOrderIds) as $orderId) {
            $orderToSync = Order::query()->with(['customer', 'items.product'])->find($orderId);

            if ($orderToSync) {
                $syncResult = $this->syncCsvOrderToShopify($orderToSync, $request);

                if ($syncResult['ok']) {
                    $shopifySyncedRows++;
                } else {
                    if (count($shopifyErrorLog) < 10) {
                        $shopifyErrorLog[] = 'Order #' . $orderToSync->external_order_id . ' sync note: ' . $syncResult['message'];
                    }
                }
            }
        }


        $successRate = $totalRows > 0 ? (int) round(($validRows / $totalRows) * 100) : 0;
        $summaryMessage = "CSV upload completed. Total: {$totalRows}, Valid: {$validRows}, Errors: {$errorRows}. Auto-synced to Shopify: {$shopifySyncedProducts} product(s), {$shopifySyncedRows} order(s).";
        $now = now();

        $notificationRows = [
            [
                'user_id' => $sellerId,
                'title' => 'CSV Import Completed',
                'message' => $summaryMessage,
                'type' => 'csv_import',
                'is_read' => false,
                'read_at' => null,
                'data' => [
                    'total' => $totalRows,
                    'valid' => $validRows,
                    'errors' => $errorRows,
                    'success_rate' => $successRate,
                    'shopify_synced_products' => $shopifySyncedProducts,
                    'shopify_synced_orders' => $shopifySyncedRows,
                ],
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $adminIds = User::query()->where('type', 'admin')->pluck('id');
        foreach ($adminIds as $adminId) {
            $notificationRows[] = [
                'user_id' => (int) $adminId,
                'title' => 'Seller CSV Import',
                'message' => 'Seller ID ' . $sellerId . ' uploaded CSV. ' . $summaryMessage,
                'type' => 'csv_import',
                'is_read' => false,
                'read_at' => null,
                'data' => [
                    'seller_id' => $sellerId,
                    'total' => $totalRows,
                    'valid' => $validRows,
                    'errors' => $errorRows,
                    'success_rate' => $successRate,
                ],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($notificationRows as $notificationRow) {
            AppNotification::query()->create($notificationRow);
        }

        return redirect()
            ->route('seller.csv-import')
            ->with('success', 'CSV processed and auto-synced successfully. Total: ' . $totalRows . ', Valid: ' . $validRows . ', Auto-synced to Shopify: ' . $shopifySyncedProducts . ' products, ' . $shopifySyncedRows . ' orders.')
            ->with('csv_import_result', [
                'headers' => $headers,
                'mappings' => $availableMappings,
                'preview' => $previewRows,
                'error_log' => array_merge($errorLog, $shopifyErrorLog),
                'summary' => [
                    'total' => $totalRows,
                    'valid' => $validRows,
                    'errors' => $errorRows,
                    'success_rate' => $successRate,
                    'shopify_synced_products' => $shopifySyncedProducts,
                    'shopify_synced' => $shopifySyncedRows,
                    'shopify_failed' => $shopifyFailedRows,
                ],
            ]);
    }

    public function settings()
    {
        $user = Auth::user();
        $sellerSettings = $this->sellerSettingsMap($user);

        return view('spedfly.seller.settings', [
            'user' => $user,
            'shopifyConnection' => $this->shopifyConnectionData($user),
            'companyProfile' => [
                'company_name' => old('company_name', $user?->company_name ?? ''),
                'support_email' => old('support_email', $user?->email ?? ''),
                'support_phone' => old('support_phone', $user?->support_phone ?? ''),
                'vat_number' => old('vat_number', $user?->vat_number ?? ''),
                'iban_code' => old('iban_code', $user?->iban_code ?? ''),
                'pickup_address' => old('pickup_address', $user?->pickup_address ?? ''),
                'pickup_latitude' => old('pickup_latitude', $user?->pickup_latitude ?? ''),
                'pickup_longitude' => old('pickup_longitude', $user?->pickup_longitude ?? ''),
            ],
            'apiSettings' => [
                'api_key' => $this->sellerSettingValue($user, 'api.api_key', 'sk_live_123456', $sellerSettings),
                'webhook_url' => old('webhook_url', $this->sellerSettingValue($user, 'api.webhook_url', '', $sellerSettings)),
            ],
            'notificationSettings' => [
                'order_created' => old('order_created', (bool) $this->sellerSettingValue($user, 'notification.order_created', true, $sellerSettings)),
                'shipment_delayed' => old('shipment_delayed', (bool) $this->sellerSettingValue($user, 'notification.shipment_delayed', false, $sellerSettings)),
            ],
            'paymentSettings' => [
                'cod_status' => old('cod_status', $this->sellerSettingValue($user, 'payment.cod_status', 'enable', $sellerSettings)),
                'online_payment_status' => old('online_payment_status', $this->sellerSettingValue($user, 'payment.online_payment_status', 'disable', $sellerSettings)),
            ],
            'securitySettings' => [
                'two_factor_enabled' => old('two_factor_enabled', (bool) $this->sellerSettingValue($user, 'security.two_factor_enabled', false, $sellerSettings)),
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $tab = $request->input('tab', 'profile');

        if ($tab === 'api') {
            $validated = $request->validate([
                'webhook_url' => ['nullable', 'url', 'max:2048'],
            ]);

            $this->saveSellerSetting($user, 'api.api_key', $request->input('api_key', 'sk_live_123456'));
            $this->saveSellerSetting($user, 'api.webhook_url', $validated['webhook_url'] ?? null);

            return redirect()
                ->route('seller.settings', ['tab' => $tab])
                ->with('success', 'API settings updated successfully.');
        }

        if ($tab === 'notification') {
            $validated = $request->validate([
                'order_created' => ['nullable', 'boolean'],
                'shipment_delayed' => ['nullable', 'boolean'],
            ]);

            $this->saveSellerSetting($user, 'notification.order_created', $request->boolean('order_created'));
            $this->saveSellerSetting($user, 'notification.shipment_delayed', $request->boolean('shipment_delayed'));

            return redirect()
                ->route('seller.settings', ['tab' => $tab])
                ->with('success', 'Notification settings updated successfully.');
        }

        if ($tab === 'payment') {
            $validated = $request->validate([
                'cod_status' => ['required', 'in:enable,disable'],
                'online_payment_status' => ['required', 'in:enable,disable'],
            ]);

            $this->saveSellerSetting($user, 'payment.cod_status', $validated['cod_status']);
            $this->saveSellerSetting($user, 'payment.online_payment_status', $validated['online_payment_status']);

            return redirect()
                ->route('seller.settings', ['tab' => $tab])
                ->with('success', 'Payment settings updated successfully.');
        }

        if ($tab === 'security') {
            $validated = $request->validate([
                'new_password' => ['nullable', 'string', 'min:8'],
                'two_factor_enabled' => ['nullable', 'boolean'],
            ]);

            if (! empty($validated['new_password'])) {
                $user?->update([
                    'password' => Hash::make($validated['new_password']),
                ]);
            }

            $this->saveSellerSetting($user, 'security.two_factor_enabled', $request->boolean('two_factor_enabled'));

            return redirect()
                ->route('seller.settings', ['tab' => $tab])
                ->with('success', 'Security settings updated successfully.');
        }

        $validated = $request->validate([
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'company_name' => ['required', 'string', 'max:255'],
            'support_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'support_phone' => ['required', 'string', 'max:255'],
            'vat_number' => ['required', 'string', 'max:255'],
            'iban_code' => ['required', 'string', 'max:255'],
            'pickup_address' => ['required', 'string', 'max:2000'],
            'pickup_latitude' => ['nullable', 'numeric'],
            'pickup_longitude' => ['nullable', 'numeric'],
        ]);

        $updates = [
            'company_name' => $validated['company_name'],
            'email' => $validated['support_email'],
            'support_phone' => $validated['support_phone'],
            'vat_number' => $validated['vat_number'],
            'iban_code' => $validated['iban_code'] ?? null,
            'pickup_address' => $validated['pickup_address'],
            'pickup_latitude' => $validated['pickup_latitude'] ?? null,
            'pickup_longitude' => $validated['pickup_longitude'] ?? null,
        ];

        if ($request->hasFile('avatar')) {
            if ($user?->avatar_path) {
                File::delete(public_path($user->avatar_path));
            }

            $avatarFile = $request->file('avatar');
            $avatarDirectory = public_path('uploads/avatars/seller');
            File::ensureDirectoryExists($avatarDirectory);

            $avatarName = 'seller-' . ($user?->id ?? 'profile') . '-' . now()->format('YmdHis') . '.' . $avatarFile->getClientOriginalExtension();
            $avatarFile->move($avatarDirectory, $avatarName);
            $updates['avatar_path'] = 'uploads/avatars/seller/' . $avatarName;
        }

        $user?->update($updates);

        return redirect()
            ->route('seller.settings', ['tab' => $tab])
            ->with('success', 'Company profile updated successfully.');
    }

    private function sellerSettingsMap(?User $user): array
    {
        $sellerId = (int) $user?->id;

        if ($sellerId <= 0) {
            return [];
        }

        return SystemSetting::query()
            ->where('setting_key', 'like', $this->sellerSettingsPrefix($sellerId) . '%')
            ->pluck('setting_value', 'setting_key')
            ->all();
    }

    private function sellerSettingValue(?User $user, string $key, mixed $default = null, ?array $settingsMap = null): mixed
    {
        $sellerId = (int) $user?->id;

        if ($sellerId <= 0) {
            return $default;
        }

        $settingsMap ??= $this->sellerSettingsMap($user);
        $settingKey = $this->sellerSettingsPrefix($sellerId) . $key;

        if (! array_key_exists($settingKey, $settingsMap)) {
            if ($key === 'api.api_key' && $default !== null) {
                $this->saveSellerSetting($user, $key, $default);
            }

            return $default;
        }

        $value = $settingsMap[$settingKey];

        if (is_bool($default)) {
            return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
        }

        return $value ?? $default;
    }

    private function saveSellerSetting(?User $user, string $key, mixed $value): void
    {
        $sellerId = (int) $user?->id;

        if ($sellerId <= 0) {
            return;
        }

        SystemSetting::query()->updateOrCreate(
            ['setting_key' => $this->sellerSettingsPrefix($sellerId) . $key],
            [
                'setting_value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                'updated_by' => $sellerId,
            ]
        );
    }

    private function sellerSettingsPrefix(int $sellerId): string
    {
        return 'seller:' . $sellerId . ':';
    }

    public function shopifyConnect(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'shop_domain' => ['required', 'string', 'max:255'],
        ]);

        $shopDomain = $this->normalizeShopifyDomain($validated['shop_domain']);

        if ($shopDomain === '') {
            return back()
                ->withErrors(['shop_domain' => 'Please enter a valid Shopify store domain.'])
                ->withInput();
        }

        $clientId = (string) config('services.shopify.client_id');
        $clientSecret = (string) config('services.shopify.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            return back()
                ->withErrors([
                    'shop_domain' => 'Shopify credentials are not configured in the environment.',
                ])
                ->withInput();
        }

        $state = Str::random(40);
        $request->session()->put('shopify_oauth_state', $state);
        $request->session()->put('shopify_oauth_shop', $shopDomain);
        $request->session()->put('shopify_oauth_user_id', $user?->id);

        return redirect()->away($this->buildShopifyAuthorizationUrl($request, $shopDomain, $state));
    }

    public function shopifyCallback(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('seller.index');
        }

        $expectedShop = (string) $request->session()->pull('shopify_oauth_shop', '');
        $request->session()->forget('shopify_oauth_user_id');

        if (! $this->isValidShopifyCallback($request)) {
            return redirect()
                ->route('seller.settings', ['tab' => 'integration'])
                ->withErrors(['shop_domain' => 'Shopify returned an invalid callback signature.']);
        }

        $shopDomain = $this->normalizeShopifyDomain((string) $request->query('shop', $expectedShop));
        $code = (string) $request->query('code', '');

        if ($shopDomain === '' || $code === '') {
            return redirect()
                ->route('seller.settings', ['tab' => 'integration'])
                ->withErrors(['shop_domain' => 'Shopify connection data is incomplete.']);
        }
        $response = Http::asForm()
            ->timeout(15)
            ->post("https://{$shopDomain}/admin/oauth/access_token", [
                'client_id' => (string) config('services.shopify.client_id'),
                'client_secret' => (string) config('services.shopify.client_secret'),
                'expiring' => 1,
                'code' => $code,
            ]);

        $payload = $response->json();
        $accessToken = (string) ($payload['access_token'] ?? '');
        $scope = (string) ($payload['scope'] ?? '');
        $grantedScopes = array_values(array_filter(array_map('trim', explode(',', $scope))));

        if ($accessToken === '') {
            return redirect()
                ->route('seller.settings', ['tab' => 'integration'])
                ->withErrors(['shop_domain' => 'Shopify did not return an access token.']);
        }

        $this->storeShopifyTokenResponse($user, $shopDomain, $payload);

        Log::info('Shopify OAuth token received.', [
            'shop_domain' => $shopDomain,
            'granted_scopes' => $grantedScopes,
            'has_read_products' => in_array('read_products', $grantedScopes, true),
            'expires_in' => $payload['expires_in'] ?? null,
            'refresh_token_expires_in' => $payload['refresh_token_expires_in'] ?? null,
        ]);

        $storedAccessToken = $accessToken;

        $this->registerShopifyWebhooks($shopDomain, $storedAccessToken, $user);
        $this->backfillShopifyProductLinks($user, $shopDomain, $storedAccessToken);
        $this->pullShopifyRecentOrders($user, $shopDomain, $storedAccessToken);
        $backfillSummary = $this->syncExistingProductsToShopify($user, $request);

        return redirect()
            ->route('seller.settings', ['tab' => 'integration'])
            ->with('success', 'Shopify connected successfully. ' . $backfillSummary);
    }

    private function registerShopifyWebhooks(string $shopDomain, string $accessToken, ?User $user = null): void
    {
        if ($accessToken === '') {
            Log::warning('Shopify webhook registration skipped because access token is empty.', [
                'shop_domain' => $shopDomain,
            ]);

            return;
        }

        if ($user && trim((string) $user->shopify_scope) !== '') {
            $installedScopes = array_values(array_filter(array_map('trim', explode(',', (string) $user->shopify_scope))));
            if (! in_array('read_products', $installedScopes, true)) {
                Log::warning('Shopify webhook registration may fail because read_products is missing.', [
                    'shop_domain' => $shopDomain,
                    'installed_scopes' => $installedScopes,
                ]);
            }
        }

        $this->registerShopifyWebhook($shopDomain, $accessToken, 'products/update', route('shopify.webhooks.products.update'));
        $this->registerShopifyWebhook($shopDomain, $accessToken, 'products/create', route('shopify.webhooks.products.create'));
        $this->registerShopifyWebhook($shopDomain, $accessToken, 'products/delete', route('shopify.webhooks.products.delete'));
        $this->registerShopifyWebhook($shopDomain, $accessToken, 'orders/create', route('shopify.webhooks.orders.create'));
        $this->registerShopifyWebhook($shopDomain, $accessToken, 'orders/updated', route('shopify.webhooks.orders.updated'));
        $this->registerShopifyWebhook($shopDomain, $accessToken, 'orders/cancelled', route('shopify.webhooks.orders.cancelled'));
        $this->registerShopifyWebhook($shopDomain, $accessToken, 'orders/fulfilled', route('shopify.webhooks.orders.fulfilled'));
        $this->registerShopifyWebhook($shopDomain, $accessToken, 'orders/partially_fulfilled', route('shopify.webhooks.orders.partially_fulfilled'));
        $this->registerShopifyWebhook($shopDomain, $accessToken, 'app/uninstalled', route('shopify.webhooks.app.uninstalled'));
    }

    private function registerShopifyWebhook(string $shopDomain, string $accessToken, string $topic, string $address): void
    {
        $apiVersion = (string) config('services.shopify.api_version', '2026-04');

        if (str_starts_with($address, 'http://') && ! str_contains($address, 'localhost')) {
            $address = 'https://' . substr($address, 7);
        }

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Accept' => 'application/json',
            ])->asJson()->timeout(20)->post(
                "https://{$shopDomain}/admin/api/{$apiVersion}/webhooks.json",
                [
                    'webhook' => [
                        'topic' => $topic,
                        'address' => $address,
                        'format' => 'json',
                    ],
                ]
            );

            if (! $response->successful()) {
                Log::warning('Shopify webhook registration failed.', [
                    'shop_domain' => $shopDomain,
                    'topic' => $topic,
                    'status' => $response->status(),
                    'body' => Str::limit((string) $response->body(), 250),
                ]);
            } else {
                Log::info('Shopify webhook registered.', [
                    'shop_domain' => $shopDomain,
                    'topic' => $topic,
                    'status' => $response->status(),
                    'body' => Str::limit((string) $response->body(), 250),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Shopify webhook registration exception.', [
                'shop_domain' => $shopDomain,
                'topic' => $topic,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function backfillShopifyProductLinks(User $seller, string $shopDomain, string $accessToken): void
    {
        $apiVersion = (string) config('services.shopify.api_version', '2026-04');
        $pageInfo = null;
        $linkedCount = 0;

        try {
            do {
                $query = ['limit' => 250, 'fields' => 'id,title,body_html,product_type,status,variants'];
                if ($pageInfo) {
                    $query['page_info'] = $pageInfo;
                }

                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $accessToken,
                    'Accept' => 'application/json',
                ])->timeout(30)->get("https://{$shopDomain}/admin/api/{$apiVersion}/products.json", $query);

                if (! $response->successful()) {
                    Log::warning('Shopify product backfill failed.', [
                        'shop_domain' => $shopDomain,
                        'status' => $response->status(),
                        'body' => Str::limit((string) $response->body(), 250),
                    ]);

                    return;
                }

                $products = (array) ($response->json('products') ?? []);

                foreach ($products as $shopifyProduct) {
                    $shopifyProductId = (int) ($shopifyProduct['id'] ?? 0);
                    if ($shopifyProductId <= 0) {
                        continue;
                    }

                    $variants = (array) ($shopifyProduct['variants'] ?? []);
                    foreach ($variants as $variant) {
                        $sku = trim((string) ($variant['sku'] ?? ''));
                        if ($sku === '') {
                            $sku = 'SHOPIFY-' . $shopifyProductId;
                        }

                        $localProduct = Product::query()
                            ->where('seller_id', $seller->id)
                            ->where('sku', $sku)
                            ->first();

                        if (! $localProduct) {
                            $shopifyPrice = $this->extractFloatFromValue($variant['price'] ?? null) ?? 0.0;
                            $shopifyStock = $this->extractIntFromValue($variant['inventory_quantity'] ?? null) ?? 0;
                            $shopifyStatus = strtolower((string) ($shopifyProduct['status'] ?? 'active'));
                            $description = isset($shopifyProduct['body_html']) ? trim(strip_tags((string) $shopifyProduct['body_html'])) : '';
                            $category = isset($shopifyProduct['product_type']) ? trim((string) $shopifyProduct['product_type']) : null;
                            $shopifyImageUrl = $this->extractShopifyImageUrl($shopifyProduct);

                            Product::query()->create([
                                'seller_id' => $seller->id,
                                'sku' => $sku,
                                'name' => trim((string) $shopifyProduct['title']),
                                'description' => $description !== '' ? $description : null,
                                'category' => $category !== '' ? $category : null,
                                'image_path' => $shopifyImageUrl,
                                'price' => $shopifyPrice,
                                'stock' => $shopifyStock,
                                'low_stock_alert' => 0,
                                'status' => $shopifyStatus === 'active' ? 'active' : 'deactive',
                                'shopify_product_id' => $shopifyProductId,
                                'shopify_sync_status' => 'synced',
                                'shopify_synced_at' => now(),
                                'shopify_sync_error' => null,
                            ]);
                            $linkedCount++;
                            break;
                        }

                        $updates = [
                            'shopify_product_id' => $shopifyProductId,
                            'shopify_sync_status' => 'synced',
                            'shopify_synced_at' => now(),
                            'shopify_sync_error' => null,
                        ];

                        $shopifyPrice = $this->extractFloatFromValue($variant['price'] ?? null);
                        if ($shopifyPrice !== null) {
                            $updates['price'] = $shopifyPrice;
                        }

                        $shopifyStock = $this->extractIntFromValue($variant['inventory_quantity'] ?? null);
                        if ($shopifyStock !== null) {
                            $updates['stock'] = $shopifyStock;
                        }

                        $shopifyStatus = strtolower((string) ($shopifyProduct['status'] ?? 'active'));
                        $updates['status'] = $shopifyStatus === 'active' ? 'active' : 'deactive';

                        if (isset($shopifyProduct['title'])) {
                            $updates['name'] = trim((string) $shopifyProduct['title']);
                        }

                        if (isset($shopifyProduct['body_html'])) {
                            $description = trim(strip_tags((string) $shopifyProduct['body_html']));
                            if ($description !== '') {
                                $updates['description'] = $description;
                            }
                        }

                        if (isset($shopifyProduct['product_type'])) {
                            $category = trim((string) $shopifyProduct['product_type']);
                            if ($category !== '') {
                                $updates['category'] = $category;
                            }
                        }

                        $shopifyImageUrl = $this->extractShopifyImageUrl($shopifyProduct);
                        if ($shopifyImageUrl !== null) {
                            $updates['image_path'] = $shopifyImageUrl;
                        }

                        $localProduct->update($updates);
                        $linkedCount++;
                        break;
                    }
                }

                $linkHeader = (string) $response->header('Link', '');
                $pageInfo = null;
                if ($linkHeader !== '' && preg_match('/[?&]page_info=([^&>]+)/', $linkHeader, $matches)) {
                    $pageInfo = urldecode($matches[1]);
                }
            } while ($pageInfo);

            if ($linkedCount > 0) {
                Log::info('Shopify product backfill completed.', [
                    'shop_domain' => $shopDomain,
                    'linked_count' => $linkedCount,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Shopify product backfill exception.', [
                'shop_domain' => $shopDomain,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function pullShopifyRecentOrders(User $seller, string $shopDomain, string $accessToken): int
    {
        $apiVersion = (string) config('services.shopify.api_version', '2026-04');
        $pulledCount = 0;

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Accept' => 'application/json',
            ])->timeout(30)->get("https://{$shopDomain}/admin/api/{$apiVersion}/orders.json", [
                'limit' => 50,
                'status' => 'any',
            ]);

            if (! $response->successful()) {
                Log::warning('Shopify orders backfill failed.', [
                    'shop_domain' => $shopDomain,
                    'status' => $response->status(),
                    'body' => Str::limit((string) $response->body(), 250),
                ]);

                return 0;
            }

            $orders = (array) ($response->json('orders') ?? []);

            foreach ($orders as $orderPayload) {
                if (is_array($orderPayload)) {
                    $this->syncLocalOrderFromShopifyWebhook($shopDomain, $orderPayload, true);
                    $pulledCount++;
                }
            }

            if ($pulledCount > 0) {
                Log::info('Shopify orders backfill completed.', [
                    'shop_domain' => $shopDomain,
                    'pulled_count' => $pulledCount,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Shopify orders backfill exception.', [
                'shop_domain' => $shopDomain,
                'message' => $exception->getMessage(),
            ]);
        }

        return $pulledCount;
    }

    public function shopifyDisconnect(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $tab = $request->input('tab', 'integration');

        $user?->update([
            'shopify_shop_domain' => null,
            'shopify_access_token' => null,
            'shopify_access_token_expires_at' => null,
            'shopify_refresh_token' => null,
            'shopify_refresh_token_expires_at' => null,
            'shopify_scope' => null,
            'shopify_connected_at' => null,
        ]);

        return redirect()
            ->route('seller.settings', ['tab' => $tab])
            ->with('success', 'Shopify disconnected successfully.');
    }

    public function addCompany()
    {
        return view('spedfly.seller.add-company');
    }

    public function addDriver()
    {
        return view('spedfly.seller.add-driver');
    }

    public function addTrip()
    {
        return view('spedfly.seller.add-trip');
    }

    public function company()
    {
        return view('spedfly.seller.company');
    }

    public function createShipment(Request $request)
    {
        $orderId = $request->query('order_id');
        $order = null;
        if ($orderId) {
            $order = Order::query()
                ->where('seller_id', Auth::id())
                ->where('id', $orderId)
                ->first();
        }

        $shipmentData = [
            'status' => 'pending',
            'payment_type' => 'prepaid',
            'shipment_date' => now()->toDateString(),
        ];

        if ($order) {
            $shipmentData['order_id'] = $order->id;
            $shipmentData['customer_id'] = $order->customer_id;
            $shipmentData['customer_name'] = $order->customer?->name;
            $shipmentData['customer_phone'] = $order->customer?->phone;
            $shipmentData['delivery_name'] = $order->customer?->name;
            $shipmentData['delivery_address'] = $order->customer?->address;
            $shipmentData['payment_type'] = $order->payment_type === 'cod' ? 'cod' : 'prepaid';
        }

        return view('spedfly.seller.create-shipment', $this->shipmentFormData(
            new Shipment($shipmentData),
            false
        ));
    }

    public function storeShipment(Request $request): RedirectResponse
    {
        $sellerId = (int) Auth::id();

        $validated = $this->validateShipment($request, $sellerId);

        if (! empty($validated['customer_id'])) {
            $selectedCustomer = Customer::query()->find($validated['customer_id']);
            if ($selectedCustomer) {
                $validated['customer_name'] = $selectedCustomer->name;
                $validated['customer_phone'] = $selectedCustomer->phone;
            }
        }

        $coordinates = $this->resolveShipmentCoordinates($validated);

        $shipment = Shipment::create([
            'seller_id' => $sellerId,
            'order_id' => $validated['order_id'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'shipment_code' => Str::upper($validated['shipment_code']),
            'shipment_date' => $validated['shipment_date'],
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'pickup_name' => $validated['pickup_name'],
            'pickup_address' => $validated['pickup_address'],
            'pickup_city' => $validated['pickup_city'],
            'pickup_postal_code' => $validated['pickup_postal_code'],
            'pickup_latitude' => $coordinates['pickup_latitude'],
            'pickup_longitude' => $coordinates['pickup_longitude'],
            'delivery_name' => $validated['delivery_name'],
            'delivery_address' => $validated['delivery_address'],
            'delivery_city' => $validated['delivery_city'],
            'delivery_postal_code' => $validated['delivery_postal_code'],
            'delivery_latitude' => $coordinates['delivery_latitude'],
            'delivery_longitude' => $coordinates['delivery_longitude'],
            'courier_name' => $validated['courier_name'],
            'weight' => $validated['weight'],
            'dimensions' => $validated['dimensions'],
            'payment_type' => strtolower($validated['payment_type']),
            'status' => strtolower($validated['status']),
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->syncOrderStatusFromShipment(
            $validated['order_id'] ?? null,
            strtolower($validated['status']),
            $sellerId
        );

        $this->notifyAdminsAboutShipmentCreated($shipment->fresh(['order.customer', 'seller']));

        return redirect()
            ->route('seller.shipments')
            ->with('success', 'Shipment ' . Str::upper($validated['shipment_code']) . ' added successfully.');
    }

    public function editShipment(Shipment $shipment)
    {
        $shipment = $this->findSellerShipmentOrFail($shipment);

        return view('spedfly.seller.create-shipment', $this->shipmentFormData($shipment, true));
    }

    public function updateShipment(Request $request, Shipment $shipment): RedirectResponse
    {
        $shipment = $this->findSellerShipmentOrFail($shipment);
        $sellerId = (int) Auth::id();
        $validated = $this->validateShipment($request, $sellerId, $shipment->id);
        $previousOrderId = $shipment->order_id;

        if (! empty($validated['customer_id'])) {
            $selectedCustomer = Customer::query()->find($validated['customer_id']);
            if ($selectedCustomer) {
                $validated['customer_name'] = $selectedCustomer->name;
                $validated['customer_phone'] = $selectedCustomer->phone;
            }
        }

        $coordinates = $this->resolveShipmentCoordinates($validated);

        $shipment->update([
            'order_id' => $validated['order_id'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'shipment_code' => Str::upper($validated['shipment_code']),
            'shipment_date' => $validated['shipment_date'],
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'pickup_name' => $validated['pickup_name'],
            'pickup_address' => $validated['pickup_address'],
            'pickup_city' => $validated['pickup_city'],
            'pickup_postal_code' => $validated['pickup_postal_code'],
            'pickup_latitude' => $coordinates['pickup_latitude'],
            'pickup_longitude' => $coordinates['pickup_longitude'],
            'delivery_name' => $validated['delivery_name'],
            'delivery_address' => $validated['delivery_address'],
            'delivery_city' => $validated['delivery_city'],
            'delivery_postal_code' => $validated['delivery_postal_code'],
            'delivery_latitude' => $coordinates['delivery_latitude'],
            'delivery_longitude' => $coordinates['delivery_longitude'],
            'courier_name' => $validated['courier_name'],
            'weight' => $validated['weight'],
            'dimensions' => $validated['dimensions'],
            'payment_type' => strtolower($validated['payment_type']),
            'status' => strtolower($validated['status']),
            'notes' => $validated['notes'] ?? null,
        ]);

        $newOrderId = $validated['order_id'] ?? null;
        $shipmentStatus = strtolower($validated['status']);

        $this->syncOrderStatusFromShipment($newOrderId, $shipmentStatus, $sellerId);

        if ($previousOrderId && $previousOrderId !== $newOrderId) {
            $this->refreshOrderStatusFromLatestShipment($previousOrderId, $sellerId);
        }

        return redirect()
            ->route('seller.shipments')
            ->with('success', 'Shipment ' . Str::upper($validated['shipment_code']) . ' updated successfully.');
    }

    public function destroyShipment(Shipment $shipment): RedirectResponse
    {
        $shipment = $this->findSellerShipmentOrFail($shipment);
        $shipmentCode = $shipment->shipment_code;
        $orderId = $shipment->order_id;
        $sellerId = (int) Auth::id();
        $shipment->delete();

        if ($orderId) {
            $this->refreshOrderStatusFromLatestShipment($orderId, $sellerId);
        }

        return redirect()
            ->route('seller.shipments')
            ->with('success', 'Shipment ' . Str::upper($shipmentCode) . ' deleted successfully.');
    }

    public function orderDetails(Order $order)
    {
        abort_unless($order->seller_id === (int) Auth::id(), 404);

        $order->load(['customer', 'items.product', 'returnRecord']);

        $shipment = Shipment::query()
            ->where('seller_id', (int) Auth::id())
            ->where('order_id', $order->id)
            ->latest('shipment_date')
            ->latest('id')
            ->first();

        $statusOptions = [
            'lead' => 'Lead',
            'new' => 'New',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'returned' => 'Returned',
        ];

        return view('spedfly.seller.order-details', compact('order', 'shipment', 'statusOptions'));
    }

    public function updateOrderStatus(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->seller_id === (int) Auth::id(), 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->sellerOrderStatusOptions()))],
        ]);

        DB::transaction(function () use ($order, $validated) {
            $this->ensureOrderStatusTransitionAllowed($order, $validated['status']);

            $order->update([
                'status' => $validated['status'],
            ]);

            $this->syncShipmentStatusFromOrder($order->fresh(['customer', 'seller']), $validated['status']);
        });

        $this->notifyAdminsAboutOrderStatusChange($order->fresh(['seller']), $validated['status']);

        return back()->with('success', 'Order status updated successfully.');
    }

    public function storeReturn(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'exists:orders,id', 'unique:order_returns,order_id'],
            'return_reason' => ['required', 'string', 'max:255'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = Order::query()->with(['customer', 'seller'])->findOrFail($validated['order_id']);

        abort_unless($order->seller_id === (int) Auth::id(), 404);
        abort_unless(strtolower((string) $order->status) !== 'returned', 409, 'This order has already been returned.');

        $previousStatus = strtolower((string) $order->status);
        $refundAmount = isset($validated['refund_amount']) && $validated['refund_amount'] !== ''
            ? (float) $validated['refund_amount']
            : 0;

        OrderReturn::query()->create([
            'return_code' => 'RTN' . now()->format('YmdHis') . str_pad((string) $order->id, 4, '0', STR_PAD_LEFT),
            'order_id' => $order->id,
            'seller_id' => (int) $order->seller_id,
            'customer_id' => (int) $order->customer_id,
            'return_reason' => $validated['return_reason'],
            'refund_amount' => $refundAmount,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
            'returned_at' => now(),
        ]);

        $order->update(['status' => 'returned']);
        $this->syncShipmentStatusFromOrder($order, 'returned');
        $this->notifyAdminsAboutOrderStatusChange($order->fresh(['seller']), 'returned');

        return back()->with('success', 'Return saved successfully.');
    }

    public function products()
    {
        $sellerId = (int) Auth::id();
        $user = Auth::user();

        if ($user && $this->shopifyConnectionData($user)['connected']) {
            $pendingProducts = Product::query()
                ->where('seller_id', $sellerId)
                ->where(function ($q) {
                    $q->whereNull('shopify_sync_status')
                      ->orWhere('shopify_sync_status', 'pending');
                })
                ->limit(20)
                ->get();

            foreach ($pendingProducts as $pendingProduct) {
                $this->syncProductWithShopify($pendingProduct, request());
            }
        }

        $productsQuery = Product::query()
            ->where('seller_id', $sellerId)
            ->latest();
        $products = $productsQuery->get();

        $totalProducts = $products->count();
        $inStockCount = $products->where('stock', '>', 0)->count();
        $lowStockCount = $products->filter(function ($product) {
            return $product->stock > 0
                && $product->low_stock_alert > 0
                && $product->stock <= $product->low_stock_alert;
        })->count();
        $outOfStockCount = $products->where('stock', '<=', 0)->count();
        $productsFeedSignature = $this->buildProductsFeedSignature($productsQuery);

        return view('spedfly.seller.products', compact(
            'products',
            'totalProducts',
            'inStockCount',
            'lowStockCount',
            'outOfStockCount',
            'productsFeedSignature'
        ));
    }

    public function productsFeed(): JsonResponse
    {
        $sellerId = (int) Auth::id();

        $products = Product::query()
            ->where('seller_id', $sellerId)
            ->get(['id', 'updated_at']);

        $latestUpdatedAt = $products->max(fn (Product $product) => optional($product->updated_at)?->timestamp ?? 0) ?? 0;

        return response()->json([
            'signature' => $products->count() . '|' . $latestUpdatedAt,
        ]);
    }

    public function productFeed(Product $product): JsonResponse
    {
        $product = $this->findSellerProductOrFail($product);

        return response()->json([
            'signature' => $product->id . '|' . optional($product->updated_at)?->timestamp,
        ]);
    }

    private function buildProductsFeedSignature($productsQuery): string
    {
        $products = (clone $productsQuery)->get(['id', 'updated_at']);
        $latestUpdatedAt = $products->max(fn (Product $product) => optional($product->updated_at)?->timestamp ?? 0) ?? 0;

        return $products->count() . '|' . $latestUpdatedAt;
    }

    public function syncProductsToShopify(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('seller.index');
        }

        $summary = $this->syncExistingProductsToShopify($user, $request);

        return redirect()
            ->route('seller.products')
            ->with('success', 'Shopify sync finished. ' . $summary);
    }

    public function addProduct()
    {
        return view('spedfly.seller.add-product', [
            'product' => new Product([
                'status' => 'active',
            ]),
            'isEdit' => false,
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $sellerId = (int) Auth::id();
        $validated = $this->validateProduct($request, $sellerId);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product = Product::query()->create([
            'seller_id' => $sellerId,
            'sku' => trim((string) $validated['sku']),
            'name' => trim((string) $validated['name']),
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'price' => (float) $validated['price'],
            'stock' => (int) $validated['stock'],
            'low_stock_alert' => (int) ($validated['low_stock_alert'] ?? 0),
            'image_path' => $imagePath,
            'status' => $validated['status'] ?? 'active',
            'shopify_sync_status' => 'pending',
        ]);

        $syncResult = $this->syncProductWithShopify($product->fresh(), $request);
        $message = $syncResult['ok']
            ? __('ui.product_created_success')
            : __('ui.product_created_warning');

        return redirect()
            ->route('seller.products')
            ->with($syncResult['ok'] ? 'success' : 'warning', $message . ' ' . $syncResult['message']);
    }

    public function editProduct(Product $product)
    {
        $product = $this->findSellerProductOrFail($product);

        return view('spedfly.seller.add-product', [
            'product' => $product,
            'isEdit' => true,
        ]);
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $product = $this->findSellerProductOrFail($product);
        $sellerId = (int) Auth::id();
        $validated = $this->validateProduct($request, $sellerId, $product->id);

        $imagePath = $product->image_path;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');

            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
        }

        $product->update([
            'sku' => trim((string) $validated['sku']),
            'name' => trim((string) $validated['name']),
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'price' => (float) $validated['price'],
            'stock' => (int) $validated['stock'],
            'low_stock_alert' => (int) ($validated['low_stock_alert'] ?? 0),
            'image_path' => $imagePath,
            'status' => $validated['status'] ?? 'active',
            'shopify_sync_status' => 'pending',
        ]);

        $syncResult = $this->syncProductWithShopify($product->fresh(), $request);
        $message = $syncResult['ok']
            ? __('ui.product_updated_success')
            : __('ui.product_updated_warning');

        return redirect()
            ->route('seller.products')
            ->with($syncResult['ok'] ? 'success' : 'warning', $message . ' ' . $syncResult['message']);
    }

    public function destroyProduct(Product $product): RedirectResponse
    {
        $product = $this->findSellerProductOrFail($product);

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $this->deleteShopifyProduct($product);
        $product->delete();

        return redirect()
            ->route('seller.products')
            ->with('success', __('ui.product_deleted_success'));
    }

    protected function validateProduct(Request $request, int $sellerId, ?int $productId = null): array
    {
        return $request->validate([
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'sku')
                    ->where(fn ($query) => $query->where('seller_id', $sellerId))
                    ->ignore($productId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'low_stock_alert' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
            'status' => ['nullable', 'in:active,deactive'],
        ]);
    }

    protected function findSellerProductOrFail(Product $product): Product
    {
        abort_unless($product->seller_id === (int) Auth::id(), 404);

        return $product;
    }

    protected function shipmentFormData(Shipment $shipment, bool $isEdit): array
    {
        $sellerId = (int) Auth::id();

        $ordersQuery = Order::query()
            ->where('seller_id', $sellerId)
            ->where(function ($query) use ($shipment, $isEdit) {
                $query->whereIn('status', ['New', 'new']);

                if ($isEdit && $shipment->order_id) {
                    $query->orWhere('id', $shipment->order_id);
                }
            });

        $orders = $ordersQuery
            ->orderByDesc('ordered_at')
            ->get(['id', 'external_order_id']);

        $customers = Customer::query()
            ->where('seller_id', $sellerId)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        return [
            'orders' => $orders,
            'customers' => $customers,
            'couriers' => ['DHL', 'FedEx', 'UPS', 'Delhivery'],
            'statuses' => [
                'pending' => 'Pending',
                'in_transit' => 'In Transit',
                'delivered' => 'Delivered',
                'delayed' => 'Delayed',
                'returned' => 'Returned',
            ],
            'payments' => [
                'prepaid' => 'Prepaid',
                'cod' => 'Cash on Delivery',
            ],
            'defaultShipmentCode' => $shipment->shipment_code ?: 'SHP-' . Str::upper(Str::random(6)),
            'shipment' => $shipment,
            'isEdit' => $isEdit,
        ];
    }

    protected function validateShipment(Request $request, int $sellerId, ?int $shipmentId = null): array
    {
        return $request->validate([
            'order_id' => [
                'nullable',
                Rule::exists('orders', 'id')->where(fn ($query) => $query->where('seller_id', $sellerId)),
            ],
            'customer_id' => [
                'nullable',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('seller_id', $sellerId)),
            ],
            'shipment_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('shipments', 'shipment_code')
                    ->where(fn ($query) => $query->where('seller_id', $sellerId))
                    ->ignore($shipmentId),
            ],
            'shipment_date' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'pickup_name' => ['required', 'string', 'max:255'],
            'pickup_address' => ['required', 'string', 'max:2000'],
            'pickup_city' => ['required', 'string', 'max:255'],
            'pickup_postal_code' => ['required', 'string', 'max:20'],
            'delivery_name' => ['required', 'string', 'max:255'],
            'delivery_address' => ['required', 'string', 'max:2000'],
            'delivery_city' => ['required', 'string', 'max:255'],
            'delivery_postal_code' => ['required', 'string', 'max:20'],
            'courier_name' => ['required', 'string', 'max:100'],
            'weight' => ['required', 'numeric', 'min:0'],
            'dimensions' => ['required', 'string', 'max:100'],
            'payment_type' => ['required', 'in:prepaid,cod'],
            'status' => ['required', 'in:pending,in_transit,delivered,delayed,returned'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    protected function findSellerShipmentOrFail(Shipment $shipment): Shipment
    {
        abort_unless($shipment->seller_id === (int) Auth::id(), 404);

        return $shipment;
    }

    protected function syncOrderStatusFromShipment(?int $orderId, string $shipmentStatus, int $sellerId): void
    {
        if (! $orderId) {
            return;
        }

        $orderStatus = $this->mapShipmentStatusToOrderStatus($shipmentStatus);
        if (! $orderStatus) {
            return;
        }

        Order::query()
            ->where('id', $orderId)
            ->where('seller_id', $sellerId)
            ->update(['status' => $orderStatus]);
    }

    protected function refreshOrderStatusFromLatestShipment(int $orderId, int $sellerId): void
    {
        $latestShipment = Shipment::query()
            ->where('order_id', $orderId)
            ->where('seller_id', $sellerId)
            ->latest('shipment_date')
            ->latest('id')
            ->first();

        if (! $latestShipment) {
            return;
        }

        $this->syncOrderStatusFromShipment($orderId, (string) $latestShipment->status, $sellerId);
    }

    protected function mapShipmentStatusToOrderStatus(string $shipmentStatus): ?string
    {
        return [
            'pending' => 'processing',
            'in_transit' => 'shipped',
            'delayed' => 'shipped',
            'delivered' => 'delivered',
            'returned' => 'returned',
        ][strtolower($shipmentStatus)] ?? null;
    }

    protected function sellerOrderStatusOptions(): array
    {
        return [
            'lead' => 'Lead',
            'new' => 'New',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'returned' => 'Returned',
        ];
    }

    protected function ensureOrderStatusTransitionAllowed(Order $order, string $newStatus): void
    {
        $currentStatus = strtolower((string) $order->status);
        $newStatus = strtolower($newStatus);

        if ($currentStatus === $newStatus) {
            return;
        }

        $allowedTransitions = [
            'lead' => ['new'],
            'new' => ['processing', 'shipped', 'returned'],
            'processing' => ['shipped', 'returned'],
            'shipped' => ['delivered', 'returned'],
            'delivered' => ['returned'],
            'returned' => [],
        ];

        abort_unless(
            in_array($newStatus, $allowedTransitions[$currentStatus] ?? [], true),
            409,
            'Order status has already changed. Please refresh the page and try again.'
        );
    }

    protected function syncShipmentStatusFromOrder(Order $order, string $orderStatus): void
    {
        $order->loadMissing(['customer', 'seller']);

        $shipmentStatus = [
            'new' => 'pending',
            'processing' => 'pending',
            'shipped' => 'in_transit',
            'delivered' => 'delivered',
            'returned' => 'returned',
        ][strtolower($orderStatus)] ?? null;

        if (! $shipmentStatus) {
            return;
        }

        $shipment = Shipment::query()
            ->where('order_id', $order->id)
            ->where('seller_id', $order->seller_id)
            ->latest('shipment_date')
            ->latest('id')
            ->first();

        if ($shipment) {
            $shipment->update(['status' => $shipmentStatus]);
            return;
        }

        if (strtolower($orderStatus) !== 'shipped') {
            return;
        }

        Shipment::query()->create($this->buildShipmentDataFromOrder($order, $shipmentStatus));
    }

    protected function buildShipmentDataFromOrder(Order $order, string $shipmentStatus): array
    {
        $order->loadMissing(['customer', 'seller']);

        $seller = $order->seller;
        $customer = $order->customer;

        $pickupName = trim((string) ($seller?->pickup_name ?? ''))
            ?: trim((string) ($seller?->company_name ?? ''))
            ?: trim((string) ($seller?->brand_name ?? ''))
            ?: trim((string) ($seller?->name ?? ''))
            ?: 'Warehouse';
        $pickupAddress = trim((string) ($seller?->pickup_address ?? ''))
            ?: trim((string) ($seller?->company_name ?? ''))
            ?: trim((string) ($seller?->brand_name ?? ''))
            ?: 'Pickup address not set';
        $pickupCity = trim((string) ($seller?->pickup_city ?? '')) ?: 'N/A';
        $pickupPostalCode = trim((string) ($seller?->pickup_postal_code ?? '')) ?: '000000';
        $pickupLatitude = $seller?->pickup_latitude;
        $pickupLongitude = $seller?->pickup_longitude;

        $deliveryAddress = trim((string) ($customer?->address ?? ''));
        if ($deliveryAddress === '') {
            $deliveryAddress = trim((string) ($customer?->name ?? ''));
        }
        if ($deliveryAddress === '') {
            $deliveryAddress = 'Delivery address not set';
        }

        $deliveryName = trim((string) ($customer?->name ?? ''));
        if ($deliveryName === '') {
            $deliveryName = 'Customer #' . $customer?->id;
        }

        [$deliveryCity, $deliveryPostalCode] = $this->extractAddressCityAndPostalCode($deliveryAddress);
        $deliveryCity = $deliveryCity !== '' ? $deliveryCity : 'N/A';
        $deliveryPostalCode = $deliveryPostalCode !== '' ? $deliveryPostalCode : '000000';
        $deliveryCoordinates = $this->geocodeAddress(
            $deliveryAddress,
            $deliveryCity !== 'N/A' ? $deliveryCity : null,
            $deliveryPostalCode !== '000000' ? $deliveryPostalCode : null
        );
        $paymentType = strtolower(trim((string) ($order->payment_type ?? '')));
        if (! in_array($paymentType, ['prepaid', 'cod'], true)) {
            $paymentType = 'cod';
        }

        $externalOrderId = trim((string) ($order->external_order_id ?? ''));
        $shipmentSeed = preg_replace('/[^A-Za-z0-9]+/', '', $externalOrderId) ?: ('ORD' . $order->id);

        return [
            'seller_id' => (int) $order->seller_id,
            'order_id' => (int) $order->id,
            'customer_id' => $order->customer_id ? (int) $order->customer_id : null,
            'shipment_code' => 'SHP-' . Str::upper(Str::substr($shipmentSeed, 0, 20)) . '-' . Str::upper(Str::random(6)),
            'shipment_date' => now()->toDateString(),
            'customer_name' => $deliveryName,
            'customer_phone' => trim((string) ($customer?->phone ?? '')),
            'pickup_name' => $pickupName,
            'pickup_address' => $pickupAddress,
            'pickup_city' => $pickupCity,
            'pickup_postal_code' => $pickupPostalCode,
            'pickup_latitude' => $pickupLatitude,
            'pickup_longitude' => $pickupLongitude,
            'delivery_name' => $deliveryName,
            'delivery_address' => $deliveryAddress,
            'delivery_city' => $deliveryCity,
            'delivery_postal_code' => $deliveryPostalCode,
            'delivery_latitude' => $deliveryCoordinates['lat'],
            'delivery_longitude' => $deliveryCoordinates['lng'],
            'courier_name' => 'Unassigned',
            'weight' => 0,
            'dimensions' => '0x0x0',
            'payment_type' => $paymentType,
            'status' => $shipmentStatus,
            'notes' => 'Auto-created when order #' . ($order->external_order_id ?: $order->id) . ' was marked shipped.',
        ];
    }

    protected function extractAddressCityAndPostalCode(string $address): array
    {
        $normalizedAddress = trim(preg_replace('/\s+/', ' ', $address) ?? $address);
        $postalCode = '';

        if (preg_match('/\b(\d{5,6})\b/', $normalizedAddress, $matches)) {
            $postalCode = $matches[1];
        }

        $segments = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', $normalizedAddress) ?: [])));
        $city = '';

        if (count($segments) >= 2) {
            $candidate = $segments[count($segments) - 2];
            $candidate = preg_replace('/\b\d{5,6}\b/', '', $candidate);
            $candidate = trim((string) preg_replace('/\s+/', ' ', $candidate), " -");
            $city = $candidate;
        }

        if ($city === '' && $postalCode !== '') {
            $city = trim(str_replace($postalCode, '', $normalizedAddress));
            $city = trim($city, ", -");
        }

        return [$city, $postalCode];
    }

    protected function notifyAdminsAboutOrderStatusChange(Order $order, string $newStatus): void
    {
        $adminIds = User::query()
            ->where('type', 'admin')
            ->pluck('id');

        if ($adminIds->isEmpty()) {
            return;
        }

        $statusLabel = $this->sellerOrderStatusOptions()[strtolower($newStatus)] ?? ucfirst($newStatus);
        $sellerName = $order->seller?->name ?? ('Seller #' . $order->seller_id);
        $now = now();

        $rows = [];
        foreach ($adminIds as $adminId) {
            $rows[] = [
                'user_id' => (int) $adminId,
                'title' => 'Seller Updated Order Status',
                'message' => $sellerName . ' changed order ' . $order->external_order_id . ' to ' . $statusLabel . '.',
                'type' => 'order_status',
                'is_read' => false,
                'read_at' => null,
                'data' => [
                    'order_id' => $order->id,
                    'external_order_id' => $order->external_order_id,
                    'seller_id' => $order->seller_id,
                    'status' => strtolower($newStatus),
                ],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($rows as $row) {
            AppNotification::query()->create($row);
        }
    }

    protected function notifyAdminsAboutCallLog(CallCenterLog $callLog): void
    {
        $adminIds = User::query()
            ->where('type', 'admin')
            ->pluck('id');

        if ($adminIds->isEmpty()) {
            return;
        }

        $sellerName = $callLog->seller?->name ?? ('Seller #' . $callLog->seller_id);
        $customerName = $callLog->customer?->name ?? ('Customer #' . $callLog->customer_id);
        $orderCode = $callLog->order?->external_order_id;
        $resultLabel = ucfirst(str_replace('_', ' ', $callLog->result));
        $message = $sellerName . ' logged a ' . $resultLabel . ' call for ' . $customerName . '.';

        if ($orderCode) {
            $message .= ' Order: ' . $orderCode . '.';
        }

        $now = now();
        $rows = [];

        foreach ($adminIds as $adminId) {
            $rows[] = [
                'user_id' => (int) $adminId,
                'title' => 'Seller Logged Call Activity',
                'message' => $message,
                'type' => 'call_center',
                'is_read' => false,
                'read_at' => null,
                'data' => [
                    'call_log_id' => $callLog->id,
                    'call_code' => $callLog->call_code,
                    'seller_id' => $callLog->seller_id,
                    'customer_id' => $callLog->customer_id,
                    'order_id' => $callLog->order_id,
                    'result' => $callLog->result,
                    'agent_name' => $callLog->agent_name,
                    'call_time' => optional($callLog->call_time)->toDateTimeString(),
                ],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($rows as $row) {
            AppNotification::query()->create($row);
        }
    }

    protected function notifyAdminsAboutShipmentCreated(Shipment $shipment): void
    {
        $adminIds = User::query()
            ->where('type', 'admin')
            ->pluck('id');

        if ($adminIds->isEmpty()) {
            return;
        }

        $sellerName = $shipment->seller?->name ?? ('Seller #' . $shipment->seller_id);
        $orderCode = $shipment->order?->external_order_id;
        $customerName = $shipment->customer_name ?: ($shipment->order?->customer?->name ?? 'Customer');

        $message = $sellerName . ' created shipment ' . $shipment->shipment_code . ' for ' . $customerName . '.';
        if ($orderCode) {
            $message .= ' Linked order: ' . $orderCode . '.';
        }

        $now = now();
        $rows = [];

        foreach ($adminIds as $adminId) {
            $rows[] = [
                'user_id' => (int) $adminId,
                'title' => 'Seller Created Shipment',
                'message' => $message,
                'type' => 'shipment_created',
                'is_read' => false,
                'read_at' => null,
                'data' => [
                    'shipment_id' => $shipment->id,
                    'shipment_code' => $shipment->shipment_code,
                    'seller_id' => $shipment->seller_id,
                    'order_id' => $shipment->order_id,
                    'external_order_id' => $orderCode,
                    'customer_name' => $customerName,
                    'status' => strtolower((string) $shipment->status),
                    'shipment_date' => optional($shipment->shipment_date)->toDateString(),
                ],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($rows as $row) {
            AppNotification::query()->create($row);
        }
    }

    protected function resolveShipmentCoordinates(array $validated): array
    {
        $pickupCoordinates = $this->geocodeAddress(
            $validated['pickup_address'],
            $validated['pickup_city'],
            $validated['pickup_postal_code']
        );

        $deliveryCoordinates = $this->geocodeAddress(
            $validated['delivery_address'],
            $validated['delivery_city'],
            $validated['delivery_postal_code']
        );

        return [
            'pickup_latitude' => $pickupCoordinates['lat'],
            'pickup_longitude' => $pickupCoordinates['lng'],
            'delivery_latitude' => $deliveryCoordinates['lat'],
            'delivery_longitude' => $deliveryCoordinates['lng'],
        ];
    }

    protected function geocodeAddress(string $address, ?string $city = null, ?string $postalCode = null): array
    {
        $query = trim(implode(', ', array_filter([
            trim($address),
            trim((string) $city),
            trim((string) $postalCode),
        ])));

        if ($query === '') {
            return ['lat' => null, 'lng' => null];
        }

        try {
            $googleMapsKey = (string) config('services.google_maps.key');

            if ($googleMapsKey !== '') {
                $response = Http::timeout(8)
                    ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                        'address' => $query,
                        'key' => $googleMapsKey,
                    ]);

                if ($response->successful()) {
                    $result = $response->json();
                    $first = is_array($result['results'] ?? null) ? ($result['results'][0] ?? null) : null;
                    $location = $first['geometry']['location'] ?? null;

                    if (is_array($location) && isset($location['lat'], $location['lng'])) {
                        return [
                            'lat' => (float) $location['lat'],
                            'lng' => (float) $location['lng'],
                        ];
                    }
                }
            }

            $response = Http::withHeaders([
                'User-Agent' => config('app.name', 'SpedFly') . '/1.0',
                'Accept' => 'application/json',
            ])
                ->timeout(8)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 1,
                ]);

            if (! $response->successful()) {
                return ['lat' => null, 'lng' => null];
            }

            $result = $response->json();
            $first = is_array($result) ? ($result[0] ?? null) : null;

            if (! is_array($first) || ! isset($first['lat'], $first['lon'])) {
                return ['lat' => null, 'lng' => null];
            }

            return [
                'lat' => (float) $first['lat'],
                'lng' => (float) $first['lon'],
            ];
        } catch (\Throwable $exception) {
            return ['lat' => null, 'lng' => null];
        }
    }

    private function parseCallDuration(?string $duration): int
    {
        if (! $duration) {
            return 0;
        }

        [$minutes, $seconds] = array_map('intval', explode(':', $duration));

        return ($minutes * 60) + $seconds;
    }

    private function formatCallDuration(int $durationSeconds): string
    {
        $minutes = intdiv($durationSeconds, 60);
        $seconds = $durationSeconds % 60;

        return sprintf('%dm %02ds', $minutes, $seconds);
    }
}







