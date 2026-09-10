@include('spedfly.include.header')

@php
    $formatCurrency = fn ($value) => system_currency_format($value);
    $formatDuration = function (int $seconds): string {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    };

    $shipmentStatusClasses = [
        'pending' => 'bg-warning text-dark',
        'in_transit' => 'bg-primary text-light',
        'delivered' => 'bg-success',
        'returned' => 'bg-danger',
    ];

    $callResultClasses = [
        'connected' => 'bg-success',
        'no_answer' => 'bg-warning text-dark',
        'failed' => 'bg-danger',
        'busy' => 'bg-secondary',
    ];

    $returnStatusClasses = [
        'pending' => 'bg-warning text-dark',
        'processed' => 'bg-info text-dark',
        'refunded' => 'bg-success',
    ];

    $callResultLabels = [
        'connected' => 'Connected',
        'no_answer' => 'No Answer',
        'failed' => 'Failed',
        'busy' => 'Busy',
    ];

    $returnStatusLabels = [
        'pending' => 'Pending',
        'processed' => 'Processed',
        'refunded' => 'Refunded',
    ];
@endphp

<div class="app-body">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h3 class="fw-bold mb-1">
                    <i class="bi bi-file-earmark-text me-2 text-primary"></i>{{ __('ui.seller_report') }}
                </h3>
                <small class="text-muted">{{ __('ui.invoice_ready_report_for') }} {{ $rangeLabel }}.</small>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary" type="button" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>{{ __('ui.print') }}
                </button>
            </div>
        </div>

        <div class="card card-shadow p-4 mb-4">
            <form method="GET" action="{{ route('seller.reports') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">{{ __('ui.from') }}</label>
                    <input class="form-control rounded-pill" type="date" name="from" value="{{ $rangeStart->toDateString() }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">{{ __('ui.to') }}</label>
                    <input class="form-control rounded-pill" type="date" name="to" value="{{ $rangeEnd->toDateString() }}">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button class="btn btn-primary rounded-pill w-100" type="submit">
                        <i class="bi bi-funnel me-2"></i>{{ __('ui.generate_report') }}
                    </button>
                    <a class="btn btn-outline-secondary rounded-pill" href="{{ route('seller.reports') }}">
                        {{ __('ui.reset') }}
                    </a>
                </div>
            </form>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-2">
                <div class="card card-shadow p-3 h-100">
                    <small class="text-muted">Shipped Orders</small>
                    <h4 class="fw-bold text-primary mb-1">{{ number_format($summary['shipped_orders']) }}</h4>
                    <small class="text-muted">{{ __('ui.orders_with_in_transit_or_delivered_shipments') }}</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card card-shadow p-3 h-100">
                    <small class="text-muted">Calls</small>
                    <h4 class="fw-bold text-success mb-1">{{ number_format($summary['calls']) }}</h4>
                    <small class="text-muted">{{ __('ui.call_logs_in_selected_period') }}</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card card-shadow p-3 h-100">
                    <small class="text-muted">Returns</small>
                    <h4 class="fw-bold text-danger mb-1">{{ number_format($summary['returns']) }}</h4>
                    <small class="text-muted">{{ __('ui.return_records_in_selected_period') }}</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card card-shadow p-3 h-100">
                    <small class="text-muted">Shipped Value</small>
                    <h4 class="fw-bold text-primary mb-1">{{ $formatCurrency($summary['shipped_amount']) }}</h4>
                    <small class="text-muted">{{ __('ui.total_value_of_shipped_orders') }}</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card card-shadow p-3 h-100">
                    <small class="text-muted">Call Duration</small>
                    <h4 class="fw-bold text-success mb-1">{{ $formatDuration((int) $summary['call_duration_seconds']) }}</h4>
                    <small class="text-muted">{{ __('ui.combined_talk_time') }}</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card card-shadow p-3 h-100">
                    <small class="text-muted">Return Amount</small>
                    <h4 class="fw-bold text-danger mb-1">{{ $formatCurrency($summary['return_amount']) }}</h4>
                    <small class="text-muted">{{ __('ui.refund_total_on_return_records') }}</small>
                </div>
            </div>
        </div>

        <div class="card card-shadow p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="section-title mb-1">{{ __('ui.shipped_orders') }}</h6>
                    <small class="text-muted">{{ __('ui.order_details_for_shipments_marked_in_transit_or_delivered') }}</small>
                </div>
                <span class="badge bg-primary">{{ number_format($summary['shipped_orders']) }} {{ __('ui.rows') }}</span>
            </div>

            <div class="table-responsive">
                <table id="shippedOrdersTable" class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('ui.order') }}</th>
                            <th>{{ __('ui.shipment') }}</th>
                            <th>{{ __('ui.customer') }}</th>
                            <th>{{ __('ui.courier') }}</th>
                            <th>{{ __('ui.status') }}</th>
                            <th>{{ __('ui.payment') }}</th>
                            <th>{{ __('ui.amount') }}</th>
                            <th>{{ __('ui.shipment_date') }}</th>
                            <th>{{ __('ui.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($shippedOrders as $shipment)
                            @php
                                $shipmentStatus = strtolower((string) $shipment->status);
                            @endphp
                            <tr>
                                <td>{{ $shipment->order?->external_order_id ?? '-' }}</td>
                                <td>{{ $shipment->shipment_code ?? '-' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $shipment->order?->customer?->name ?? $shipment->customer_name ?? '-' }}</div>
                                    <small class="text-muted">{{ $shipment->order?->customer?->phone ?? $shipment->customer_phone ?? '-' }}</small>
                                </td>
                                <td>{{ $shipment->courier_name ?: '-' }}</td>
                                <td>
                                    <span class="badge {{ $shipmentStatusClasses[$shipmentStatus] ?? 'bg-secondary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $shipmentStatus)) }}
                                    </span>
                                </td>
                                <td>{{ strtoupper((string) ($shipment->order?->payment_type ?? $shipment->payment_type ?? 'COD')) }}</td>
                                <td>{{ $formatCurrency((float) ($shipment->order?->amount ?? 0)) }}</td>
                                <td>{{ optional($shipment->shipment_date)->format('d M Y') ?? '-' }}</td>
                                <td>
                                    @if ($shipment->order)
                                        <a href="{{ route('seller.order-details', $shipment->order) }}" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">{{ __('ui.no_shipped_orders_found_in_selected_period') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card card-shadow p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="section-title mb-1">{{ __('ui.call_logs') }}</h6>
                            <small class="text-muted">{{ __('ui.calls_logged_for_invoicing_and_support_tracking') }}</small>
                        </div>
                        <span class="badge bg-success">{{ number_format($summary['calls']) }} {{ __('ui.rows') }}</span>
                    </div>

                    <div class="table-responsive">
                        <table id="callsTable" class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('ui.call_id') }}</th>
                                    <th>{{ __('ui.customer') }}</th>
                                    <th>{{ __('ui.order') }}</th>
                                    <th>{{ __('ui.agent') }}</th>
                                    <th>{{ __('ui.result') }}</th>
                                    <th>{{ __('ui.duration') }}</th>
                                    <th>{{ __('ui.call_time') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($callLogs as $log)
                                    @php
                                        $callResult = strtolower((string) $log->result);
                                        $minutes = intdiv((int) $log->duration_seconds, 60);
                                        $seconds = (int) $log->duration_seconds % 60;
                                    @endphp
                                    <tr>
                                        <td>#{{ $log->call_code ?? ('C' . str_pad((string) $log->id, 4, '0', STR_PAD_LEFT)) }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $log->customer?->name ?? '-' }}</div>
                                            <small class="text-muted">{{ $log->customer?->phone ?? $log->customer?->email ?? '-' }}</small>
                                        </td>
                                        <td>{{ $log->order?->external_order_id ?? '-' }}</td>
                                        <td>{{ $log->agent_name }}</td>
                                        <td>
                                            <span class="badge {{ $callResultClasses[$callResult] ?? 'bg-primary' }}">
                                                {{ $callResultLabels[$callResult] ?? ucfirst(str_replace('_', ' ', $callResult)) }}
                                            </span>
                                        </td>
                                        <td>{{ sprintf('%02d:%02d', $minutes, $seconds) }}</td>
                                        <td>{{ optional($log->call_time)->format('d M Y h:i A') ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">{{ __('ui.no_call_logs_found_in_selected_period') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-shadow p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="section-title mb-1">{{ __('ui.returns') }}</h6>
                            <small class="text-muted">{{ __('ui.return_records_tied_to_this_seller') }}</small>
                        </div>
                        <span class="badge bg-danger">{{ number_format($summary['returns']) }} rows</span>
                    </div>

                    <div class="table-responsive">
                        <table id="returnsTable" class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Return</th>
                                    <th>Order</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                    <th>Refund</th>
                                    <th>Returned At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($returns as $return)
                                    @php
                                        $returnStatus = strtolower((string) $return->status);
                                    @endphp
                                    <tr>
                                        <td>{{ $return->return_code }}</td>
                                        <td>{{ $return->order?->external_order_id ?? '-' }}</td>
                                        <td>{{ $return->order?->customer?->name ?? $return->customer?->name ?? '-' }}</td>
                                        <td>
                                            <span class="badge {{ $returnStatusClasses[$returnStatus] ?? 'bg-secondary' }}">
                                                {{ $returnStatusLabels[$returnStatus] ?? ucfirst($returnStatus) }}
                                            </span>
                                        </td>
                                        <td>{{ $formatCurrency((float) $return->refund_amount) }}</td>
                                        <td>{{ optional($return->returned_at ?? $return->created_at)->format('d M Y') ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No returns found in the selected period.</td>
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

@push('page-scripts')
<script>
  $(function () {
    $('#shippedOrdersTable, #callsTable, #returnsTable').DataTable({
      aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, "All"]],
      iDisplayLength: 5,
      order: []
    });
  });
</script>
@endpush

@include('spedfly.include.footer')
