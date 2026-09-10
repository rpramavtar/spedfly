@forelse($shipments as $shipment)
    @php
        $pickupLat = $shipment->pickup_latitude;
        $pickupLng = $shipment->pickup_longitude;
        $deliveryLat = $shipment->delivery_latitude;
        $deliveryLng = $shipment->delivery_longitude;
        $hasMapCoordinates = filled($pickupLat) && filled($pickupLng) && filled($deliveryLat) && filled($deliveryLng);
        $mapUrl = $hasMapCoordinates
            ? 'https://www.google.com/maps/dir/?api=1&origin=' . urlencode($pickupLat . ',' . $pickupLng) . '&destination=' . urlencode($deliveryLat . ',' . $deliveryLng)
            : null;
        $editPayload = [
            'shipment_code' => $shipment->shipment_code,
            'courier_name' => $shipment->courier_name,
            'shipment_date' => optional($shipment->shipment_date)->format('Y-m-d'),
            'pickup_address' => $shipment->pickup_address,
            'delivery_address' => $shipment->delivery_address,
        ];
    @endphp
    <tr>
        <td>#{{ $shipment->shipment_code }}</td>
        <td>
            @if($shipment->order)
                #{{ $shipment->order->external_order_id }}
            @else
                -
            @endif
        </td>
        <td>{{ $shipment->seller?->name ?? '-' }}</td>
        <td>{{ $shipment->courier_name ?: '-' }}</td>
        <td>
            <span class="badge {{ $statusBadgeClasses[$shipment->status] ?? 'bg-secondary' }}">
                {{ $statusLabels[$shipment->status] ?? ucfirst((string) $shipment->status) }}
            </span>
        </td>
        <td>{{ optional($shipment->shipment_date)->format('Y-m-d') ?? '-' }}</td>
        <td>
            <div class="d-flex gap-2">
                @if($shipment->order)
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.orders.details', $shipment->order) }}">Track</a>
                @else
                    <button class="btn btn-sm btn-outline-primary" type="button" disabled>Track</button>
                @endif
                <button
                    class="btn btn-sm btn-outline-warning edit-shipment-btn"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#editShipmentModal"
                    data-update-url="{{ route('admin.shipments.update', $shipment) }}"
                    data-shipment='@json($editPayload)'
                >
                    Update
                </button>
                @if($hasMapCoordinates)
                    <a
                        class="btn btn-sm btn-outline-secondary"
                        href="{{ $mapUrl }}"
                        target="_blank"
                        rel="noopener"
                    >
                        Map
                    </a>
                @else
                    <button class="btn btn-sm btn-outline-secondary" type="button" disabled title="Location coordinates are not available for this shipment">
                        Map
                    </button>
                @endif
            </div>
        </td>
    </tr>
@empty
    <tr class="empty-row">
        <td colspan="7" class="text-center text-muted py-4">No shipments found for the selected filters.</td>
    </tr>
@endforelse
