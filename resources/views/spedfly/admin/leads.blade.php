@include('spedfly.include.header')

@php
    $leadBadgeMap = [
        'lead' => 'bg-warning text-dark',
        'new' => 'bg-primary',
        'processing' => 'bg-info text-dark',
        'shipped' => 'bg-primary',
        'delivered' => 'bg-success',
        'returned' => 'bg-danger',
    ];
@endphp

<div class="app-body">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-person-lines-fill me-2 text-primary"></i>{{ __('ui.cod_leads') }}</h3>
                <small class="text-muted">{{ __('ui.admin_panel_lead_orders_description') }}</small>
            </div>
            <a href="{{ route('admin.call-center') }}" class="btn btn-primary">
                <i class="bi bi-telephone me-1"></i> {{ __('ui.open_call_center') }}
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success js-auto-hide-alert">{{ session('success') }}</div>
        @endif

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card p-4 h-100">
                    <h6>{{ __('ui.total_leads') }}</h6>
                    <h3 class="fw-bold mb-0">{{ $stats['total'] }}</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 h-100">
                    <h6>{{ __('ui.today') }}</h6>
                    <h3 class="fw-bold mb-0">{{ $stats['today'] }}</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 h-100">
                    <h6>{{ __('ui.called') }}</h6>
                    <h3 class="fw-bold mb-0">{{ $stats['called'] }}</h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 h-100">
                    <h6>{{ __('ui.needs_retry') }}</h6>
                    <h3 class="fw-bold mb-0">{{ $stats['unreached'] }}</h3>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">{{ __('ui.lead_list') }}</h4>
                <small class="text-muted">{{ __('ui.confirm_a_lead_to_move_it_into_orders') }}</small>
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table id="leadsTable" class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('ui.lead') }}</th>
                                <th>{{ __('ui.seller') }}</th>
                                <th>{{ __('ui.date') }}</th>
                                <th>{{ __('ui.customer') }}</th>
                                <th>{{ __('ui.status') }}</th>
                                <th>{{ __('ui.amount') }}</th>
                                <th>{{ __('ui.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($leads as $lead)
                                @php
                                    $status = strtolower((string) $lead->status);
                                    $badgeClass = $leadBadgeMap[$status] ?? 'bg-secondary';
                                @endphp
                                <tr>
                                    <td>{{ $lead->external_order_id }}</td>
                                    <td>{{ $lead->seller?->name ?? '-' }}</td>
                                    <td>{{ optional($lead->ordered_at)->format('d M Y') ?? optional($lead->created_at)->format('d M Y') }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $lead->customer?->name ?? '-' }}</div>
                                        <small class="text-muted">{{ $lead->customer?->phone ?? '-' }}</small>
                                    </td>
                                    <td><span class="badge {{ $badgeClass }}">{{ ucfirst($status) }}</span></td>
                                    <td>{{ system_currency_format($lead->amount) }}</td>
                                    <td>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <a href="{{ route('admin.orders.details', $lead) }}" class="btn btn-outline-primary btn-sm">{{ __('ui.view') }}</a>
                                            <form method="POST" action="{{ route('admin.leads.confirm', $lead) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-success btn-sm">{{ __('ui.confirm_lead') }}</button>
                                            </form>
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
    </div>
</div>

@push('page-scripts')
<script>
    $(function () {
        $('#leadsTable').DataTable({
            aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, 'All']],
            iDisplayLength: 5,
            language: {
                emptyTable: 'No COD leads found.'
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
