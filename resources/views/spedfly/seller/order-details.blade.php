@include('spedfly.include.header')

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
    $statusLabel = __('ui.' . $orderStatus);
    $orderPlacedAt = optional($order->ordered_at ?? $order->created_at)->format('d M Y h:i A');
    $itemsSubtotal = $order->items->sum(fn ($item) => (float) $item->total_price);
    $shippingAmount = 0;
    $taxAmount = 0;
    $total = (float) ($order->amount ?? 0);
    $subtotal = $total - $shippingAmount - $taxAmount;
    if ($subtotal < 0) {
        $subtotal = $itemsSubtotal;
        $total = $subtotal + $shippingAmount + $taxAmount;
    }
@endphp

<div class="app-body">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">{{ __('ui.order') }} {{ str_starts_with((string)$order->external_order_id, '#') ? $order->external_order_id : ('#' . $order->external_order_id) }}</h3>
                <small class="text-muted">{{ __('ui.placed_on', ['date' => $orderPlacedAt ?: '-']) }}</small><br>
                <span class="badge {{ $badgeClass }} status-badge">{{ $statusLabel }}</span>
            </div>
            <a href="{{ $orderStatus === 'lead' ? route('seller.leads') : route('seller.orders') }}" class="btn btn-primary"><i class="bi bi-arrow-left"></i> {{ __('ui.back') }}</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success js-auto-hide-alert">{{ session('success') }}</div>
        @endif

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card card-elegant p-4 mb-4">
                    <h6 class="fw-semibold mb-3">{{ __('ui.customer') }}</h6>
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle bg-light text-uppercase fw-bold d-flex align-items-center justify-content-center me-3" style="width:56px;height:56px;">
                            {{ strtoupper(substr($order->customer?->name ?? 'U', 0, 1)) }}
                        </div>
                        <div>
                            <div class="fw-semibold">{{ $order->customer?->name ?? '-' }}</div>
                            <small class="text-muted d-block">{{ $order->customer?->email ?? '-' }}</small>
                            <small class="text-muted">{{ $order->customer?->phone ?? '-' }}</small>
                        </div>
                    </div>
                    <hr>
                    <h6 class="fw-semibold">{{ __('ui.shipping_address') }}</h6>
                    <p class="small text-muted">{{ $order->customer?->address ?? '-' }}</p>
                    <h6 class="fw-semibold mt-3">{{ __('ui.billing_address') }}</h6>
                    <p class="small text-muted mb-0">{{ $order->customer?->address ?? '-' }}</p>
                </div>

                <div class="card card-elegant p-4">
                    <h6 class="fw-semibold mb-4">{{ __('ui.order_activity') }}</h6>
                    <div class="timeline-item">
                        <strong>{{ __('ui.order_placed') }}</strong>
                        <div class="small text-muted">{{ optional($order->created_at)->format('d M Y h:i A') ?? '-' }}</div>
                    </div>
                    <div class="timeline-item">
                        <strong>{{ __('ui.status_updated') }}</strong>
                        <div class="small text-muted">{{ optional($order->updated_at)->format('d M Y h:i A') ?? '-' }}</div>
                    </div>
                    @if ($shipment)
                        <div class="timeline-item">
                            <strong>{{ __('ui.shipment') }} {{ __('ui.' . $shipment->status) }}</strong>
                            <div class="small text-muted">{{ optional($shipment->updated_at)->format('d M Y h:i A') ?? '-' }}</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card card-elegant p-4 mb-4">
                    <h6 class="fw-semibold mb-3">{{ __('ui.order_items') }}</h6>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('ui.product') }}</th>
                                    <th>{{ __('ui.sku') }}</th>
                                    <th>{{ __('ui.qty') }}</th>
                                    <th>{{ __('ui.price') }}</th>
                                    <th>{{ __('ui.total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($order->items as $item)
                                    <tr>
                                        <td>{{ $item->product?->name ?? ( __('ui.product') . ' #' . $item->product_id) }}</td>
                                        <td>{{ $item->product?->sku ?? '-' }}</td>
                                        <td>{{ $item->quantity }}</td>
                                        <td>{{ system_currency_format($item->unit_price) }}</td>
                                        <td class="fw-semibold">{{ system_currency_format($item->total_price) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">{{ __('ui.no_items_recorded_for_this_order') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <div class="soft-box mt-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>{{ __('ui.subtotal') }}</span> <span>{{ system_currency_format($subtotal) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>{{ __('ui.shipping') }}</span> <span>{{ system_currency_format($shippingAmount) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>{{ __('ui.tax') }}</span> <span>{{ system_currency_format($taxAmount) }}</span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold border-top pt-2">
                                <span>{{ __('ui.total') }}</span> <span>{{ system_currency_format($total) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card card-elegant p-4">
                            <h6 class="fw-semibold mb-3">{{ __('ui.payment') }}</h6>
                            <p class="small text-muted mb-0">
                                {{ __('ui.method') }}: {{ strtoupper((string) ($order->payment_type ?? 'COD')) }}<br>
                                {{ __('ui.order_status') }}: <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                <div class="card card-elegant p-4">
                    <h6 class="fw-semibold mb-3">{{ __('ui.shipment') }}</h6>
                    @if ($shipment)
                        <p class="small text-muted mb-0">
                            {{ __('ui.courier') }}: {{ $shipment->courier_name ?: '-' }}<br>
                            {{ __('ui.tracking_id') }}: {{ $shipment->shipment_code ?: '-' }}<br>
                            {{ __('ui.eta') }}: {{ optional($shipment->shipment_date)->format('d M Y') ?? '-' }}<br>
                            {{ __('ui.status') }}: {{ __('ui.' . $shipment->status) }}
                        </p>
                        <a href="{{ route('seller.shipments.label', $shipment) }}" target="_blank" class="btn btn-sm btn-outline-primary mt-3">
                            <i class="bi bi-printer me-1"></i> Print Label
                        </a>
                    @else
                        <p class="small text-muted mb-3">{{ __('ui.no_shipment_linked_to_this_order') }}</p>
                        <a href="{{ route('seller.create-shipment', ['order_id' => $order->id]) }}" class="btn btn-sm btn-success">
                            <i class="bi bi-truck me-1"></i> Ship Order
                        </a>
                    @endif
                </div>
            </div>

            @if ($order->returnRecord)
                @php
                    $returnStatus = strtolower((string) $order->returnRecord->status);
                @endphp
                <div class="col-md-6">
                    <div class="card card-elegant p-4">
                        <h6 class="fw-semibold mb-3">{{ __('ui.return') }}</h6>
                        <p class="small text-muted mb-0">
                            {{ __('ui.return_id') }}: {{ $order->returnRecord->return_code }}<br>
                            {{ __('ui.status') }}: <span class="badge {{ $returnStatusClasses[$returnStatus] ?? 'bg-secondary' }}">{{ $returnStatusLabels[$returnStatus] ?? ucfirst($returnStatus) }}</span><br>
                            {{ __('ui.reason') }}: {{ $order->returnRecord->return_reason }}<br>
                            {{ __('ui.refund') }}: {{ system_currency_format($order->returnRecord->refund_amount) }}
                        </p>
                    </div>
                </div>
            @endif
        </div>

                <div class="card mt-4" style="background:#f7f9fc;">
                    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <div class="fw-semibold mb-1">{{ __('ui.current_order_status') }}</div>
                            <div class="text-muted small">{{ __('ui.seller_can_only_view_status_here') }}</div>
                        </div>
                        <span class="badge {{ $badgeClass }} px-3 py-2">{{ $statusLabel }}</span>
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
