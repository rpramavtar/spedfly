@include('spedfly.include.header')

<div
    class="app-body"
    data-orders-feed-url="{{ route('seller.orders.feed') }}"
    data-orders-signature="{{ $ordersFeedSignature ?? '' }}"
>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-card-checklist me-2 text-primary"></i>{{ __('ui.orders') }}</h3>
                <small class="text-muted">{{ __('ui.orders_created_from_csv_imports') }}</small>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <form method="post" action="{{ route('seller.orders.sync-shopify') }}" class="d-inline">
                    @csrf
                    <button class="btn btn-outline-primary" type="submit">
                        <i class="bi bi-arrow-repeat me-1"></i> {{ __('ui.sync_shopify_orders') }}
                    </button>
                </form>
                <a href="{{ route('seller.leads') }}" class="btn btn-outline-primary"><i class="bi bi-person-lines-fill me-1"></i> {{ __('ui.cod_leads') }}</a>
                <button class="btn btn-success" type="button"><i class="bi bi-download me-1"></i> {{ __('ui.export_csv') }}</button>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success js-auto-hide-alert">{{ session('success') }}</div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning js-auto-hide-alert">{{ session('warning') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger js-auto-hide-alert">{{ session('error') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <h4 class="card-title mb-0">{{ __('ui.order_list') }}</h4>
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table id="ordersTable" class="table table-bordered">
                        <thead>
                            <tr>
                                <th style="width: 70px;">ID</th>
                                <th>{{ __('ui.order') }}</th>
                                <th>{{ __('ui.date') }}</th>
                                <th>{{ __('ui.customer') }}</th>
                                <th>{{ __('ui.status') }}</th>
                                <th>{{ __('ui.payment_type') }}</th>
                                <th>{{ __('ui.amount') }}</th>
                                <th>{{ __('ui.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                @php
                                    $status = strtolower((string) $order->status);
                                    $statusClass = match ($status) {
                                        'lead' => 'bg-warning text-dark',
                                        'delivered' => 'bg-success',
                                        'shipped' => 'bg-warning text-dark',
                                        'returned', 'rejected' => 'bg-danger',
                                        default => 'bg-primary',
                                    };
                                    $returnPayload = [
                                        'order_id' => $order->id,
                                        'external_order_id' => $order->external_order_id,
                                        'customer_name' => $order->customer?->name ?? '-',
                                        'default_refund_amount' => number_format((float) $order->amount, 2, '.', ''),
                                    ];
                                @endphp
                                <tr>
                                    <td><span class="badge bg-light text-dark border fw-bold">#{{ $order->id }}</span></td>
                                    <td><span class="fw-semibold text-primary">{{ $order->external_order_id }}</span></td>
                                    <td>{{ optional($order->ordered_at)->format('d M Y') ?? $order->created_at->format('d M Y') }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $order->customer?->name ?? '-' }}</div>
                                        <small class="text-muted">{{ $order->customer?->phone ?? '-' }}</small>
                                    </td>
                                    <td><span class="badge {{ $statusClass }}">{{ ucfirst($status) }}</span></td>
                                    <td>{{ strtoupper((string) ($order->payment_type ?? 'COD')) }}</td>
                                    <td>{{ system_currency_format($order->amount) }}</td>
                                    <td>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <a href="{{ route('seller.order-details', $order) }}" class="btn btn-outline-primary btn-sm" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                         <!--   <button
                                                class="btn btn-outline-danger btn-sm return-order-btn"
                                                type="button"
                                                @if($status !== 'returned')
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#returnOrderModal"
                                                    data-order='@json($returnPayload)'
                                                @else
                                                    disabled
                                                @endif
                                            >
                                                {{ $status === 'returned' ? 'Returned' : 'Return' }}
                                            </button>  -->
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="empty-row">
                                    <td colspan="8" class="text-center text-muted py-4">No orders found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="returnOrderModal" tabindex="-1" aria-labelledby="returnOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-3 px-4">
                <h4 class="modal-title fw-semibold" id="returnOrderModalLabel">Add New Return</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="returnOrderForm" action="{{ route('seller.returns.store') }}" method="post">
                @csrf
                <div class="modal-body px-4 py-3">
                    <input type="hidden" name="order_id" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Order ID</label>
                            <input type="text" name="external_order_id" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer Name</label>
                            <input type="text" name="customer_name" class="form-control" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Reason for Return</label>
                            <select class="form-select" name="return_reason" required>
                                <option value="">Select Reason</option>
                                <option>Defective Product</option>
                                <option>Wrong Item</option>
                                <option>Changed Mind</option>
                                <option>Size Issue</option>
                                <option>Damaged In Transit</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Refund Amount</label>
                            <input type="number" step="0.01" min="0" name="refund_amount" class="form-control" placeholder="0.00">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" name="notes" rows="4" placeholder="Additional notes"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer px-4 py-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Return</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('page-scripts')
<script>
  $(function () {
    var $appBody = $('.app-body');
    var feedUrl = $appBody.data('orders-feed-url') || '';
    var currentSignature = String($appBody.data('orders-signature') || '');

    if ($('#ordersTable').length && !$('#ordersTable tbody tr.empty-row').length) {
      $('#ordersTable').DataTable({
        aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, 'All']],
        iDisplayLength: 5
      });
    }

    $(document).on('click', '.return-order-btn', function () {
      var order = $(this).data('order') || {};
      var $form = $('#returnOrderForm');

      $form.find('[name="order_id"]').val(order.order_id || '');
      $form.find('[name="external_order_id"]').val(order.external_order_id || '');
      $form.find('[name="customer_name"]').val(order.customer_name || '');
      $form.find('[name="return_reason"]').val('');
      $form.find('[name="refund_amount"]').val(order.default_refund_amount || '');
      $form.find('[name="notes"]').val('');
    });

    if (feedUrl) {
      setInterval(function () {
        $.getJSON(feedUrl)
          .done(function (response) {
            if (response && response.signature && response.signature !== currentSignature) {
              window.location.reload();
            }
          });
      }, 15000);
    }
  });
</script>
@endpush

@include('spedfly.include.footer')
