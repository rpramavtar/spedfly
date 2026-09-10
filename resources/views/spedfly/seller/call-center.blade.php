@include('spedfly.include.header')

@php
    $resultClasses = [
        'connected' => 'bg-success',
        'no_answer' => 'bg-warning text-dark',
        'failed' => 'bg-danger',
        'busy' => 'bg-secondary',
    ];
@endphp

<div class="app-body">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-telephone me-2 text-primary"></i>{{ __('ui.call_center') }}</h3>
                <small class="text-muted">{{ __('ui.call_center_subtitle') }}</small>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <div class="fw-semibold mb-1">{{ __('ui.fix_following_errors') }}</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card p-4 h-100">
                    <h6>{{ __('ui.total_calls_today') }}</h6>
                    <h3 class="fw-bold mb-1">{{ $stats['total_calls_today'] }}</h3>
                    <small class="{{ $stats['vs_yesterday'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $stats['vs_yesterday'] >= 0 ? '+' : '' }}{{ $stats['vs_yesterday'] }}% {{ __('ui.vs_yesterday') }}
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 h-100">
                    <h6>{{ __('ui.connected_percentage') }}</h6>
                    <h3 class="fw-bold mb-1">{{ $stats['connected_percentage'] }}%</h3>
                    <small class="text-muted">{{ __('ui.connection_success_rate_today') }}</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 h-100">
                    <h6>{{ __('ui.missed_calls') }}</h6>
                    <h3 class="fw-bold mb-1">{{ $stats['missed_calls_today'] }}</h3>
                    <small class="text-danger">{{ __('ui.no_answer_failed_busy') }}</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 h-100">
                    <h6>{{ __('ui.avg_duration') }}</h6>
                    <h3 class="fw-bold mb-1">{{ $stats['average_duration'] }}</h3>
                    <small class="text-muted">{{ __('ui.avg_duration_today') }}</small>
                </div>
            </div>
        </div>

        <div class="card p-4 mb-4">
            <form method="GET" action="{{ route('seller.call-center') }}" class="row g-3 align-items-center">
                <div class="col-12 col-lg-7">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('seller.call-center', array_filter(['date' => $dateFilter, 'search' => $search])) }}"
                           class="btn {{ $resultFilter === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">
                            {{ __('ui.all') }}
                        </a>
                        @foreach ($resultOptions as $value => $label)
                            <a href="{{ route('seller.call-center', array_filter(['result' => $value, 'date' => $dateFilter, 'search' => $search])) }}"
                               class="btn {{ $resultFilter === $value ? 'btn-primary' : 'btn-outline-primary' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <input class="form-control" type="date" name="date" value="{{ $dateFilter }}">
                </div>
                <div class="col-12 col-md-6 col-lg-2">
                    <input class="form-control" type="text" name="search" value="{{ $search }}" placeholder="{{ __('ui.search_customer') }}">
                </div>
                <div class="col-12 col-md-2 col-lg-1">
                    <button class="btn btn-outline-secondary w-100" type="submit">{{ __('ui.apply') }}</button>
                </div>
                @if ($resultFilter !== 'all')
                    <input type="hidden" name="result" value="{{ $resultFilter }}">
                @endif
            </form>
        </div>

        <div id="callCenterLiveSection">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card p-4 h-100">
                    <h6 class="fw-semibold mb-3">{{ __('ui.call_attempts') }}</h6>
                    <div class="table-responsive">
                        <table id="callCenterTable" class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('ui.call_id') }}</th>
                                    <th>{{ __('ui.customer') }}</th>
                                    <th>{{ __('ui.order') }}</th>
                                    <th>{{ __('ui.agent') }}</th>
                                    <th>{{ __('ui.attempt') }}</th>
                                    <th>{{ __('ui.result') }}</th>
                                    <th>{{ __('ui.duration') }}</th>
                                    <th>{{ __('ui.date') }}</th>
                                    <th>{{ __('ui.action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($callLogs as $log)
                                    @php
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
                                        <td>{{ $log->attempt_number }}</td>
                                        <td>
                                            <span class="badge {{ $resultClasses[$log->result] ?? 'bg-primary' }}">
                                                {{ $resultOptions[$log->result] ?? ucfirst(str_replace('_', ' ', $log->result)) }}
                                            </span>
                                        </td>
                                        <td>{{ sprintf('%02d:%02d', $minutes, $seconds) }}</td>
                                        <td>{{ optional($log->call_time)->format('d M Y h:i A') }}</td>
                                        <td>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#callDetailsModal{{ $log->id }}">
                                                    {{ __('ui.view') }}
                                                </button>
                                                @if ($log->can_confirm_lead)
                                                    <form method="POST" action="{{ route('seller.leads.confirm', $log->order) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button class="btn btn-success btn-sm" type="submit">{{ __('ui.confirm_lead') }}</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                @endforelse
                            </tbody>
                        </table>
                        @if ($callLogs->isEmpty())
                            <div class="text-center text-muted py-4">
                                {{ __('ui.no_call_logs_found') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card p-4 h-100">
                    <h6 class="fw-semibold mb-4">{{ __('ui.agent_performance') }}</h6>
                    @forelse ($agentPerformance as $agent)
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="mb-0">{{ $agent['name'] }}</h6>
                            <small class="text-muted">{{ $agent['total'] }} {{ __('ui.calls') }}</small>
                        </div>
                        <div class="progress mb-2" style="height: 10px;">
                            <div class="progress-bar bg-success" style="width: {{ $agent['rate'] }}%"></div>
                        </div>
                        <small class="d-block text-muted mb-3">{{ $agent['rate'] }}% {{ __('ui.connected_rate') }}</small>
                    @empty
                        <div class="text-muted">{{ __('ui.no_agent_activity_yet') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
        </div>

    @foreach ($callLogs as $log)
        <div class="modal fade" id="callDetailsModal{{ $log->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('ui.call_details') }}</h5>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2"><span class="fw-semibold">{{ __('ui.call_id') }}:</span> #{{ $log->call_code ?? ('C' . str_pad((string) $log->id, 4, '0', STR_PAD_LEFT)) }}</div>
                        <div class="mb-2"><span class="fw-semibold">{{ __('ui.customer') }}:</span> {{ $log->customer?->name ?? '-' }}</div>
                        <div class="mb-2"><span class="fw-semibold">{{ __('ui.phone') }}:</span> {{ $log->customer?->phone ?? '-' }}</div>
                        <div class="mb-2"><span class="fw-semibold">{{ __('ui.order') }}:</span> {{ $log->order?->external_order_id ?? '-' }}</div>
                        @if ($log->order)
                            <div class="mb-2"><span class="fw-semibold">{{ __('ui.order_status') }}:</span> {{ ucfirst(strtolower((string) $log->order->status)) }}</div>
                        @endif
                        <div class="mb-2"><span class="fw-semibold">{{ __('ui.agent') }}:</span> {{ $log->agent_name }}</div>
                        <div class="mb-2"><span class="fw-semibold">{{ __('ui.type') }}:</span> {{ ucfirst($log->call_type) }}</div>
                        <div class="mb-2"><span class="fw-semibold">{{ __('ui.priority') }}:</span> {{ ucfirst($log->priority) }}</div>
                        <div class="mb-2"><span class="fw-semibold">{{ __('ui.result') }}:</span> {{ $resultOptions[$log->result] ?? ucfirst(str_replace('_', ' ', $log->result)) }}</div>
                        <div class="mb-2"><span class="fw-semibold">{{ __('ui.time') }}:</span> {{ optional($log->call_time)->format('d M Y h:i A') }}</div>
                        <div class="mb-0"><span class="fw-semibold">{{ __('ui.notes') }}:</span><br>{{ $log->notes ?: __('ui.no_notes_added') }}</div>
                        @if ($log->can_confirm_lead)
                            <form method="POST" action="{{ route('seller.leads.confirm', $log->order) }}" class="mt-3">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-success btn-sm" type="submit">{{ __('ui.confirm_lead_move_orders') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
</div>

@push('page-scripts')
<script>
  $(function () {
    if ($('#callCenterTable').length) {
      $('#callCenterTable').DataTable({
        aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, 'All']],
        iDisplayLength: 5,
        order: []
      });
    }
  });
</script>
@endpush

@include('spedfly.include.footer')
