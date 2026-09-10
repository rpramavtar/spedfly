@include('spedfly.include.header')

@php
  $sellerStatus = strtolower((string) ($seller->status ?? 'active'));
  if ($sellerStatus === 'suspended') {
      $sellerStatus = 'deactive';
  }
  $statusBadge = $sellerStatus === 'active' ? 'bg-success' : 'bg-secondary';
  $memberSince = optional($seller->created_at)->format('M d, Y h:i A') ?? '-';
  $feeSummaryCards = $feeSummary['cards'] ?? [];
  $feeCurrency = system_currency_code();
  $currencySymbols = [
      'EUR' => '€',
      'USD' => '$',
      'GBP' => '£',
      'INR' => '₹',
  ];
  $formatFeeAmount = function ($amount, ?string $currency = null) use ($currencySymbols) {
      $currency = strtoupper((string) ($currency ?: 'EUR'));
      $symbol = $currencySymbols[$currency] ?? ($currency . ' ');

      return $symbol . number_format((float) $amount, 2);
  };
  $feeRows = old('fee_rules');
  $feeCurrency = system_currency_code();
  $feeCurrencySymbol = system_currency_symbol();
  $defaultFeeType = array_key_first($feeTypeOptions) ?? 'delivery';
  $formatFeeAmount = function ($amount) use ($feeCurrencySymbol) {
      return $feeCurrencySymbol . number_format((float) $amount, 2);
  };

  if (! is_array($feeRows) || empty($feeRows)) {
      $feeRows = $feeRules->map(function ($rule) {
          return [
              'label' => $rule->label ?? '',
              'fee_type' => $rule->fee_type,
              'billing_unit' => $rule->billing_unit,
              'min_quantity' => $rule->min_quantity,
              'max_quantity' => $rule->max_quantity,
              'rate' => $rule->rate,
              'is_active' => $rule->is_active ? '1' : '0',
              'notes' => $rule->notes,
          ];
      })->values()->all();
  }

  if (empty($feeRows)) {
      $feeRows = [[
          'label' => '',
          'fee_type' => $defaultFeeType,
          'billing_unit' => 'monthly_orders',
          'min_quantity' => '',
          'max_quantity' => '',
          'rate' => '',
          'is_active' => '1',
          'notes' => '',
      ]];
  }
@endphp

