@include('spedfly.include.header')

@php
    $statusBadgeClasses = [
        'pending' => 'bg-warning text-dark',
        'processed' => 'bg-info text-dark',
        'refunded' => 'bg-success',
    ];
    $statusOptions = [
        'pending' => __('ui.pending'),
        'processed' => __('ui.processed'),
        'refunded' => __('ui.refunded'),
    ];
@endphp

<div class="app-body">
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
            <div>
                <h1 class="mt-4 mb-1">{{ __('ui.returns') }}</h1>
                <p class="text-muted mb-0">{{ __('ui.returned_shipments_loaded_from_live_records') }}</p>
            </div>
            <a href="{{ route('admin.shipments') }}" class="btn btn-outline-primary">
                <i class="bi bi-truck me-1"></i> {{ __('ui.open_shipments') }}
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success js-auto-hide-alert">{{ session('success') }}</div>
        @endif

        <div class="row mb-3 g-3">
            <div class="col-md-3">
                <div class="card p-3 h-100">
                    <h6 class="mb-1">{{ __('ui.total_returns') }}</h6>
                    <h4 class="mb-0">{{ number_format($stats['total'] ?? 0) }}</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 h-100">
                    <h6 class="mb-1">{{ __('ui.today') }}</h6>
                    <h4 class="mb-0">{{ number_format($stats['today'] ?? 0) }}</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 h-100">
                    <h6 class="mb-1">{{ __('ui.this_week') }}</h6>
                    <h4 class="mb-0">{{ number_format($stats['this_week'] ?? 0) }}</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3 h-100">
                    <h6 class="mb-1">{{ __('ui.sellers_affected') }}</h6>
                    <h4 class="mb-0">{{ number_format($stats['sellers'] ?? 0) }}</h4>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2" method="get" action="{{ route('admin.returns') }}">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="q" value="{{ $search }}" placeholder="{{ __('ui.search_return_order_seller_customer_phone_or_notes') }}">
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="from" value="{{ $fromDate }}">
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="to" value="{{ $toDate }}">
                    </div>
                    <div class="col-md-3 text-end">
                        <button class="btn btn-primary w-100" type="submit">{{ __('ui.filter') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table id="returnsTable" class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('ui.return_id') }}</th>
                        <th>{{ __('ui.order_id') }}</th>
                        <th>{{ __('ui.seller') }}</th>
                        <th>{{ __('ui.customer') }}</th>
                        <th>{{ __('ui.reason') }}</th>
                        <th>{{ __('ui.refund') }}</th>
                        <th>{{ __('ui.status') }}</th>
                        <th>{{ __('ui.date') }}</th>
                        <th style="width:180px;">{{ __('ui.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($returns as $return)
                        @php
                            $status = strtolower((string) $return->status);
                        @endphp
                        <tr>
                            <td>#{{ $return->return_code }}</td>
                            <td>{{ $return->order?->external_order_id ? (str_starts_with((string)$return->order->external_order_id, '#') ? $return->order->external_order_id : ('#' . $return->order->external_order_id)) : '-' }}</td>
                            <td>{{ $return->seller?->name ?? '-' }}</td>
                            <td>
                                <div class="fw-semibold">{{ $return->customer?->name ?? $return->order?->customer?->name ?? '-' }}</div>
                                <small class="text-muted">{{ $return->customer?->phone ?? $return->order?->customer?->phone ?? '-' }}</small>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $return->return_reason }}</div>
                                <small class="text-muted">{{ $return->notes ?: __('ui.no_notes') }}</small>
                            </td>
                            <td>{{ system_currency_format($return->refund_amount) }}</td>
                            <td><span class="badge {{ $statusBadgeClasses[$status] ?? 'bg-secondary' }}">{{ $statusOptions[$status] ?? ucfirst($status) }}</span></td>
                            <td>{{ optional($return->returned_at ?? $return->created_at)->format('Y-m-d') ?? '-' }}</td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap align-items-center">
                                    @if($return->order)
                                        <a href="{{ route('admin.orders.details', $return->order) }}" class="btn btn-outline-primary btn-sm">{{ __('ui.view_order') }}</a>
                                    @endif
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            {{ __('ui.actions') }}
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            @foreach ($statusOptions as $value => $label)
                                                @if ($status !== $value)
                                                    <li>
                                                        <form method="post" action="{{ route('admin.returns.status', $return) }}" class="dropdown-item p-0">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="{{ $value }}">
                                                            <button type="submit" class="dropdown-item">{{ $label }}</button>
                                                        </form>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('page-scripts')
<script>
    $(function() {
        $('#returnsTable').DataTable({
            aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, 'All']],
            iDisplayLength: 5,
            language: {
                emptyTable: @json(__('ui.returned_shipments_found'))
            }
        });

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
