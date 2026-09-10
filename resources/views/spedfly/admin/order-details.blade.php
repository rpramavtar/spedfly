@include('spedfly.include.header')

<div class="app-body">
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <h1 class="mt-4">{{ __('ui.order_details') }}</h1>
            <p class="mb-0 text-muted">{{ __('ui.review_everything_about_this_order_the_customer_and_the_fulfilment_history') }}</p>
          </div>
          <div>
            <a href="{{ route('admin.orders') }}" class="btn btn-outline-secondary">
              <i class="bi bi-arrow-left me-1"></i>{{ __('ui.back_to_orders') }}
            </a>
          </div>
        </div>

        @php
          $statusClasses = [
              'lead' => 'bg-warning text-dark',
              'new' => 'bg-warning text-dark',
              'processing' => 'bg-info text-dark',
              'shipped' => 'bg-primary text-light',
              'delivered' => 'bg-success',
              'returned' => 'bg-danger',
          ];
          $returnStatusClasses = [
              'pending' => 'bg-warning text-dark',
              'processed' => 'bg-info text-dark',
              'refunded' => 'bg-success',
          ];
          $returnStatusLabels = [
              'pending' => __('ui.pending'),
              'processed' => __('ui.processed'),
              'refunded' => __('ui.refunded'),
          ];
          $orderStatus = strtolower((string) ($order->status ?? 'new'));
          $badgeClass = $statusClasses[$orderStatus] ?? 'bg-secondary text-light';
          $statusLabel = $statusOptions[$orderStatus] ?? ($order->status ? (trans()->has('ui.' . $orderStatus) ? __('ui.' . $orderStatus) : ucfirst($order->status)) : __('ui.pending'));
          $orderPlacedAt = optional($order->ordered_at ?? $order->created_at)->format('M d, Y h:i A');
          $itemsSubtotal = $order->items->sum(fn ($item) => (float) $item->unit_price * (int) $item->quantity);
          $shipping = $order->shipping_amount ?? 0;
          $tax = $order->tax_amount ?? 0;
          $total = (float) ($order->amount ?? 0);
          $subtotal = $total - $shipping - $tax;
          if ($subtotal < 0) {
              $subtotal = $itemsSubtotal;
              $total = $subtotal + $shipping + $tax;
          }
          $shipmentStatus = strtolower((string) ($shipment->status ?? ''));
          $shipmentStatusLabel = $shipment ? (trans()->has('ui.' . $shipmentStatus) ? __('ui.' . $shipmentStatus) : ucfirst(str_replace('_', ' ', $shipmentStatus))) : '-';
          $availableActions = match ($orderStatus) {
              'lead' => [
                  ['label' => __('ui.confirm_lead'), 'status' => 'new', 'class' => 'btn btn-success'],
              ],
              'new', 'processing' => [
                  ['label' => __('ui.mark_as_shipped'), 'status' => 'shipped', 'class' => 'btn btn-warning text-white'],
                  ['label' => __('ui.mark_as_processing'), 'status' => 'processing', 'class' => 'btn btn-outline-primary'],
              ],
              'shipped' => [
                  ['label' => __('ui.mark_as_delivered'), 'status' => 'delivered', 'class' => 'btn btn-success'],
              ],
              'delivered' => [],
              default => [],
          };
        @endphp

        @if (session('success'))
          <div class="alert alert-success js-auto-hide-alert mt-3">{{ session('success') }}</div>
        @endif

        <div class="row mt-4">
          <div class="col-md-8">
            <div class="card mb-3 p-3">
              <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                  <h3 class="mb-1">{{ __('ui.order') }} {{ $order->external_order_id }}</h3>
                  <p class="text-muted mb-1">{{ $orderPlacedAt }}</p>
                  <span class="badge {{ $badgeClass }} py-2 px-3 fs-6">{{ $statusLabel }}</span>
                </div>
                <a href="{{ route('admin.orders') }}" class="btn btn-outline-dark">
                  <i class="bi bi-arrow-left me-1"></i>{{ __('ui.back_to_orders') }}
                </a>
              </div>
            </div>

            <div class="card mb-3">
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <h6 class="text-muted mb-1">{{ __('ui.customer') }}</h6>
                    <p class="mb-0">{{ $order->customer?->name ?? '-' }}</p>
                    <small class="text-muted">{{ $order->customer?->email ?? '-' }} · {{ $order->customer?->phone ?? '-' }}</small>
                  </div>
                  <div class="col-md-6 text-md-end">
                    <h6 class="text-muted mb-1">{{ __('ui.seller') }}</h6>
                    <p class="mb-0">{{ $order->seller?->name ?? __('ui.marketplace') }}</p>
                    <small class="text-muted text-uppercase">{{ $order->payment_type ?? '-' }}</small>
                  </div>
                  <div class="col-md-6">
                    <h6 class="text-muted mb-1">{{ __('ui.order_date') }}</h6>
                    <p class="mb-0">{{ optional($order->ordered_at)->format('M d, Y h:i A') }}</p>
                  </div>
                  <div class="col-md-6 text-md-end">
                    <h6 class="text-muted mb-1">{{ __('ui.amount') }}</h6>
                    <p class="mb-0 h5">{{ system_currency_format($total) }}</p>
                  </div>
                </div>
              </div>
            </div>

            <div class="card mb-3">
              <div class="card-body">
                <h5 class="card-title mb-3">{{ __('ui.order_items') }}</h5>
                @if ($order->items->isEmpty())
                  <p class="text-muted mb-0">{{ __('ui.no_items_recorded_for_this_order') }}</p>
                @else
                  <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                      <thead class="table-light">
                        <tr>
                          <th>{{ __('ui.product_id') }}</th>
                          <th>{{ __('ui.sku') }}</th>
                          <th class="text-end">{{ __('ui.quantity') }}</th>
                          <th class="text-end">{{ __('ui.price') }}</th>
                          <th class="text-end">{{ __('ui.total') }}</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach ($order->items as $item)
                          <tr>
                            <td>{{ $item->product_id }}</td>
                            <td>{{ $item->product?->sku ?? '-' }}</td>
                            <td class="text-end">{{ $item->quantity }}</td>
                            <td class="text-end">{{ system_currency_format($item->unit_price) }}</td>
                            <td class="text-end">{{ system_currency_format($item->total_price) }}</td>
                          </tr>
                        @endforeach
                      </tbody>
                      <tfoot>
                        <tr>
                          <th colspan="4" class="text-end">{{ __('ui.subtotal') }}</th>
                          <th class="text-end">{{ system_currency_format($subtotal) }}</th>
                        </tr>
                        <tr>
                          <th colspan="4" class="text-end">{{ __('ui.shipping') }}</th>
                          <th class="text-end">{{ system_currency_format($shipping) }}</th>
                        </tr>
                        <tr>
                          <th colspan="4" class="text-end">{{ __('ui.tax') }}</th>
                          <th class="text-end">{{ system_currency_format($tax) }}</th>
                        </tr>
                        <tr>
                          <th colspan="4" class="text-end">{{ __('ui.total') }}</th>
                          <th class="text-end">{{ system_currency_format($total) }}</th>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                @endif
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card mb-3">
              <div class="card-body">
                <h5 class="card-title mb-3">{{ __('ui.customer') }}</h5>
                <div class="d-flex align-items-start gap-3 mb-3">
                  <img src="https://randomuser.me/api/portraits/men/68.jpg" width="56" class="rounded-circle" alt="customer">
                  <div>
                    <p class="mb-0">{{ $order->customer?->name ?? '-' }}</p>
                    <small class="text-muted d-block">{{ $order->customer?->email ?? '-' }}</small>
                    <small class="text-muted">{{ $order->customer?->phone ?? '-' }}</small>
                  </div>
                </div>
                <h6 class="text-muted mb-1">{{ __('ui.shipping_address') }}</h6>
                <p class="mb-2">{{ $order->customer?->address ?? '-' }}</p>
                <h6 class="text-muted mb-1">{{ __('ui.billing_address') }}</h6>
                <p class="mb-0">{{ $order->customer?->address ?? '-' }}</p>
              </div>
            </div>

            <div class="card mb-3">
              <div class="card-body">
                <h5 class="card-title mb-3">{{ __('ui.order_activity') }}</h5>
                <ul class="list-unstyled mb-0">
                  <li class="d-flex justify-content-between mb-2">
                    <span>{{ __('ui.order_placed') }}</span>
                    <small>{{ $orderPlacedAt }}</small>
                  </li>
                  <li class="d-flex justify-content-between mb-2">
                    <span>{{ __('ui.payment_confirmed') }}</span>
                    <small>{{ optional($order->created_at)->format('M d, Y h:i A') }}</small>
                  </li>
                  <li class="d-flex justify-content-between">
                    <span>{{ __('ui.last_status_update') }}</span>
                    <small>{{ optional($order->updated_at)->format('M d, Y h:i A') }}</small>
                  </li>
                </ul>
              </div>
            </div>

            <div class="card mb-3">
              <div class="card-body">
                <h5 class="card-title mb-3">{{ __('ui.payment') }}</h5>
                <p class="mb-1 text-muted">{{ __('ui.method') }}</p>
                <p class="mb-2">{{ $order->payment_type ?? '-' }}</p>
                <p class="mb-1 text-muted">{{ __('ui.status') }}</p>
                <span class="badge bg-success">{{ __('ui.paid') }}</span>
              </div>
            </div>

            <div class="card">
              <div class="card-body">
                <h5 class="card-title mb-3">{{ __('ui.shipment') }}</h5>
                <p class="mb-1 text-muted">{{ __('ui.courier') }}</p>
                <p class="mb-2">{{ $shipment?->courier_name ?? '-' }}</p>
                <p class="mb-1 text-muted">{{ __('ui.tracking_id') }}</p>
                <p class="mb-2">{{ $shipment?->shipment_code ?? '-' }}</p>
                <p class="mb-1 text-muted">{{ __('ui.eta') }}</p>
                <p class="mb-2">{{ optional($shipment?->shipment_date)->format('M d, Y') ?? '-' }}</p>
                <p class="mb-1 text-muted">{{ __('ui.shipment_status') }}</p>
                <p class="mb-0">{{ $shipmentStatusLabel }}</p>
              </div>
            </div>

            @if ($order->returnRecord)
              @php
                $returnStatus = strtolower((string) $order->returnRecord->status);
              @endphp
              <div class="card mt-3">
                <div class="card-body">
                  <h5 class="card-title mb-3">{{ __('ui.return') }}</h5>
                  <p class="mb-1 text-muted">{{ __('ui.return_id') }}</p>
                  <p class="mb-2">{{ $order->returnRecord->return_code }}</p>
                  <p class="mb-1 text-muted">{{ __('ui.status') }}</p>
                  <p class="mb-2">
                    <span class="badge {{ $returnStatusClasses[$returnStatus] ?? 'bg-secondary' }}">
                      {{ $returnStatusLabels[$returnStatus] ?? ucfirst($returnStatus) }}
                    </span>
                  </p>
                  <p class="mb-1 text-muted">{{ __('ui.reason') }}</p>
                  <p class="mb-2">{{ $order->returnRecord->return_reason }}</p>
                  <p class="mb-1 text-muted">{{ __('ui.refund') }}</p>
                  <p class="mb-0">{{ system_currency_format($order->returnRecord->refund_amount) }}</p>
                </div>
              </div>
            @endif
          </div>
        </div>

        <div class="card mt-4" style="background:#fdf3d4;">
          <div class="card-body d-flex justify-content-end gap-2 flex-wrap">
            @forelse ($availableActions as $action)
              <form method="post" action="{{ route('admin.orders.status', $order) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $action['status'] }}">
                <button class="{{ $action['class'] }}" type="submit">{{ $action['label'] }}</button>
              </form>
            @empty
              <span class="text-muted">{{ __('ui.no_further_actions_available_for_this_order') }}</span>
            @endforelse
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@push('page-scripts')
<script>
  $(function () {
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