<div class="app-body">
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
          <div>
            <h1 class="mt-4 mb-1">{{ __('ui.seller_profile') }}</h1>
            <p class="text-muted mb-0">{{ __('ui.detailed_profile_bank_details_and_recent_activity_for_this_seller') }}</p>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.sellers') }}" class="btn btn-outline-secondary">
              <i class="bi bi-arrow-left me-1"></i>{{ __('ui.back_to_sellers') }}
            </a>
            <a href="{{ route('admin.sellers.wallet', $seller) }}" class="btn btn-outline-primary">
              <i class="bi bi-wallet2 me-1"></i>{{ __('ui.wallet') }}
            </a>
            <button class="btn btn-outline-warning" type="button" data-bs-toggle="modal" data-bs-target="#editSellerModal">
              <i class="bi bi-pencil-square me-1"></i>{{ __('ui.edit_seller') }}
            </button>
          </div>
        </div>

        @if (session('success'))
          <div class="alert alert-success js-auto-hide-alert mt-3">{{ session('success') }}</div>
        @endif

        <div class="row mt-4 g-3">
          <div class="col-md-3">
            <div class="card p-3 h-100">
              <h6 class="text-muted mb-1">{{ __('ui.total_orders') }}</h6>
              <h3 class="mb-0">{{ number_format($stats['orders'] ?? 0) }}</h3>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card p-3 h-100">
              <h6 class="text-muted mb-1">{{ __('ui.customers') }}</h6>
              <h3 class="mb-0">{{ number_format($stats['customers'] ?? 0) }}</h3>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card p-3 h-100">
              <h6 class="text-muted mb-1">{{ __('ui.shipments') }}</h6>
              <h3 class="mb-0">{{ number_format($stats['shipments'] ?? 0) }}</h3>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card p-3 h-100">
              <h6 class="text-muted mb-1">{{ __('ui.revenue') }}</h6>
              <h3 class="mb-0">{{ system_currency_format($stats['revenue'] ?? 0) }}</h3>
            </div>
          </div>
        </div>

        <div class="row mt-4 g-3">
          <div class="col-12">
            <div class="card h-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                  <div>
                    <h5 class="card-title mb-1">{{ __('ui.fee_overview') }}</h5>
                    <p class="text-muted mb-0">{{ __('ui.monthly_fee_estimate_based_on_the_active_seller_tariffs') }}</p>
                  </div>
                  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#feeRulesModal">
                    <i class="bi bi-sliders me-1"></i>{{ __('ui.manage_fee_rules') }}
                  </button>
                </div>

                <div class="row g-3">
                  <div class="col-md-3">
                      <div class="border rounded-3 p-3 h-100">
                        <small class="text-muted text-uppercase d-block mb-2">{{ __('ui.total_estimated_fee') }}</small>
                      <h3 class="mb-0">{{ $formatFeeAmount($feeSummary['total_amount'] ?? 0) }}</h3>
                      </div>
                  </div>
                  <div class="col-md-3">
                    <div class="border rounded-3 p-3 h-100">
                      <small class="text-muted text-uppercase d-block mb-2">{{ __('ui.monthly_orders') }}</small>
                      <h3 class="mb-0">{{ number_format($feeSummary['metrics']['monthly_orders'] ?? 0) }}</h3>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="border rounded-3 p-3 h-100">
                      <small class="text-muted text-uppercase d-block mb-2">{{ __('ui.delivered_orders') }}</small>
                      <h3 class="mb-0">{{ number_format($feeSummary['metrics']['delivered_orders'] ?? 0) }}</h3>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="border rounded-3 p-3 h-100">
                      <small class="text-muted text-uppercase d-block mb-2">{{ __('ui.calls') }}</small>
                      <h3 class="mb-0">{{ number_format($feeSummary['metrics']['calls'] ?? 0) }}</h3>
                    </div>
                  </div>
                </div>

                <div class="table-responsive mt-4">
                  <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>{{ __('ui.fee_type') }}</th>
                        <th>{{ __('ui.billing_unit') }}</th>
                        <th>{{ __('ui.tier') }}</th>
                        <th>{{ __('ui.rate') }}</th>
                        <th>{{ __('ui.quantity') }}</th>
                        <th>{{ __('ui.estimated_fee') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse ($feeSummaryCards as $card)
                        <tr>
                          <td>{{ $card['label'] }}</td>
                          <td>{{ $card['quantity_label'] }}</td>
                          <td>{{ $card['tier_label'] }}</td>
                          <td>{{ $formatFeeAmount($card['rate'] ?? 0) }}</td>
                          <td>{{ number_format($card['quantity'] ?? 0) }}</td>
                          <td class="fw-semibold">{{ $formatFeeAmount($card['amount'] ?? 0) }}</td>
                        </tr>
                      @empty
                        <tr>
                          <td colspan="6" class="text-center text-muted py-4">{{ __('ui.no_active_fee_rules_are_configured_for_this_seller_yet') }}</td>
                        </tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                  <div id="sellerProductsInfo" class="text-muted small"></div>
                  <nav aria-label="Seller products pagination">
                    <ul id="sellerProductsPagination" class="pagination pagination-sm mb-0"></ul>
                  </nav>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                  <div id="sellerProductsInfo" class="text-muted small"></div>
                  <nav aria-label="Seller products pagination">
                    <ul id="sellerProductsPagination" class="pagination pagination-sm mb-0"></ul>
                  </nav>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row mt-4 g-3">
          <div class="col-lg-5">
            <div class="card h-100">
              <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                  <img src="{{ asset('assets/admin/images/user.png') }}" width="72" height="72" class="rounded-circle" alt="seller">
                  <div>
                    <h3 class="mb-1">{{ $seller->name }}</h3>
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                      <span class="badge {{ $statusBadge }} px-3 py-2">{{ ucfirst($sellerStatus) }}</span>
                      <span class="badge bg-light text-dark px-3 py-2">{{ __('ui.member_since') }} {{ $memberSince }}</span>
                    </div>
                  </div>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                  <div class="d-flex align-items-center gap-2">
                    <label for="sellerProductsPerPage" class="form-label mb-0">{{ __('ui.show') }}</label>
                    <select id="sellerProductsPerPage" class="form-select form-select-sm" style="width: 92px;">
                      <option value="5" selected>5</option>
                      <option value="10">10</option>
                      <option value="25">25</option>
                      <option value="50">50</option>
                      <option value="-1">All</option>
                    </select>
                    <span class="text-muted">{{ __('ui.entries_per_page') }}</span>
                  </div>

                  <div class="d-flex align-items-center gap-2">
                    <label for="sellerProductsSearch" class="form-label mb-0">{{ __('ui.search') }}:</label>
                    <input id="sellerProductsSearch" type="search" class="form-control form-control-sm" style="width: 220px;" placeholder="{{ __('ui.search_products') }}" oninput="window.sellerProductsFilter && window.sellerProductsFilter()" onchange="window.sellerProductsFilter && window.sellerProductsFilter()">
                  </div>
                </div>

                <form method="get" action="{{ route('admin.sellers.show', $seller) }}" class="row g-2 align-items-end mb-3">
                  <div class="col-md-3">
                    <label for="sellerProductsPerPage" class="form-label mb-1">{{ __('ui.show') }}</label>
                    <select id="sellerProductsPerPage" name="product_per_page" class="form-select form-select-sm" onchange="this.form.submit()">
                      <option value="5" {{ (int) ($productPerPage ?? 5) === 5 ? 'selected' : '' }}>5</option>
                      <option value="10" {{ (int) ($productPerPage ?? 5) === 10 ? 'selected' : '' }}>10</option>
                      <option value="25" {{ (int) ($productPerPage ?? 5) === 25 ? 'selected' : '' }}>25</option>
                      <option value="50" {{ (int) ($productPerPage ?? 5) === 50 ? 'selected' : '' }}>50</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="sellerProductsSearch" class="form-label mb-1">{{ __('ui.search') }}</label>
                    <input id="sellerProductsSearch" name="product_q" type="search" value="{{ $productSearch ?? '' }}" class="form-control form-control-sm" placeholder="{{ __('ui.search_products') }}" oninput="this.form.requestSubmit()" onchange="this.form.requestSubmit()">
                  </div>
                  <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('ui.filter') }}</button>
                    <a href="{{ route('admin.sellers.show', $seller) }}" class="btn btn-outline-secondary btn-sm">{{ __('ui.reset') }}</a>
                  </div>
                </form>

                <div class="table-responsive">
                  <table class="table mb-0">
                    <tbody>
                      <tr>
                        <th class="ps-0">{{ __('ui.company_name') }}</th>
                        <td>{{ $seller->company_name ?? '-' }}</td>
                      </tr>
                      <tr>
                        <th class="ps-0">{{ __('ui.current_balance') }}</th>
                        <td class="fw-bold text-success">{{ system_currency_format($seller->getOrCreateWallet()->balance) }}</td>
                      </tr>
                      <tr>
                        <th class="ps-0" style="width: 40%;">{{ __('ui.email') }}</th>
                        <td>{{ $seller->email }}</td>
                      </tr>
                      <tr>
                        <th class="ps-0">{{ __('ui.telephone_number') }}</th>
                        <td>{{ $seller->support_phone ?? '-' }}</td>
                      </tr>
                      <tr>
                        <th class="ps-0">{{ __('ui.vat_number') }}</th>
                        <td>{{ $seller->vat_number ?? '-' }}</td>
                      </tr>
                      <tr>
                        <th class="ps-0">{{ __('ui.address') }}</th>
                        <td>{{ $seller->pickup_address ?? '-' }}</td>
                      </tr>
                      <tr>
                        <th class="ps-0">{{ __('ui.iban_code') }}</th>
                        <td>{{ $seller->iban_code ?? '-' }}</td>
                      </tr>
                      <tr>
                        <th class="ps-0">{{ __('ui.latitude') }}</th>
                        <td>{{ $seller->pickup_latitude ?? '-' }}</td>
                      </tr>
                      <tr>
                        <th class="ps-0">{{ __('ui.longitude') }}</th>
                        <td>{{ $seller->pickup_longitude ?? '-' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-7">
            <div class="card h-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <h5 class="card-title mb-0">{{ __('ui.recent_orders') }}</h5>
                  <span class="badge bg-primary">{{ number_format(($orders ?? collect())->count()) }} {{ __('ui.shown') }}</span>
                </div>
    <div class="table-responsive">
      <table id="sellerProductsTable" class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>{{ __('ui.order') }}</th>
                        <th>{{ __('ui.customer') }}</th>
                        <th>{{ __('ui.status') }}</th>
                        <th class="text-end">{{ __('ui.amount') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse ($orders as $order)
                        <tr>
                          <td>{{ $order->external_order_id ?? ('#' . $order->id) }}</td>
                          <td>{{ $order->customer?->name ?? '-' }}</td>
                          <td>{{ ucfirst((string) ($order->status ?? 'pending')) }}</td>
                          <td class="text-end">{{ system_currency_format($order->amount ?? 0) }}</td>
                        </tr>
                      @empty
                        <tr>
                          <td colspan="4" class="text-center text-muted py-4">{{ __('ui.no_recent_orders_found_for_this_seller') }}</td>
                        </tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                  <div id="sellerProductsInfo" class="text-muted small"></div>
                  <nav aria-label="Seller products pagination">
                    <ul id="sellerProductsPagination" class="pagination pagination-sm mb-0"></ul>
                  </nav>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row mt-4 g-3">
          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <h5 class="card-title mb-0">{{ __('ui.recent_customers') }}</h5>
                  <span class="badge bg-primary">{{ number_format(($customers ?? collect())->count()) }} {{ __('ui.shown') }}</span>
                </div>
                <div class="table-responsive">
                  <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>{{ __('ui.name') }}</th>
                        <th>{{ __('ui.email') }}</th>
                        <th>{{ __('ui.status') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse ($customers as $customer)
                        <tr>
                          <td>{{ $customer->name ?? (__('ui.customer_number') . ' #' . $customer->id) }}</td>
                          <td>{{ $customer->email ?? '-' }}</td>
                          <td>{{ __('ui.' . strtolower((string) ($customer->status ?? 'active'))) }}</td>
                        </tr>
                      @empty
                        <tr>
                          <td colspan="3" class="text-center text-muted py-4">{{ __('ui.no_recent_customers_found_for_this_seller') }}</td>
                        </tr>
                      @endforelse
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-end mt-3">
      {{ $products->links('pagination::bootstrap-5') }}
    </div>
  </div>
</div>
          </div>

          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <h5 class="card-title mb-0">{{ __('ui.recent_shipments') }}</h5>
                  <span class="badge bg-primary">{{ number_format(($shipments ?? collect())->count()) }} {{ __('ui.shown') }}</span>
                </div>
                <div class="table-responsive">
                  <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>{{ __('ui.shipment') }}</th>
                        <th>{{ __('ui.courier') }}</th>
                        <th>{{ __('ui.status') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse ($shipments as $shipment)
                        <tr>
                          <td>{{ $shipment->shipment_code ?? ('#' . $shipment->id) }}</td>
                          <td>{{ $shipment->courier_name ?? '-' }}</td>
                          <td>{{ __('ui.' . strtolower((string) ($shipment->status ?? 'pending'))) }}</td>
                        </tr>
                      @empty
                        <tr>
                          <td colspan="3" class="text-center text-muted py-4">{{ __('ui.no_recent_shipments_found_for_this_seller') }}</td>
                        </tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                  @if ($products->hasPages())
                    {{ $products->links('pagination::bootstrap-5') }}
                  @else
                    <nav aria-label="Products pagination">
                      <ul class="pagination pagination-sm mb-0">
                        <li class="page-item disabled"><span class="page-link">1</span></li>
                      </ul>
                    </nav>
                  @endif
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row mt-4 g-3">
          <div class="col-12">
            <div class="card h-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <div>
                    <h5 class="card-title mb-1">{{ __('ui.recent_products') }}</h5>
                    <p class="text-muted mb-0">{{ __('ui.latest_products_added_by_this_seller') }}</p>
                  </div>
                  <span class="badge bg-primary">{{ number_format(($products ?? collect())->count()) }} {{ __('ui.shown') }}</span>
                </div>

                <form method="get" action="{{ route('admin.sellers.show', $seller) }}" class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 seller-products-toolbar">
                  <div class="d-flex flex-wrap align-items-center gap-2">
                    <label for="sellerProductsPerPage" class="form-label mb-0">{{ __('ui.show') }}</label>
                    <select
                      id="sellerProductsPerPage"
                      name="product_per_page"
                      class="form-select form-select-sm"
                      style="width: 92px;"
                      onchange="this.form.submit()"
                    >
                      <option value="5" {{ (int) ($productPerPage ?? 5) === 5 ? 'selected' : '' }}>5</option>
                      <option value="10" {{ (int) ($productPerPage ?? 5) === 10 ? 'selected' : '' }}>10</option>
                      <option value="25" {{ (int) ($productPerPage ?? 5) === 25 ? 'selected' : '' }}>25</option>
                      <option value="50" {{ (int) ($productPerPage ?? 5) === 50 ? 'selected' : '' }}>50</option>
                    </select>
                    <span class="text-muted">{{ __('ui.entries_per_page') }}</span>
                  </div>

                  <div class="d-flex flex-wrap align-items-center gap-2">
                    <label for="sellerProductsSearch" class="form-label mb-0">{{ __('ui.search') }}:</label>
                    <input
                      id="sellerProductsSearch"
                      name="product_q"
                      type="search"
                      value="{{ $productSearch ?? '' }}"
                      class="form-control form-control-sm"
                      style="width: 220px;"
                      placeholder="{{ __('ui.search_products') }}"
                    >
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('ui.filter') }}</button>
                    <a href="{{ route('admin.sellers.show', $seller) }}" class="btn btn-outline-secondary btn-sm">{{ __('ui.reset') }}</a>
                  </div>
                </form>

                <div class="table-responsive">
                  <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>{{ __('ui.sku') }}</th>
                        <th>{{ __('ui.image') }}</th>
                        <th>{{ __('ui.product') }}</th>
                        <th>{{ __('ui.description') }}</th>
                        <th>{{ __('ui.price') }}</th>
                        <th>{{ __('ui.stock') }}</th>
                        <th>{{ __('ui.reserved') }}</th>
                        <th>{{ __('ui.available') }}</th>
                        <th>{{ __('ui.status') }}</th>
                        <th>{{ __('ui.shopify') }}</th>
                        <th>{{ __('ui.updated') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse ($products as $product)
                        @php
                          $reserved = 0;
                          $available = max(0, (int) $product->stock - $reserved);
                          if ($product->stock <= 0) {
                              $stockBadge = 'bg-danger';
                              $stockLabel = __('ui.out_of_stock');
                          } elseif ($product->low_stock_alert > 0 && $product->stock <= $product->low_stock_alert) {
                              $stockBadge = 'bg-warning text-dark';
                              $stockLabel = __('ui.low_stock');
                          } else {
                              $stockBadge = 'bg-success';
                              $stockLabel = __('ui.in_stock');
                          }
                          $shopifyBadge = match ($product->shopify_sync_status) {
                              'synced' => ['bg-success', __('ui.synced')],
                              'failed' => ['bg-danger', __('ui.failed')],
                              default => ['bg-warning text-dark', __('ui.pending')],
                          };
                        @endphp
                        <tr data-search="{{ e(strtolower(trim($product->sku . ' ' . $product->name . ' ' . ($product->category ?? '') . ' ' . ($product->description ?? '') . ' ' . $product->price . ' ' . $product->stock . ' ' . $stockLabel . ' ' . $shopifyBadge[1]))) }}">
                          <td>{{ $product->sku }}</td>
                          <td>
                            <img
                              src="{{ $product->image_url ?? asset('assets/admin/images/user.png') }}"
                              alt="{{ $product->name }}"
                              style="width:56px;height:56px;object-fit:cover;border-radius:10px;"
                            >
                          </td>
                          <td>
                            <div class="fw-semibold">{{ $product->name }}</div>
                          </td>
                          <td>{{ $product->description ?: '-' }}</td>
                          <td>{{ system_currency_format($product->price) }}</td>
                          <td>{{ $product->stock }}</td>
                          <td>{{ $reserved }}</td>
                          <td>{{ $available }}</td>
                          <td><span class="badge {{ $stockBadge }}">{{ $stockLabel }}</span></td>
                          <td><span class="badge {{ $shopifyBadge[0] }}">{{ $shopifyBadge[1] }}</span></td>
                          <td>{{ optional($product->updated_at)->format('d M Y') ?: '-' }}</td>
                        </tr>
                      @empty
                        <tr>
                          <td colspan="11" class="text-center text-muted py-4">{{ __('ui.no_products_found_for_this_seller') }}</td>
                        </tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@push('page-scripts')
<script>
  $(function () {
    var table = document.getElementById('sellerProductsTable');
    var search = document.getElementById('sellerProductsSearch');
    var perPage = document.getElementById('sellerProductsPerPage');
    var info = document.getElementById('sellerProductsInfo');
    var pagination = document.getElementById('sellerProductsPagination');

    if (!table || !search || !perPage || !info || !pagination) {
      return;
    }

    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr')).filter(function (row) {
      return row.querySelectorAll('td').length > 1;
    });
    var currentPage = 1;

    function filteredRows() {
      var q = search.value.trim().toLowerCase();
      if (!q) {
        return rows;
      }

      return rows.filter(function (row) {
        return (row.getAttribute('data-search') || row.textContent || '').toLowerCase().indexOf(q) !== -1;
      });
    }

    function renderPagination(totalPages) {
      pagination.innerHTML = '';
      if (totalPages <= 1) {
        return;
      }

      function add(label, page, disabled, active) {
        var li = document.createElement('li');
        li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'page-link';
        button.textContent = label;
        button.disabled = disabled;
        button.addEventListener('click', function () {
          if (!disabled) {
            currentPage = page;
            render();
          }
        });
        li.appendChild(button);
        pagination.appendChild(li);
      }

      add('«', Math.max(1, currentPage - 1), currentPage === 1, false);
      for (var page = 1; page <= totalPages; page++) {
        add(String(page), page, false, page === currentPage);
      }
      add('»', Math.min(totalPages, currentPage + 1), currentPage === totalPages, false);
    }

    function render() {
      var items = filteredRows();
      var limit = parseInt(perPage.value, 10) || 5;
      var total = items.length;
      var totalPages = limit === -1 ? 1 : Math.max(1, Math.ceil(total / limit));

      if (currentPage > totalPages) {
        currentPage = totalPages;
      }

      var start = limit === -1 ? 0 : (currentPage - 1) * limit;
      var end = limit === -1 ? total : Math.min(total, start + limit);

      rows.forEach(function (row) {
        row.style.display = 'none';
      });

      items.slice(start, end).forEach(function (row) {
        row.style.display = '';
      });

      info.textContent = total
        ? @json(__('ui.showing_entries_range')).replace(':start', start + 1).replace(':end', end).replace(':total', total)
        : @json(__('ui.showing_zero_entries'));

      renderPagination(totalPages);
    }

    search.addEventListener('input', function () {
      currentPage = 1;
      render();
    });

    perPage.addEventListener('change', function () {
      currentPage = 1;
      render();
    });

    render();
  });
</script>
@endpush

<div class="modal fade" id="editSellerModal" tabindex="-1" aria-labelledby="editSellerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-3 px-4">
        <h4 class="modal-title fw-semibold" id="editSellerModalLabel">{{ __('ui.edit_seller') }}</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
      </div>
      <form id="editSellerForm" method="post" action="{{ route('admin.sellers.update', $seller) }}">
        @csrf
        @method('PATCH')
        <div class="modal-body px-4 py-3">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.business_name') }}</label>
              <input type="text" name="name" class="form-control" value="{{ old('name', $seller->name) }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.email') }}</label>
              <input type="email" name="email" class="form-control" value="{{ old('email', $seller->email) }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.company_name') }}</label>
              <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $seller->company_name) }}" placeholder="{{ __('ui.company_name') }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.telephone_number') }}</label>
              <input type="text" name="telephone_number" class="form-control" value="{{ old('telephone_number', $seller->support_phone) }}" placeholder="{{ __('ui.telephone_number') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.vat_number') }}</label>
              <input type="text" name="vat_number" class="form-control" value="{{ old('vat_number', $seller->vat_number) }}" placeholder="{{ __('ui.vat_number') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.status') }}</label>
              <select class="form-select" name="status" required>
                <option value="active" {{ old('status', $sellerStatus) === 'active' ? 'selected' : '' }}>{{ __('ui.active') }}</option>
                <option value="deactive" {{ old('status', $sellerStatus) === 'deactive' ? 'selected' : '' }}>{{ __('ui.deactive') }}</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.iban_code') }}</label>
              <input type="text" name="iban_code" class="form-control" value="{{ old('iban_code', $seller->iban_code) }}" placeholder="{{ __('ui.iban_code') }}">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold" for="seller_pickup_address">{{ __('ui.address') }}</label>
              <textarea id="seller_pickup_address" name="pickup_address" class="form-control" rows="3" placeholder="{{ __('ui.start_typing_address_to_autocomplete') }}" required>{{ old('pickup_address', $seller->pickup_address) }}</textarea>
              <input type="hidden" name="pickup_latitude" value="{{ old('pickup_latitude', $seller->pickup_latitude) }}">
              <input type="hidden" name="pickup_longitude" value="{{ old('pickup_longitude', $seller->pickup_longitude) }}">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">{{ __('ui.password') }}</label>
              <input type="password" name="password" class="form-control" placeholder="{{ __('ui.leave_blank_to_keep_current_password') }}">
            </div>
          </div>

          @if ($errors->any())
            <div class="alert alert-danger mt-3 mb-0">
              <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif
        </div>
        <div class="modal-footer px-4 py-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.close') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('ui.save_changes') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="feeRulesModal" tabindex="-1" aria-labelledby="feeRulesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable fee-rules-modal-dialog">
    <div class="modal-content">
      <form method="post" action="{{ route('admin.sellers.fees.update', $seller) }}">
        @csrf
        @method('PUT')
        <div class="modal-header py-3 px-4">
          <div>
            <h4 class="modal-title fw-semibold" id="feeRulesModalLabel">{{ __('ui.manage_seller_fee_rules') }}</h4>
            <p class="mb-0 text-muted">{{ __('ui.create_manual_tariffs_for_this_seller_each_row_is_one_tier') }}</p>
          </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
        </div>
        <div class="modal-body px-4 py-3">
          @if ($errors->any())
            <div class="alert alert-danger">
              <strong>{{ __('ui.please_fix_the_highlighted_fee_rules_and_try_again') }}</strong>
            </div>
          @endif

          <div id="feeRulesContainer">
            @foreach ($feeRows as $index => $feeRow)
              <div class="fee-rule-row border rounded-3 p-3 mb-3" data-fee-rule-row>
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div>
                    <h6 class="mb-0">{{ __('ui.rule') }} #{{ $index + 1 }}</h6>
                    <small class="text-muted">{{ __('ui.set_a_range_and_rate_for_this_tariff') }}</small>
                  </div>
                  <button type="button" class="btn btn-outline-danger btn-sm js-remove-fee-rule">
                    <i class="bi bi-trash me-1"></i>{{ __('ui.remove') }}
                  </button>
                </div>

                <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label fw-semibold">{{ __('ui.label') }}</label>
                  <input type="text" class="form-control" name="fee_rules[{{ $index }}][label]" value="{{ $feeRow['label'] ?? '' }}" placeholder="{{ __('ui.e_g_standard_shipping') }}">
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">{{ __('ui.fee_type') }}</label>
                  <select class="form-select" name="fee_rules[{{ $index }}][fee_type]" required>
                    @foreach ($feeTypeOptions as $value => $label)
                        <option value="{{ $value }}" {{ ($feeRow['fee_type'] ?? $defaultFeeType) === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                  <div class="col-md-3">
                    <label class="form-label fw-semibold">{{ __('ui.billing_unit') }}</label>
                    <select class="form-select" name="fee_rules[{{ $index }}][billing_unit]" required>
                      @foreach ($billingUnitOptions as $value => $label)
                        <option value="{{ $value }}" {{ ($feeRow['billing_unit'] ?? 'monthly_orders') === $value ? 'selected' : '' }}>{{ $label }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-1">
                    <label class="form-label fw-semibold">{{ __('ui.min') }}</label>
                    <input type="number" min="0" class="form-control" name="fee_rules[{{ $index }}][min_quantity]" value="{{ $feeRow['min_quantity'] ?? '' }}" placeholder="0">
                  </div>
                  <div class="col-md-1">
                    <label class="form-label fw-semibold">{{ __('ui.max') }}</label>
                    <input type="number" min="0" class="form-control" name="fee_rules[{{ $index }}][max_quantity]" value="{{ $feeRow['max_quantity'] ?? '' }}" placeholder="100">
                  </div>
                  <div class="col-md-1">
                    <label class="form-label fw-semibold">{{ __('ui.active') }}</label>
                    <div class="form-check mt-2">
                      <input type="hidden" name="fee_rules[{{ $index }}][is_active]" value="0">
                      <input class="form-check-input" type="checkbox" name="fee_rules[{{ $index }}][is_active]" value="1" {{ ($feeRow['is_active'] ?? '1') === '1' ? 'checked' : '' }}>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label fw-semibold">{{ __('ui.rate') }}</label>
                    <input type="number" min="0" step="0.01" class="form-control" name="fee_rules[{{ $index }}][rate]" value="{{ $feeRow['rate'] ?? '' }}" placeholder="3.00" required>
                  </div>
                  <div class="col-md-9">
                    <label class="form-label fw-semibold">{{ __('ui.notes') }}</label>
                    <input type="text" class="form-control" name="fee_rules[{{ $index }}][notes]" value="{{ $feeRow['notes'] ?? '' }}" placeholder="{{ __('ui.optional_note_for_the_seller') }}">
                  </div>
                </div>
              </div>
            @endforeach
          </div>

          <template id="feeRuleTemplate">
            <div class="fee-rule-row border rounded-3 p-3 mb-3" data-fee-rule-row>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                  <h6 class="mb-0">{{ __('ui.rule') }}</h6>
                  <small class="text-muted">{{ __('ui.set_a_range_and_rate_for_this_tariff') }}</small>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm js-remove-fee-rule">
                  <i class="bi bi-trash me-1"></i>{{ __('ui.remove') }}
                </button>
              </div>

              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label fw-semibold">{{ __('ui.label') }}</label>
                  <input type="text" class="form-control" name="fee_rules[__INDEX__][label]" placeholder="{{ __('ui.e_g_standard_shipping') }}">
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">{{ __('ui.fee_type') }}</label>
                  <select class="form-select" name="fee_rules[__INDEX__][fee_type]" required>
                    @foreach ($feeTypeOptions as $value => $label)
                      <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">{{ __('ui.billing_unit') }}</label>
                  <select class="form-select" name="fee_rules[__INDEX__][billing_unit]" required>
                    @foreach ($billingUnitOptions as $value => $label)
                      <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-1">
                  <label class="form-label fw-semibold">{{ __('ui.min') }}</label>
                  <input type="number" min="0" class="form-control" name="fee_rules[__INDEX__][min_quantity]" placeholder="0">
                </div>
                <div class="col-md-1">
                  <label class="form-label fw-semibold">{{ __('ui.max') }}</label>
                  <input type="number" min="0" class="form-control" name="fee_rules[__INDEX__][max_quantity]" placeholder="100">
                </div>
                <div class="col-md-1">
                  <label class="form-label fw-semibold">{{ __('ui.active') }}</label>
                  <div class="form-check mt-2">
                    <input type="hidden" name="fee_rules[__INDEX__][is_active]" value="0">
                    <input class="form-check-input" type="checkbox" name="fee_rules[__INDEX__][is_active]" value="1" checked>
                  </div>
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">{{ __('ui.rate') }}</label>
                  <input type="number" min="0" step="0.01" class="form-control" name="fee_rules[__INDEX__][rate]" placeholder="3.00" required>
                </div>
                <div class="col-md-9">
                  <label class="form-label fw-semibold">{{ __('ui.notes') }}</label>
                  <input type="text" class="form-control" name="fee_rules[__INDEX__][notes]" placeholder="{{ __('ui.optional_note_for_the_seller') }}">
                </div>
              </div>
            </div>
          </template>

          <button type="button" class="btn btn-outline-primary" id="addFeeRuleBtn">
            <i class="bi bi-plus-circle me-1"></i>{{ __('ui.add_rule') }}
          </button>
        </div>
        <div class="modal-footer px-4 py-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.close') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('ui.save_fee_rules') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
  .fee-rules-modal-dialog {
    max-width: min(1400px, calc(100vw - 1rem));
    margin: 0.5rem auto;
    height: calc(100vh - 1rem);
  }

  .fee-rules-modal-dialog .modal-content {
    max-height: calc(100vh - 1rem);
    display: flex;
    flex-direction: column;
  }

  .fee-rules-modal-dialog .modal-content > form {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
  }

  .fee-rules-modal-dialog .modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
  }

  .fee-rules-modal-dialog .modal-footer {
    flex-shrink: 0;
    position: sticky;
    bottom: 0;
    background: #fff;
    z-index: 2;
  }
</style>

@push('page-scripts')
<script>
  function bindSellerAddressAutocomplete(input, latField, lngField) {
    if (!input || input.dataset.autocompleteReady === 'true') {
      return;
    }

    var autocomplete = new google.maps.places.Autocomplete(input, {
      fields: ['geometry', 'formatted_address', 'name'],
      types: ['address'],
    });

    autocomplete.addListener('place_changed', function () {
      var place = autocomplete.getPlace();
      if (!place) {
        return;
      }

      input.value = place.formatted_address || place.name || input.value;

      if (place.geometry && place.geometry.location) {
        if (latField) {
          latField.value = place.geometry.location.lat();
        }
        if (lngField) {
          lngField.value = place.geometry.location.lng();
        }
      }
    });

    input.addEventListener('input', function () {
      if (latField) {
        latField.value = '';
      }
      if (lngField) {
        lngField.value = '';
      }
    });

    input.dataset.autocompleteReady = 'true';
  }

  window.initAdminSellerAddressAutocomplete = function () {
    if (!window.google || !window.google.maps || !window.google.maps.places) {
      return;
    }

    var input = document.getElementById('seller_pickup_address');
    var container = input ? input.closest('.col-12') : null;
    var latField = container ? container.querySelector('input[name="pickup_latitude"]') : null;
    var lngField = container ? container.querySelector('input[name="pickup_longitude"]') : null;
    bindSellerAddressAutocomplete(input, latField, lngField);
  };

  $(function () {
    $('#editSellerModal').on('show.bs.modal', function () {
      setTimeout(function () {
        if (window.google && window.google.maps && window.google.maps.places) {
          initAdminSellerAddressAutocomplete();
        }
      }, 0);
    });

    @if ($errors->any() && session('openEditSellerModal'))
      var editSellerModal = new bootstrap.Modal(document.getElementById('editSellerModal'));
      editSellerModal.show();
    @endif

    @if ($errors->any() && session('openFeeRulesModal'))
      var feeRulesModal = new bootstrap.Modal(document.getElementById('feeRulesModal'));
      feeRulesModal.show();
    @endif

    var feeRuleIndex = {{ count($feeRows) }};

    function refreshFeeRuleTitles() {
      $('#feeRulesContainer [data-fee-rule-row]').each(function (index) {
        $(this).find('h6.mb-0').first().text(@json(__('ui.rule')) + ' #' + (index + 1));
      });
    }

    $('#addFeeRuleBtn').on('click', function () {
      var template = document.getElementById('feeRuleTemplate');
      if (! template) {
        return;
      }

      var html = template.innerHTML.split('__INDEX__').join(String(feeRuleIndex));
      feeRuleIndex += 1;
      $('#feeRulesContainer').append(html);
      refreshFeeRuleTitles();
    });

    $(document).on('click', '.js-remove-fee-rule', function () {
      $(this).closest('[data-fee-rule-row]').remove();
      refreshFeeRuleTitles();
    });

    var $autoHideAlert = $('.js-auto-hide-alert');
    if ($autoHideAlert.length) {
      setTimeout(function () {
        $autoHideAlert.fadeOut(300);
      }, 5000);
    }

    if (window.google && window.google.maps && window.google.maps.places) {
      window.initAdminSellerAddressAutocomplete();
    }
  });
</script>
@if (config('services.google_maps.key'))
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places&loading=async&callback=initAdminSellerAddressAutocomplete" async defer></script>
@endif
@endpush

<script>
  (function () {
    function initSellerProductsFilter() {
      var table = document.getElementById('sellerProductsTable');
      var search = document.getElementById('sellerProductsSearch');
      var perPage = document.getElementById('sellerProductsPerPage');
      var info = document.getElementById('sellerProductsInfo');
      var pagination = document.getElementById('sellerProductsPagination');
      if (!table || !search || !perPage || !info || !pagination) {
        return;
      }

      var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr')).filter(function (row) {
        return row.cells.length > 1;
      });
      var page = 1;

      function matchRows() {
        var q = search.value.trim().toLowerCase();
        if (!q) {
          return rows;
        }

        return rows.filter(function (row) {
          return (row.getAttribute('data-search') || row.textContent || '').toLowerCase().indexOf(q) !== -1;
        });
      }

      function render() {
        var list = matchRows();
        var limit = parseInt(perPage.value, 10) || 5;
        var total = list.length;
        var totalPages = limit === -1 ? 1 : Math.max(1, Math.ceil(total / limit));

        if (page > totalPages) {
          page = totalPages;
        }

        var start = limit === -1 ? 0 : (page - 1) * limit;
        var end = limit === -1 ? total : Math.min(total, start + limit);

        rows.forEach(function (row) {
          row.style.display = 'none';
        });
        list.slice(start, end).forEach(function (row) {
          row.style.display = '';
        });

        info.textContent = total
          ? @json(__('ui.showing_entries_range')).replace(':start', start + 1).replace(':end', end).replace(':total', total)
          : @json(__('ui.showing_zero_entries'));

        pagination.innerHTML = '';
        if (totalPages <= 1) {
          return;
        }

        function addButton(text, targetPage, disabled, active) {
          var li = document.createElement('li');
          li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'page-link';
          button.textContent = text;
          button.disabled = disabled;
          button.addEventListener('click', function () {
            if (!disabled) {
              page = targetPage;
              render();
            }
          });
          li.appendChild(button);
          pagination.appendChild(li);
        }

        addButton('«', Math.max(1, page - 1), page === 1, false);
        for (var i = 1; i <= totalPages; i++) {
          addButton(String(i), i, false, i === page);
        }
        addButton('»', Math.min(totalPages, page + 1), page === totalPages, false);
      }

      search.addEventListener('input', function () {
        page = 1;
        render();
      });
      perPage.addEventListener('change', function () {
        page = 1;
        render();
      });

      window.sellerProductsFilter = function () {
        page = 1;
        render();
      };

      render();
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initSellerProductsFilter);
    } else {
      initSellerProductsFilter();
    }
  })();
</script>

@push('page-scripts')
<script>
  $(function () {
    var tableEl = document.getElementById('sellerProductsTable');
    if (!tableEl || !window.DataTable) {
      return;
    }

    if (window.DataTable.isDataTable && window.DataTable.isDataTable(tableEl)) {
      window.DataTable(tableEl).destroy();
    }

    new DataTable(tableEl, {
      dom: 'lfrtip',
      pageLength: 10,
      lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
      searching: true,
      paging: true,
      info: true,
      ordering: true,
      autoWidth: false,
      order: []
    });
  });
</script>
@endpush

<style>
  .seller-products-toolbar .form-label {
    white-space: nowrap;
  }
</style>

@include('spedfly.include.footer')

<script>
  (function () {
    function initSellerProductsTable() {
      var table = document.getElementById('sellerProductsTable');
      var search = document.getElementById('sellerProductsSearch');
      var perPage = document.getElementById('sellerProductsPerPage');
      var info = document.getElementById('sellerProductsInfo');
      var pagination = document.getElementById('sellerProductsPagination');

      if (!table || !search || !perPage || !info || !pagination) {
        return;
      }

      var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr')).filter(function (row) {
        return row.cells.length > 1;
      });
      var currentPage = 1;

      function getFilteredRows() {
        var query = search.value.trim().toLowerCase();
        if (!query) {
          return rows;
        }

        return rows.filter(function (row) {
          return (row.getAttribute('data-search') || row.textContent || '').toLowerCase().indexOf(query) !== -1;
        });
      }

      function render() {
        var filtered = getFilteredRows();
        var limit = parseInt(perPage.value, 10);
        var total = filtered.length;
        var totalPages = limit === -1 ? 1 : Math.max(1, Math.ceil(total / limit));
        if (currentPage > totalPages) {
          currentPage = totalPages;
        }

        var start = limit === -1 ? 0 : (currentPage - 1) * limit;
        var end = limit === -1 ? total : Math.min(total, start + limit);

        rows.forEach(function (row) {
          row.style.display = 'none';
        });

        filtered.slice(start, end).forEach(function (row) {
          row.style.display = '';
        });

        info.textContent = total
          ? @json(__('ui.showing_entries_range')).replace(':start', start + 1).replace(':end', end).replace(':total', total)
          : @json(__('ui.showing_zero_entries'));

        pagination.innerHTML = '';
        if (totalPages <= 1) {
          return;
        }

        function addItem(label, page, disabled, active) {
          var li = document.createElement('li');
          li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'page-link';
          btn.textContent = label;
          btn.disabled = disabled;
          btn.addEventListener('click', function () {
            if (disabled) {
              return;
            }
            currentPage = page;
            render();
          });
          li.appendChild(btn);
          pagination.appendChild(li);
        }

        addItem('«', Math.max(1, currentPage - 1), currentPage === 1, false);
        for (var p = 1; p <= totalPages; p++) {
          addItem(String(p), p, false, p === currentPage);
        }
        addItem('»', Math.min(totalPages, currentPage + 1), currentPage === totalPages, false);
      }

      search.addEventListener('input', function () {
        currentPage = 1;
        render();
      });

      perPage.addEventListener('change', function () {
        currentPage = 1;
        render();
      });

      render();
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initSellerProductsTable);
    } else {
      initSellerProductsTable();
    }
  })();
</script>

<script>
  $(function () {
    var $table = $('#sellerProductsTable');
    if (! $table.length || ! $.fn.DataTable) {
      return;
    }

    if ($.fn.DataTable.isDataTable($table[0])) {
      $table.DataTable().destroy();
    }

    $table.DataTable({
      destroy: true,
      dom: 'lfrtip',
      pageLength: 5,
      lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, 'All']],
      searching: true,
      paging: true,
      info: true,
      ordering: true,
      autoWidth: false,
      order: []
    });
  });
</script>

