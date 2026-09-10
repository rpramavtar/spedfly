<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\ActivityLog;
use App\Models\CallCenterLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\SystemSetting;
use App\Models\SystemSettingHistory;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\SellerFeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Notifications\ResetPassword;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class AdminController extends Controller
{
    public function login()
    {
        return view('spedfly.admin.login');
    }

    public function forgotPassword()
    {
        return view('spedfly.admin.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        ResetPassword::createUrlUsing(function (User $user, string $token) {
            return route('admin.password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        $status = Password::broker()->sendResetLink([
            'email' => $validated['email'],
            'type' => 'admin',
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()
                ->withErrors(['email' => 'We could not find an admin account with that email address.'])
                ->withInput($request->only('email'));
        }

        return back()->with('status', 'We sent a password reset link to your email address.');
    }

    public function resetPasswordForm(Request $request, string $token)
    {
        return view('spedfly.admin.reset-password', [
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
            'type' => 'admin',
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
            ->route('admin.login')
            ->with('status', 'Password updated successfully. You can log in now.');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials['type'] = 'admin';

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Invalid credentials or you are not an admin.'])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $redirectRoute = Auth::user()?->type === 'admin' ? 'admin.login' : 'seller.login';

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($redirectRoute);
    }

    public function dashboard(Request $request)
    {
        $data = $this->buildAdminDashboardData($request);
        $data['currencySymbol'] = system_currency_symbol();

        return view('spedfly.admin.dashboard', $data);
    }

    private function currencySymbolForCode(?string $code): string
    {
        return match (strtoupper(trim((string) $code))) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'INR' => '₹',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'JPY' => '¥',
            default => system_currency_symbol(),
        };
    }

    public function profile()
    {
        $user = Auth::user();

        return view('spedfly.admin.profile', [
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

        if (! empty($validated['password'] ?? null)) {
            $updates['password'] = Hash::make($validated['password']);
        }

        if ($request->hasFile('avatar')) {
            if ($user?->avatar_path) {
                File::delete(public_path($user->avatar_path));
            }

            $avatarFile = $request->file('avatar');
            $avatarDirectory = public_path('uploads/avatars/admin');
            File::ensureDirectoryExists($avatarDirectory);

            $avatarName = 'admin-' . ($user?->id ?? 'profile') . '-' . now()->format('YmdHis') . '.' . $avatarFile->getClientOriginalExtension();
            $avatarFile->move($avatarDirectory, $avatarName);
            $updates['avatar_path'] = 'uploads/avatars/admin/' . $avatarName;
        }

        $user?->update($updates);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function sellers(Request $request)
    {
        $sellerQuery = User::query()->where('type', 'seller');

        $search = trim((string) $request->input('name', ''));
        $status = strtolower(trim((string) $request->input('status', 'all')));
        if ($status === 'suspended') {
            $status = 'deactive';
        }
        if (! in_array($status, ['all', 'active', 'deactive'], true)) {
            $status = 'all';
        }

        if ($search !== '') {
            $sellerQuery->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        if ($status !== 'all') {
            $sellerQuery->where('status', $status);
        }

        $sellers = $sellerQuery
            ->withCount('orders')
            ->withSum('orders as orders_revenue', 'amount')
            ->latest()
            ->get();

        $activeSellersCount = User::where('type', 'seller')->where('status', 'active')->count();
        $newThisMonthCount = User::where('type', 'seller')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
        $topSeller = User::where('type', 'seller')->orderBy('created_at')->first();

        return view('spedfly.admin.sellers', compact(
            'sellers',
            'activeSellersCount',
            'newThisMonthCount',
            'topSeller',
            'search',
            'status'
        ));
    }

    public function showSeller(Request $request, User $seller)
    {
        if ($seller->type !== 'seller') {
            abort(404);
        }

        $feeService = app(SellerFeeService::class);
        $feeSummary = $feeService->buildDashboardSummary($seller);

        $orders = Order::query()
            ->with(['customer', 'seller'])
            ->where('seller_id', $seller->id)
            ->latest()
            ->limit(5)
            ->get();

        $customers = Customer::query()
            ->where('seller_id', $seller->id)
            ->latest()
            ->limit(5)
            ->get();

        $shipments = Shipment::query()
            ->where('seller_id', $seller->id)
            ->latest()
            ->limit(5)
            ->get();

        $productSearch = trim((string) $request->input('product_q', ''));
        $productPerPage = (int) $request->input('product_per_page', 5);
        if (! in_array($productPerPage, [5, 10, 25, 50], true)) {
            $productPerPage = 5;
        }

        $productsQuery = Product::query()
            ->where('seller_id', $seller->id);

        if ($productSearch !== '') {
            $productsQuery->where(function ($query) use ($productSearch) {
                $query
                    ->where('sku', 'like', '%' . $productSearch . '%')
                    ->orWhere('name', 'like', '%' . $productSearch . '%')
                    ->orWhere('description', 'like', '%' . $productSearch . '%')
                    ->orWhere('category', 'like', '%' . $productSearch . '%');
            });
        }

        $products = $productsQuery
            ->latest()
            ->paginate($productPerPage)
            ->withQueryString();

        $feeRules = $seller->feeRules()
            ->orderBy('fee_type')
            ->orderBy('billing_unit')
            ->orderByRaw('COALESCE(min_quantity, 0) ASC')
            ->get();

        $stats = [
            'orders' => Order::where('seller_id', $seller->id)->count(),
            'customers' => Customer::where('seller_id', $seller->id)->count(),
            'shipments' => Shipment::where('seller_id', $seller->id)->count(),
            'products' => Product::where('seller_id', $seller->id)->count(),
            'revenue' => (float) Order::where('seller_id', $seller->id)->sum('amount'),
        ];

        return view('spedfly.admin.seller-details', [
            'seller' => $seller,
            'orders' => $orders,
            'customers' => $customers,
            'shipments' => $shipments,
            'feeRules' => $feeRules,
            'feeSummary' => $feeSummary,
            'feeTypeOptions' => $feeService->feeTypeOptions(),
            'billingUnitOptions' => $feeService->billingUnitOptions(),
            'products' => $products,
            'productSearch' => $productSearch,
            'productPerPage' => $productPerPage,
            'stats' => $stats,
        ]);
    }

    public function updateSellerFees(Request $request, User $seller): RedirectResponse
    {
        if ($seller->type !== 'seller') {
            abort(404);
        }

        $feeService = app(SellerFeeService::class);
        $feeTypeOptions = array_keys($feeService->feeTypeOptions());
        $billingUnitOptions = array_keys($feeService->billingUnitOptions());
        $rawRows = $request->input('fee_rules', []);

        if (! is_array($rawRows)) {
            $rawRows = [];
        }

        $normalizedRows = [];
        $errors = [];

        foreach ($rawRows as $index => $row) {
            $row = is_array($row) ? $row : [];
            $hasContent = collect($row)
                ->except(['is_active'])
                ->filter(function ($value) {
                    return $value !== null && $value !== '';
                })
                ->isNotEmpty();

            if (! $hasContent) {
                continue;
            }

            $validator = Validator::make($row, [
                'label' => ['nullable', 'string', 'max:255'],
                'fee_type' => ['required', Rule::in($feeTypeOptions)],
                'billing_unit' => ['required', Rule::in($billingUnitOptions)],
                'min_quantity' => ['nullable', 'integer', 'min:0'],
                'max_quantity' => ['nullable', 'integer', 'min:0'],
                'rate' => ['required', 'numeric', 'min:0'],
                'is_active' => ['nullable', 'boolean'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors['fee_rules.' . $index . '.' . $field] = $messages[0];
                }
                continue;
            }

            $validatedRow = $validator->validated();
            $minQuantity = isset($validatedRow['min_quantity']) && $validatedRow['min_quantity'] !== ''
                ? (int) $validatedRow['min_quantity']
                : null;
            $maxQuantity = isset($validatedRow['max_quantity']) && $validatedRow['max_quantity'] !== ''
                ? (int) $validatedRow['max_quantity']
                : null;

            if ($minQuantity !== null && $maxQuantity !== null && $maxQuantity < $minQuantity) {
                $errors['fee_rules.' . $index . '.max_quantity'] = 'Maximum quantity must be greater than or equal to minimum quantity.';
                continue;
            }

            $normalizedRows[] = [
                'label' => trim((string) ($validatedRow['label'] ?? '')),
                'fee_type' => $validatedRow['fee_type'],
                'billing_unit' => $validatedRow['billing_unit'],
                'min_quantity' => $minQuantity,
                'max_quantity' => $maxQuantity,
                'rate' => (float) $validatedRow['rate'],
                'currency' => system_currency_code(),
                'is_active' => array_key_exists('is_active', $row) ? filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN) : true,
                'notes' => trim((string) ($validatedRow['notes'] ?? '')) ?: null,
            ];
        }

        if (! empty($errors)) {
            return back()
                ->withErrors($errors)
                ->withInput()
                ->with('openFeeRulesModal', true);
        }

        DB::transaction(function () use ($seller, $normalizedRows): void {
            $seller->feeRules()->delete();

            foreach ($normalizedRows as $row) {
                $seller->feeRules()->create([
                    'label' => $row['label'] !== '' ? $row['label'] : null,
                    'fee_type' => $row['fee_type'],
                    'billing_unit' => $row['billing_unit'],
                    'min_quantity' => $row['min_quantity'],
                    'max_quantity' => $row['max_quantity'],
                    'rate' => $row['rate'],
                    'currency' => $row['currency'],
                    'is_active' => $row['is_active'],
                    'notes' => $row['notes'],
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
            }
        });

        return back()->with('success', 'Seller fee rules updated successfully.');
    }

    public function storeSeller(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'company_name' => ['required', 'string', 'max:255'],
            'pickup_address' => ['required', 'string', 'max:2000'],
            'pickup_latitude' => ['nullable', 'numeric'],
            'pickup_longitude' => ['nullable', 'numeric'],
            'password' => ['nullable', 'string', 'min:8'],
            'iban_code' => ['nullable', 'string', 'max:255'],
            'telephone_number' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
        ]);

        $plainPassword = $validated['password'] ?? 'Seller@123';

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'company_name' => $validated['company_name'],
            'type' => 'seller',
            'status' => 'active',
            'iban_code' => $validated['iban_code'] ?? null,
            'pickup_address' => $validated['pickup_address'],
            'pickup_latitude' => $validated['pickup_latitude'] ?? null,
            'pickup_longitude' => $validated['pickup_longitude'] ?? null,
            'support_phone' => $validated['telephone_number'] ?? null,
            'vat_number' => $validated['vat_number'] ?? null,
            'password' => Hash::make($plainPassword),
        ]);

        return redirect()
            ->route('admin.sellers')
            ->with('success', 'Seller added successfully.');
    }

    public function updateSeller(Request $request, User $seller): RedirectResponse
    {
        if ($seller->type !== 'seller') {
            abort(404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($seller->id)],
            'company_name' => ['required', 'string', 'max:255'],
            'pickup_address' => ['required', 'string', 'max:2000'],
            'pickup_latitude' => ['nullable', 'numeric'],
            'pickup_longitude' => ['nullable', 'numeric'],
            'status' => ['required', 'in:active,deactive'],
            'password' => ['nullable', 'string', 'min:8'],
            'iban_code' => ['nullable', 'string', 'max:255'],
            'telephone_number' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('openEditSellerModal', $seller->id);
        }

        $validated = $validator->validated();

        $updates = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'company_name' => $validated['company_name'],
            'status' => $validated['status'],
            'iban_code' => $validated['iban_code'] ?? null,
            'pickup_address' => $validated['pickup_address'],
            'pickup_latitude' => $validated['pickup_latitude'] ?? null,
            'pickup_longitude' => $validated['pickup_longitude'] ?? null,
            'support_phone' => $validated['telephone_number'] ?? null,
            'vat_number' => $validated['vat_number'] ?? null,
        ];

        if (! empty($validated['password'])) {
            $updates['password'] = Hash::make($validated['password']);
        }

        $seller->update($updates);

        return redirect()
            ->route('admin.sellers')
            ->with('success', 'Seller updated successfully.');
    }

    public function updateSellerStatus(Request $request, User $seller): RedirectResponse
    {
        if ($seller->type !== 'seller') {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:active,deactive'],
        ]);

        $seller->update([
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Seller status updated successfully.');
    }

    public function destroySeller(User $seller): RedirectResponse
    {
        if ($seller->type !== 'seller') {
            abort(404);
        }

        $seller->delete();

        return redirect()
            ->route('admin.sellers')
            ->with('success', 'Seller deleted successfully.');
    }

    public function customers(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $sellerId = $request->input('seller', '');
        $statusFilter = strtolower(trim((string) $request->input('status', 'all')));
        if (! in_array($statusFilter, ['all', 'active', 'blocked'], true)) {
            $statusFilter = 'all';
        }

        $customersQuery = Customer::with(['seller'])->withCount('orders');

        if ($search !== '') {
            $customersQuery->where(function ($query) use ($search) {
                $query
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        if ($sellerId !== '' && $sellerId !== null) {
            $customersQuery->where('seller_id', $sellerId);
        }

        if ($statusFilter !== 'all') {
            $customersQuery->where('status', $statusFilter);
        }

        $customers = $customersQuery->latest()->get();

        $totalCustomers = Customer::count();
        $newCustomersThisMonth = Customer::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
        $sellersWithCustomers = Customer::distinct()->count('seller_id');
        $sellerOptions = User::where('type', 'seller')->orderBy('name')->get(['id', 'name']);

        return view('spedfly.admin.customers', compact(
            'customers',
            'totalCustomers',
            'newCustomersThisMonth',
            'sellersWithCustomers',
            'search',
            'sellerId',
            'sellerOptions',
            'statusFilter'
        ));
    }

    public function sellerWallet(Request $request, User $seller)
    {
        if ($seller->type !== 'seller') {
            abort(404);
        }
        $wallet = $seller->getOrCreateWallet();
        $transactions = $wallet->transactions()->latest()->paginate(15);
        
        $ordersThisMonth = $seller->orders()
            ->whereYear('ordered_at', now()->year)
            ->whereMonth('ordered_at', now()->month)
            ->count();
            
        return view('spedfly.admin.seller-wallet', compact('seller', 'wallet', 'transactions', 'ordersThisMonth'));
    }

    public function topupSellerWallet(Request $request, User $seller): RedirectResponse
    {
        if ($seller->type !== 'seller') {
            abort(404);
        }
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', 'string', 'in:topup,deduct'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = (float) $request->input('amount');
        $type = $request->input('type');
        $description = $request->input('description') ?: 'Manual ' . $type;

        if ($type === 'deduct') {
            $seller->chargeWallet($amount, null, $description);
        } else {
            $seller->topupWallet($amount, $description);
        }

        return redirect()->back()->with('success', 'Seller wallet balance updated successfully.');
    }

    public function updateSellerWalletThreshold(Request $request, User $seller): RedirectResponse
    {
        if ($seller->type !== 'seller') {
            abort(404);
        }
        $request->validate([
            'min_threshold' => ['required', 'numeric', 'min:0'],
        ]);

        $wallet = $seller->getOrCreateWallet();
        $wallet->min_threshold = (float) $request->input('min_threshold');
        $wallet->save();

        return redirect()->back()->with('success', 'Seller wallet low balance threshold updated successfully.');
    }

    public function withdrawals(Request $request)
    {
        $status = $request->input('status', 'all');
        $query = WithdrawalRequest::with('seller');
        
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        
        $withdrawals = $query->latest()->get();
        return view('spedfly.admin.withdrawals', compact('withdrawals', 'status'));
    }

    public function approveWithdrawal(WithdrawalRequest $request): RedirectResponse
    {
        if ($request->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending requests can be approved.');
        }

        $request->update(['status' => 'approved']);

        return redirect()->back()->with('success', 'Withdrawal request approved successfully.');
    }

    public function rejectWithdrawal(Request $req, WithdrawalRequest $request): RedirectResponse
    {
        if ($request->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending requests can be rejected.');
        }

        $amount = (float) $request->amount;
        $seller = $request->seller;

        DB::transaction(function () use ($request, $seller, $amount, $req) {
            $request->update([
                'status' => 'rejected',
                'admin_notes' => $req->input('admin_notes') ?: 'Rejected by Admin',
            ]);

            // Refund the deducted amount to seller's wallet
            $seller->topupWallet($amount, "Withdrawal request rejected (Request ID: #{$request->id}) - Refunded");
        });

        return redirect()->back()->with('success', 'Withdrawal request rejected and funds refunded successfully.');
    }

    public function products(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $sellerId = $request->input('seller', '');
        $statusFilter = strtolower(trim((string) $request->input('status', 'all')));

        if (! in_array($statusFilter, ['all', 'active', 'inactive', 'draft'], true)) {
            $statusFilter = 'all';
        }

        $productsQuery = Product::query()->with('seller');

        if ($search !== '') {
            $productsQuery->where(function ($query) use ($search) {
                $query
                    ->where('sku', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%')
                    ->orWhereHas('seller', function ($sellerQuery) use ($search) {
                        $sellerQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($sellerId !== '' && $sellerId !== null) {
            $productsQuery->where('seller_id', $sellerId);
        }

        if ($statusFilter !== 'all') {
            $productsQuery->where('status', $statusFilter);
        }

        $products = $productsQuery->latest()->get();
        $sellerOptions = User::where('type', 'seller')->orderBy('name')->get(['id', 'name']);

        $totalProducts = Product::count();
        $activeProductsCount = Product::where('status', 'active')->count();
        $lowStockCount = Product::where('stock', '>', 0)
            ->whereColumn('stock', '<=', 'low_stock_alert')
            ->count();
        $outOfStockCount = Product::where('stock', '<=', 0)->count();

        return view('spedfly.admin.products', compact(
            'products',
            'sellerOptions',
            'search',
            'sellerId',
            'statusFilter',
            'totalProducts',
            'activeProductsCount',
            'lowStockCount',
            'outOfStockCount'
        ));
    }

    public function storeCustomer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:users,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,blocked'],
        ]);

        Customer::create($validated);

        return back()->with('success', 'Customer added successfully.');
    }

    public function updateCustomer(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:users,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,blocked'],
        ]);

        $customer->update($validated);

        return back()->with('success', 'Customer updated successfully.');
    }

    public function updateCustomerStatus(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,blocked'],
        ]);

        $customer->update(['status' => $validated['status']]);

        return back()->with('success', 'Customer status updated.');
    }

    public function destroyCustomer(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return back()->with('success', 'Customer deleted.');
    }

    public function orders(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $statusFilter = strtolower(trim((string) $request->input('status', 'all')));
        $orderDate = $request->input('from');

        $statusOptions = $this->orderStatusOptions();

        $ordersQuery = Order::with(['customer', 'seller'])
            ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead']);

        if ($search !== '') {
            $ordersQuery->where(function ($query) use ($search) {
                $query
                    ->where('external_order_id', 'like', '%' . $search . '%')
                    ->orWhereHas('customer', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($statusFilter !== 'all' && array_key_exists($statusFilter, $statusOptions)) {
            $ordersQuery->where('status', $statusFilter);
        }

        if ($orderDate) {
            $ordersQuery->whereDate('ordered_at', $orderDate);
        }

        $orders = $ordersQuery->latest('ordered_at')->get();
        $counts = $this->orderCounts();
        $customers = Customer::with('seller')->orderBy('name')->get();
        $ordersFeedSignature = $this->buildOrdersFeedSignature($ordersQuery);

        return view('spedfly.admin.orders', compact(
            'orders',
            'counts',
            'ordersFeedSignature',
            'statusOptions',
            'search',
            'statusFilter',
            'orderDate',
            'customers'
        ));
    }

    public function ordersFeed(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('q', ''));
        $statusFilter = strtolower(trim((string) $request->input('status', 'all')));
        $orderDate = $request->input('from');

        $statusOptions = $this->orderStatusOptions();

        $ordersQuery = Order::query()
            ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead']);

        if ($search !== '') {
            $ordersQuery->where(function ($query) use ($search) {
                $query
                    ->where('external_order_id', 'like', '%' . $search . '%')
                    ->orWhereHas('customer', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($statusFilter !== 'all' && array_key_exists($statusFilter, $statusOptions)) {
            $ordersQuery->where('status', $statusFilter);
        }

        if ($orderDate) {
            $ordersQuery->whereDate('ordered_at', $orderDate);
        }

        $orders = $ordersQuery->get(['id', 'updated_at']);
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

    public function storeReturn(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'exists:orders,id', 'unique:order_returns,order_id'],
            'return_reason' => ['required', 'string', 'max:255'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = Order::query()->with(['customer', 'seller'])->findOrFail($validated['order_id']);
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
        $this->syncShipmentStatusFromOrder($order->fresh(), 'returned');
        $this->notifySellerAboutOrderStatusChange($order->fresh(['seller']), $previousStatus, 'returned');

        return back()->with('success', 'Return saved successfully.');
    }

    public function updateReturnStatus(Request $request, OrderReturn $orderReturn): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'processed', 'refunded'])],
        ]);

        $previousStatus = strtolower((string) $orderReturn->status);
        $newStatus = strtolower($validated['status']);

        if ($previousStatus === $newStatus) {
            return back()->with('success', 'Return status already up to date.');
        }

        $orderReturn->update([
            'status' => $newStatus,
        ]);

        $this->notifySellerAboutReturnStatusChange($orderReturn->fresh(['seller', 'order']), $previousStatus, $newStatus);

        return back()->with('success', 'Return status updated successfully.');
    }

    public function leads(Request $request)
    {
        $leads = Order::query()
            ->with(['customer', 'seller', 'callLogs' => function ($query) {
                $query->latest('call_time')->latest('id');
            }])
            ->whereRaw('LOWER(COALESCE(status, "")) = ?', ['lead'])
          //  ->whereRaw('LOWER(COALESCE(payment_type, "")) = ?', ['cod'])
          ->whereRaw('LOWER(COALESCE(payment_type, "")) IN (?, ?)', ['cod', 'prepaid'])
            ->latest('ordered_at')
            ->latest('id')
            ->get();

        $stats = [
            'total' => $leads->count(),
            'today' => $leads->filter(fn (Order $order) => optional($order->ordered_at ?? $order->created_at)?->isToday())->count(),
            'called' => $leads->filter(fn (Order $order) => $order->callLogs->isNotEmpty())->count(),
            'unreached' => $leads->filter(function (Order $order) {
                $latestLog = $order->callLogs->first();

                return $latestLog && in_array($latestLog->result, ['no_answer', 'failed', 'busy'], true);
            })->count(),
        ];

        return view('spedfly.admin.leads', compact('leads', 'stats'));
    }

    public function confirmLead(Order $order): RedirectResponse
    {
        abort_unless(
        strtolower((string) $order->status) === 'lead'
        && in_array(
            strtolower((string) $order->payment_type),
            ['cod', 'prepaid']
        ),
        404
    );

        $this->ensureOrderStatusTransitionAllowed($order, 'new');

        $previousStatus = strtolower((string) $order->status);

        $order->update([
            'status' => 'new',
        ]);

        $this->syncOrderStatusToShopify($order->fresh(['customer', 'seller', 'items.product']), 'new');
        $this->notifySellerAboutOrderStatusChange($order->fresh(['seller']), $previousStatus, 'new');

        return back()->with('success', 'Lead confirmed and moved to orders successfully.');
    }

    public function storeOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'external_order_id' => ['required', 'string', 'max:255', 'unique:orders,external_order_id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_keys($this->orderStatusOptions()))],
            'ordered_at' => ['required', 'date'],
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);

        $order = Order::create([
            'external_order_id' => $validated['external_order_id'],
            'customer_id' => $customer->id,
            'seller_id' => $customer->seller_id,
            'amount' => $validated['amount'],
            'status' => $validated['status'],
            'ordered_at' => $validated['ordered_at'],
        ]);

        $order->load(['customer', 'seller']);

        $counts = $this->orderCounts();

        return response()->json([
            'order' => $order,
            'counts' => $counts,
        ]);
    }

    public function updateOrderStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->orderStatusOptions()))],
        ]);

        $previousStatus = strtolower((string) $order->status);

        DB::transaction(function () use ($order, $validated) {
            $this->ensureOrderStatusTransitionAllowed($order, $validated['status']);

            $order->update([
                'status' => $validated['status'],
            ]);

            $this->syncShipmentStatusFromOrder($order->fresh(['customer', 'seller']), $validated['status']);
        });

        $shopifySyncResult = $this->syncOrderStatusToShopify($order->fresh(['customer', 'seller', 'items.product']), $validated['status']);
        $this->notifySellerAboutOrderStatusChange($order->fresh(['seller']), $previousStatus, $validated['status']);

        return back()->with(
            $shopifySyncResult['ok'] ? 'success' : 'warning',
            $shopifySyncResult['ok']
                ? 'Order status updated successfully and synced to Shopify.'
                : 'Order status updated successfully, but Shopify sync could not be completed. ' . $shopifySyncResult['message']
        );
    }

    public function updateOrder(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'external_order_id' => ['required', 'string', 'max:255', Rule::unique('orders', 'external_order_id')->ignore($order->id)],
            'customer_id' => ['required', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_keys($this->orderStatusOptions()))],
            'ordered_at' => ['required', 'date'],
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $this->ensureOrderStatusTransitionAllowed($order, $validated['status']);
        $previousStatus = strtolower((string) $order->status);

        $order->update([
            'external_order_id' => $validated['external_order_id'],
            'customer_id' => $customer->id,
            'seller_id' => $customer->seller_id,
            'amount' => $validated['amount'],
            'status' => $validated['status'],
            'ordered_at' => $validated['ordered_at'],
        ]);

        $this->syncShipmentStatusFromOrder($order, $validated['status']);
        $shopifySyncResult = $this->syncOrderStatusToShopify($order->fresh(['customer', 'seller', 'items.product']), $validated['status']);
        $this->notifySellerAboutOrderStatusChange($order->fresh(['seller']), $previousStatus, $validated['status']);

        return back()->with(
            $shopifySyncResult['ok'] ? 'success' : 'warning',
            $shopifySyncResult['ok']
                ? 'Order updated successfully and synced to Shopify.'
                : 'Order updated successfully, but Shopify sync could not be completed. ' . $shopifySyncResult['message']
        );
    }

    public function orderDetails(Order $order)
    {
        $order->load(['customer', 'seller', 'items.product', 'returnRecord']);
        $statusOptions = $this->orderStatusOptions();
        $shipment = Shipment::query()
            ->where('order_id', $order->id)
            ->latest('shipment_date')
            ->latest('id')
            ->first();

        return view('spedfly.admin.order-details', compact('order', 'statusOptions', 'shipment'));
    }

    private function orderStatusOptions(): array
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

    private function ensureOrderStatusTransitionAllowed(Order $order, string $newStatus): void
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

    private function syncOrderStatusToShopify(Order $order, string $newStatus): array
    {
        $seller = $order->seller;
        $shopDomain = trim((string) $seller?->shopify_shop_domain);
        $accessToken = trim((string) $seller?->shopify_access_token);
        $scope = trim((string) $seller?->shopify_scope);

        if ($shopDomain === '' || $accessToken === '') {
            return [
                'ok' => false,
                'message' => 'Shopify is not connected for this seller.',
            ];
        }

        $installedScopes = array_values(array_filter(array_map('trim', explode(',', $scope))));
        if (! in_array('write_orders', $installedScopes, true)) {
            return [
                'ok' => false,
                'message' => 'Shopify app is connected, but the installed token does not include write_orders permission.',
            ];
        }

        $shopifyOrderId = $this->findShopifyOrderIdByLocalOrder($shopDomain, $accessToken, $order);
        if (! $shopifyOrderId) {
            return [
                'ok' => false,
                'message' => 'Could not find the matching Shopify order for this local order.',
            ];
        }

        $apiVersion = (string) config('services.shopify.api_version', '2026-04');
        $headers = [
            'X-Shopify-Access-Token' => $accessToken,
            'Accept' => 'application/json',
        ];

        $currentOrderResponse = Http::withHeaders($headers)
            ->timeout(20)
            ->get("https://{$shopDomain}/admin/api/{$apiVersion}/orders/{$shopifyOrderId}.json");

        if (! $currentOrderResponse->successful()) {
            $responseBody = trim((string) $currentOrderResponse->body());
            $responseSnippet = $responseBody !== '' ? ' Response: ' . Str::limit($responseBody, 250) : '';

            return [
                'ok' => false,
                'message' => 'Unable to load the Shopify order before updating status. Status ' . $currentOrderResponse->status() . '.' . $responseSnippet,
            ];
        }

        $currentOrder = (array) ($currentOrderResponse->json('order') ?? []);
        $existingTags = array_filter(array_map('trim', explode(',', (string) ($currentOrder['tags'] ?? ''))));
        $statusTag = 'Spedfly status: ' . $newStatus;
        $localOrderTag = 'Local Order #' . $order->external_order_id;

        $mergedTags = array_values(array_unique(array_filter(array_merge(
            $existingTags,
            ['Spedfly', $localOrderTag, $statusTag]
        ))));

        $noteParts = array_filter([
            (string) ($currentOrder['note'] ?? ''),
            'Spedfly status updated to ' . $newStatus . ' at ' . now()->format('Y-m-d H:i:s'),
        ]);

        $updateResponse = Http::withHeaders($headers)
            ->asJson()
            ->timeout(20)
            ->put("https://{$shopDomain}/admin/api/{$apiVersion}/orders/{$shopifyOrderId}.json", [
                'order' => [
                    'id' => $shopifyOrderId,
                    'tags' => implode(', ', $mergedTags),
                    'note' => implode("\n\n", $noteParts),
                ],
            ]);

        if (! $updateResponse->successful()) {
            $responseBody = trim((string) $updateResponse->body());
            $responseSnippet = $responseBody !== '' ? ' Response: ' . Str::limit($responseBody, 250) : '';

            return [
                'ok' => false,
                'message' => 'Shopify status update failed with status ' . $updateResponse->status() . '.' . $responseSnippet,
            ];
        }

        if (strtolower($newStatus) === 'delivered') {
            $deliverySyncResult = $this->createShopifyDeliveryFulfillment($order, $shopDomain, $accessToken, $installedScopes);

            if (! $deliverySyncResult['ok']) {
                return $deliverySyncResult;
            }
        }

        return [
            'ok' => true,
            'message' => 'Shopify order updated successfully.',
        ];
    }

    private function createShopifyDeliveryFulfillment(
        Order $order,
        string $shopDomain,
        string $accessToken,
        array $installedScopes
    ): array {
        $fulfillmentScopes = [
            'read_merchant_managed_fulfillment_orders',
            'write_merchant_managed_fulfillment_orders',
            'read_assigned_fulfillment_orders',
            'write_assigned_fulfillment_orders',
            'read_third_party_fulfillment_orders',
            'write_third_party_fulfillment_orders',
            'read_fulfillments',
            'write_fulfillments',
        ];

        $hasFulfillmentScope = false;
        foreach ($fulfillmentScopes as $fulfillmentScope) {
            if (in_array($fulfillmentScope, $installedScopes, true)) {
                $hasFulfillmentScope = true;
                break;
            }
        }

        if (! $hasFulfillmentScope) {
            return [
                'ok' => false,
                'message' => 'Shopify order was marked delivered locally, but the installed token does not include fulfillment permissions. Reconnect the app after enabling fulfillment scopes in Shopify app settings.',
            ];
        }

        $shopifyOrderId = $this->findShopifyOrderIdByLocalOrder($shopDomain, $accessToken, $order);
        if (! $shopifyOrderId) {
            return [
                'ok' => false,
                'message' => 'Could not find the matching Shopify order for delivery fulfillment.',
            ];
        }

        $apiVersion = (string) config('services.shopify.api_version', '2026-04');
        $headers = [
            'X-Shopify-Access-Token' => $accessToken,
            'Accept' => 'application/json',
        ];

        $shipment = Shipment::query()
            ->where('order_id', $order->id)
            ->latest('shipment_date')
            ->latest('id')
            ->first();

        $fulfillmentOrdersResponse = Http::withHeaders($headers)
            ->timeout(20)
            ->get("https://{$shopDomain}/admin/api/{$apiVersion}/orders/{$shopifyOrderId}/fulfillment_orders.json");

        if (! $fulfillmentOrdersResponse->successful()) {
            $responseBody = trim((string) $fulfillmentOrdersResponse->body());
            $responseSnippet = $responseBody !== '' ? ' Response: ' . Str::limit($responseBody, 250) : '';

            return [
                'ok' => false,
                'message' => 'Unable to load Shopify fulfillment orders. Status ' . $fulfillmentOrdersResponse->status() . '.' . $responseSnippet,
            ];
        }

        $fulfillmentOrders = (array) ($fulfillmentOrdersResponse->json('fulfillment_orders') ?? []);
        $fulfillmentOrder = collect($fulfillmentOrders)
            ->first(fn ($item) => is_array($item) && (string) ($item['status'] ?? '') !== 'cancelled');

        if (! is_array($fulfillmentOrder) || ! isset($fulfillmentOrder['id'])) {
            return [
                'ok' => false,
                'message' => 'No active Shopify fulfillment order was found for this order.',
            ];
        }

        $trackingInfo = [];
        $trackingNumber = trim((string) $shipment?->shipment_code);
        $courierName = trim((string) $shipment?->courier_name);

        if ($trackingNumber !== '') {
            $trackingInfo['number'] = $trackingNumber;
        }

        if ($courierName !== '') {
            $trackingInfo['company'] = $courierName;
        }

        $payload = [
            'fulfillment' => [
                'line_items_by_fulfillment_order' => [
                    [
                        'fulfillment_order_id' => (int) $fulfillmentOrder['id'],
                    ],
                ],
                'notify_customer' => filled($order->customer?->email),
            ],
        ];

        if ($trackingInfo !== []) {
            $payload['fulfillment']['tracking_info'] = $trackingInfo;
        }

        $fulfillmentResponse = Http::withHeaders($headers)
            ->asJson()
            ->timeout(20)
            ->post("https://{$shopDomain}/admin/api/{$apiVersion}/fulfillments.json", $payload);

        if (! $fulfillmentResponse->successful()) {
            $responseBody = trim((string) $fulfillmentResponse->body());
            $responseSnippet = $responseBody !== '' ? ' Response: ' . Str::limit($responseBody, 250) : '';

            return [
                'ok' => false,
                'message' => 'Shopify fulfillment creation failed with status ' . $fulfillmentResponse->status() . '.' . $responseSnippet,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Shopify order fulfilled successfully.',
        ];
    }

    private function findShopifyOrderIdByLocalOrder(string $shopDomain, string $accessToken, Order $order): ?int
    {
        $apiVersion = (string) config('services.shopify.api_version', '2026-04');
        $searchQuery = sprintf('tag:"Local Order #%s"', $order->external_order_id);

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Accept' => 'application/json',
        ])->asJson()->timeout(20)->post(
            "https://{$shopDomain}/admin/api/{$apiVersion}/graphql.json",
            [
                'query' => <<<'GRAPHQL'
query ($query: String!) {
  orders(first: 1, query: $query) {
    edges {
      node {
        legacyResourceId
        name
        tags
      }
    }
  }
}
GRAPHQL,
                'variables' => [
                    'query' => $searchQuery,
                ],
            ]
        );

        if (! $response->successful()) {
            return null;
        }

        $legacyResourceId = data_get($response->json(), 'data.orders.edges.0.node.legacyResourceId');

        return $legacyResourceId ? (int) $legacyResourceId : null;
    }

    private function notifySellerAboutReturnStatusChange(OrderReturn $orderReturn, ?string $previousStatus, string $newStatus): void
    {
        if (! $orderReturn->seller_id) {
            return;
        }

        $statusLabels = [
            'pending' => 'Pending',
            'processed' => 'Processed',
            'refunded' => 'Refunded',
        ];

        $previousLabel = $statusLabels[strtolower((string) $previousStatus)] ?? ucfirst((string) $previousStatus);
        $newLabel = $statusLabels[strtolower($newStatus)] ?? ucfirst($newStatus);

        AppNotification::query()->create([
            'user_id' => $orderReturn->seller_id,
            'title' => 'Return Status Updated',
            'message' => 'Return ' . $orderReturn->return_code . ' changed from ' . $previousLabel . ' to ' . $newLabel . ' by admin.',
            'type' => 'return_status',
            'is_read' => false,
            'read_at' => null,
            'data' => [
                'return_id' => $orderReturn->id,
                'return_code' => $orderReturn->return_code,
                'previous_status' => strtolower((string) $previousStatus),
                'new_status' => $newStatus,
                'order_id' => $orderReturn->order_id,
                'external_order_id' => $orderReturn->order?->external_order_id,
            ],
        ]);
    }

    private function orderCounts(): array
    {
        $startOfWeek = now()->copy()->startOfWeek();
        $endOfWeek = now()->copy()->endOfWeek();

        return [
            'total' => Order::query()
                ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead'])
                ->count(),
            'pending' => Order::query()
                ->whereRaw('LOWER(COALESCE(status, "")) = ?', ['new'])
                ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead'])
                ->count(),
            'this_week' => Order::query()
                ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead'])
                ->whereBetween('ordered_at', [$startOfWeek, $endOfWeek])
                ->count(),
        ];
    }

    private function buildAdminDashboardData(Request $request): array
    {
        $today = now();

        $orders = Order::query()
            ->with([
                'customer:id,name',
                'seller:id,name',
            ])
            ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead'])
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'seller_id',
                'customer_id',
                'external_order_id',
                'amount',
                'payment_type',
                'status',
                'ordered_at',
            ]);

        $currentMonthStart = $today->copy()->startOfMonth()->startOfDay();
        $currentMonthEnd = $today->copy()->endOfDay();
        $previousMonthStart = $today->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay();
        $previousMonthEnd = $today->copy()->startOfMonth()->subSecond();

        $monthWindows = collect(range(11, 0))->map(function (int $offset) use ($today) {
            $monthStart = $today->copy()->subMonthsNoOverflow($offset)->startOfMonth()->startOfDay();
            $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

            return [
                'start' => $monthStart,
                'end' => $monthEnd,
                'label' => $monthStart->format('M Y'),
            ];
        });

        $topSellerRows = $orders
            ->groupBy('seller_id')
            ->map(function ($sellerOrders, $sellerId) use ($currentMonthStart, $currentMonthEnd, $previousMonthStart, $previousMonthEnd) {
                /** @var \Illuminate\Support\Collection<int, Order> $sellerOrders */
                $currentMonthOrders = $sellerOrders->filter(function (Order $order) use ($currentMonthStart, $currentMonthEnd) {
                    return $order->ordered_at
                        && $order->ordered_at->between($currentMonthStart, $currentMonthEnd);
                });

                $previousMonthOrders = $sellerOrders->filter(function (Order $order) use ($previousMonthStart, $previousMonthEnd) {
                    return $order->ordered_at
                        && $order->ordered_at->between($previousMonthStart, $previousMonthEnd);
                });

                $currentRevenue = (float) $currentMonthOrders->sum('amount');
                $previousRevenue = (float) $previousMonthOrders->sum('amount');
                $growth = $this->calculateGrowth($currentRevenue, $previousRevenue);

                $seller = $sellerOrders->first()?->seller;

                return [
                    'seller_id' => (int) $sellerId,
                    'name' => $seller?->name ?? ('Seller #' . $sellerId),
                    'orders' => $sellerOrders->count(),
                    'revenue' => (float) $sellerOrders->sum('amount'),
                    'growth' => $growth,
                    'growth_class' => $growth >= 0 ? 'text-success' : 'text-danger',
                    'growth_icon' => $growth >= 0 ? 'bi-arrow-up' : 'bi-arrow-down',
                    'initials' => $this->sellerInitials($seller?->name ?? ('Seller #' . $sellerId)),
                ];
            })
            ->sortByDesc('revenue')
            ->take(4)
            ->values();

        $topSeller = $topSellerRows->first();
        $totalSales = (float) $orders->sum('amount');
        $totalOrders = $orders->count();
        $newCustomers = Customer::query()
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->count();
        $totalCustomers = Customer::count();

        $monthlyOrderCounts = [];
        $monthlyReturnedCounts = [];
        $monthlyRevenue = [];

        foreach ($monthWindows as $window) {
            $windowOrders = $orders->filter(function (Order $order) use ($window) {
                return $order->ordered_at && $order->ordered_at->between($window['start'], $window['end']);
            });

            $monthlyOrderCounts[] = $windowOrders->count();
            $monthlyReturnedCounts[] = $windowOrders->filter(function (Order $order) {
                return strtolower((string) $order->status) === 'returned';
            })->count();
            $monthlyRevenue[] = (float) $windowOrders->sum('amount');
        }

        $paymentLabelsMap = [
            'cod' => 'COD',
            'prepaid' => 'Prepaid',
            'bank_transfer' => 'Bank Transfer',
            'card' => 'Card',
        ];

        $paymentBreakdown = $orders
            ->groupBy(function (Order $order) {
                $paymentType = strtolower((string) $order->payment_type);

                return $paymentType !== '' ? $paymentType : 'cod';
            })
            ->mapWithKeys(function ($group, string $paymentType) use ($paymentLabelsMap) {
                $label = $paymentLabelsMap[$paymentType] ?? Str::headline($paymentType);

                return [$label => $group->count()];
            })
            ->sortDesc();

        if ($paymentBreakdown->isEmpty()) {
            $paymentBreakdown = collect(['COD' => 1]);
        }

        $recentOrders = $orders
            ->take(7)
            ->map(function (Order $order) {
                return [
                    'id' => $order->external_order_id ?? ('#' . $order->id),
                    'customer' => $order->customer?->name ?? '-',
                    'amount' => (float) $order->amount,
                    'status' => strtolower((string) $order->status),
                    'status_label' => $this->orderStatusLabel((string) $order->status),
                    'status_class' => $this->orderStatusBadgeClass((string) $order->status),
                    'date' => optional($order->ordered_at)->format('d M'),
                ];
            })
            ->values();

        return [
            'metrics' => [
                'total_sales' => $totalSales,
                'total_orders' => $totalOrders,
                'new_customers' => $newCustomers,
                'total_customers' => $totalCustomers,
                'top_seller_name' => $topSeller['name'] ?? 'No sellers yet',
                'top_seller_orders' => $topSeller['orders'] ?? 0,
            ],
            'chartData' => [
                'labels' => $monthWindows->pluck('label')->all(),
                'orders' => $monthlyOrderCounts,
                'returned' => $monthlyReturnedCounts,
                'revenue' => $monthlyRevenue,
            ],
            'topSellers' => $topSellerRows,
            'recentOrders' => $recentOrders,
            'paymentBreakdown' => [
                'labels' => $paymentBreakdown->keys()->values()->all(),
                'data' => $paymentBreakdown->values()->all(),
            ],
        ];
    }

    private function buildAdminAnalyticsData(Request $request): array
    {
        $today = now();
        $year = (int) $request->input('year', $today->year);

        if ($year < 2000 || $year > 2100) {
            $year = $today->year;
        }

        $rangeStart = Carbon::create($year, 1, 1)->startOfDay();
        $rangeEnd = Carbon::create($year, 12, 31)->endOfDay();

        $orders = Order::query()
            ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead'])
            ->whereBetween('ordered_at', [$rangeStart, $rangeEnd])
            ->get(['id', 'amount', 'payment_type', 'status', 'ordered_at']);

        $returns = OrderReturn::query()
            ->whereBetween('returned_at', [$rangeStart, $rangeEnd])
            ->get(['id', 'order_id', 'return_reason', 'refund_amount', 'returned_at']);

        $monthWindows = collect(range(1, 12))->map(function (int $month) use ($year) {
            $monthStart = Carbon::create($year, $month, 1)->startOfDay();
            $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

            return [
                'start' => $monthStart,
                'end' => $monthEnd,
                'label' => $monthStart->format('M'),
            ];
        });

        $monthlyOrders = [];
        $monthlyReturned = [];
        $monthlyRevenue = [];

        foreach ($monthWindows as $window) {
            $windowOrders = $orders->filter(function (Order $order) use ($window) {
                return $order->ordered_at && $order->ordered_at->between($window['start'], $window['end']);
            });

            $monthlyOrders[] = $windowOrders->count();
            $monthlyReturned[] = $windowOrders->filter(
                fn (Order $order) => strtolower((string) $order->status) === 'returned'
            )->count();
            $monthlyRevenue[] = (float) $windowOrders->sum('amount');
        }

        $statusLabels = [
            'lead' => 'Lead',
            'new' => 'New',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'returned' => 'Returned',
        ];

        $statusBreakdown = collect(array_keys($statusLabels))
            ->mapWithKeys(function (string $status) use ($orders, $statusLabels) {
                return [
                    $statusLabels[$status] => $orders->filter(
                        fn (Order $order) => strtolower((string) $order->status) === $status
                    )->count(),
                ];
            })
            ->filter(fn (int $count) => $count > 0);

        $paymentLabelsMap = [
            'cod' => 'COD',
            'prepaid' => 'Prepaid',
            'bank_transfer' => 'Bank Transfer',
            'card' => 'Card',
        ];

        $paymentBreakdown = $orders
            ->groupBy(function (Order $order) {
                $paymentType = strtolower((string) $order->payment_type);

                return $paymentType !== '' ? $paymentType : 'cod';
            })
            ->mapWithKeys(function ($group, string $paymentType) use ($paymentLabelsMap) {
                return [
                    $paymentLabelsMap[$paymentType] ?? Str::headline($paymentType) => $group->count(),
                ];
            })
            ->sortDesc();

        $returnBreakdown = $returns
            ->groupBy(function (OrderReturn $return) {
                $reason = trim((string) $return->return_reason);

                return $reason !== '' ? $reason : 'Other';
            })
            ->mapWithKeys(function ($group, string $reason) {
                return [$reason => $group->count()];
            })
            ->sortDesc()
            ->take(6);

        $totalSales = (float) $orders->sum('amount');
        $totalOrders = $orders->count();
        $returnedOrders = $orders->filter(
            fn (Order $order) => strtolower((string) $order->status) === 'returned'
        )->count();
        $averageOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;
        $returnRate = $totalOrders > 0 ? (int) round(($returnedOrders / $totalOrders) * 100) : 0;
        $fulfilledOrders = $orders->filter(
            fn (Order $order) => in_array(strtolower((string) $order->status), ['processing', 'shipped', 'delivered'], true)
        )->count();

        if ($statusBreakdown->isEmpty()) {
            $statusBreakdown = collect(['No Data' => 1]);
        }

        if ($paymentBreakdown->isEmpty()) {
            $paymentBreakdown = collect(['COD' => 1]);
        }

        if ($returnBreakdown->isEmpty()) {
            $returnBreakdown = collect(['No Returns' => 1]);
        }

        return [
            'analyticsYear' => $year,
            'summary' => [
                'total_sales' => $totalSales,
                'total_orders' => $totalOrders,
                'average_order_value' => $averageOrderValue,
                'fulfilled_orders' => $fulfilledOrders,
                'returned_orders' => $returnedOrders,
                'return_rate' => $returnRate,
                'return_amount' => (float) $returns->sum('refund_amount'),
            ],
            'performanceChart' => [
                'labels' => $monthWindows->pluck('label')->all(),
                'orders' => $monthlyOrders,
                'returned' => $monthlyReturned,
                'revenue' => $monthlyRevenue,
            ],
            'statusBreakdown' => [
                'labels' => $statusBreakdown->keys()->values()->all(),
                'data' => $statusBreakdown->values()->all(),
            ],
            'returnBreakdown' => [
                'labels' => $returnBreakdown->keys()->values()->all(),
                'data' => $returnBreakdown->values()->all(),
            ],
            'paymentBreakdown' => [
                'labels' => $paymentBreakdown->keys()->values()->all(),
                'data' => $paymentBreakdown->values()->all(),
            ],
        ];
    }

    private function sellerInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn (string $part) => Str::substr($part, 0, 1))
            ->implode('');

        return Str::upper($initials !== '' ? $initials : 'S');
    }

    private function orderStatusLabel(string $status): string
    {
        return [
            'lead' => 'Lead',
            'new' => 'New',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'returned' => 'Returned',
        ][strtolower($status)] ?? ucfirst(str_replace('_', ' ', $status));
    }

    private function orderStatusBadgeClass(string $status): string
    {
        return [
            'lead' => 'bg-secondary',
            'new' => 'bg-info',
            'processing' => 'bg-warning text-dark',
            'shipped' => 'bg-primary',
            'delivered' => 'bg-success',
            'returned' => 'bg-danger',
        ][strtolower($status)] ?? 'bg-dark';
    }

    private function calculateGrowth(int|float $current, int|float $previous): int
    {
        if ((float) $previous === 0.0) {
            return $current > 0 ? 100 : 0;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    private function syncShipmentStatusFromOrder(Order $order, string $orderStatus): void
    {
        $order->loadMissing(['customer', 'seller']);

        $shipmentStatus = $this->mapOrderStatusToShipmentStatus($orderStatus);

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

    private function buildShipmentDataFromOrder(Order $order, string $shipmentStatus): array
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

    private function extractAddressCityAndPostalCode(string $address): array
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

    private function mapOrderStatusToShipmentStatus(string $orderStatus): ?string
    {
        return [
            'new' => 'pending',
            'processing' => 'pending',
            'shipped' => 'in_transit',
            'delivered' => 'delivered',
            'returned' => 'returned',
        ][strtolower($orderStatus)] ?? null;
    }

    public function shipments(Request $request)
    {
        $statusLabels = [
            'pending' => 'Pending',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'delayed' => 'Delayed',
            'returned' => 'Returned',
        ];

        $search = trim((string) $request->input('q', ''));
        $carrier = trim((string) $request->input('carrier', ''));
        $fromDate = $request->input('from');
        $toDate = $request->input('to');

        $shipmentsQuery = Shipment::query()->with(['order', 'seller']);

        if ($search !== '') {
            $shipmentsQuery->where(function ($query) use ($search) {
                $query
                    ->where('shipment_code', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%')
                    ->orWhere('customer_phone', 'like', '%' . $search . '%')
                    ->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('external_order_id', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($carrier !== '') {
            $shipmentsQuery->where('courier_name', $carrier);
        }

        if ($fromDate) {
            $shipmentsQuery->whereDate('shipment_date', '>=', $fromDate);
        }

        if ($toDate) {
            $shipmentsQuery->whereDate('shipment_date', '<=', $toDate);
        }

        $shipments = $shipmentsQuery
            ->orderByDesc('shipment_date')
            ->orderByDesc('id')
            ->get();

        $stats = [
            'in_transit' => Shipment::where('status', 'in_transit')->count(),
            'delivered' => Shipment::where('status', 'delivered')->count(),
            'delayed' => Shipment::where('status', 'delayed')->count(),
        ];

        $carrierOptions = Shipment::query()
            ->select('courier_name')
            ->whereNotNull('courier_name')
            ->distinct()
            ->orderBy('courier_name')
            ->pluck('courier_name');

        if ($request->input('export') === 'csv') {
            return $this->exportShipmentsCsv($shipments, $statusLabels);
        }

        return view('spedfly.admin.shipments', compact(
            'shipments',
            'stats',
            'statusLabels',
            'carrierOptions',
            'search',
            'carrier',
            'fromDate',
            'toDate'
        ));
    }

    public function shipmentsFeed(Request $request): JsonResponse
    {
        $statusLabels = [
            'pending' => 'Pending',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'delayed' => 'Delayed',
            'returned' => 'Returned',
        ];

        $search = trim((string) $request->input('q', ''));
        $carrier = trim((string) $request->input('carrier', ''));
        $fromDate = $request->input('from');
        $toDate = $request->input('to');

        $shipmentsQuery = Shipment::query()->with(['order', 'seller']);

        if ($search !== '') {
            $shipmentsQuery->where(function ($query) use ($search) {
                $query
                    ->where('shipment_code', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%')
                    ->orWhere('customer_phone', 'like', '%' . $search . '%')
                    ->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('external_order_id', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($carrier !== '') {
            $shipmentsQuery->where('courier_name', $carrier);
        }

        if ($fromDate) {
            $shipmentsQuery->whereDate('shipment_date', '>=', $fromDate);
        }

        if ($toDate) {
            $shipmentsQuery->whereDate('shipment_date', '<=', $toDate);
        }

        $shipments = $shipmentsQuery
            ->orderByDesc('shipment_date')
            ->orderByDesc('id')
            ->get();

        $signature = $shipments->map(fn (Shipment $shipment) => $shipment->id . '|' . optional($shipment->updated_at)->timestamp)->implode(';');
        $statusBadgeClasses = [
            'pending' => 'bg-secondary',
            'in_transit' => 'bg-info',
            'delivered' => 'bg-success',
            'delayed' => 'bg-danger',
            'returned' => 'bg-dark',
        ];

        return response()->json([
            'signature' => $signature,
            'html' => view('spedfly.admin.partials.shipments-table-rows', [
                'shipments' => $shipments,
                'statusLabels' => $statusLabels,
                'statusBadgeClasses' => $statusBadgeClasses,
            ])->render(),
            'stats' => [
                'in_transit' => Shipment::query()->where('status', 'in_transit')->count(),
                'delivered' => Shipment::query()->where('status', 'delivered')->count(),
                'delayed' => Shipment::query()->where('status', 'delayed')->count(),
            ],
        ]);
    }

    public function shipmentLabel(Shipment $shipment)
    {
        $shipment->loadMissing(['order.customer', 'seller']);

        return view('spedfly.shipments.label', [
            'shipment' => $shipment,
            'companyName' => config('app.name', 'Spedfly'),
            'printedAt' => now(),
        ]);
    }

    public function updateShipment(Request $request, Shipment $shipment): RedirectResponse
    {
        $validated = $request->validate([
            'shipment_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('shipments', 'shipment_code')
                    ->where(fn ($query) => $query->where('seller_id', $shipment->seller_id))
                    ->ignore($shipment->id),
            ],
            'courier_name' => ['required', 'string', 'max:255'],
            'shipment_date' => ['required', 'date'],
            'pickup_address' => ['required', 'string', 'max:2000'],
            'delivery_address' => ['required', 'string', 'max:2000'],
        ]);

        $coordinates = $this->resolveShipmentCoordinates([
            'pickup_address' => $validated['pickup_address'],
            'pickup_city' => $shipment->pickup_city,
            'pickup_postal_code' => $shipment->pickup_postal_code,
            'delivery_address' => $validated['delivery_address'],
            'delivery_city' => $shipment->delivery_city,
            'delivery_postal_code' => $shipment->delivery_postal_code,
        ]);

        $shipment->update([
            'shipment_code' => strtoupper($validated['shipment_code']),
            'courier_name' => $validated['courier_name'],
            'shipment_date' => $validated['shipment_date'],
            'pickup_address' => $validated['pickup_address'],
            'delivery_address' => $validated['delivery_address'],
            'pickup_latitude' => $coordinates['pickup_latitude'],
            'pickup_longitude' => $coordinates['pickup_longitude'],
            'delivery_latitude' => $coordinates['delivery_latitude'],
            'delivery_longitude' => $coordinates['delivery_longitude'],
        ]);

        return back()->with('success', 'Shipment updated successfully.');
    }

    private function exportShipmentsCsv($shipments, array $statusLabels): StreamedResponse
    {
        return response()->streamDownload(function () use ($shipments, $statusLabels) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Shipment ID',
                'Order ID',
                'Seller',
                'Carrier',
                'Customer',
                'Phone',
                'Status',
                'Shipped Date',
            ]);

            foreach ($shipments as $shipment) {
                fputcsv($handle, [
                    $shipment->shipment_code,
                    $shipment->order?->external_order_id,
                    $shipment->seller?->name,
                    $shipment->courier_name,
                    $shipment->customer_name,
                    $shipment->customer_phone,
                    $statusLabels[$shipment->status] ?? ucfirst((string) $shipment->status),
                    optional($shipment->shipment_date)->format('Y-m-d'),
                ]);
            }

            fclose($handle);
        }, 'shipments-export.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function returns(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $fromDate = $request->input('from');
        $toDate = $request->input('to');

        $returnsQuery = OrderReturn::query()->with(['order.customer', 'seller', 'customer']);

        if ($search !== '') {
            $returnsQuery->where(function ($query) use ($search) {
                $query
                    ->where('return_code', 'like', '%' . $search . '%')
                    ->orWhere('return_reason', 'like', '%' . $search . '%')
                    ->orWhere('notes', 'like', '%' . $search . '%')
                    ->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('external_order_id', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('seller', function ($sellerQuery) use ($search) {
                        $sellerQuery->where('name', 'like', '%' . $search . '%');
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
            'total' => OrderReturn::count(),
            'today' => OrderReturn::query()
                ->whereDate('returned_at', now()->toDateString())
                ->count(),
            'this_week' => OrderReturn::query()
                ->whereBetween('returned_at', [now()->copy()->startOfWeek(), now()->copy()->endOfWeek()])
                ->count(),
            'sellers' => OrderReturn::query()
                ->distinct()
                ->count('seller_id'),
        ];

        return view('spedfly.admin.returns', compact('returns', 'stats', 'search', 'fromDate', 'toDate'));
    }

    public function payments(Request $request)
    {
        $paymentMethod = strtolower(trim((string) $request->input('method', 'all')));
        $transactionSearch = trim((string) $request->input('tx', ''));
        $statusFilter = strtolower(trim((string) $request->input('status', 'all')));

        $paymentLabelsMap = [
            'cod' => 'COD',
            'prepaid' => 'Prepaid',
            'bank_transfer' => 'Bank Transfer',
            'card' => 'Card',
            'upi' => 'UPI',
            'cash' => 'Cash',
        ];

        $baseQuery = Order::query()
            ->with(['customer', 'seller', 'returnRecord'])
            ->whereRaw('LOWER(COALESCE(status, "")) != ?', ['lead']);

        if ($paymentMethod !== '' && $paymentMethod !== 'all') {
            $baseQuery->whereRaw('LOWER(COALESCE(payment_type, "")) = ?', [$paymentMethod]);
        }

        if ($transactionSearch !== '') {
            $baseQuery->where(function ($query) use ($transactionSearch) {
                $query
                    ->where('external_order_id', 'like', '%' . $transactionSearch . '%')
                    ->orWhere('id', 'like', '%' . $transactionSearch . '%')
                    ->orWhereHas('customer', function ($customerQuery) use ($transactionSearch) {
                        $customerQuery->where('name', 'like', '%' . $transactionSearch . '%');
                    });
            });
        }

        $orders = $baseQuery
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->get();

        $payments = $orders->map(function (Order $order) use ($paymentLabelsMap) {
            $paymentType = strtolower((string) $order->payment_type);
            $paymentLabel = $paymentLabelsMap[$paymentType] ?? Str::headline($paymentType !== '' ? $paymentType : 'cod');
            $refundAmount = (float) ($order->returnRecord?->refund_amount ?? 0);
            $returnStatus = strtolower((string) ($order->returnRecord?->status ?? ''));
            $orderStatus = strtolower((string) $order->status);

            $paymentStatus = 'Completed';
            $statusClass = 'success';

            if ($refundAmount > 0 || in_array($returnStatus, ['refunded', 'processed'], true)) {
                $paymentStatus = 'Refunded';
                $statusClass = 'danger';
            } elseif ($paymentType === 'cod' && ! in_array($orderStatus, ['delivered'], true)) {
                $paymentStatus = 'Pending';
                $statusClass = 'warning';
            } elseif (in_array($orderStatus, ['new', 'processing', 'shipped'], true)) {
                $paymentStatus = 'Pending';
                $statusClass = 'warning';
            } elseif (in_array($orderStatus, ['cancelled', 'failed'], true)) {
                $paymentStatus = 'Failed';
                $statusClass = 'secondary';
            }

            return [
                'payment_id' => 'PAY-' . str_pad((string) $order->id, 5, '0', STR_PAD_LEFT),
                'order_id' => $order->external_order_id ?: ('#' . $order->id),
                'method' => $paymentLabel,
                'amount' => (float) $order->amount,
                'status' => $paymentStatus,
                'status_class' => $statusClass,
                'date' => optional($order->ordered_at ?? $order->created_at)->format('Y-m-d'),
                'transaction_id' => $order->external_order_id ?: (string) $order->id,
                'customer_name' => $order->customer?->name ?? '-',
                'seller_name' => $order->seller?->name ?? '-',
                'refund_amount' => $refundAmount,
            ];
        });

        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $payments = $payments->filter(function (array $payment) use ($statusFilter) {
                return strtolower($payment['status']) === $statusFilter;
            })->values();
        }

        return view('spedfly.admin.payments', [
            'payments' => $payments,
            'stats' => [
                'total_payments' => (float) $payments->sum('amount'),
                'refunds' => (float) $payments->sum('refund_amount'),
                'pending' => $payments->filter(fn (array $payment) => $payment['status'] === 'Pending')->count(),
                'completed' => $payments->filter(fn (array $payment) => $payment['status'] === 'Completed')->count(),
            ],
            'filters' => [
                'method' => $paymentMethod !== '' ? $paymentMethod : 'all',
                'tx' => $transactionSearch,
                'status' => $statusFilter !== '' ? $statusFilter : 'all',
            ],
            'methodOptions' => $paymentLabelsMap,
        ]);
    }

    public function callCenter(Request $request)
    {
        $data = $this->buildAdminCallCenterData($request);

        return view('spedfly.admin.call-center', $data);
    }

    public function storeCallCenter(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seller_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('type', 'seller')),
            ],
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where(
                    fn ($query) => $query->where('seller_id', $request->integer('seller_id'))
                ),
            ],
            'order_id' => [
                'nullable',
                Rule::exists('orders', 'id')->where(
                    fn ($query) => $query->where('seller_id', $request->integer('seller_id'))
                ),
            ],
            'agent_name' => ['required', 'string', 'max:255'],
            'call_type' => ['required', 'in:outgoing,incoming'],
            'priority' => ['required', 'in:normal,high,urgent'],
            'result' => ['required', 'in:connected,no_answer,failed,busy'],
            'duration' => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'call_time' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $seller = User::query()
            ->where('type', 'seller')
            ->findOrFail((int) $validated['seller_id']);

        $log = CallCenterLog::create([
            'seller_id' => $seller->id,
            'customer_id' => $validated['customer_id'],
            'order_id' => $validated['order_id'] ?? null,
            'agent_name' => $validated['agent_name'],
            'call_type' => $validated['call_type'],
            'priority' => $validated['priority'],
            'result' => $validated['result'],
            'duration_seconds' => $this->parseCallDuration($validated['duration'] ?? null),
            'notes' => $validated['notes'] ?? null,
            'call_time' => isset($validated['call_time']) ? Carbon::parse($validated['call_time']) : now(),
        ]);

        $log->update([
            'call_code' => 'C' . str_pad((string) $log->id, 4, '0', STR_PAD_LEFT),
        ]);

        return redirect()
            ->route('admin.call-center')
            ->with('success', 'Call log saved successfully.');
    }

    public function analytics(Request $request)
    {
        return view('spedfly.admin.analytics', $this->buildAdminAnalyticsData($request));
    }

    public function marketing()
    {
        return view('spedfly.admin.marketing');
    }

    public function reports()
    {
        $data = $this->buildSellerWiseReportData(request());

        return view('spedfly.admin.reports', $data);
    }

    public function exportReports(Request $request, string $format)
    {
        $format = strtolower($format);
        abort_unless(in_array($format, ['pdf', 'xls', 'excel', 'csv'], true), 404);

        $data = $this->buildSellerWiseReportData($request);
        $sellerLabel = $data['selectedSellerName'] ?? 'All Sellers';
        $dateLabel = $data['rangeStart']->format('Y-m-d') . '_to_' . $data['rangeEnd']->format('Y-m-d');

        if ($format === 'pdf') {
            $html = view('spedfly.admin.reports-export', $data)->render();
            if (! is_dir(storage_path('app/mpdf'))) {
                @mkdir(storage_path('app/mpdf'), 0777, true);
            }
            $mpdf = new Mpdf([
                'tempDir' => storage_path('app/mpdf'),
            ]);
            $mpdf->WriteHTML($html);

            return response(
                $mpdf->Output(
                    'seller-wise-report-' . Str::slug($sellerLabel) . '-' . $dateLabel . '.pdf',
                    Destination::STRING_RETURN
                ),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="seller-wise-report-' . Str::slug($sellerLabel) . '-' . $dateLabel . '.pdf"',
                ]
            );
        }

        $isCsv = $format === 'csv';
        $filename = 'seller-wise-report-' . Str::slug($sellerLabel) . '-' . $dateLabel . ($isCsv ? '.csv' : '.xls');
        $headers = [
            'Content-Type' => $isCsv ? 'text/csv; charset=UTF-8' : 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($data, $isCsv) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            $delimiter = $isCsv ? ',' : "\t";

            fputcsv($handle, ['Seller Wise Report'], $delimiter);
            fputcsv($handle, ['Range', $data['rangeLabel']], $delimiter);
            fputcsv($handle, ['Seller Filter', $data['selectedSellerName'] ?? 'All Sellers'], $delimiter);
            fputcsv($handle, [], $delimiter);
            fputcsv($handle, ['Seller', 'Shipped Orders', 'Calls', 'Returns', 'Shipped Value'], $delimiter);

            foreach ($data['sellerRows'] as $seller) {
                fputcsv($handle, [
                    $seller['seller_name'],
                    $seller['shipped_orders'],
                    $seller['calls'],
                    $seller['returns'],
                    number_format((float) $seller['shipped_amount'], 2, '.', ''),
                ], $delimiter);

                foreach ($seller['orders'] as $order) {
                    fputcsv($handle, [
                        '  ' . $order['order_id'],
                        $order['customer_name'],
                        $order['courier_name'],
                        ucfirst(str_replace('_', ' ', (string) $order['status'])),
                        number_format((float) $order['amount'], 2, '.', ''),
                        $order['shipment_date'],
                    ], $delimiter);
                }
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }

    private function buildSellerWiseReportData(Request $request): array
    {
        $today = now();
        $rangeStart = $today->copy()->startOfMonth()->startOfDay();
        $rangeEnd = $today->copy()->endOfDay();

        $fromInput = trim((string) $request->input('from', ''));
        $toInput = trim((string) $request->input('to', ''));
        $selectedSellerId = (int) $request->input('seller_id', 0);

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

        $sellerQuery = User::query()
            ->where('type', 'seller')
            ->orderBy('name');

        if ($selectedSellerId > 0) {
            $sellerQuery->where('id', $selectedSellerId);
        }

        $sellers = $sellerQuery->get(['id', 'name', 'email']);
        $sellerOptions = User::query()
            ->where('type', 'seller')
            ->orderBy('name')
            ->get(['id', 'name']);
        $selectedSellerName = $selectedSellerId > 0
            ? ($sellers->firstWhere('id', $selectedSellerId)?->name ?? 'Seller #' . $selectedSellerId)
            : 'All Sellers';

        $shippedShipments = Shipment::query()
            ->with(['order.customer', 'seller'])
            ->whereIn('status', ['in_transit', 'delivered'])
            ->whereBetween('shipment_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->when($selectedSellerId > 0, fn ($query) => $query->where('seller_id', $selectedSellerId))
            ->orderByDesc('shipment_date')
            ->orderByDesc('id')
            ->get(['id', 'seller_id', 'order_id', 'shipment_code', 'shipment_date', 'courier_name', 'status']);

        $callLogs = CallCenterLog::query()
            ->with('seller')
            ->whereBetween('call_time', [$rangeStart, $rangeEnd])
            ->when($selectedSellerId > 0, fn ($query) => $query->where('seller_id', $selectedSellerId))
            ->orderByDesc('call_time')
            ->orderByDesc('id')
            ->get(['id', 'seller_id', 'call_code', 'agent_name', 'result', 'duration_seconds', 'call_time']);

        $returns = OrderReturn::query()
            ->with(['seller', 'order.customer'])
            ->where(function ($query) use ($rangeStart, $rangeEnd) {
                $query
                    ->whereBetween('returned_at', [$rangeStart, $rangeEnd])
                    ->orWhere(function ($innerQuery) use ($rangeStart, $rangeEnd) {
                        $innerQuery
                            ->whereNull('returned_at')
                            ->whereBetween('created_at', [$rangeStart, $rangeEnd]);
                    });
            })
            ->when($selectedSellerId > 0, fn ($query) => $query->where('seller_id', $selectedSellerId))
            ->orderByDesc('returned_at')
            ->orderByDesc('id')
            ->get();

        $callCounts = $callLogs->groupBy('seller_id')->map->count();
        $returnCounts = $returns->groupBy('seller_id')->map->count();

        $sellerRows = $sellers->map(function (User $seller) use ($shippedShipments, $callCounts, $returnCounts) {
            $sellerShipments = $shippedShipments->where('seller_id', $seller->id);
            $uniqueShippedOrders = $sellerShipments
                ->filter(fn (Shipment $shipment) => filled($shipment->order_id))
                ->groupBy('order_id')
                ->map(fn ($group) => $group->first())
                ->values();

            $shippedAmount = (float) $uniqueShippedOrders->sum(
                fn (Shipment $shipment) => (float) ($shipment->order?->amount ?? 0)
            );

            return [
                'seller_id' => $seller->id,
                'seller_name' => $seller->name,
                'seller_email' => $seller->email,
                'shipped_orders' => $uniqueShippedOrders->count(),
                'calls' => (int) ($callCounts[$seller->id] ?? 0),
                'returns' => (int) ($returnCounts[$seller->id] ?? 0),
                'shipped_amount' => $shippedAmount,
                'orders' => $uniqueShippedOrders
                    ->map(function (Shipment $shipment) {
                        return [
                            'order_id' => $shipment->order?->external_order_id ?? '-',
                            'customer_name' => $shipment->order?->customer?->name ?? $shipment->customer_name ?? '-',
                            'courier_name' => $shipment->courier_name ?: '-',
                            'status' => $shipment->status,
                            'amount' => (float) ($shipment->order?->amount ?? 0),
                            'shipment_date' => optional($shipment->shipment_date)->format('d M Y') ?? '-',
                        ];
                    })
                    ->values(),
            ];
        })->sortByDesc('shipped_orders')->values();

        $shipmentDetailRows = $shippedShipments
            ->filter(fn (Shipment $shipment) => filled($shipment->order_id))
            ->groupBy(fn (Shipment $shipment) => $shipment->seller_id . '|' . $shipment->order_id)
            ->map(function ($group) {
                /** @var Shipment $shipment */
                $shipment = $group->first();

                return [
                    'seller_name' => $shipment->seller?->name ?? ('Seller #' . $shipment->seller_id),
                    'seller_email' => $shipment->seller?->email ?? '-',
                    'seller_id' => $shipment->seller_id,
                    'order_id' => $shipment->order?->external_order_id ?? '-',
                    'customer_name' => $shipment->order?->customer?->name ?? $shipment->customer_name ?? '-',
                    'courier_name' => $shipment->courier_name ?: '-',
                    'status' => $shipment->status,
                    'amount' => (float) ($shipment->order?->amount ?? 0),
                    'shipment_date' => optional($shipment->shipment_date)->format('d M Y') ?? '-',
                ];
            })
            ->sortByDesc('shipment_date')
            ->values();

        return [
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'rangeLabel' => $rangeStart->format('d M Y') . ' - ' . $rangeEnd->format('d M Y'),
            'selectedSellerId' => $selectedSellerId > 0 ? $selectedSellerId : null,
            'selectedSellerName' => $selectedSellerName,
            'sellerOptions' => $sellerOptions,
            'summary' => [
                'seller_count' => $sellers->count(),
                'shipped_orders' => $shipmentDetailRows->count(),
                'call_count' => $callLogs->count(),
                'return_count' => $returns->count(),
            ],
            'sellerRows' => $sellerRows,
            'shipmentDetailRows' => $shipmentDetailRows,
        ];
    }

    public function systemSettings(): View
    {
        $definitions = $this->systemSettingDefinitions();
        $settings = $this->resolveSystemSettings($definitions);
        $historyRows = SystemSettingHistory::query()
            ->with(['changedBy', 'setting'])
            ->latest()
            ->limit(12)
            ->get();

        $enabledSecuritySettings = collect([
            (bool) $settings['enable_ssl'],
            (bool) $settings['enable_two_fa'],
            (bool) $settings['enable_audit_logging'],
        ])->filter()->count();

        return view('spedfly.admin.system-settings', [
            'settingDefinitions' => $definitions,
            'settings' => $settings,
            'historyRows' => $historyRows,
            'summaryCards' => [
                [
                    'icon' => 'bi-hdd-rack',
                    'value' => 'Live',
                    'label' => 'Configuration Status',
                    'color' => 'primary',
                ],
                [
                    'icon' => 'bi-shield-lock',
                    'value' => $enabledSecuritySettings . '/3',
                    'label' => 'Security Settings Enabled',
                    'color' => 'success',
                ],
                [
                    'icon' => 'bi-clock-history',
                    'value' => $historyRows->first()?->created_at?->diffForHumans() ?? 'No changes yet',
                    'label' => 'Last Config Change',
                    'color' => 'warning',
                ],
                [
                    'icon' => 'bi-journal-text',
                    'value' => (string) $historyRows->count(),
                    'label' => 'Audit Entries',
                    'color' => 'danger',
                ],
            ],
        ]);
    }

    public function updateSystemSettings(Request $request): RedirectResponse
    {
        $definitions = $this->systemSettingDefinitions();

        $rules = [];
        foreach ($definitions as $key => $definition) {
            $rules[$key] = match ($definition['type']) {
                'text' => ['required', 'string', 'max:255'],
                'select' => ['required', Rule::in(array_keys($definition['options']))],
                'number' => ['required', 'integer', 'min:' . ($definition['min'] ?? 1)],
                'boolean' => ['nullable', 'boolean'],
                default => ['required', 'string', 'max:255'],
            };
        }

        $validated = $request->validate($rules);
        $currentSettings = $this->resolveSystemSettings($definitions);
        $storedSettings = SystemSetting::query()->get()->keyBy('setting_key');
        $admin = Auth::user();

        DB::transaction(function () use ($definitions, $validated, $currentSettings, $storedSettings, $admin): void {
            foreach ($definitions as $key => $definition) {
                $newValue = $this->normalizeSystemSettingValue($validated[$key] ?? null, $definition);
                $previousValue = $currentSettings[$key] ?? null;
                $existingSetting = $storedSettings->get($key);
                $persistedValue = $this->persistedSystemSettingValue($newValue, $definition['type']);

                if ($existingSetting === null) {
                    SystemSetting::query()->create([
                        'setting_key' => $key,
                        'setting_value' => $persistedValue,
                        'updated_by' => $admin?->id,
                    ]);

                    if ($this->settingsAreEqual($previousValue, $newValue, $definition['type'])) {
                        continue;
                    }
                } elseif ($this->settingsAreEqual($previousValue, $newValue, $definition['type'])) {
                    continue;
                }

                $setting = SystemSetting::query()->updateOrCreate(
                    ['setting_key' => $key],
                    [
                        'setting_value' => $persistedValue,
                        'updated_by' => $admin?->id,
                    ]
                );

                SystemSettingHistory::query()->create([
                    'system_setting_id' => $setting->id,
                    'setting_key' => $key,
                    'setting_label' => $definition['label'],
                    'previous_value' => $this->displaySystemSettingValue($key, $previousValue, $definition),
                    'new_value' => $this->displaySystemSettingValue($key, $newValue, $definition),
                    'changed_by' => $admin?->id,
                    'status' => 'applied',
                ]);

                ActivityLog::query()->create([
                    'user_id' => $admin?->id,
                    'title' => 'System Setting Updated',
                    'message' => $definition['label'] . ' changed from ' . $this->displaySystemSettingValue($key, $previousValue, $definition) . ' to ' . $this->displaySystemSettingValue($key, $newValue, $definition) . '.',
                    'type' => 'system_setting',
                    'module' => 'system',
                    'severity' => 'success',
                    'data' => [
                        'setting_key' => $key,
                        'previous_value' => $previousValue,
                        'new_value' => $newValue,
                        'ip_address' => request()?->ip(),
                    ],
                ]);
            }
        });

        Cache::forget('spedfly.system.currency');
        Cache::forget('spedfly.system.timezone');

        $language = strtolower((string) ($validated['language'] ?? 'english'));
        $localeMap = [
            'en' => 'en',
            'english' => 'en',
            'fr' => 'fr',
            'french' => 'fr',
            'es' => 'es',
            'spanish' => 'es',
            'it' => 'it',
            'italian' => 'it',
        ];
        $locale = $localeMap[$language] ?? 'en';

        SystemSetting::query()->updateOrCreate(
            ['setting_key' => 'language'],
            [
                'setting_value' => $locale,
                'updated_by' => $admin?->id,
            ]
        );

        session(['locale' => $locale]);
        App::setLocale($locale);

        return redirect()
            ->route('admin.system-settings')
            ->with('success', 'System settings updated successfully.');
    }

    private function systemSettingDefinitions(): array
    {
        return [
            'system_name' => [
                'label_key' => 'system_name',
                'label' => 'System Name',
                'type' => 'text',
                'default' => 'Spedfly Multi-Tenant SaaS',
            ],
            'timezone' => [
                'label_key' => 'timezone',
                'label' => 'Timezone',
                'type' => 'select',
                'default' => 'Asia/Kolkata',
                'options' => [
                    'Asia/Kolkata' => 'Asia/Kolkata (UTC+5:30)',
                    'Asia/Dubai' => 'Asia/Dubai (UTC+4:00)',
                    'Europe/London' => 'Europe/London (UTC+0:00)',
                    'America/New_York' => 'America/New_York (UTC-5:00)',
                ],
            ],
            'language' => [
                'label_key' => 'default_language',
                'label' => 'Default Language',
                'type' => 'select',
                'default' => 'en',
                'options' => [
                    'en' => __('ui.english'),
                    'fr' => __('ui.french'),
                    'es' => __('ui.spanish'),
                    'it' => __('ui.italian'),
                ],
            ],
            'currency' => [
                'label_key' => 'default_currency',
                'label' => 'Default Currency',
                'type' => 'select',
                'default' => 'INR',
                'options' => [
                    'INR' => 'INR (₹)',
                    'USD' => 'USD ($)',
                    'EUR' => 'EUR (€)',
                    'GBP' => 'GBP (£)',
                ],
            ],
            'api_rate_limit' => [
                'label_key' => 'api_rate_limit',
                'label' => 'API Rate Limit (requests/hour)',
                'type' => 'number',
                'default' => 10000,
                'min' => 1,
            ],
            'session_timeout' => [
                'label_key' => 'session_timeout',
                'label' => 'Session Timeout (minutes)',
                'type' => 'number',
                'default' => 30,
                'min' => 5,
            ],
            'enable_ssl' => [
                'label_key' => 'enable_ssl_tls',
                'label' => 'Enable SSL/TLS',
                'type' => 'boolean',
                'default' => true,
            ],
            'enable_two_fa' => [
                'label_key' => 'enable_two_factor_auth',
                'label' => 'Enforce Two-Factor Authentication',
                'type' => 'boolean',
                'default' => true,
            ],
            'enable_audit_logging' => [
                'label_key' => 'enable_audit_logging',
                'label' => 'Enable Audit Logging',
                'type' => 'boolean',
                'default' => true,
            ],
        ];
    }

    private function resolveSystemSettings(array $definitions): array
    {
        $storedSettings = SystemSetting::query()->pluck('setting_value', 'setting_key')->all();
        $resolved = [];

        foreach ($definitions as $key => $definition) {
            $rawValue = $storedSettings[$key] ?? $definition['default'];
            $resolved[$key] = $key === 'language'
                ? $this->normalizeLanguageLocale($rawValue)
                : $this->castSystemSettingValue($rawValue, $definition['type']);
        }

        return $resolved;
    }

    private function normalizeLanguageLocale(mixed $value): string
    {
        return match (strtolower((string) $value)) {
            'en', 'english' => 'en',
            'fr', 'french' => 'fr',
            'es', 'spanish' => 'es',
            'it', 'italian' => 'it',
            default => 'en',
        };
    }

    private function castSystemSettingValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            'number' => (int) $value,
            default => $value,
        };
    }

    private function normalizeSystemSettingValue(mixed $value, array $definition): mixed
    {
        return match ($definition['type']) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => (int) $value,
            default => is_string($value) ? trim($value) : $value,
        };
    }

    private function settingsAreEqual(mixed $previousValue, mixed $newValue, string $type): bool
    {
        return match ($type) {
            'boolean' => (bool) $previousValue === (bool) $newValue,
            'number' => (int) $previousValue === (int) $newValue,
            default => (string) $previousValue === (string) $newValue,
        };
    }

    private function persistedSystemSettingValue(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'number' => (string) $value,
            default => (string) $value,
        };
    }

    private function displaySystemSettingValue(string $key, mixed $value, array $definition): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return match ($definition['type']) {
            'boolean' => $value ? 'Enabled' : 'Disabled',
            'number' => match ($key) {
                'api_rate_limit' => number_format((int) $value) . '/hour',
                'session_timeout' => (int) $value . ' minutes',
                default => (string) $value,
            },
            'select' => $key === 'currency'
                ? match ((string) $value) {
                    'INR' => 'INR (Rs)',
                    'USD' => 'USD ($)',
                    'EUR' => 'EUR',
                    'GBP' => 'GBP',
                    default => (string) $value,
                }
                : ($key === 'language'
                    ? match (strtolower((string) $value)) {
                        'en', 'english' => __('ui.english'),
                        'fr', 'french' => __('ui.french'),
                        'es', 'spanish' => __('ui.spanish'),
                        'it', 'italian' => __('ui.italian'),
                        default => (string) $value,
                    }
                    : ($definition['options'][$value] ?? (string) $value)),
            default => (string) $value,
        };
    }

    public function adminManagement()
    {
        return view('spedfly.admin.admin-management');
    }

    public function logs(Request $request): View
    {
        return view('spedfly.admin.logs', $this->buildAdminLogsData($request));
    }

    public function admin()
    {
        return view('spedfly.admin.admin');
    }

    private function buildAdminCallCenterData(Request $request): array
    {
        $today = now();
        $fromDate = trim((string) $request->input('from', ''));
        $toDate = trim((string) $request->input('to', ''));
        $resultFilter = strtolower(trim((string) $request->input('result', 'all')));
        $search = trim((string) $request->input('search', ''));

        $resultOptions = [
            'connected' => __('ui.connected'),
            'no_answer' => __('ui.no_answer'),
            'failed' => __('ui.failed'),
            'busy' => __('ui.busy'),
        ];

        if (! in_array($resultFilter, array_merge(['all'], array_keys($resultOptions)), true)) {
            $resultFilter = 'all';
        }

        $rangeStart = $today->copy()->startOfMonth()->startOfDay();
        $rangeEnd = $today->copy()->endOfDay();

        if ($fromDate !== '') {
            try {
                $rangeStart = Carbon::parse($fromDate)->startOfDay();
            } catch (\Throwable $exception) {
                $rangeStart = $today->copy()->startOfMonth()->startOfDay();
            }
        }

        if ($toDate !== '') {
            try {
                $rangeEnd = Carbon::parse($toDate)->endOfDay();
            } catch (\Throwable $exception) {
                $rangeEnd = $today->copy()->endOfDay();
            }
        }

        if ($rangeStart->greaterThan($rangeEnd)) {
            [$rangeStart, $rangeEnd] = [$rangeEnd->copy()->startOfDay(), $rangeStart->copy()->endOfDay()];
        }

        $logsQuery = CallCenterLog::query()
            ->with(['customer', 'order.customer', 'seller'])
            ->whereBetween('call_time', [$rangeStart, $rangeEnd]);

        if ($search !== '') {
            $logsQuery->where(function ($query) use ($search) {
                $query
                    ->where('call_code', 'like', '%' . $search . '%')
                    ->orWhere('agent_name', 'like', '%' . $search . '%')
                    ->orWhere('notes', 'like', '%' . $search . '%')
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery
                            ->where('name', 'like', '%' . $search . '%')
                            ->orWhere('phone', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('external_order_id', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('seller', function ($sellerQuery) use ($search) {
                        $sellerQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        $callLogs = $logsQuery
            ->orderByDesc('call_time')
            ->orderByDesc('id')
            ->get();

        $callRows = $callLogs
            ->groupBy(function (CallCenterLog $log) {
                if ($log->order_id) {
                    return 'order:' . $log->order_id;
                }

                return 'customer:' . $log->customer_id;
            })
            ->map(function ($logs) use ($resultFilter) {
                $orderedLogs = $logs->sortBy([
                    ['call_time', 'asc'],
                    ['id', 'asc'],
                ])->values();

                $finalLog = $orderedLogs->last();
                $finalResult = strtolower((string) ($finalLog?->result ?? ''));

                if ($resultFilter !== 'all' && $finalResult !== $resultFilter) {
                    return null;
                }

                $attempts = [];
                foreach ($orderedLogs->take(3) as $index => $log) {
                    $attempts[$index] = [
                        'label' => $this->callResultLabel((string) $log->result),
                        'time' => optional($log->call_time)->format('Y-m-d H:i') ?? '-',
                    ];
                }

                $attempts = array_pad($attempts, 3, null);
                $notes = trim((string) ($finalLog->notes ?? ''));

                return [
                    'sort_time' => optional($finalLog?->call_time)->timestamp ?? 0,
                    'order_id' => $finalLog?->order?->external_order_id
                        ?? $finalLog?->order_id
                        ?? '-',
                    'customer_name' => $finalLog?->customer?->name
                        ?? $finalLog?->order?->customer?->name
                        ?? '-',
                    'phone' => $finalLog?->customer?->phone
                        ?? $finalLog?->order?->customer?->phone
                        ?? '-',
                    'attempts' => $attempts,
                    'final_status' => $this->callResultLabel((string) ($finalLog?->result ?? '')),
                    'final_class' => $this->callResultBadgeClass((string) ($finalLog?->result ?? '')),
                    'notes' => $notes !== '' ? $notes : '-',
                    'notes_full' => $notes,
                    'seller_name' => $finalLog?->seller?->name ?? ('Seller #' . ($finalLog?->seller_id ?? '-')),
                ];
            })
            ->filter()
            ->sortByDesc('sort_time')
            ->values();

        $sellerOptions = User::query()
            ->where('type', 'seller')
            ->orderBy('name')
            ->get(['id', 'name']);

        $customerOptions = Customer::query()
            ->with('seller')
            ->orderBy('name')
            ->get(['id', 'seller_id', 'name', 'phone']);

        $orderOptions = Order::query()
            ->with(['customer', 'seller'])
            ->latest('ordered_at')
            ->latest('id')
            ->get(['id', 'seller_id', 'customer_id', 'external_order_id']);

        $agentOptions = $callLogs
            ->pluck('agent_name')
            ->prepend((string) Auth::user()?->name)
            ->filter()
            ->unique()
            ->values();

        $totalLogs = $callLogs->count();
        $connectedLogs = $callLogs->where('result', 'connected')->count();
        $missedLogs = $callLogs->whereIn('result', ['no_answer', 'failed', 'busy'])->count();
        $uniqueOrders = $callRows->count();
        $uniqueSellers = $callLogs->pluck('seller_id')->unique()->count();
        $connectedRate = $totalLogs > 0 ? (int) round(($connectedLogs / $totalLogs) * 100) : 0;

        return [
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'resultFilter' => $resultFilter,
            'resultOptions' => $resultOptions,
            'search' => $search,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'sellerOptions' => $sellerOptions,
            'customerOptions' => $customerOptions,
            'orderOptions' => $orderOptions,
            'agentOptions' => $agentOptions,
            'summary' => [
                'total_calls' => $totalLogs,
                'connected_rate' => $connectedRate,
                'missed_calls' => $missedLogs,
                'orders' => $uniqueOrders,
                'sellers' => $uniqueSellers,
            ],
            'callRows' => $callRows,
        ];
    }

    private function callCenterEligibleOrderStatuses(): array
    {
        return ['lead', 'new', 'processing', 'shipped'];
    }

    private function parseCallDuration(?string $duration): int
    {
        if (! $duration || ! preg_match('/^\d{1,2}:\d{2}$/', $duration)) {
            return 0;
        }

        [$minutes, $seconds] = array_map('intval', explode(':', $duration));

        return ($minutes * 60) + $seconds;
    }

    private function buildAdminLogsData(Request $request): array
    {
        $dateFilter = trim((string) $request->input('date', ''));
        $moduleFilter = trim((string) $request->input('module', 'all'));
        $typeFilter = strtolower(trim((string) $request->input('type', 'all')));
        $userFilter = trim((string) $request->input('user', ''));
        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 10);

        if (! in_array($perPage, [5, 10, 25, 50], true)) {
            $perPage = 10;
        }

        $logsQuery = ActivityLog::query()
            ->with('user')
            ->whereHas('user', function ($query) {
                $query->where('type', 'admin');
            });

        if ($moduleFilter !== '' && $moduleFilter !== 'all' && array_key_exists($moduleFilter, $this->adminLogModuleOptions())) {
            $logsQuery->where('module', $moduleFilter);
        }

        if ($typeFilter !== 'all') {
            $logsQuery->where('severity', match ($typeFilter) {
                'error' => 'danger',
                'warning' => 'warning',
                'success' => 'success',
                default => 'success',
            });
        }

        if ($dateFilter !== '') {
            try {
                $date = Carbon::parse($dateFilter);
                $logsQuery->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
            } catch (\Throwable $exception) {
                // Ignore invalid dates and fall back to the full log list.
            }
        }

        if ($userFilter !== '') {
            $logsQuery->where(function ($query) use ($userFilter) {
                $query->whereHas('user', function ($userQuery) use ($userFilter) {
                    $userQuery
                        ->where('name', 'like', '%' . $userFilter . '%')
                        ->orWhere('email', 'like', '%' . $userFilter . '%');
                });
            });
        }

        if ($search !== '') {
            $logsQuery->where(function ($query) use ($search) {
                $query
                    ->where('title', 'like', '%' . $search . '%')
                    ->orWhere('message', 'like', '%' . $search . '%')
                    ->orWhere('type', 'like', '%' . $search . '%')
                    ->orWhere('module', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        $summaryLogs = (clone $logsQuery)->get();

        $logs = $logsQuery
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $logs->setCollection($logs->getCollection()->map(function (ActivityLog $log) {
            $module = strtolower((string) $log->module);
            $severity = strtolower((string) $log->severity);

            return [
                'id' => $log->id,
                'timestamp' => optional($log->created_at)->format('d M Y, h:i A') ?? '-',
                'raw_timestamp' => optional($log->created_at)->timestamp ?? 0,
                'module_raw' => $module,
                'type_raw' => strtolower((string) $log->type),
                'type' => $this->adminLogSeverityLabel($severity),
                'type_class' => $this->adminLogBadgeClass($severity),
                'severity' => $severity,
                'module' => $this->adminLogModuleLabel($module),
                'user' => $log->user?->name ?? $log->user?->email ?? 'System',
                'message' => $log->message,
                'ip_address' => $this->adminLogIpLabel(
                    (string) data_get($log->data, 'ip_address', data_get($log->data, 'ip', '-'))
                ),
                'title' => $log->title,
                'details' => [
                    'title' => $log->title,
                    'message' => $log->message,
                    'type' => $this->adminLogSeverityLabel($severity),
                    'module' => $this->adminLogModuleLabel($module),
                    'user' => $log->user?->name ?? $log->user?->email ?? 'System',
                    'ip_address' => $this->adminLogIpLabel(
                        (string) data_get($log->data, 'ip_address', data_get($log->data, 'ip', '-'))
                    ),
                    'created_at' => optional($log->created_at)->format('d M Y, h:i A') ?? '-',
                    'data' => is_array($log->data) ? $log->data : [],
                ],
            ];
        }));

        $totalLogs = $summaryLogs->count();
        $todayLogs = $summaryLogs->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->count();
        $errorLogs = $summaryLogs->where('severity', 'danger')->count();
        $warningLogs = $summaryLogs->where('severity', 'warning')->count();
        $infoLogs = $summaryLogs->where('severity', 'success')->count();

        return [
            'logs' => $logs,
            'summaryCards' => [
                [
                    'icon' => 'bi-list-check',
                    'value' => number_format($totalLogs),
                    'label' => 'Total Logs',
                    'color' => 'primary',
                ],
                [
                    'icon' => 'bi-exclamation-triangle',
                    'value' => number_format($errorLogs),
                    'label' => 'Errors',
                    'color' => 'danger',
                ],
                [
                    'icon' => 'bi-info-circle',
                    'value' => number_format($warningLogs),
                    'label' => 'Warnings',
                    'color' => 'warning',
                ],
                [
                    'icon' => 'bi-check-circle',
                    'value' => number_format($todayLogs),
                    'label' => 'Today',
                    'color' => 'success',
                ],
            ],
            'filters' => [
                'type' => $typeFilter,
                'module' => $moduleFilter,
                'date' => $dateFilter,
                'user' => $userFilter,
                'search' => $search,
            ],
            'moduleOptions' => $this->adminLogModuleOptions(),
        ];
    }

    private function adminLogModuleOptions(): array
    {
        return [
            'imports' => 'Imports',
            'orders' => 'Orders',
            'calls' => 'Call Center',
            'returns' => 'Returns',
            'payments' => 'Payments',
            'system' => 'System',
        ];
    }

    private function adminLogSeverityLabel(string $severity): string
    {
        return match ($severity) {
            'danger' => 'Error',
            'warning' => 'Warning',
            default => 'Info',
        };
    }

    private function adminLogModuleLabel(string $module): string
    {
        return $this->adminLogModuleOptions()[$module] ?? ucfirst(str_replace('_', ' ', $module));
    }

    private function adminLogBadgeClass(string $severity): string
    {
        return match ($severity) {
            'danger' => 'bg-danger',
            'warning' => 'bg-warning text-dark',
            default => 'bg-success',
        };
    }

    private function adminLogIpLabel(string $ip): string
    {
        $normalized = trim($ip);

        if ($normalized === '' || $normalized === '-') {
            return '-';
        }

        if (in_array($normalized, ['::1', '127.0.0.1'], true)) {
            return 'Localhost';
        }

        return $normalized;
    }

    private function callResultLabel(string $result): string
    {
        return [
            'connected' => 'Connected',
            'no_answer' => 'No Answer',
            'failed' => 'Failed',
            'busy' => 'Busy',
        ][strtolower($result)] ?? ucfirst(str_replace('_', ' ', $result));
    }

    private function callResultBadgeClass(string $result): string
    {
        return [
            'connected' => 'bg-success',
            'no_answer' => 'bg-warning text-dark',
            'failed' => 'bg-danger',
            'busy' => 'bg-secondary',
        ][strtolower($result)] ?? 'bg-primary';
    }

    private function resolveShipmentCoordinates(array $validated): array
    {
        $pickupCoordinates = $this->geocodeAddress(
            $validated['pickup_address'],
            $validated['pickup_city'] ?? null,
            $validated['pickup_postal_code'] ?? null
        );

        $deliveryCoordinates = $this->geocodeAddress(
            $validated['delivery_address'],
            $validated['delivery_city'] ?? null,
            $validated['delivery_postal_code'] ?? null
        );

        return [
            'pickup_latitude' => $pickupCoordinates['lat'],
            'pickup_longitude' => $pickupCoordinates['lng'],
            'delivery_latitude' => $deliveryCoordinates['lat'],
            'delivery_longitude' => $deliveryCoordinates['lng'],
        ];
    }

    private function geocodeAddress(string $address, ?string $city = null, ?string $postalCode = null): array
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

    private function notifySellerAboutOrderStatusChange(Order $order, ?string $previousStatus, string $newStatus): void
    {
        if (! $order->seller_id) {
            return;
        }

        $previousLabel = $this->orderStatusOptions()[strtolower((string) $previousStatus)] ?? ucfirst((string) $previousStatus);
        $newLabel = $this->orderStatusOptions()[strtolower($newStatus)] ?? ucfirst($newStatus);

        AppNotification::query()->create([
            'user_id' => $order->seller_id,
            'title' => 'Order Status Updated',
            'message' => 'Order ' . $order->external_order_id . ' changed from ' . $previousLabel . ' to ' . $newLabel . ' by admin.',
            'type' => 'order_status',
            'is_read' => false,
            'read_at' => null,
            'data' => [
                'order_id' => $order->id,
                'external_order_id' => $order->external_order_id,
                'previous_status' => strtolower((string) $previousStatus),
                'new_status' => strtolower($newStatus),
            ],
        ]);
    }


}

