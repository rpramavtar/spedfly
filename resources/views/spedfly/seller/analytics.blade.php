@include('spedfly.include.header')

@php
    $formatCurrency = fn ($value) => system_currency_format($value);
@endphp

<div class="app-body">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>{{ __('ui.analytics') }}</h4>
                <small class="text-muted">{{ __('ui.live_performance_summary_for') }} {{ $rangeLabel }}.</small>
            </div>

            <form method="GET" action="{{ route('seller.analytics') }}">
                <input type="hidden" name="courier" value="{{ $selectedCourier }}">
                <input type="hidden" name="agent" value="{{ $selectedAgent }}">
                <input type="hidden" name="date" value="{{ $selectedDate }}">
                <select class="form-select w-auto rounded-pill" name="range" onchange="this.form.submit()">
                    @foreach ($rangeOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedRange === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card card-shadow p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">{{ __('ui.total_revenue') }}</small>
                            <h4 class="fw-bold text-success">{{ $formatCurrency($summary['total_revenue']) }}</h4>
                            <small class="text-muted">{{ $summary['shipment_count'] }} {{ __('ui.shipments_linked') }}</small>
                        </div>
                        <div class="icon-circle bg-success bg-opacity-10 text-success">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-shadow p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">{{ __('ui.orders') }}</small>
                            <h4 class="fw-bold text-primary">{{ number_format($summary['total_orders']) }}</h4>
                            <small class="text-muted">{{ $summary['call_count'] }} {{ __('ui.call_logs_in_range') }}</small>
                        </div>
                        <div class="icon-circle bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-cart"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card card-shadow p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">{{ __('ui.confirmation_rate') }}</small>
                            <h4 class="fw-bold text-info">{{ $summary['confirmation_rate'] }}%</h4>
                            <small class="text-muted">{{ __('ui.processing_shipped_or_delivered') }}</small>
                        </div>
                        <div class="icon-circle bg-info bg-opacity-10 text-info">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card card-shadow p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">{{ __('ui.delivery_rate') }}</small>
                            <h4 class="fw-bold text-success">{{ $summary['delivery_rate'] }}%</h4>
                            <small class="text-muted">{{ number_format($summary['delivered_orders']) }} {{ __('ui.delivered_orders_in_selected_range') }}</small>
                        </div>
                        <div class="icon-circle bg-success bg-opacity-10 text-success">
                            <i class="bi bi-truck"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card card-shadow p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">{{ __('ui.return_rate') }}</small>
                            <h4 class="fw-bold text-danger">{{ $summary['return_rate'] }}%</h4>
                            <small class="text-muted">{{ __('ui.returned_orders_in_selected_range') }}</small>
                        </div>
                        <div class="icon-circle bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card card-shadow p-4">
                    <h6 class="section-title">{{ __('ui.revenue_trend') }}</h6>
                    <canvas height="220" id="revenueChart"></canvas>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-shadow p-4">
                    <h6 class="section-title">{{ __('ui.orders_by_courier') }}</h6>
                    <canvas height="220" id="ordersChart"></canvas>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card card-shadow p-4">
                    <h6 class="section-title">{{ __('ui.status_breakdown') }}</h6>
                    <canvas id="returnPie"></canvas>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-shadow p-4">
                    <h6 class="section-title">{{ __('ui.payment_methods') }}</h6>
                    <canvas id="paymentPie"></canvas>
                </div>
            </div>
        </div>

        <div class="card card-shadow p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h6 class="section-title mb-0">{{ __('ui.drill_down_analysis') }}</h6>
                <small class="text-muted">{{ __('ui.filter_shipment_rows_by_courier_agent_or_specific_date') }}</small>
            </div>

            <form method="GET" action="{{ route('seller.analytics') }}" class="row g-3 mb-3">
                <input type="hidden" name="range" value="{{ $selectedRange }}">
                <div class="col-md-3">
                    <select class="form-select rounded-pill" name="courier">
                        <option value="">{{ __('ui.all_couriers') }}</option>
                        @foreach ($courierOptions as $courier)
                            <option value="{{ $courier }}" @selected($selectedCourier === $courier)>{{ $courier }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select rounded-pill" name="agent">
                        <option value="">{{ __('ui.all_agents') }}</option>
                        @foreach ($agentOptions as $agent)
                            <option value="{{ $agent }}" @selected($selectedAgent === $agent)>{{ $agent }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <input class="form-control rounded-pill" type="date" name="date" value="{{ $selectedDate }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary rounded-pill w-100" type="submit">
                        <i class="bi bi-filter me-2"></i>{{ __('ui.apply_filter') }}
                    </button>
                    <a class="btn btn-outline-secondary rounded-pill" href="{{ route('seller.analytics', ['range' => $selectedRange]) }}">{{ __('ui.reset') }}</a>
                </div>
            </form>

            <div class="table-responsive">
                <table id="analyticsTable" class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('ui.date') }}</th>
                            <th>{{ __('ui.courier') }}</th>
                            <th>{{ __('ui.orders') }}</th>
                            <th>{{ __('ui.revenue') }}</th>
                            <th>{{ __('ui.confirmed') }}</th>
                            <th>{{ __('ui.returned') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($drilldownRows as $row)
                            <tr>
                                <td>{{ $row['date'] }}</td>
                                <td>{{ $row['courier'] }}</td>
                                <td>{{ $row['orders'] }}</td>
                                <td>{{ $formatCurrency($row['revenue']) }}</td>
                                <td>{{ $row['confirmed'] }}</td>
                                <td>{{ $row['returned'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('page-scripts')
<script>
  $(function () {
    $('#analyticsTable').DataTable({
      aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, "{{ __('ui.all') }}"]],
      iDisplayLength: 5,
      language: {
        emptyTable: @json(__('ui.no_data'))
      },
      order: []
    });

    if (typeof Chart === 'undefined') {
      return;
    }

    const withFallback = (payload, fallbackLabel) => {
      if (!payload || !Array.isArray(payload.labels) || payload.labels.length === 0) {
        return { labels: [fallbackLabel], data: [0] };
      }

      return payload;
    };

    const chartLabels = {
      revenue: @json(__('ui.revenue')),
      orders: @json(__('ui.orders')),
    };

    const revenueChartData = withFallback(@json($revenueChart), @json(__('ui.no_data')));
    const ordersChartData = withFallback(@json($ordersChart), @json(__('ui.no_data')));
    const statusBreakdown = withFallback(@json($statusBreakdown), @json(__('ui.no_status_data')));
    const paymentBreakdown = withFallback(@json($paymentBreakdown), @json(__('ui.no_payment_data')));

    new Chart(document.getElementById('revenueChart'), {
      type: 'line',
      data: {
        labels: revenueChartData.labels,
        datasets: [{
          label: chartLabels.revenue,
          data: revenueChartData.data,
          borderColor: '#0d6efd',
          backgroundColor: 'rgba(13,110,253,0.12)',
          fill: true,
          tension: 0.35
        }]
      },
      options: {
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true
          }
        }
      }
    });

    new Chart(document.getElementById('ordersChart'), {
      type: 'bar',
      data: {
        labels: ordersChartData.labels,
        datasets: [{
          label: chartLabels.orders,
          data: ordersChartData.data,
          backgroundColor: ['#0d6efd', '#198754', '#dc3545', '#fd7e14', '#6610f2', '#20c997']
        }]
      },
      options: {
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            precision: 0
          }
        }
      }
    });

    new Chart(document.getElementById('returnPie'), {
      type: 'bar',
      data: {
        labels: statusBreakdown.labels,
        datasets: [{
          label: chartLabels.orders,
          data: statusBreakdown.data,
          backgroundColor: ['#ffc107', '#0dcaf0', '#0d6efd', '#198754', '#dc3545'],
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
            precision: 0,
            grid: { color: 'rgba(0,0,0,0.05)' }
          },
          y: {
            grid: { display: false }
          }
        }
      }
    });

    new Chart(document.getElementById('paymentPie'), {
      type: 'doughnut',
      data: {
        labels: paymentBreakdown.labels,
        datasets: [{
          data: paymentBreakdown.data,
          backgroundColor: ['#198754', '#0d6efd', '#fd7e14', '#6f42c1'],
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
