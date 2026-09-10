@include('spedfly.include.header')

@php
    $payments = $payments ?? collect();
    $stats = $stats ?? ['total_payments' => 0, 'refunds' => 0, 'pending' => 0, 'completed' => 0];
    $filters = $filters ?? ['method' => 'all', 'tx' => '', 'status' => 'all'];
    $methodOptions = $methodOptions ?? [];
@endphp

<div class="app-body">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h1 class="mt-4">{{ __('ui.payments') }}</h1>
                        <p>{{ __('ui.track_payment_status_methods_and_reconcile_transactions') }}</p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="card p-3 mb-2">
                            <h6>{{ __('ui.total_payments') }}</h6>
                            <h4>{{ system_currency_format($stats['total_payments']) }}</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3 mb-2">
                            <h6>{{ __('ui.refunds') }}</h6>
                            <h4>{{ system_currency_format($stats['refunds']) }}</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3 mb-2">
                            <h6>{{ __('ui.pending') }}</h6>
                            <h4>{{ number_format((int) $stats['pending']) }}</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3 mb-2">
                            <h6>{{ __('ui.completed') }}</h6>
                            <h4>{{ number_format((int) $stats['completed']) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <form class="row g-2" method="get" action="{{ route('admin.payments') }}">
                            <div class="col-md-3">
                                <select class="form-select" name="method">
                                    <option value="all" @selected(($filters['method'] ?? 'all') === 'all')>{{ __('ui.all_methods') }}</option>
                                    @foreach ($methodOptions as $key => $label)
                                        <option value="{{ $key }}" @selected(($filters['method'] ?? 'all') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="status">
                                    <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>{{ __('ui.all_statuses') }}</option>
                                    <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>{{ __('ui.completed') }}</option>
                                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>{{ __('ui.pending') }}</option>
                                    <option value="refunded" @selected(($filters['status'] ?? '') === 'refunded')>{{ __('ui.refunded') }}</option>
                                    <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>{{ __('ui.failed') }}</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="tx" value="{{ $filters['tx'] ?? '' }}" placeholder="{{ __('ui.search_payment_transaction_order_id_customer') }}">
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary w-100" type="submit">{{ __('ui.search') }}</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="paymentsTable" class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('ui.payment_id') }}</th>
                                <th>{{ __('ui.order_id') }}</th>
                                <th>{{ __('ui.method') }}</th>
                                <th>{{ __('ui.amount') }}</th>
                                <th>{{ __('ui.status') }}</th>
                                <th>{{ __('ui.date') }}</th>
                                <th class="text-end">{{ __('ui.action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                <tr
                                    class="payment-row"
                                    data-payment-id="{{ $payment['payment_id'] }}"
                                    data-order-id="{{ $payment['order_id'] }}"
                                    data-transaction-id="{{ $payment['transaction_id'] }}"
                                    data-method="{{ $payment['method'] }}"
                                    data-amount="{{ number_format((float) $payment['amount'], 2, '.', '') }}"
                                    data-status="{{ $payment['status'] }}"
                                    data-status-class="{{ $payment['status_class'] }}"
                                    data-date="{{ $payment['date'] }}"
                                    data-customer-name="{{ $payment['customer_name'] }}"
                                    data-seller-name="{{ $payment['seller_name'] }}"
                                    data-refund-amount="{{ number_format((float) $payment['refund_amount'], 2, '.', '') }}">
                                    <td>{{ $payment['payment_id'] }}</td>
                                    <td>
                                        {{ $payment['order_id'] }}
                                        <div class="small text-muted">{{ $payment['customer_name'] }}</div>
                                    </td>
                                    <td>{{ $payment['method'] }}</td>
                                    <td>{{ system_currency_format($payment['amount']) }}</td>
                                    <td><span class="badge bg-{{ $payment['status_class'] }}">{{ $payment['status'] }}</span></td>
                                    <td>{{ $payment['date'] }}</td>
                                    <td class="text-end">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary payment-view-btn"
                                                data-payment-id="{{ $payment['payment_id'] }}"
                                                data-order-id="{{ $payment['order_id'] }}"
                                                data-transaction-id="{{ $payment['transaction_id'] }}"
                                                data-method="{{ $payment['method'] }}"
                                                data-amount="{{ number_format((float) $payment['amount'], 2, '.', '') }}"
                                                data-status="{{ $payment['status'] }}"
                                                data-status-class="{{ $payment['status_class'] }}"
                                                data-date="{{ $payment['date'] }}"
                                                data-customer-name="{{ $payment['customer_name'] }}"
                                                data-seller-name="{{ $payment['seller_name'] }}"
                                                data-refund-amount="{{ number_format((float) $payment['refund_amount'], 2, '.', '') }}">
                                            {{ __('ui.view') }}
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr class="empty-row">
                                    <td colspan="7" class="text-center text-muted py-4">{{ __('ui.no_payment_records_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="paymentDetailsModal" tabindex="-1" aria-labelledby="paymentDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="paymentDetailsModalLabel">{{ __('ui.payment_details') }}</h5>
                    <small class="text-muted">{{ __('ui.review_payment_and_refund_information') }}</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">{{ __('ui.payment_id') }}</small>
                            <div class="fw-semibold" id="detailPaymentId">-</div>
                            <small class="text-muted d-block mt-3">{{ __('ui.transaction_id') }}</small>
                            <div id="detailTransactionId">-</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">{{ __('ui.order_id') }}</small>
                            <div class="fw-semibold" id="detailOrderId">-</div>
                            <small class="text-muted d-block mt-3">{{ __('ui.customer') }}</small>
                            <div id="detailCustomerName">-</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">{{ __('ui.seller') }}</small>
                            <div class="fw-semibold" id="detailSellerName">-</div>
                            <small class="text-muted d-block mt-3">{{ __('ui.date') }}</small>
                            <div id="detailDate">-</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">{{ __('ui.method') }}</small>
                            <div class="fw-semibold" id="detailMethod">-</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">{{ __('ui.amount') }}</small>
                            <div class="fw-semibold" id="detailAmount">-</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">{{ __('ui.status') }}</small>
                            <span class="badge mt-1" id="detailStatusBadge">-</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded-3 p-3">
                            <small class="text-muted d-block">{{ __('ui.refund_amount') }}</small>
                            <div class="fw-semibold" id="detailRefundAmount">-</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.close') }}</button>
            </div>
        </div>
    </div>
</div>

@push('page-scripts')
<script>
    $(function () {
        var hasEmptyRow = $('#paymentsTable tbody tr.empty-row').length > 0;
        @php
            $statusLabels = [
                'completed' => __('ui.completed'),
                'pending' => __('ui.pending'),
                'refunded' => __('ui.refunded'),
                'failed' => __('ui.failed'),
            ];
        @endphp
        var statusLabels = @json($statusLabels);

        if (!hasEmptyRow) {
            $('#paymentsTable').DataTable({
                aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, @json(__('ui.all'))]],
                iDisplayLength: 5
            });
        }

        function fillPaymentModal($row) {
            $('#detailPaymentId').text($row.data('payment-id') || '-');
            $('#detailOrderId').text($row.data('order-id') || '-');
            $('#detailTransactionId').text($row.data('transaction-id') || '-');
            $('#detailMethod').text($row.data('method') || '-');
            $('#detailAmount').text('$' + parseFloat($row.data('amount') || 0).toFixed(2));
            $('#detailDate').text($row.data('date') || '-');
            $('#detailCustomerName').text($row.data('customer-name') || '-');
            $('#detailSellerName').text($row.data('seller-name') || '-');
            $('#detailRefundAmount').text('$' + parseFloat($row.data('refund-amount') || 0).toFixed(2));

            var status = $row.data('status') || '-';
            var statusClass = $row.data('status-class') || 'secondary';
            $('#detailStatusBadge')
                .removeClass('bg-success bg-warning bg-danger bg-secondary bg-info')
                .addClass('bg-' + statusClass)
                .text(statusLabels[String(status).toLowerCase()] || status);
        }

        $('#paymentsTable').on('click', '.payment-view-btn', function (event) {
            event.stopPropagation();
            fillPaymentModal($(this));

            var modalEl = document.getElementById('paymentDetailsModal');
            if (modalEl && window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        });
    });
</script>
@endpush

@include('spedfly.include.footer')
