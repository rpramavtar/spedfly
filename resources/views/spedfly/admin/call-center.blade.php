@include('spedfly.include.header')

@php
    $formatDateTime = fn (?string $value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : '-';
    $attemptSlot = function ($attempt) {
        if (! $attempt) {
            return '<span class="text-muted">-</span>';
        }

        return '<div>' . e($attempt['label']) . '<br><small class="text-muted">' . e($attempt['time']) . '</small></div>';
    };
@endphp

<style>
    #newCallModal .modal-dialog {
        width: min(980px, calc(100vw - 1rem));
        max-width: none;
        margin: 0.5rem auto;
        height: calc(100dvh - 1rem);
    }

    #newCallModal .modal-content {
        height: 100%;
        max-height: none;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    #newCallModal .modal-body {
        min-height: 0;
        max-height: calc(100dvh - 240px);
        overflow-y: auto;
        flex: 1 1 auto;
    }

    #newCallModal .modal-footer {
        background: rgba(255, 255, 255, 0.98);
        padding: 0.85rem 1rem 0.95rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        justify-content: flex-end;
        border-top: 1px solid rgba(148, 163, 184, 0.18);
        flex-shrink: 0;
    }

    #newCallModal .modal-header {
        flex-shrink: 0;
    }

    #newCallModal .modal-footer .btn {
        min-width: 0;
        padding: 0.7rem 1.15rem;
        border-radius: 0.75rem;
        font-weight: 600;
    }

    #newCallModal .save-call-btn {
        background: linear-gradient(135deg, #2f8f45, #4caf50);
        border: 0;
        box-shadow: 0 10px 24px rgba(76, 175, 80, 0.22);
    }

    #newCallModal .save-call-btn:hover {
        background: linear-gradient(135deg, #2a7d3c, #429347);
        transform: translateY(-1px);
    }

    #newCallModal .cancel-call-btn {
        background: #fff;
        border: 1px solid #d0d7de;
        color: #334155;
    }

    #newCallModal .cancel-call-btn:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }

    @media (max-width: 767.98px) {
        #newCallModal .modal-dialog {
            width: calc(100vw - 0.75rem);
            margin: 0.375rem auto;
            height: calc(100dvh - 0.75rem);
        }

        #newCallModal .modal-body {
            padding: 1rem;
            max-height: calc(100dvh - 210px);
        }

        #newCallModal .modal-footer .btn {
            width: 100%;
        }
    }
</style>

