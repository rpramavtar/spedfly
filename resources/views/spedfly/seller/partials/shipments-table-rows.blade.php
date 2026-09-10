@forelse($shipments as $shipment)
    <tr>
        <td>#{{ $shipment->shipment_code }}</td>
        <td>
            <div class="d-flex align-items-center gap-2">
                <span class="rounded-circle bg-light text-uppercase fw-bold d-flex align-items-center justify-content-center" style="width:32px;height:32px;">
                    {{ strtoupper(substr($shipment->customer_name ?? 'U', 0, 1)) }}
                </span>
                <div>
                    <div class="fw-bold">{{ $shipment->customer_name }}</div>
                    <small class="text-muted">{{ $shipment->customer_phone }}</small>
                </div>
            </div>
        </td>
        <td>{{ $shipment->courier_name }}</td>
        <td>{{ $shipment->order ? (str_starts_with((string)$shipment->order->external_order_id, '#') ? $shipment->order->external_order_id : ('#' . $shipment->order->external_order_id)) : '-' }}</td>
        <td>
            <span class="badge {{ $statusBadgeClasses[$shipment->status] ?? 'bg-secondary text-white' }}">
                {{ $statusLabels[$shipment->status] ?? ucfirst($shipment->status) }}
            </span>
        </td>
        <td>{{ optional($shipment->shipment_date)->format('d M Y') ?? '-' }}</td>
        <td>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('seller.shipments.label', $shipment) }}" target="_blank" class="btn btn-outline-primary btn-sm" title="Print Label">
                    <i class="bi bi-printer"></i> Label
                </a>
                <a href="{{ route('seller.shipments.edit', $shipment) }}" class="btn btn-outline-secondary btn-sm" title="Edit">
                    <i class="bi bi-pencil"></i>
                </a>
                <form action="{{ route('seller.shipments.destroy', $shipment) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this shipment?');" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </div>
        </td>
    </tr>
@empty
    <tr class="empty-row">
        <td colspan="7" class="text-center text-muted py-4">{{ __('ui.no_shipments_matched_filters') }}</td>
    </tr>
@endforelse
