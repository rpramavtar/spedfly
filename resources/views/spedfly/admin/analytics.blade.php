@include('spedfly.include.header')

@php
    $formatCurrency = fn ($value) => system_currency_format($value);
    $performanceChart = $performanceChart ?? ['labels' => [], 'orders' => [], 'returned' => [], 'revenue' => []];
    $statusBreakdown = $statusBreakdown ?? ['labels' => [], 'data' => []];
    $returnBreakdown = $returnBreakdown ?? ['labels' => [], 'data' => []];
    $paymentBreakdown = $paymentBreakdown ?? ['labels' => [], 'data' => []];
@endphp

<div class="app-body">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="bi bi-graph-up-arrow me-2 text-primary"></i>{{ __('ui.analytics') }}
                </h4>
                <small class="text-muted">{{ __('ui.live_business_summary_for') }} {{ $analyticsYear ?? now()->year }}.</small>
            </div>
        </div>

        <div class="row mb-4 g-3">
            <div class="col-md-3">
                <div class="card card-shadow p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">{{ __('ui.total_sales') }}</small>
                            <h4 class="fw-bold text-success">{{ $formatCurrency($summary['total_sales'] ?? 0) }}</h4>
                            <small class="text-muted">{{ number_format($summary['total_orders'] ?? 0) }} {{ __('ui.orders') }} in {{ $analyticsYear ?? now()->year }}</small>
                        </div>
                        <div class="icon-circle bg-success bg-opacity-10 text-success">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-shadow p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">{{ __('ui.average_order_value') }}</small>
                            <h4 class="fw-bold text-primary">{{ $formatCurrency($summary['average_order_value'] ?? 0) }}</h4>
                            <small class="text-muted">{{ number_format($summary['fulfilled_orders'] ?? 0) }} {{ __('ui.fulfilled_orders') }}</small>
                        </div>
                        <div class="icon-circle bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-shadow p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">{{ __('ui.returned_orders') }}</small>
                            <h4 class="fw-bold text-danger">{{ number_format($summary['returned_orders'] ?? 0) }}</h4>
                            <small class="text-muted">{{ $summary['return_rate'] ?? 0 }}% {{ __('ui.return_rate') }}</small>
                        </div>
                        <div class="icon-circle bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-shadow p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">{{ __('ui.return_amount') }}</small>
                            <h4 class="fw-bold text-warning">{{ $formatCurrency($summary['return_amount'] ?? 0) }}</h4>
                            <small class="text-muted">{{ __('ui.refunded_value_in_selected_year') }}</small>
                        </div>
                        <div class="icon-circle bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4 g-4">
            <div class="col-md-8">
                <div class="card p-4 h-100">
                    <h6 class="fw-semibold mb-3">{{ __('ui.monthly_performance_overview') }} ({{ $analyticsYear ?? now()->year }})</h6>
                    <canvas height="250" id="performanceChart"></canvas>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 h-100 text-center">
                    <h6 class="fw-semibold mb-3">{{ __('ui.payment_methods') }}</h6>
                    <div class="chart-wrapper">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card p-4 h-100">
                    <h6 class="fw-semibold mb-3">{{ __('ui.order_status_breakdown') }}</h6>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card p-4 h-100">
                    <h6 class="fw-semibold mb-3">{{ __('ui.return_reasons') }}</h6>
                    <canvas id="returnChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

@push('page-scripts')
<script>
  $(function () {
    if (typeof Chart === 'undefined') {
      return;
    }

    const withFallback = (payload, fallbackLabel) => {
      if (!payload || !Array.isArray(payload.labels) || payload.labels.length === 0) {
        return { labels: [fallbackLabel], data: [0] };
      }

      return payload;
    };

    const withPerformanceFallback = (payload, fallbackLabel) => {
      if (!payload || !Array.isArray(payload.labels) || payload.labels.length === 0) {
        return {
          labels: [fallbackLabel],
          orders: [0],
          returned: [0],
          revenue: [0]
        };
      }

      return payload;
    };

    const performanceData = withPerformanceFallback(@json($performanceChart), @json(__('ui.no_data')));
    const statusData = withFallback(@json($statusBreakdown), @json(__('ui.no_status_data')));
    const returnData = withFallback(@json($returnBreakdown), @json(__('ui.no_return_data')));
    const paymentData = withFallback(@json($paymentBreakdown), @json(__('ui.no_payment_data')));

    new Chart(document.getElementById('performanceChart'), {
      type: 'bar',
      data: {
        labels: performanceData.labels,
        datasets: [
          {
            label: @json(__('ui.orders')),
            data: performanceData.orders ?? [],
            backgroundColor: '#0d6efd',
            borderRadius: 8,
            categoryPercentage: 0.6,
            barPercentage: 0.7
          },
          {
            label: @json(__('ui.returned')),
            data: performanceData.returned ?? [],
            backgroundColor: '#dc3545',
            borderRadius: 8,
            categoryPercentage: 0.6,
            barPercentage: 0.7
          },
          {
            label: @json(__('ui.revenue')),
            data: performanceData.revenue ?? [],
            backgroundColor: '#198754',
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
            position: 'top'
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

    new Chart(document.getElementById('statusChart'), {
      type: 'bar',
      data: {
        labels: statusData.labels,
        datasets: [{
          label: @json(__('ui.orders')),
          data: statusData.data,
          backgroundColor: ['#ffc107', '#0dcaf0', '#0d6efd', '#198754', '#dc3545', '#6f42c1'],
          borderRadius: 8,
          barThickness: 20
        }]
      },
      options: {
        indexAxis: 'y',
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: {
            beginAtZero: true,
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          y: {
            grid: { display: false }
          }
        }
      }
    });

    new Chart(document.getElementById('returnChart'), {
      type: 'bar',
      data: {
        labels: returnData.labels,
        datasets: [{
          label: @json(__('ui.returns')),
          data: returnData.data,
          backgroundColor: ['#dc3545', '#ffc107', '#0d6efd', '#20c997', '#6f42c1', '#fd7e14'],
          borderRadius: 8,
          barThickness: 18
        }]
      },
      options: {
        indexAxis: 'y',
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: {
            beginAtZero: true,
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          y: {
            grid: { display: false }
          }
        }
      }
    });

    new Chart(document.getElementById('paymentChart'), {
      type: 'doughnut',
      data: {
        labels: paymentData.labels,
        datasets: [{
          data: paymentData.data,
          backgroundColor: ['#198754', '#0d6efd', '#6f42c1', '#fd7e14', '#dc3545'],
          borderWidth: 0
        }]
      },
      options: {
        cutout: '65%',
        plugins: {
          legend: { position: 'top' }
        }
      }
    });
  });
</script>
@endpush

@include('spedfly.include.footer')
