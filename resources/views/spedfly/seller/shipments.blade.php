@include('spedfly.include.header')

<div class="app-body">

            <!-- Container starts -->
            <div class="container-fluid">
             <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-truck me-2 text-primary"></i>{{ __('ui.shipments') }}</h3>
            </div>
            <a class="btn btn-primary" href="{{ route('seller.create-shipment') }}"><i class="bi bi-plus me-1"></i> Create Shipment</a>
        </div>

                @if(session('success'))
                    <div id="shipment-alert" class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('ui.close') }}"></button>
                    </div>
                @endif

               <div class="row mb-4 g-3">
            <div class="col-md-3">
                <div class="card card-shadow p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted fw-bold">{{ __('ui.in_transit') }}</small>
                            <h4 id="seller-shipments-in-transit" class="fw-bold text-primary">{{ $stats['in_transit'] ?? 0 }}</h4>
                        </div>
                        <div class="icon-circle bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-truck"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-shadow p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted fw-bold">{{ __('ui.delivered') }}</small>
                            <h4 id="seller-shipments-delivered" class="fw-bold text-success">{{ $stats['delivered'] ?? 0 }}</h4>
                        </div>
                        <div class="icon-circle bg-success bg-opacity-10 text-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-shadow p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted fw-bold">{{ __('ui.delayed') }}</small>
                            <h4 id="seller-shipments-delayed" class="fw-bold text-danger">{{ $stats['delayed'] ?? 0 }}</h4>
                        </div>
                        <div class="icon-circle bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-exclamation-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-shadow p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted fw-bold">{{ __('ui.total_shipments') }}</small>
                            <h4 id="seller-shipments-total" class="fw-bold">{{ $totalShipments }}</h4>
                        </div>
                        <div class="icon-circle bg-dark bg-opacity-10 text-dark">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div> 

              <!-- Row start -->
              <div class="row">
                <div class="col-12">
                  <div class="card mb-3">
                    <div class="card-body p-3">
                        <form class="row g-3 align-items-end mb-3" method="GET" action="{{ route('seller.shipments') }}">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="col-md-4">
                                <label class="form-label visually-hidden" for="search">{{ __('ui.search') }}</label>
                                <input id="search" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('ui.search_shipments_customers_orders') }}" type="search">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label visually-hidden" for="date">{{ __('ui.shipment_date') }}</label>
                                <input id="date" name="date" value="{{ $dateFilter }}" class="form-control" type="date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label visually-hidden" for="status">{{ __('ui.status') }}</label>
                                <select id="status" name="status" class="form-select">
                                    <option value="">{{ __('ui.all_statuses') }}</option>
                                    @foreach($statusLabels as $key => $label)
                                        <option value="{{ $key }}" @selected($statusFilter === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">{{ __('ui.apply') }}</button>
                            </div>
                        </form>
            <ul class="nav nav-tabs mb-3">
                @php
                    $tabQuery = request()->except('tab');
                @endphp
                @foreach(array_merge(['all' => __('ui.all')], $statusLabels) as $key => $label)
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                           href="{{ route('seller.shipments', array_merge($tabQuery, ['tab' => $key])) }}">
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="card card-shadow p-3">
                <div id="sellerShipmentsTableSection" class="table-responsive">
                    @php
                        $statusBadgeClasses = [
                            'pending' => 'bg-secondary text-white',
                            'in_transit' => 'bg-primary text-white',
                            'delivered' => 'bg-success text-white',
                            'delayed' => 'bg-danger text-white',
                            'returned' => 'bg-dark text-white',
                        ];
                    @endphp
                    <div id="shipments-empty-state" class="{{ $shipments->isEmpty() ? '' : 'd-none' }} text-center py-5 text-muted">
                        {{ __('ui.no_shipments_matched_filters') }}
                    </div>
                    <table id="shipments-table" class="table table-bordered mb-0 align-middle {{ $shipments->isEmpty() ? 'd-none' : '' }}">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('ui.shipment_id') }}</th>
                                <th scope="col">{{ __('ui.customer') }}</th>
                                <th scope="col">{{ __('ui.courier') }}</th>
                                <th scope="col">{{ __('ui.order') }}</th>
                                <th scope="col">{{ __('ui.status') }}</th>
                                <th scope="col">{{ __('ui.eta') }}</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody id="shipments-table-body">
                            @include('spedfly.seller.partials.shipments-table-rows', [
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
              </div>
              <!-- Row end -->

            </div>
            <!-- Container ends -->

          </div>
          <!-- App body ends -->

@if(session('success'))
<script>
        setTimeout(() => {
            const alert = document.getElementById('shipment-alert');
            if (!alert) {
                return;
            }
            alert.classList.remove('show');
            alert.classList.add('fade');
        }, 5000);
    </script>
@endif

<script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = $('#shipments-table');
            const tbody = document.getElementById('shipments-table-body');
            const emptyState = document.getElementById('shipments-empty-state');
            const feedUrl = @json(route('seller.shipments.feed'));
            const initialSignature = @json(collect($shipments)->map(fn ($shipment) => $shipment->id . '|' . optional($shipment->updated_at)->timestamp)->implode(';'));
            let currentSignature = initialSignature;
            let dataTableInstance = null;

            function destroyDataTableIfNeeded() {
                if (dataTableInstance) {
                    dataTableInstance.destroy();
                    dataTableInstance = null;
                }
            }

            function initDataTableIfNeeded() {
                if (!table.length || !tbody || !$.fn.DataTable || tbody.querySelector('tr.empty-row')) {
                    return;
                }

                dataTableInstance = table.DataTable({
                    pageLength: 10,
                    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, {{ json_encode(__('ui.all')) }}]],
                    ordering: false,
                    dom: 'lfrtip',
                    language: {
                        search: {{ json_encode(__('ui.quick_search')) }},
                        searchPlaceholder: {{ json_encode(__('ui.search_visible_rows')) }}
                    },
                    autoWidth: false
                });
            }

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
                    destroyDataTableIfNeeded();

                    if (typeof payload.html === 'string') {
                        tbody.innerHTML = payload.html;
                    }

                    if (emptyState && tbody) {
                        const hasRows = !tbody.querySelector('tr.empty-row');
                        emptyState.classList.toggle('d-none', hasRows);
                        if (table.length) {
                            table.toggle(hasRows);
                        }
                    }

                    const sellerInTransitCard = document.getElementById('seller-shipments-in-transit');
                    const sellerDeliveredCard = document.getElementById('seller-shipments-delivered');
                    const sellerDelayedCard = document.getElementById('seller-shipments-delayed');
                    const sellerTotalCard = document.getElementById('seller-shipments-total');

                    if (sellerInTransitCard && payload.stats) {
                        sellerInTransitCard.textContent = payload.stats.in_transit ?? 0;
                    }
                    if (sellerDeliveredCard && payload.stats) {
                        sellerDeliveredCard.textContent = payload.stats.delivered ?? 0;
                    }
                    if (sellerDelayedCard && payload.stats) {
                        sellerDelayedCard.textContent = payload.stats.delayed ?? 0;
                    }
                    if (sellerTotalCard) {
                        sellerTotalCard.textContent = payload.totalShipments ?? 0;
                    }

                    initDataTableIfNeeded();
                } catch (error) {
                    // Keep polling silently if one request fails.
                }
            }

            initDataTableIfNeeded();
            setInterval(refreshShipments, 15000);
        });
    </script>

@include('spedfly.include.footer')
