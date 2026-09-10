@include('spedfly.include.header')

@php
    $statusBadgeClasses = [
        'pending' => 'bg-warning text-dark',
        'processed' => 'bg-info text-dark',
        'refunded' => 'bg-success',
    ];

    $statusLabels = [
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
                    <i class="bi bi-arrow-counterclockwise me-2 text-danger"></i>{{ __('ui.returns') }}
                </h3>
                <small class="text-muted">{{ __('ui.returned_shipments_loaded_from_live_records') }}</small>
            </div>
            <a href="{{ route('seller.orders') }}" class="btn btn-outline-primary">
                <i class="bi bi-card-checklist me-1"></i> {{ __('ui.back_to_orders') }}
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success js-auto-hide-alert">{{ session('success') }}</div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card p-3 h-100">
                    <small class="text-muted">{{ __('ui.total_returns') }}</small>
                    <h4 class="fw-bold mb-0">{{ number_format((int) ($stats['total'] ?? 0)) }}</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 h-100">
                    <small class="text-muted">{{ __('ui.today') }}</small>
                    <h4 class="fw-bold mb-0">{{ number_format((int) ($stats['today'] ?? 0)) }}</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 h-100">
                    <small class="text-muted">{{ __('ui.this_week') }}</small>
                    <h4 class="fw-bold mb-0">{{ number_format((int) ($stats['this_week'] ?? 0)) }}</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 h-100">
                    <small class="text-muted">{{ __('ui.refund_amount') }}</small>
                    <h4 class="fw-bold mb-0">{{ system_currency_format($stats['refund_amount'] ?? 0) }}</h4>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form method="get" action="{{ route('seller.returns') }}" class="row g-2 align-items-end">
                    <div class="col-lg-5">
                        <label class="form-label fw-semibold">{{ __('ui.search') }}</label>
                        <input type="text" class="form-control" name="q" value="{{ $search }}" placeholder="{{ __('ui.search_return_order_seller_customer_phone_or_notes') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">{{ __('ui.from') }}</label>
                        <input type="date" class="form-control" name="from" value="{{ $fromDate }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">{{ __('ui.to') }}</label>
                        <input type="date" class="form-control" name="to" value="{{ $toDate }}">
                    </div>
                    <div class="col-md-1 d-grid">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-funnel"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">{{ __('ui.return_orders_list') }}</h4>
                <span class="badge bg-danger">{{ number_format($returns->count()) }} {{ __('ui.rows') }}</span>
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table id="returnsTable" class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('ui.return_id') }}</th>
                                <th>{{ __('ui.order') }}</th>
                                <th>{{ __('ui.customer') }}</th>
                                <th>{{ __('ui.reason') }}</th>
                                <th>{{ __('ui.status') }}</th>
                                <th>Refund</th>
                                <th>Returned At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($returns as $return)
                                @php
                                    $status = strtolower((string) $return->status);
                                @endphp
                                <tr>
                                    <td>{{ $return->return_code }}</td>
                                    <td>{{ $return->order?->external_order_id ?? '-' }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $return->order?->customer?->name ?? $return->customer?->name ?? '-' }}</div>
                                        <small class="text-muted">{{ $return->order?->customer?->phone ?? $return->customer?->phone ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $return->return_reason ?? '-' }}</div>
                                        <small class="text-muted">{{ $return->notes ?: 'No notes' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge {{ $statusBadgeClasses[$status] ?? 'bg-secondary' }}">
                                            {{ $statusLabels[$status] ?? ucfirst($status) }}
                                        </span>
                                    </td>
                                    <td>{{ system_currency_format($return->refund_amount) }}</td>
                                    <td>{{ optional($return->returned_at ?? $return->created_at)->format('d M Y') ?? '-' }}</td>
                                    <td>
                                        @if ($return->order)
                                            <a href="{{ route('seller.order-details', $return->order) }}" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr class="empty-row">
                                    <td colspan="8" class="text-center text-muted py-4">No returns found for your seller account.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('page-scripts')
<script>
    $(function () {
        if ($('#returnsTable').length && !$('#returnsTable tbody tr.empty-row').length) {
            $('#returnsTable').DataTable({
                aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, 'All']],
                iDisplayLength: 5,
                order: []
            });
        }

        var $autoHideAlert = $('.js-auto-hide-alert');
        if ($autoHideAlert.length) {
            setTimeout(function () {
                $autoHideAlert.fadeOut(300);
            }, 4000);
        }
    });
</script>
@endpush

@include('spedfly.include.footer')
