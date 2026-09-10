@include('spedfly.include.header')

@php
    $formatCurrency = fn ($value) => system_currency_format($value);
    $statusClasses = [
        'in_transit' => 'bg-primary text-light',
        'delivered' => 'bg-success',
        'returned' => 'bg-danger',
        'pending' => 'bg-warning text-dark',
    ];
    $exportQuery = fn (?int $sellerId, string $format) => route('admin.reports.export', array_filter([
        'format' => $format,
        'from' => $rangeStart->toDateString(),
        'to' => $rangeEnd->toDateString(),
        'seller_id' => $sellerId,
    ], fn ($value) => $value !== null && $value !== ''));
@endphp

<div class="app-body">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-file-earmark-text me-2 text-primary"></i>{{ __('ui.seller_wise_reports') }}</h3>
                <small class="text-muted">{{ __('ui.counts_shipped_orders_calls_and_returns_by_seller_for_invoicing') }}</small>
            </div>
        </div>

        <div class="card card-shadow p-4 mb-4">
            <form method="GET" action="{{ route('admin.reports') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">{{ __('ui.seller') }}</label>
                    <select class="form-select rounded-pill" name="seller_id">
                        <option value="">{{ __('ui.all_sellers') }}</option>
                        @foreach ($sellerOptions as $seller)
                            <option value="{{ $seller->id }}" @selected((int) ($selectedSellerId ?? 0) === (int) $seller->id)>{{ $seller->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">{{ __('ui.from') }}</label>
                    <input class="form-control rounded-pill" type="date" name="from" value="{{ $rangeStart->toDateString() }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">{{ __('ui.to') }}</label>
                    <input class="form-control rounded-pill" type="date" name="to" value="{{ $rangeEnd->toDateString() }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary rounded-pill w-100" type="submit">
                        <i class="bi bi-funnel me-2"></i>{{ __('ui.generate_report') }}
                    </button>
                    <a class="btn btn-outline-secondary rounded-pill" href="{{ route('admin.reports') }}">{{ __('ui.reset') }}</a>
                </div>
            </form>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-4">
            <a class="btn btn-success" href="{{ $exportQuery($selectedSellerId ?? null, 'pdf') }}">
                <i class="bi bi-file-earmark-pdf me-1"></i>{{ __('ui.export_pdf') }}
            </a>
            <a class="btn btn-outline-success" href="{{ $exportQuery($selectedSellerId ?? null, 'xls') }}">
                <i class="bi bi-file-earmark-excel me-1"></i>{{ __('ui.export_excel') }}
            </a>
            <a class="btn btn-outline-secondary" href="{{ $exportQuery($selectedSellerId ?? null, 'csv') }}">
                <i class="bi bi-filetype-csv me-1"></i>{{ __('ui.export_csv') }}
            </a>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card card-shadow p-3 h-100">
                    <small class="text-muted">{{ __('ui.sellers') }}</small>
                    <h4 class="fw-bold text-primary mb-1">{{ number_format($summary['seller_count']) }}</h4>
                    <small class="text-muted">{{ __('ui.active_seller_records') }}</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-shadow p-3 h-100">
                    <small class="text-muted">{{ __('ui.shipped_orders') }}</small>
                    <h4 class="fw-bold text-success mb-1">{{ number_format($summary['shipped_orders']) }}</h4>
                    <small class="text-muted">{{ __('ui.orders_with_in_transit_or_delivered_shipments') }}</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-shadow p-3 h-100">
                    <small class="text-muted">{{ __('ui.calls') }}</small>
                    <h4 class="fw-bold text-info mb-1">{{ number_format($summary['call_count']) }}</h4>
                    <small class="text-muted">{{ __('ui.call_logs_in_the_selected_range') }}</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-shadow p-3 h-100">
                    <small class="text-muted">{{ __('ui.returns_label') }}</small>
                    <h4 class="fw-bold text-danger mb-1">{{ number_format($summary['return_count']) }}</h4>
                    <small class="text-muted">{{ __('ui.return_records_in_the_selected_range') }}</small>
                </div>
            </div>
        </div>

        <div class="card card-shadow p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="section-title mb-1">{{ __('ui.seller_summary') }}</h6>
                    <small class="text-muted">{{ __('ui.one_row_per_seller_with_the_three_invoice_counts') }}</small>
                </div>
                <span class="badge bg-primary">{{ number_format($sellerRows->count()) }} {{ __('ui.sellers') }}</span>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('ui.seller') }}</th>
                            <th>{{ __('ui.shipped_orders') }}</th>
                            <th>{{ __('ui.calls') }}</th>
                            <th>{{ __('ui.returns_label') }}</th>
                            <th>{{ __('ui.shipped_value') }}</th>
                            <th>{{ __('ui.details') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sellerRows as $seller)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $seller['seller_name'] }}</div>
                                    <small class="text-muted">{{ $seller['seller_email'] }}</small>
                                </td>
                                <td>{{ number_format($seller['shipped_orders']) }}</td>
                                <td>{{ number_format($seller['calls']) }}</td>
                                <td>{{ number_format($seller['returns']) }}</td>
                                <td>{{ $formatCurrency($seller['shipped_amount']) }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#sellerReport{{ $seller['seller_id'] }}">
                                            {{ __('ui.view_orders') }}
                                        </button>
                                        <a class="btn btn-outline-success btn-sm" href="{{ $exportQuery((int) $seller['seller_id'], 'pdf') }}">
                                            {{ __('ui.export_pdf') }}
                                        </a>
                                        <a class="btn btn-outline-success btn-sm" href="{{ $exportQuery((int) $seller['seller_id'], 'xls') }}">
                                            {{ __('ui.export_excel') }}
                                        </a>
                                        <a class="btn btn-outline-secondary btn-sm" href="{{ $exportQuery((int) $seller['seller_id'], 'csv') }}">
                                            {{ __('ui.export_csv') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <tr class="collapse" id="sellerReport{{ $seller['seller_id'] }}">
                                <td colspan="6" class="bg-light">
                                    <div class="p-2">
                                        <h6 class="fw-semibold mb-3">{{ $seller['seller_name'] }} {{ __('ui.shipped_order_details') }}</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('ui.order') }}</th>
                                                        <th>{{ __('ui.customer') }}</th>
                                                        <th>{{ __('ui.courier') }}</th>
                                                        <th>{{ __('ui.status') }}</th>
                                                        <th>{{ __('ui.amount') }}</th>
                                                        <th>{{ __('ui.shipment_date') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($seller['orders'] as $order)
                                                        @php
                                                            $orderStatus = strtolower((string) $order['status']);
                                                            $orderStatusLabel = __('ui.' . $orderStatus);
                                                        @endphp
                                                        <tr>
                                                            <td>{{ $order['order_id'] }}</td>
                                                            <td>{{ $order['customer_name'] }}</td>
                                                            <td>{{ $order['courier_name'] }}</td>
                                                            <td>
                                                                <span class="badge {{ $statusClasses[$orderStatus] ?? 'bg-secondary' }}">
                                                                    {{ $orderStatusLabel !== ('ui.' . $orderStatus) ? $orderStatusLabel : ucfirst(str_replace('_', ' ', $orderStatus)) }}
                                                                </span>
                                                            </td>
                                                            <td>{{ $formatCurrency($order['amount']) }}</td>
                                                            <td>{{ $order['shipment_date'] }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="6" class="text-center text-muted">{{ __('ui.no_shipped_orders_for_this_seller_in_the_selected_range') }}</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">{{ __('ui.no_sellers_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-shadow p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="section-title mb-1">{{ __('ui.all_shipped_order_details') }}</h6>
                    <small class="text-muted">{{ __('ui.flat_list_for_quick_invoice_checking_and_export') }}</small>
                </div>
                <span class="badge bg-success">{{ number_format($shipmentDetailRows->count()) }} {{ __('ui.orders') }}</span>
            </div>

            <div class="table-responsive">
                <table id="shipmentDetailsTable" class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('ui.seller') }}</th>
                            <th>{{ __('ui.order') }}</th>
                            <th>{{ __('ui.customer') }}</th>
                            <th>{{ __('ui.courier') }}</th>
                            <th>{{ __('ui.status') }}</th>
                            <th>{{ __('ui.amount') }}</th>
                            <th>{{ __('ui.shipment_date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($shipmentDetailRows as $row)
                            @php
                                $rowStatus = strtolower((string) $row['status']);
                                $rowStatusLabel = __('ui.' . $rowStatus);
                            @endphp
                            <tr>
                                <td>{{ $row['seller_name'] }}</td>
                                <td>{{ $row['order_id'] }}</td>
                                <td>{{ $row['customer_name'] }}</td>
                                <td>{{ $row['courier_name'] }}</td>
                                <td>
                                    <span class="badge {{ $statusClasses[$rowStatus] ?? 'bg-secondary' }}">
                                        {{ $rowStatusLabel !== ('ui.' . $rowStatus) ? $rowStatusLabel : ucfirst(str_replace('_', ' ', $rowStatus)) }}
                                    </span>
                                </td>
                                <td>{{ $formatCurrency($row['amount']) }}</td>
                                <td>{{ $row['shipment_date'] }}</td>
                            </tr>
                        @empty
                            <tr class="empty-row">
                                <td colspan="7" class="text-center text-muted py-4">{{ __('ui.no_shipped_order_details_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('page-scripts')
<script>
  $(function () {
    var hasEmptyRow = $('#shipmentDetailsTable tbody tr.empty-row').length > 0;

    if (!hasEmptyRow) {
      $('#shipmentDetailsTable').DataTable({
        aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, 'All']],
        iDisplayLength: 5,
        order: []
      });
    }
  });
</script>
@endpush

@include('spedfly.include.footer')
