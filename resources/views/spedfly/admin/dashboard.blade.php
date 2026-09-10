@include('spedfly.include.header')

@php
    $currencySymbol = $currencySymbol ?? system_currency_symbol();
@endphp

<div class="app-body">
    <div class="container-fluid">
        <div class="row g-4">
            <div class="col-xl-3 col-sm-6 col-12">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex flex-row align-items-center">
                            <div class="icon-box lg rounded-3 bg-light mb-4">
                                <i class="bi bi-currency-dollar text-success fs-2"></i>
                            </div>
                            <div class="ms-4">
                                <h4 class="fw-bold mb-2">{{ $currencySymbol }}{{ number_format($metrics['total_sales'] ?? 0, 2) }}</h4>
                                <h6 class="m-0 fw-normal opacity-50">{{ __('ui.total_sales') }}</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6 col-12">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex flex-row align-items-center">
                            <div class="icon-box lg rounded-3 bg-light mb-4">
                                <i class="bi bi-box text-primary fs-2"></i>
                            </div>
                            <div class="ms-4">
                                <h4 class="fw-bold mb-2">{{ number_format($metrics['total_orders'] ?? 0) }}</h4>
                                <h6 class="m-0 fw-normal opacity-50">{{ __('ui.total_orders') }}</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6 col-12">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex flex-row align-items-center">
                            <div class="icon-box lg rounded-3 bg-light mb-4">
                                <i class="bi bi-person text-info fs-2"></i>
                            </div>
                            <div class="ms-4">
                                <h4 class="fw-bold mb-2">{{ number_format($metrics['new_customers'] ?? 0) }}</h4>
                                <h6 class="m-0 fw-normal opacity-50">{{ __('ui.new_customers') }}</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-12 col-md-12 col-sm-12 col-12">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex flex-row align-items-center">
                            <div class="icon-box lg rounded-3 bg-light mb-4">
                                <i class="bi bi-star text-warning fs-2"></i>
                            </div>
                            <div class="ms-4">
                                <h4 class="fw-bold mb-2">{{ $metrics['top_seller_name'] ?? __('ui.no_sellers_yet') }}</h4>
                                <h6 class="m-0 fw-normal opacity-50">
                                    {{ number_format($metrics['top_seller_orders'] ?? 0) }} {{ __('ui.orders') }}
                                </h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-xl-8 col-12">
                <div class="card h-100">
                    <div class="card-header">
                        <h4 class="card-title">{{ __('ui.sales_overview') }}</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="ordersChart" height="170"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-12">
                <div class="card h-100">
                    <div class="card-header">
                        <h4 class="card-title">{{ __('ui.top_sellers') }}</h4>
                    </div>
                    <div class="card-body">
                        @forelse ($topSellers as $seller)
                            <div class="seller-item d-flex align-items-center justify-content-between mb-3 p-3 rounded-4">
                                <div class="d-flex align-items-center">
                                    <div class="seller-avatar me-3 rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-semibold"
                                         style="width: 48px; height: 48px;">
                                        {{ $seller['initials'] ?? 'S' }}
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-semibold">{{ $seller['name'] }}</h6>
                                        <small class="text-muted">{{ number_format($seller['orders']) }} {{ __('ui.orders') }}</small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <h6 class="mb-0 fw-bold text-success">{{ $currencySymbol }}{{ number_format($seller['revenue'], 2) }}</h6>
                                    <small class="{{ $seller['growth_class'] ?? 'text-success' }}">
                                        <i class="bi {{ $seller['growth_icon'] ?? 'bi-arrow-up' }}"></i>
                                        {{ abs((int) ($seller['growth'] ?? 0)) }}%
                                    </small>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted">
                                {{ __('ui.no_seller_performance_data') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-xl-8 col-lg-8 col-md-12 col-sm-8 col-12">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="card-title">{{ __('ui.recent_orders') }}</h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <table id="orders" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentOrders as $order)
                                    <tr>
                                        <td>{{ $order['id'] }}</td>
                                        <td>{{ $order['customer'] }}</td>
                                        <td>{{ $currencySymbol }}{{ number_format($order['amount'], 2) }}</td>
                                        <td>
                                            <span class="badge {{ $order['status_class'] }}">
                                                {{ $order['status_label'] }}
                                            </span>
                                        </td>
                                        <td>{{ $order['date'] }}</td>
                                    </tr>
                                @empty
                                    <tr class="empty-row">
                                        <td colspan="5" class="text-center text-muted py-4">
                                            {{ __('ui.no_recent_orders_found') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-lg-4 col-md-12 col-sm-4 col-12">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="card-title">{{ __('ui.payment_methods') }}</h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="paymentChart" height="130"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('page-scripts')
@php
    $dashboardChartData = $chartData ?? ['labels' => [], 'orders' => [], 'returned' => [], 'revenue' => []];
    $dashboardPaymentBreakdown = $paymentBreakdown ?? ['labels' => [], 'data' => []];
@endphp
<script>
$(function () {
    if ($('#orders').length) {
        const hasEmptyRow = $('#orders tbody tr.empty-row').length > 0;

        if (!hasEmptyRow) {
            $('#orders').DataTable({
                aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, 'All']],
                iDisplayLength: 5
            });
        }
    }

    const chartData = @json($dashboardChartData);
    const paymentBreakdown = @json($dashboardPaymentBreakdown);

    if (document.getElementById('ordersChart')) {
        new Chart(document.getElementById('ordersChart'), {
            type: 'bar',
            data: {
                labels: chartData.labels ?? [],
                datasets: [
                    {
                        label: 'Orders',
                        data: chartData.orders ?? [],
                        backgroundColor: '#fba901',
                        borderRadius: 8,
                        categoryPercentage: 0.6,
                        barPercentage: 0.7
                    },
                    {
                        label: 'Returned',
                        data: chartData.returned ?? [],
                        backgroundColor: '#dc3545',
                        borderRadius: 8,
                        categoryPercentage: 0.6,
                        barPercentage: 0.7
                    },
                    {
                        label: 'Revenue',
                        data: chartData.revenue ?? [],
                        backgroundColor: '#0d6efd',
                        borderRadius: 8,
                        categoryPercentage: 0.6,
                        barPercentage: 0.7
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            padding: 15
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    if (document.getElementById('paymentChart')) {
        new Chart(document.getElementById('paymentChart'), {
            type: 'doughnut',
            data: {
                labels: paymentBreakdown.labels ?? [],
                datasets: [{
                    data: paymentBreakdown.data ?? [],
                    backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1']
                }]
            },
            options: {
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});
</script>
@endpush

@include('spedfly.include.footer')