<div class="app-body">
    <div class="container-fluid">
        <div class="row mb-3 align-items-center">
            <div class="col-12 col-lg-8">
                <h1 class="mt-4 mb-1">{{ __('ui.call_center_activity') }}</h1>
                <p class="text-muted mb-0">{{ __('ui.monitor_real_call_attempts_and_outcomes') }}</p>
            </div>
            <div class="col-12 col-lg-4 text-lg-end mt-3 mt-lg-0">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCallModal">
                    <i class="bi bi-phone me-1"></i> {{ __('ui.new_call') }}
                </button>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('ui.close') }}"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <div class="fw-semibold mb-1">{{ __('ui.please_fix_the_following_errors') }}</div>
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
                    <h6>{{ __('ui.total_calls') }}</h6>
                    <h3 class="fw-bold mb-1">{{ number_format($summary['total_calls']) }}</h3>
                    <small class="text-muted">{{ number_format($summary['orders']) }} {{ __('ui.orders_touched') }}</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 h-100">
                    <h6>{{ __('ui.connected_rate') }}</h6>
                    <h3 class="fw-bold mb-1">{{ $summary['connected_rate'] }}%</h3>
                    <small class="text-muted">{{ __('ui.connected_calls_in_the_selected_range') }}</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 h-100">
                    <h6>{{ __('ui.missed_calls') }}</h6>
                    <h3 class="fw-bold mb-1">{{ number_format($summary['missed_calls']) }}</h3>
                    <small class="text-muted">{{ __('ui.no_answer_failed_busy') }}</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 h-100">
                    <h6>{{ __('ui.sellers') }}</h6>
                    <h3 class="fw-bold mb-1">{{ number_format($summary['sellers']) }}</h3>
                    <small class="text-muted">{{ __('ui.unique_sellers_with_call_logs') }}</small>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2" method="GET" action="{{ route('admin.call-center') }}">
                    <div class="col-md-3">
                        <input type="date" class="form-control" name="from" value="{{ $fromDate }}">
                    </div>
                    <div class="col-md-3">
                        <input type="date" class="form-control" name="to" value="{{ $toDate }}">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="result">
                            <option value="all" @selected($resultFilter === 'all')>{{ __('ui.all_outcomes') }}</option>
                            @foreach ($resultOptions as $value => $label)
                                <option value="{{ $value }}" @selected($resultFilter === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="text" class="form-control" name="search" value="{{ $search }}" placeholder="{{ __('ui.search_order_customer') }}">
                    </div>
                    <div class="col-md-1 text-end">
                        <button class="btn btn-primary w-100" type="submit">{{ __('ui.filter') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="newCallModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content border-0 rounded-4 shadow-lg">
                    <form method="POST" action="{{ route('admin.call-center.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title fw-semibold">
                                <i class="bi bi-telephone-plus me-2 text-primary"></i>
                                {{ __('ui.log_new_call') }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body pt-4 pb-2">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ __('ui.seller') }}</label>
                                <select class="form-select" name="seller_id" required>
                                    <option value="">{{ __('ui.select_seller') }}</option>
                                    @foreach ($sellerOptions as $seller)
                                        <option value="{{ $seller->id }}" @selected(old('seller_id') == $seller->id)>
                                            {{ $seller->name ?: __('ui.seller') . ' #' . $seller->id }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">{{ __('ui.customer') }}</label>
                                        <select class="form-select" name="customer_id" required>
                                            <option value="">{{ __('ui.select_customer') }}</option>
                                            @foreach ($customerOptions as $customer)
                                                <option value="{{ $customer->id }}" data-seller="{{ $customer->seller_id }}" @selected(old('customer_id') == $customer->id)>
                                                    {{ $customer->name ?: __('ui.customer') . ' #' . $customer->id }}{{ $customer->phone ? ' - ' . $customer->phone : '' }}{{ $customer->seller?->name ? ' (' . $customer->seller->name . ')' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">{{ __('ui.related_order') }}</label>
                                        <select class="form-select" name="order_id">
                                            <option value="">{{ __('ui.no_order_selected') }}</option>
                                            @foreach ($orderOptions as $order)
                                                <option value="{{ $order->id }}" data-seller="{{ $order->seller_id }}" @selected(old('order_id') == $order->id)>
                                                    {{ $order->external_order_id }}{{ $order->customer?->name ? ' - ' . $order->customer->name : '' }}{{ $order->seller?->name ? ' (' . $order->seller->name . ')' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">{{ __('ui.assign_agent') }}</label>
                                        <input type="text" class="form-control" name="agent_name" value="{{ old('agent_name', auth()->user()->name) }}" list="agentList" required>
                                        <datalist id="agentList">
                                            @foreach ($agentOptions as $agentName)
                                                <option value="{{ $agentName }}"></option>
                                            @endforeach
                                        </datalist>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">{{ __('ui.call_time') }}</label>
                                        <input type="datetime-local" class="form-control" name="call_time" value="{{ old('call_time', now()->format('Y-m-d\TH:i')) }}">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">{{ __('ui.call_type') }}</label>
                                        <select class="form-select" name="call_type" required>
                                            <option value="outgoing" @selected(old('call_type', 'outgoing') === 'outgoing')>{{ __('ui.outgoing') }}</option>
                                            <option value="incoming" @selected(old('call_type') === 'incoming')>{{ __('ui.incoming') }}</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">{{ __('ui.priority') }}</label>
                                        <select class="form-select" name="priority" required>
                                            <option value="normal" @selected(old('priority', 'normal') === 'normal')>{{ __('ui.normal') }}</option>
                                            <option value="high" @selected(old('priority') === 'high')>{{ __('ui.high') }}</option>
                                            <option value="urgent" @selected(old('priority') === 'urgent')>{{ __('ui.urgent') }}</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">{{ __('ui.result') }}</label>
                                        <select class="form-select" name="result" required>
                                            @foreach ($resultOptions as $value => $label)
                                                <option value="{{ $value }}" @selected(old('result', 'connected') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">{{ __('ui.duration') }}</label>
                                        <input type="text" class="form-control" name="duration" value="{{ old('duration', '00:00') }}" placeholder="MM:SS">
                                    </div>
                                </div>
                            </div>

                            <div class="mt-2">
                                <label class="form-label fw-semibold">{{ __('ui.call_notes') }}</label>
                                <textarea class="form-control" rows="4" name="notes" placeholder="{{ __('ui.write_purpose_or_summary_of_the_call') }}">{{ old('notes') }}</textarea>
                            </div>
                        </div>

                        <div class="modal-footer">
                                <button type="button" class="btn cancel-call-btn" data-bs-dismiss="modal">
                                    <i class="bi bi-x-lg me-2"></i>{{ __('ui.cancel') }}
                                </button>
                                <button type="submit" class="btn save-call-btn text-white">
                                    <i class="bi bi-save me-2"></i>{{ __('ui.save_call') }}
                                </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table id="CallsTable" class="table table-striped table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('ui.order_id') }}</th>
                        <th>{{ __('ui.seller') }}</th>
                        <th>{{ __('ui.customer') }}</th>
                        <th>{{ __('ui.phone') }}</th>
                        <th>{{ __('ui.attempt_1') }}</th>
                        <th>{{ __('ui.attempt_2') }}</th>
                        <th>{{ __('ui.attempt_3') }}</th>
                        <th>{{ __('ui.final_status') }}</th>
                        <th>{{ __('ui.comments') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($callRows as $row)
                        <tr data-order="{{ $row['order_id'] }}">
                            <td>{{ $row['order_id'] }}</td>
                            <td>{{ $row['seller_name'] }}</td>
                            <td>{{ $row['customer_name'] }}</td>
                            <td>{{ $row['phone'] }}</td>
                            <td>{!! $attemptSlot($row['attempts'][0] ?? null) !!}</td>
                            <td>{!! $attemptSlot($row['attempts'][1] ?? null) !!}</td>
                            <td>{!! $attemptSlot($row['attempts'][2] ?? null) !!}</td>
                            <td><span class="badge {{ $row['final_class'] }}">{{ $row['final_status'] }}</span></td>
                            <td>
                                @if ($row['notes_full'] !== '')
                                    <button
                                        class="btn btn-sm btn-outline-primary view-notes"
                                        type="button"
                                        data-bs-toggle="modal"
                                        data-bs-target="#callNotesModal"
                                        data-order="{{ $row['order_id'] }}"
                                        data-customer="{{ $row['customer_name'] }}"
                                        data-seller="{{ $row['seller_name'] }}"
                                        data-notes="{{ $row['notes_full'] }}"
                                    >
                                        {{ __('ui.view') }}
                                    </button>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="empty-row">
                            <td colspan="9" class="text-center text-muted py-4">{{ __('ui.no_call_logs_found_for_the_selected_filters') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="callNotesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('ui.call_notes') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2"><span class="fw-semibold">{{ __('ui.order_id') }}:</span> <span id="callNotesOrder">-</span></div>
                <div class="mb-2"><span class="fw-semibold">{{ __('ui.seller') }}:</span> <span id="callNotesSeller">-</span></div>
                <div class="mb-2"><span class="fw-semibold">{{ __('ui.customer') }}:</span> <span id="callNotesCustomer">-</span></div>
                <div class="mb-2"><span class="fw-semibold">{{ __('ui.notes') }}:</span></div>
                <div id="callNotesBody" class="border rounded p-3 bg-light">-</div>
            </div>
        </div>
    </div>
</div>

@push('page-scripts')
<script>
  $(document).ready(function() {
    function filterCallCenterRecipients() {
      var sellerId = $('select[name="seller_id"]').val();

      $('select[name="customer_id"] option[data-seller], select[name="order_id"] option[data-seller]').each(function () {
        var $option = $(this);
        var matchesSeller = !sellerId || $option.data('seller').toString() === sellerId.toString();
        $option.prop('hidden', !matchesSeller);
      });

      var $customerSelect = $('select[name="customer_id"]');
      var $orderSelect = $('select[name="order_id"]');

      if ($customerSelect.find('option:selected').prop('hidden')) {
        $customerSelect.val('');
      }

      if ($orderSelect.find('option:selected').prop('hidden')) {
        $orderSelect.val('');
      }
    }

    $(document).on('change', 'select[name="seller_id"]', filterCallCenterRecipients);
    filterCallCenterRecipients();

    var hasEmptyRow = $('#CallsTable tbody tr.empty-row').length > 0;

    if (!hasEmptyRow) {
      $('#CallsTable').DataTable({
        aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, @json(__('ui.all'))]],
        iDisplayLength: 5,
        order: []
      });
    }

    @if ($errors->any())
      filterCallCenterRecipients();
      new bootstrap.Modal(document.getElementById('newCallModal')).show();
    @endif

    $(document).on('click', '.view-notes', function () {
      var $btn = $(this);
      $('#callNotesOrder').text($btn.data('order') || '-');
      $('#callNotesSeller').text($btn.data('seller') || '-');
      $('#callNotesCustomer').text($btn.data('customer') || '-');
      $('#callNotesBody').text($btn.data('notes') || '-');
    });
  });
</script>
@endpush

@include('spedfly.include.footer')
