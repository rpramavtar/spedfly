@include('spedfly.include.header')

@php
    $googleMapsApiKey = config('services.google_maps.key');
@endphp

<style>
    .pac-container {
        z-index: 2000 !important;
    }
</style>

<div class="app-body">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h1 class="mt-4 mb-1">{{ __('ui.shipments') }}</h1>
                        <p class="text-muted mb-0">{{ __('ui.manage_shipments_carriers_and_tracking_details') }}</p>
                    </div>
                </div>

                @if (session('success'))
                    <div class="alert alert-success js-auto-hide-alert">{{ session('success') }}</div>
                @endif

                <div class="row mb-3 g-3">
                    <div class="col-md-4">
                        <div class="card p-3 h-100">
                            <h6 class="mb-1">{{ __('ui.in_transit') }}</h6>
                            <h4 id="admin-shipments-in-transit" class="mb-0">{{ number_format($stats['in_transit'] ?? 0) }}</h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-3 h-100">
                            <h6 class="mb-1">{{ __('ui.delivered') }}</h6>
                            <h4 id="admin-shipments-delivered" class="mb-0">{{ number_format($stats['delivered'] ?? 0) }}</h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-3 h-100">
                            <h6 class="mb-1">{{ __('ui.delayed') }}</h6>
                            <h4 id="admin-shipments-delayed" class="mb-0">{{ number_format($stats['delayed'] ?? 0) }}</h4>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <form class="row g-2" method="get" action="{{ route('admin.shipments') }}">
                            <div class="col-md-4">
                                <input
                                    type="text"
                                    class="form-control"
                                    name="q"
                                    value="{{ $search }}"
                                    placeholder="{{ __('ui.search_by_order_shipment_customer_or_phone') }}"
                                >
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="carrier">
                                    <option value="">{{ __('ui.all_carriers') }}</option>
                                    @foreach($carrierOptions as $carrierOption)
                                        <option value="{{ $carrierOption }}" @selected($carrier === $carrierOption)>{{ $carrierOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control" name="from" value="{{ $fromDate }}">
                            </div>
                            <div class="col-md-1">
                                <input type="date" class="form-control" name="to" value="{{ $toDate }}">
                            </div>
                            <div class="col-md-2 text-end d-flex gap-2 justify-content-end">
                                <button class="btn btn-outline-secondary" type="submit" name="export" value="csv">{{ __('ui.export') }}</button>
                                <button class="btn btn-primary" type="submit">{{ __('ui.filter') }}</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    @php
                        $statusBadgeClasses = [
                            'pending' => 'bg-secondary',
                            'in_transit' => 'bg-info',
                            'delivered' => 'bg-success',
                            'delayed' => 'bg-danger',
                            'returned' => 'bg-dark',
                        ];
                    @endphp
                    <table id="shipmentsTable" class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('ui.shipment_id') }}</th>
                                <th>{{ __('ui.order_id') }}</th>
                                <th>{{ __('ui.seller') }}</th>
                                <th>{{ __('ui.carrier') }}</th>
                                <th>{{ __('ui.status') }}</th>
                                <th>{{ __('ui.shipped_date') }}</th>
                                <th style="width:120px;">{{ __('ui.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody id="admin-shipments-table-body">
                            @include('spedfly.admin.partials.shipments-table-rows', [
                                'shipments' => $shipments,
                                'statusLabels' => $statusLabels,
                                'statusBadgeClasses' => $statusBadgeClasses,
                            ])
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editShipmentModal" tabindex="-1" aria-labelledby="editShipmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-3 px-4">
                <h4 class="modal-title fw-semibold" id="editShipmentModalLabel">{{ __('ui.update_shipment') }}</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editShipmentForm" method="post">
                @csrf
                @method('PATCH')
                <div class="modal-body px-4 py-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('ui.tracking_number') }}</label>
                            <input type="text" name="shipment_code" class="form-control" placeholder="Ex: TRK123456" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('ui.carrier') }}</label>
                            <input type="text" name="courier_name" class="form-control" placeholder="{{ __('ui.select_carrier') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('ui.origin_location') }}</label>
                            <input
                                type="text"
                                name="pickup_address"
                                class="form-control"
                                placeholder="City/Address"
                                autocomplete="off"
                                required
                            >
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('ui.destination') }}</label>
                            <input
                                type="text"
                                name="delivery_address"
                                class="form-control"
                                placeholder="City/Address"
                                autocomplete="off"
                                required
                            >
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">{{ __('ui.shipped_date') }}</label>
                            <input type="date" name="shipment_date" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer px-4 py-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.close') }}</button>
                    <button class="btn btn-primary" type="submit">{{ __('ui.save_shipment') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('page-scripts')
<script>
    function initAdminShipmentAutocomplete() {
        if (!window.google || !window.google.maps || !window.google.maps.places) {
            return;
        }

        const modal = document.getElementById('editShipmentModal');
        if (!modal) {
            return;
        }

        const originInput = modal.querySelector('input[name="pickup_address"]');
        const destinationInput = modal.querySelector('input[name="delivery_address"]');

        [originInput, destinationInput].forEach(function (input) {
            if (!input || input.dataset.autocompleteReady === 'true') {
                return;
            }

            const autocomplete = new google.maps.places.Autocomplete(input, {
                fields: ['formatted_address', 'geometry', 'name'],
                types: ['geocode'],
            });

            autocomplete.addListener('place_changed', function () {
                const place = autocomplete.getPlace();

                if (place && place.formatted_address) {
                    input.value = place.formatted_address;
                }
            });

            input.dataset.autocompleteReady = 'true';
        });
    }

    $(function () {
        const table = $('#shipmentsTable');
        const hasEmptyRow = $('#shipmentsTable tbody tr.empty-row').length > 0;
        const tbody = document.getElementById('admin-shipments-table-body');
        const feedUrl = @json(route('admin.shipments.feed'));
        const initialSignature = @json(collect($shipments)->map(fn ($shipment) => $shipment->id . '|' . optional($shipment->updated_at)->timestamp)->implode(';'));
        let currentSignature = initialSignature;
        let dataTableInstance = null;

        if (table.length && $.fn.DataTable && !hasEmptyRow) {
            dataTableInstance = table.DataTable({
                aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, "All"]],
                iDisplayLength: 5,
                ordering: false,
                searching: false
            });
        }

        const autoHideAlert = $('.js-auto-hide-alert');
        if (autoHideAlert.length) {
            setTimeout(function () {
                autoHideAlert.fadeOut(300);
            }, 4000);
        }

        $('#editShipmentModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const shipment = button.attr('data-shipment')
                ? JSON.parse(button.attr('data-shipment'))
                : {};
            const updateUrl = button.attr('data-update-url') || '';
            const form = $('#editShipmentForm');

            form.attr('action', updateUrl);
            form.find('[name="shipment_code"]').val(shipment.shipment_code || '');
            form.find('[name="courier_name"]').val(shipment.courier_name || '');
            form.find('[name="pickup_address"]').val(shipment.pickup_address || '');
            form.find('[name="delivery_address"]').val(shipment.delivery_address || '');
            form.find('[name="shipment_date"]').val(shipment.shipment_date || '');
        });

        initAdminShipmentAutocomplete();

        async function refreshShipments() {
            try {
                const params = new URLSearchParams(window.location.search);
                const response = await fetch(feedUrl + '?' + params.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                if (!payload || payload.signature === currentSignature) {
                    return;
                }

                currentSignature = payload.signature || '';

                if (dataTableInstance) {
                    dataTableInstance.destroy();
                    dataTableInstance = null;
                }

                if (tbody && typeof payload.html === 'string') {
                    tbody.innerHTML = payload.html;
                }

                const emptyRow = $('#shipmentsTable tbody tr.empty-row').length > 0;
                if (table.length && $.fn.DataTable && !emptyRow) {
                    dataTableInstance = table.DataTable({
                        aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, "All"]],
                        iDisplayLength: 5,
                        ordering: false,
                        searching: false
                    });
                }

                const adminInTransitCard = document.getElementById('admin-shipments-in-transit');
                const adminDeliveredCard = document.getElementById('admin-shipments-delivered');
                const adminDelayedCard = document.getElementById('admin-shipments-delayed');

                if (adminInTransitCard && payload.stats) {
                    adminInTransitCard.textContent = payload.stats.in_transit ?? 0;
                }
                if (adminDeliveredCard && payload.stats) {
                    adminDeliveredCard.textContent = payload.stats.delivered ?? 0;
                }
                if (adminDelayedCard && payload.stats) {
                    adminDelayedCard.textContent = payload.stats.delayed ?? 0;
                }
            } catch (error) {
                // Silent retry on next interval.
            }
        }

        setInterval(refreshShipments, 15000);
    });
</script>
@if ($googleMapsApiKey)
<script
    src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&libraries=places&loading=async&callback=initAdminShipmentAutocomplete"
    async
    defer
></script>
@endif
@endpush

@include('spedfly.include.footer')
