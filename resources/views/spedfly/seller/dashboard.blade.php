@include('spedfly.include.header')

@php
    $currencySymbol = system_currency_symbol();
    $statusBadgeClasses = [
        'pending' => 'bg-secondary',
        'in_transit' => 'bg-primary',
        'delivered' => 'bg-success',
        'delayed' => 'bg-warning text-dark',
        'returned' => 'bg-danger',
    ];

    $growthClass = function (int $value): string {
        return $value >= 0 ? 'text-success' : 'text-danger';
    };

    $growthIcon = function (int $value): string {
        return $value >= 0 ? 'bi-caret-up-fill' : 'bi-caret-down-fill';
    };

    $growthText = function (int $value): string {
        return ($value >= 0 ? '+' : '') . $value . '%';
    };

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

    $firstFeeCurrency = ! empty($feeCards) ? ($feeCards[0]['currency'] ?? 'EUR') : 'EUR';
@endphp

<style>
    .dash-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 100%);
        color: #fff;
        border-radius: 20px;
        padding: 22px;
        overflow: hidden;
        position: relative;
    }

    .dash-hero::after {
        content: "";
        position: absolute;
        inset: auto -80px -80px auto;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
    }

    .metric-card {
        border: 1px solid #e4e9f1;
        border-radius: 18px;
        padding: 18px;
        background: #fff;
        height: 100%;
    }

    .metric-icon {
        width: 54px;
        height: 54px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    .metric-value {
        font-size: 34px;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 8px;
        color: #111827;
    }

    .metric-label {
        margin: 0;
        color: #6b7280;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 700;
    }

    .mini-kpi {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 14px;
        height: 100%;
    }

    .mini-kpi h6 {
        margin-bottom: 6px;
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 700;
    }

    .mini-kpi h3 {
        margin: 0;
        font-size: 24px;
        font-weight: 800;
        color: #0f172a;
    }

    .chart-shell {
        background: #fff;
        border: 1px solid #e4e9f1;
        border-radius: 18px;
        padding: 18px;
        height: 100%;
    }

    .fee-shell {
        background: #fff;
        border: 1px solid #e4e9f1;
        border-radius: 18px;
        overflow: hidden;
    }

    .fee-shell .section-header {
        padding: 18px 20px 14px;
        border-bottom: 1px solid #dbe3ef;
    }

    .fee-card {
        border: 1px solid #d9dee7;
        border-radius: 16px;
        background: #fff;
        min-height: 170px;
        padding: 14px 12px 12px;
        box-shadow: 0 1px 0 rgba(15, 23, 42, 0.02);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .fee-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .fee-icon {
        width: 64px;
        height: 64px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff7d9;
        color: #f4b400;
        font-size: 1.45rem;
        margin-bottom: 18px;
    }

    .fee-amount {
        margin: 0;
        color: #111827;
        font-weight: 800;
        font-size: 1.35rem;
        line-height: 1.1;
    }

    .fee-label {
        margin: 6px 0 0;
        color: #9097a6;
        font-weight: 700;
        font-size: 0.84rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        line-height: 1.35;
    }

    .section-title {
        margin: 0;
        font-weight: 800;
        color: #0f172a;
    }

    .section-subtitle {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 13px;
    }

    .badge-soft {
        border-radius: 999px;
        padding: 8px 12px;
        font-weight: 700;
        font-size: 12px;
    }

    .shipment-table td,
    .shipment-table th {
        vertical-align: middle;
    }

    .avatar-circle {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        background: #e0ecff;
        color: #1d4ed8;
        flex-shrink: 0;
    }

    .table-actions .btn {
        min-width: 36px;
    }

    .integration-card {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.14);
        border-radius: 16px;
        padding: 16px;
    }
</style>

<div class="app-body">
    <div class="container-fluid">
        <div class="card card-shadow mb-4 border-0">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <h5 class="fw-bold mb-1">{{ __('ui.shopify_integration') }}</h5>
                    @if($shopifyConnection['connected'])
                        <p class="mb-0 text-muted">{{ __('ui.connected_to') }} {{ $shopifyConnection['shop_domain'] }}.</p>
                    @else
                        <p class="mb-0 text-muted">{{ __('ui.connect_your_shopify_store_to_sync_products_and_orders') }}</p>
                    @endif
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    @if($shopifyConnection['connected'])
                        <a href="{{ route('seller.settings', ['tab' => 'integration']) }}" class="btn btn-outline-primary">{{ __('ui.manage_shopify') }}</a>
                    @else
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#shopifyConnectModal">
                            {{ __('ui.connect_shopify') }}
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div id="sellerDashboardHero" class="dash-hero mb-4">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-2">{{ __('ui.seller_dashboard') }}</h2>
                    <p class="mb-0 text-white-50">
                        {{ __('ui.live_snapshot_of_customers_orders_shipments_and_revenue_pulled_from_your_store_data') }}
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <div class="d-inline-flex gap-2 flex-wrap justify-content-lg-end">
                        <span class="badge bg-white text-dark badge-soft">{{ __('ui.orders') }}: {{ number_format($metrics['total_orders']) }}</span>
                        <span class="badge bg-white text-dark badge-soft">{{ __('ui.revenue') }}: {{ $currencySymbol }}{{ number_format($metrics['monthly_revenue'], 2) }}</span>
                        <span class="badge bg-white text-dark badge-soft">{{ __('ui.fees') }}: {{ $formatFeeAmount($feeSummary['total_amount'] ?? 0, $firstFeeCurrency) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div id="sellerDashboardMetrics" class="row g-3 mb-4">
            <div class="col-xl-3 col-sm-6">
                <div class="metric-card">
                    <div class="d-flex align-items-start gap-3">
                        <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi {{ $growthIcon($metrics['customer_growth']) }} {{ $growthClass($metrics['customer_growth']) }} fs-5"></i>
                                <span class="{{ $growthClass($metrics['customer_growth']) }} fw-semibold">{{ $growthText($metrics['customer_growth']) }}</span>
                                <span class="text-muted small">{{ __('ui.vs_last_7_days') }}</span>
                            </div>
                            <div class="metric-value">{{ number_format($metrics['total_customers']) }}</div>
                            <p class="metric-label">{{ __('ui.total_customers') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="metric-card">
                    <div class="d-flex align-items-start gap-3">
                        <div class="metric-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi {{ $growthIcon($metrics['order_growth']) }} {{ $growthClass($metrics['order_growth']) }} fs-5"></i>
                                <span class="{{ $growthClass($metrics['order_growth']) }} fw-semibold">{{ $growthText($metrics['order_growth']) }}</span>
                                <span class="text-muted small">{{ __('ui.vs_last_7_days') }}</span>
                            </div>
                            <div class="metric-value">{{ number_format($metrics['total_orders']) }}</div>
                            <p class="metric-label">{{ __('ui.total_orders') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="metric-card">
                    <div class="d-flex align-items-start gap-3">
                        <div class="metric-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-truck"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi {{ $growthIcon($metrics['delivery_growth']) }} {{ $growthClass($metrics['delivery_growth']) }} fs-5"></i>
                                <span class="{{ $growthClass($metrics['delivery_growth']) }} fw-semibold">{{ $growthText($metrics['delivery_growth']) }}</span>
                                <span class="text-muted small">{{ __('ui.vs_last_7_days') }}</span>
                            </div>
                            <div class="metric-value">{{ number_format($metrics['delivered_shipments']) }}</div>
                            <p class="metric-label">{{ __('ui.delivered_shipments') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="metric-card">
                    <div class="d-flex align-items-start gap-3">
                        <div class="metric-icon bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi {{ $growthIcon($metrics['return_growth']) }} {{ $growthClass($metrics['return_growth']) }} fs-5"></i>
                                <span class="{{ $growthClass($metrics['return_growth']) }} fw-semibold">{{ $growthText($metrics['return_growth']) }}</span>
                                <span class="text-muted small">{{ __('ui.vs_last_7_days') }}</span>
                            </div>
                            <div class="metric-value">{{ number_format($metrics['returned_shipments']) }}</div>
                            <p class="metric-label">{{ __('ui.returned_shipments') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @php
            $tabQuery = request()->except('tab');
        @endphp

        <div id="sellerDashboardOverviewSection" class="chart-shell mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h4 class="section-title">{{ __('ui.order_overview') }}</h4>
                </div>
            </div>

            <ul class="nav nav-tabs flex-wrap gap-1 mb-4">
                @foreach($overviewTabs as $tabKey => $tabLabel)
                    <li class="nav-item">
                        <a
                            class="nav-link {{ $selectedTab === $tabKey ? 'active' : '' }}"
                            href="{{ route('seller.dashboard', array_merge($tabQuery, ['tab' => $tabKey])) }}"
                        >
                            {{ $tabLabel }}
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="row g-0 align-items-stretch">
                <div class="col-lg-4 border-end">
                    <div class="p-3 p-lg-4 h-100">
                        <div class="text-center mb-3">
                            <p class="text-muted mb-1">{{ __('ui.this_month') }}</p>
                            <h2 class="mb-0">{{ $currencySymbol }}{{ number_format($overviewStats['earnings'], 2) }}</h2>
                            <div class="badge bg-success mt-2">
                                {{ $growthText($metrics['revenue_growth']) }}
                            </div>
                        </div>

                        <div class="row g-0">
                            <div class="col-sm-6">
                                <div class="border-bottom border-end p-3 text-center">
                                    <p class="text-muted mb-1">{{ $overviewChart['primary']['label'] }}</p>
                                    <h5 class="m-0">{{ number_format($overviewStats['shipped']) }}</h5>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="border-bottom p-3 text-center">
                                    <p class="text-muted mb-1">{{ $overviewChart['secondary']['label'] }}</p>
                                    <h5 class="m-0">{{ number_format($overviewStats['cancelled']) }}</h5>
                                </div>
                            </div>
                        </div>

                        <div class="row g-0">
                            <div class="col-12">
                                <div class="border-bottom p-3 text-center">
                                    <p class="text-muted mb-1">{{ __('ui.delivered') }}</p>
                                    <h5 class="m-0">{{ number_format($overviewStats['delivered']) }}</h5>
                                </div>
                            </div>
                        </div>

                        <div class="row g-0">
                            <div class="col-sm-6">
                                <div class="border-bottom border-end p-3 text-center">
                                    <p class="text-muted mb-1">{{ __('ui.growth') }}</p>
                                    <h5 class="m-0">{{ $growthText($overviewStats['growth']) }}</h5>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="border-bottom p-3 text-center">
                                    <p class="text-muted mb-1">{{ __('ui.signups') }}</p>
                                    <h5 class="m-0">{{ number_format($overviewStats['signups']) }}</h5>
                                </div>
                            </div>
                        </div>

                        <div class="row g-0">
                            <div class="col-sm-6">
                                <div class="border-end p-3 text-center">
                                    <p class="text-muted mb-1">{{ __('ui.ratings') }}</p>
                                    <h5 class="m-0">{{ number_format($overviewStats['ratings'], 1) }}</h5>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 text-center">
                                    <p class="text-muted mb-1">{{ __('ui.earnings') }}</p>
                                    <h5 class="m-0">{{ $currencySymbol }}{{ number_format($overviewStats['earnings'], 2) }}</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="p-3 p-lg-4 h-100">
                        <div id="overview" style="min-height: 317px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div id="sellerDashboardFees" class="row g-4 mb-4">
            <div class="col-12">
                <div class="fee-shell">
                    <div class="section-header">
                        <div>
                            <h4 class="section-title">{{ __('ui.fees') }}</h4>
                            <p class="section-subtitle">{{ __('ui.admin_managed_tariffs_calculated_from_this_months_activity') }}</p>
                        </div>
                    </div>
                    <div class="p-3 p-lg-4">
                        <div class="row g-3 row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4">
                            @forelse ($feeCards as $card)
                                <div class="col">
                                    <div class="fee-card h-100 d-flex flex-column">
                                        <div class="fee-icon mb-4">
                                            <i class="bi {{ $card['icon'] }}"></i>
                                        </div>
                                        <div class="mt-auto">
                                            <p class="fee-amount">{{ $formatFeeAmount($card['amount'], $card['currency'] ?? 'EUR') }}</p>
                                            <p class="fee-label">{{ $card['label'] }}</p>
                                            <p class="mb-0 small text-muted">{{ number_format($card['quantity'] ?? 0) }} {{ strtolower((string) ($card['quantity_label'] ?? 'items')) }} · {{ __('ui.rate') }} {{ $formatFeeAmount($card['rate'] ?? 0, $card['currency'] ?? 'EUR') }}</p>
                                            <p class="mb-0 small text-muted">{{ $card['tier_label'] ?? '-' }}</p>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12">
                                    <div class="alert alert-light border mb-0">
                                        {{ __('ui.no_fee_rules_are_active_yet_your_admin_will_add_manual_tariffs_here') }}
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="chart-shell">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="section-title">{{ __('ui.orders_activity') }}</h4>
                            <p class="section-subtitle">{{ __('ui.daily_order_volume_for_the_last_7_days') }}</p>
                        </div>
                        <div id="sparkline1"></div>
                    </div>
                    <div class="text-center py-4">
                        <h1 class="display-6 fw-bold mb-2">{{ number_format($metrics['total_orders']) }}</h1>
                        <p class="text-muted mb-0">{{ __('ui.orders_recorded_across_your_account') }}</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="chart-shell">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="section-title">{{ __('ui.revenue') }}</h4>
                            <p class="section-subtitle">{{ __('ui.daily_revenue_for_the_last_7_days') }}</p>
                        </div>
                        <div id="sparkline2"></div>
                    </div>
                    <div class="text-center py-4">
                        <h1 class="display-6 fw-bold mb-2">{{ $currencySymbol }}{{ number_format($metrics['monthly_revenue'], 2) }}</h1>
                        <p class="text-muted mb-0">{{ __('ui.this_month_revenue_total') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="sellerRecentShipmentsSection" class="row mt-4">
            <div class="col-12">
                <div class="chart-shell">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h4 class="section-title">{{ __('ui.recent_shipments') }}</h4>
                            <p class="section-subtitle">{{ __('ui.latest_live_shipment_records_from_your_dashboard') }}</p>
                        </div>
                        <a href="{{ route('seller.shipments') }}" class="btn btn-outline-primary btn-sm">{{ __('ui.view_all_shipments') }}</a>
                    </div>

                    <div class="table-responsive">
                        <table id="recentShipmentsTable" class="table table-bordered shipment-table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('ui.shipment') }}</th>
                                    <th>{{ __('ui.customer') }}</th>
                                    <th>{{ __('ui.courier') }}</th>
                                    <th>{{ __('ui.status') }}</th>
                                    <th>{{ __('ui.date') }}</th>
                                    <th style="width: 140px;">{{ __('ui.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentShipments as $shipment)
                                    <tr>
                                        <td>
                                            <div class="fw-bold">#{{ $shipment->shipment_code }}</div>
                                            <small class="text-muted">{{ $shipment->order?->external_order_id ? __('ui.order') . ' #' . $shipment->order->external_order_id : __('ui.no_linked_order') }}</small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="avatar-circle">
                                                    {{ strtoupper(substr($shipment->customer_name ?? 'U', 0, 1)) }}
                                                </div>
                                                <div>
                                                    <div class="fw-semibold">{{ $shipment->customer_name }}</div>
                                                    <small class="text-muted">{{ $shipment->customer_phone }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $shipment->courier_name ?: '-' }}</td>
                                        <td>
                                            <span class="badge {{ $statusBadgeClasses[$shipment->status] ?? 'bg-secondary' }}">
                                                {{ ucfirst(str_replace('_', ' ', (string) $shipment->status)) }}
                                            </span>
                                        </td>
                                        <td>{{ optional($shipment->shipment_date)->format('d M Y') ?? '-' }}</td>
                                        <td>
                                            <div class="d-flex gap-2 table-actions">
                                              <!--  <a href="{{ route('seller.shipments.label', $shipment) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm" title="Print Label">
                                                    <i class="bi bi-printer"></i>
                                                </a> -->
                                               <!--  <a href="{{ route('seller.shipments.edit', $shipment) }}" class="btn btn-outline-primary btn-sm" title="Edit Shipment">
                                                    <i class="bi bi-pencil"></i>
                                                </a>  -->
                                                @if($shipment->order)
                                                    <a href="{{ route('seller.order-details', $shipment->order) }}" class="btn btn-outline-success btn-sm" title="{{ __('ui.open_order') }}">
                                                        <i class="bi bi-box-arrow-up-right"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="empty-row">
                                        <td colspan="6" class="text-center text-muted py-4">{{ __('ui.no_shipments_found_yet_create_one_to_see_live_data_here') }}</td>
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

<div class="modal fade" id="shopifyConnectModal" tabindex="-1" aria-labelledby="shopifyConnectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="{{ route('seller.shopify.connect') }}" target="_top">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="shopifyConnectModalLabel">{{ __('ui.connect_shopify_store') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">{{ __('ui.enter_your_shopify_store_domain_to_start_the_secure_connection_flow') }}</p>
                    <label class="form-label" for="shop_domain">{{ __('ui.shop_domain') }}</label>
                    <input
                        type="text"
                        class="form-control @error('shop_domain') is-invalid @enderror"
                        id="shop_domain"
                        name="shop_domain"
                        value="{{ old('shop_domain', $shopifyConnection['shop_domain']) }}"
                        placeholder="your-store.myshopify.com"
                    >
                    @error('shop_domain')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('ui.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('ui.connect_shopify') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('page-scripts')
<script>
    @if ($errors->has('shop_domain'))
    document.addEventListener('DOMContentLoaded', function () {
        const modalElement = document.getElementById('shopifyConnectModal');
        if (modalElement && typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
            modal.show();
        }
    });
    @endif

    document.addEventListener('DOMContentLoaded', function () {
        const chartData = @json($chartData);
        const overviewPayload = @json($overviewChart);
        const currencySymbol = @json($currencySymbol);
        const chartLabels = {
            orders: @json(__('ui.orders')),
            revenue: @json(__('ui.revenue')),
            all: @json(__('ui.all')),
        };

        function renderSparkline(selector, series, color) {
            const el = document.querySelector(selector);
            if (!el || typeof ApexCharts === 'undefined') {
                return;
            }

            const chart = new ApexCharts(el, {
                series: [{
                    name: color === '#16a34a' ? chartLabels.revenue : chartLabels.orders,
                    data: series,
                }],
                chart: {
                    type: 'line',
                    width: 100,
                    height: 40,
                    sparkline: { enabled: true },
                },
                stroke: {
                    show: true,
                    lineCap: 'round',
                    colors: [color],
                    width: 3,
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return color === '#16a34a'
                                ? currencySymbol + Number(val).toFixed(2)
                                : Number(val).toFixed(0);
                        },
                    },
                },
            });

            chart.render();
        }

        if (typeof ApexCharts !== 'undefined') {
            const overviewEl = document.querySelector('#overview');
            if (overviewEl) {
                const overviewChartInstance = new ApexCharts(overviewEl, {
                    chart: {
                        height: 320,
                        type: 'area',
                        toolbar: { show: false },
                    },
                    dataLabels: { enabled: false },
                    stroke: {
                        curve: 'smooth',
                        width: 3,
                    },
                    series: [
                        {
                            name: overviewPayload.primary.label,
                            data: overviewPayload.primary.data,
                        },
                        {
                            name: overviewPayload.secondary.label,
                            data: overviewPayload.secondary.data,
                        },
                    ],
                    xaxis: {
                        categories: chartData.labels,
                    },
                    grid: {
                        borderColor: '#e0e6ed',
                        strokeDashArray: 5,
                    },
                    colors: ['#2563eb', '#dc2626'],
                    markers: {
                        size: 0,
                    },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.28,
                            opacityTo: 0.05,
                        },
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'right',
                    },
                });

                overviewChartInstance.render();
            }

            renderSparkline('#sparkline1', chartData.orders, '#2563eb');
            renderSparkline('#sparkline2', chartData.revenue, '#16a34a');
        }

        if ($('#recentShipmentsTable').length && $.fn.DataTable) {
            const hasEmptyRow = $('#recentShipmentsTable tbody tr.empty-row').length > 0;

            if (!hasEmptyRow) {
                $('#recentShipmentsTable').DataTable({
                    pageLength: 5,
                    lengthMenu: [[5, 10, 25, -1], [5, 10, 25, 'All']],
                    ordering: false,
                    searching: false,
                    info: false
                });
            }
        }
    });
</script>
@endpush

@push('page-scripts')
<script>
    window.SpedflySellerDashboard = window.SpedflySellerDashboard || {};

    window.SpedflySellerDashboard.init = function (payload) {
        if (! payload) {
            return;
        }

        const chartData = payload.chartData || {};
        const overviewPayload = payload.overviewChart || {};

        if (typeof ApexCharts !== 'undefined') {
            const overviewEl = document.querySelector('#overview');
            if (overviewEl && window.SpedflySellerDashboard.overviewChartInstance) {
                window.SpedflySellerDashboard.overviewChartInstance.destroy();
                window.SpedflySellerDashboard.overviewChartInstance = null;
            }

            if (overviewEl) {
                window.SpedflySellerDashboard.overviewChartInstance = new ApexCharts(overviewEl, {
                    chart: {
                        height: 320,
                        type: 'area',
                        toolbar: { show: false },
                    },
                    dataLabels: { enabled: false },
                    stroke: {
                        curve: 'smooth',
                        width: 3,
                    },
                    series: [
                        {
                            name: overviewPayload.primary?.label || 'Primary',
                            data: overviewPayload.primary?.data || [],
                        },
                        {
                            name: overviewPayload.secondary?.label || 'Secondary',
                            data: overviewPayload.secondary?.data || [],
                        },
                    ],
                    xaxis: {
                        categories: chartData.labels || [],
                    },
                    grid: {
                        borderColor: '#e0e6ed',
                        strokeDashArray: 5,
                    },
                    colors: ['#2563eb', '#dc2626'],
                    markers: {
                        size: 0,
                    },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.28,
                            opacityTo: 0.05,
                        },
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'right',
                    },
                });

                window.SpedflySellerDashboard.overviewChartInstance.render();
            }

            var spark1 = document.querySelector('#sparkline1');
            var spark2 = document.querySelector('#sparkline2');

            if (window.SpedflySellerDashboard.ordersSparklineChart) {
                window.SpedflySellerDashboard.ordersSparklineChart.destroy();
                window.SpedflySellerDashboard.ordersSparklineChart = null;
            }
            if (window.SpedflySellerDashboard.revenueSparklineChart) {
                window.SpedflySellerDashboard.revenueSparklineChart.destroy();
                window.SpedflySellerDashboard.revenueSparklineChart = null;
            }

            if (spark1) {
                window.SpedflySellerDashboard.ordersSparklineChart = new ApexCharts(spark1, {
                    series: [{
                        name: chartLabels.orders,
                        data: chartData.orders || [],
                    }],
                    chart: {
                        type: 'line',
                        width: 100,
                        height: 40,
                        sparkline: { enabled: true },
                    },
                    stroke: {
                        show: true,
                        lineCap: 'round',
                        colors: ['#2563eb'],
                        width: 3,
                    },
                });
                window.SpedflySellerDashboard.ordersSparklineChart.render();
            }

            if (spark2) {
                window.SpedflySellerDashboard.revenueSparklineChart = new ApexCharts(spark2, {
                    series: [{
                        name: chartLabels.revenue,
                        data: chartData.revenue || [],
                    }],
                    chart: {
                        type: 'line',
                        width: 100,
                        height: 40,
                        sparkline: { enabled: true },
                    },
                    stroke: {
                        show: true,
                        lineCap: 'round',
                        colors: ['#16a34a'],
                        width: 3,
                    },
                });
                window.SpedflySellerDashboard.revenueSparklineChart.render();
            }
        }

        if ($('#recentShipmentsTable').length && $.fn.DataTable) {
            if ($.fn.DataTable.isDataTable('#recentShipmentsTable')) {
                $('#recentShipmentsTable').DataTable().destroy();
            }

            if ($('#recentShipmentsTable tbody tr.empty-row').length === 0) {
                $('#recentShipmentsTable').DataTable({
                    pageLength: 5,
                    lengthMenu: [[5, 10, 25, -1], [5, 10, 25, chartLabels.all]],
                    ordering: false,
                    searching: false,
                    info: false
                });
            }
        }
    };
</script>
<script type="application/json" id="sellerDashboardChartData">@json(['chartData' => $chartData, 'overviewChart' => $overviewChart])</script>
@endpush

@include('spedfly.include.footer')
