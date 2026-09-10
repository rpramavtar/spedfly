@include('spedfly.include.header')

<div
  class="app-body"
  data-orders-feed-url="{{ route('admin.orders.feed', request()->query()) }}"
  data-orders-signature="{{ $ordersFeedSignature ?? '' }}"
>
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <h1 class="mt-4">{{ __('ui.orders') }}</h1>
            <p>{{ __('ui.manage_orders_apply_filters_and_take_quick_actions') }}</p>
          </div>
        </div>

        <div id="ordersAlerts"></div>

        @if (session('success'))
          <div class="alert alert-success js-auto-hide-alert">{{ session('success') }}</div>
        @endif

        <div class="row mb-3">
          <div class="col-md-4">
            <div class="card p-3 mb-2">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h6 class="mb-1">{{ __('ui.total_orders') }}</h6>
                  <h4 class="mb-0" id="totalOrdersCount">{{ number_format($counts['total'] ?? 0) }}</h4>
                </div>
                <i class="bi bi-box fs-2 text-primary"></i>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card p-3 mb-2">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h6 class="mb-1">{{ __('ui.pending') }}</h6>
                  <h4 class="mb-0" id="pendingOrdersCount">{{ number_format($counts['pending'] ?? 0) }}</h4>
                </div>
                <i class="bi bi-hourglass-split fs-2 text-warning"></i>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card p-3 mb-2">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h6 class="mb-1">{{ __('ui.this_week') }}</h6>
                  <h4 class="mb-0" id="thisWeekOrdersCount">{{ number_format($counts['this_week'] ?? 0) }}</h4>
                </div>
                <i class="bi bi-arrow-up-right fs-2 text-success"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-body">
            <form class="row g-2" method="get" action="{{ route('admin.orders') }}">
              <div class="col-md-4">
                <input type="text" class="form-control" name="q" value="{{ $search }}" placeholder="{{ __('ui.search_order_or_customer') }}">
              </div>
              <div class="col-md-3">
                <select class="form-select" name="status">
                  <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>{{ __('ui.all_status') }}</option>
                  @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" {{ $statusFilter === $value ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-3">
                <input type="date" class="form-control" name="from" value="{{ $orderDate }}">
              </div>
              <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">{{ __('ui.filter') }}</button>
              </div>
            </form>
          </div>
        </div>

        <div class="table-responsive">
          <table id="ordersTable" class="table table-striped table-bordered">
            <thead class="table-light">
              <tr>
                <th style="width: 70px;">ID</th>
                <th>{{ __('ui.order_id') }}</th>
                <th>{{ __('ui.customer') }}</th>
                <th>{{ __('ui.status') }}</th>
                <th>{{ __('ui.total') }}</th>
                <th>{{ __('ui.date') }}</th>
                <th style="width:220px;">{{ __('ui.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @php
                $badgeMap = [
                    'lead' => 'bg-warning text-dark',
                    'new' => 'bg-warning text-dark',
                    'processing' => 'bg-info text-dark',
                    'shipped' => 'bg-primary text-light',
                    'delivered' => 'bg-success',
                    'returned' => 'bg-danger',
                ];
              @endphp
              @forelse ($orders as $order)
                @php
                  $status = strtolower($order->status ?? 'new');
                  $badgeClass = $badgeMap[$status] ?? 'bg-secondary text-light';
                  $trackPayload = [
                      'external_order_id' => $order->external_order_id,
                      'customer_id' => $order->customer_id,
                      'amount' => number_format((float) $order->amount, 2, '.', ''),
                      'status' => $status,
                      'ordered_at' => optional($order->ordered_at)->format('Y-m-d'),
                  ];
                  $returnPayload = [
                      'order_id' => $order->id,
                      'external_order_id' => $order->external_order_id,
                      'customer_name' => $order->customer?->name ?? '-',
                      'default_refund_amount' => number_format((float) $order->amount, 2, '.', ''),
                  ];
                @endphp
                <tr>
                  <td data-order="{{ $order->id }}"><span class="badge bg-light text-dark border fw-bold">#{{ $order->id }}</span></td>
                  <td><span class="fw-semibold text-primary">{{ $order->external_order_id }}</span></td>
                  <td>{{ $order->customer?->name ?? '—' }}</td>
                  <td><span class="badge {{ $badgeClass }}">{{ $statusOptions[$status] ?? ucfirst($status) }}</span></td>
                  <td>{{ system_currency_format($order->amount) }}</td>
                  <td>{{ optional($order->ordered_at)->format('Y-m-d') }}</td>
                  <td>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                      <a
                        class="btn btn-sm btn-outline-primary"
                        href="{{ route('admin.orders.details', $order) }}"
                      >
                        {{ __('ui.view') }}
                      </a>
                      <button
                        class="btn btn-sm btn-outline-warning track-order-btn"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#trackOrderModal"
                        data-update-url="{{ route('admin.orders.update', $order) }}"
                        data-order='@json($trackPayload)'
                      >
                        Track
                      </button>
                      <button
                        class="btn btn-sm btn-outline-danger return-order-btn"
                        type="button"
                        @if($status !== 'returned')
                          data-bs-toggle="modal"
                          data-bs-target="#returnOrderModal"
                          data-order='@json($returnPayload)'
                        @else
                          disabled
                        @endif
                      >
                        {{ $status === 'returned' ? __('ui.returned') : __('ui.return') }}
                      </button>
                    </div>
                  </td>
                </tr>
              @empty
                <tr class="empty-row">
                  <td class="text-center" colspan="7">{{ __('ui.no_orders_found') }}</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="trackOrderModal" tabindex="-1" aria-labelledby="trackOrderModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-3 px-4">
        <h4 class="modal-title fw-semibold" id="trackOrderModalLabel">{{ __('ui.track_order') }}</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="trackOrderForm" method="post" action="">
        @csrf
        @method('PUT')
        <div class="modal-body px-4 py-3">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.order_id') }}</label>
              <input type="text" name="external_order_id" class="form-control" placeholder="e.g. #1008" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.customer') }}</label>
              <select name="customer_id" class="form-select" required>
                <option value="">{{ __('ui.select_customer') }}</option>
                @foreach ($customers as $customer)
                  <option value="{{ $customer->id }}">
                    {{ $customer->name }} ({{ $customer->seller?->name ?? '—' }})
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.amount') }}</label>
              <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.status') }}</label>
              <select name="status" class="form-select">
                @foreach ($statusOptions as $value => $label)
                  <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.order_date') }}</label>
              <input type="date" name="ordered_at" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="modal-footer px-4 py-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-primary" type="submit">Save Changes</button>
        </div>
      </form>
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
      <form id="returnOrderForm" action="{{ route('admin.returns.store') }}" method="post">
        @csrf
        <div class="modal-body px-4 py-3">
          <input type="hidden" name="order_id" value="">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Order ID</label>
              <input type="text" name="external_order_id" class="form-control" placeholder="Ex: #1001" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.customer') }}</label>
              <input type="text" name="customer_name" class="form-control" readonly>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">{{ __('ui.reason_for_return') }}</label>
              <select class="form-select" name="return_reason" required>
                <option value="">{{ __('ui.select_reason') }}</option>
                <option>{{ __('ui.defective_product') }}</option>
                <option>{{ __('ui.wrong_item') }}</option>
                <option>{{ __('ui.changed_mind') }}</option>
                <option>{{ __('ui.size_issue') }}</option>
                <option>{{ __('ui.damaged_in_transit') }}</option>
                <option>{{ __('ui.other') }}</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">{{ __('ui.refund_amount') }}</label>
              <input type="number" step="0.01" min="0" name="refund_amount" class="form-control" placeholder="0.00">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">{{ __('ui.notes') }}</label>
              <textarea class="form-control" name="notes" rows="4" placeholder="{{ __('ui.additional_notes') }}"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer px-4 py-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">{{ __('ui.save_return') }}</button>
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

    var hasEmptyRow = $('#ordersTable tbody tr.empty-row').length > 0;

    if (!hasEmptyRow) {
      $('#ordersTable').DataTable({
        "aLengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
        "iDisplayLength": 5,
        order: []
      });
    }

    var $autoHideAlert = $('.js-auto-hide-alert');
    if ($autoHideAlert.length) {
      setTimeout(function () {
        $autoHideAlert.fadeOut(300);
      }, 4000);
    }

    $('.track-order-btn').on('click', function () {
      var order = $(this).data('order') || {};
      var updateUrl = $(this).data('update-url') || '';
      var $form = $('#trackOrderForm');

      $form.attr('action', updateUrl);
      $form.find('[name="external_order_id"]').val(order.external_order_id || '');
      $form.find('[name="customer_id"]').val(order.customer_id || '');
      $form.find('[name="amount"]').val(order.amount || '');
      $form.find('[name="status"]').val(order.status || 'new');
      $form.find('[name="ordered_at"]').val(order.ordered_at || '');
    });

    $('.return-order-btn').on('click', function () {
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
