@include('spedfly.include.header')

@php
    $pageTitle = $isEdit ? 'Edit Shipment' : 'Shipments';
    $cardTitle = $isEdit ? 'Edit Shipment' : 'Create New Shipment';
    $submitLabel = $isEdit ? 'Update Shipment' : 'Create Shipment';
    $formAction = $isEdit ? route('seller.shipments.update', $shipment) : route('seller.create-shipment.store');
    $googleMapsApiKey = config('services.google_maps.key');
@endphp

<style>
    .pac-container {
        z-index: 2000 !important;
    }
</style>

<div class="app-body">

            <!-- Container starts -->
            <div class="container-fluid">
             <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">{{ $pageTitle }}</h3>
               
            </div>
            <a class="btn btn-primary" href="{{ route('seller.shipments') }}"><i class="bi bi-arrow-left me-1"></i> Back To Shipments</a>
        </div>

              
              <!-- Row start -->
              <div class="row">
                <div class="col-12">
                  <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between">
                      <h4 class="card-title">{{ $cardTitle }}</h4>
                     
                     
                    </div>
                    <div class="card-body p-3">
                        <form method="POST" action="{{ $formAction }}">
                            @csrf
                            @if($isEdit)
                                @method('PUT')
                            @endif

                            @if($errors->any())
                                <div class="alert alert-danger">Please check the highlighted fields and try again.</div>
                            @endif

                            <div class="card card-shadow p-4 mb-4">
                                <h5 class="section-title">Shipment Information</h5>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Shipment ID</label>
                                        <input name="shipment_code" type="text" readonly class="form-control rounded-3 {{ $errors->has('shipment_code') ? 'is-invalid' : '' }}" placeholder="Auto generated" value="{{ old('shipment_code', $defaultShipmentCode ?? '') }}">
                                        @error('shipment_code')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Order</label>
                                        <select name="order_id" class="form-select rounded-3 {{ $errors->has('order_id') ? 'is-invalid' : '' }}">
                                            <option value="">Select Order</option>
                                            @foreach($orders as $order)
                                                <option value="{{ $order->id }}" @selected(old('order_id', $shipment->order_id) == $order->id)>{{ str_starts_with((string)$order->external_order_id, '#') ? $order->external_order_id : ('#' . $order->external_order_id) }}</option>
                                            @endforeach
                                        </select>
                                        @error('order_id')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Shipment Date</label>
                                        <input name="shipment_date" type="date" class="form-control rounded-3 {{ $errors->has('shipment_date') ? 'is-invalid' : '' }}" value="{{ old('shipment_date', optional($shipment->shipment_date)->format('Y-m-d') ?? now()->toDateString()) }}">
                                        @error('shipment_date')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div><!-- ================= CUSTOMER INFO ================= -->
                            <div class="card card-shadow p-4 mb-4">
                                <h5 class="section-title">Customer Details</h5>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Existing Customer</label>
                                        <select name="customer_id" class="form-select rounded-3 {{ $errors->has('customer_id') ? 'is-invalid' : '' }}">
                                            <option value="">Select Customer</option>
                                            @foreach($customers as $customer)
                                                <option value="{{ $customer->id }}" @selected(old('customer_id', $shipment->customer_id) == $customer->id)>{{ $customer->name }} ({{ $customer->phone }})</option>
                                            @endforeach
                                        </select>
                                        @error('customer_id')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Customer Name</label>
                                        <input name="customer_name" class="form-control rounded-3 {{ $errors->has('customer_name') ? 'is-invalid' : '' }}" placeholder="e.g. Rahul Sharma" type="text" value="{{ old('customer_name', $shipment->customer_name) }}">
                                        @error('customer_name')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Contact Number</label>
                                        <input name="customer_phone" class="form-control rounded-3 {{ $errors->has('customer_phone') ? 'is-invalid' : '' }}" placeholder="+91 9876543210" type="text" value="{{ old('customer_phone', $shipment->customer_phone) }}">
                                        @error('customer_phone')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div><!-- ================= ADDRESS SECTION ================= -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card card-shadow p-4 mb-4">
                                        <h5 class="section-title">Pickup Address</h5>
                                        <input name="pickup_name" class="form-control rounded-3 mb-3 {{ $errors->has('pickup_name') ? 'is-invalid' : '' }}" placeholder="Warehouse Name" type="text" value="{{ old('pickup_name', $shipment->pickup_name) }}">
                                        @error('pickup_name')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <input
                                            id="pickup_address_search"
                                            class="form-control rounded-3 mb-3"
                                            placeholder="Search pickup location"
                                            type="text"
                                            autocomplete="off"
                                            value="{{ old('pickup_address', $shipment->pickup_address) }}"
                                        >
                                        <textarea name="pickup_address" class="form-control rounded-3 mb-3 {{ $errors->has('pickup_address') ? 'is-invalid' : '' }}" placeholder="Pickup Address" rows="3">{{ old('pickup_address', $shipment->pickup_address) }}</textarea>
                                        @error('pickup_address')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <input name="pickup_city" class="form-control rounded-3 {{ $errors->has('pickup_city') ? 'is-invalid' : '' }}" placeholder="City" type="text" value="{{ old('pickup_city', $shipment->pickup_city) }}">
                                                @error('pickup_city')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-6">
                                                <input name="pickup_postal_code" class="form-control rounded-3 {{ $errors->has('pickup_postal_code') ? 'is-invalid' : '' }}" placeholder="Postal Code" type="text" value="{{ old('pickup_postal_code', $shipment->pickup_postal_code) }}">
                                                @error('pickup_postal_code')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card card-shadow p-4 mb-4">
                                        <h5 class="section-title">Delivery Address</h5>
                                        <input name="delivery_name" class="form-control rounded-3 mb-3 {{ $errors->has('delivery_name') ? 'is-invalid' : '' }}" placeholder="Recipient Name" type="text" value="{{ old('delivery_name', $shipment->delivery_name) }}">
                                        @error('delivery_name')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <input
                                            id="delivery_address_search"
                                            class="form-control rounded-3 mb-3"
                                            placeholder="Search delivery location"
                                            type="text"
                                            autocomplete="off"
                                            value="{{ old('delivery_address', $shipment->delivery_address) }}"
                                        >
                                        <textarea name="delivery_address" class="form-control rounded-3 mb-3 {{ $errors->has('delivery_address') ? 'is-invalid' : '' }}" placeholder="Delivery Address" rows="3">{{ old('delivery_address', $shipment->delivery_address) }}</textarea>
                                        @error('delivery_address')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <input name="delivery_city" class="form-control rounded-3 {{ $errors->has('delivery_city') ? 'is-invalid' : '' }}" placeholder="City" type="text" value="{{ old('delivery_city', $shipment->delivery_city) }}">
                                                @error('delivery_city')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-6">
                                                <input name="delivery_postal_code" class="form-control rounded-3 {{ $errors->has('delivery_postal_code') ? 'is-invalid' : '' }}" placeholder="Postal Code" type="text" value="{{ old('delivery_postal_code', $shipment->delivery_postal_code) }}">
                                                @error('delivery_postal_code')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div><!-- ================= COURIER & PACKAGE ================= -->
                            <div class="card card-shadow p-4 mb-4">
                                <h5 class="section-title">Courier & Package Details</h5>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Courier</label>
                                        <select name="courier_name" class="form-select rounded-3 {{ $errors->has('courier_name') ? 'is-invalid' : '' }}">
                                            <option value="">Select courier</option>
                                            @foreach($couriers as $courier)
                                                <option value="{{ $courier }}" @selected(old('courier_name', $shipment->courier_name) === $courier)>{{ $courier }}</option>
                                            @endforeach
                                        </select>
                                        @error('courier_name')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Weight (kg)</label>
                                        <input name="weight" class="form-control rounded-3 {{ $errors->has('weight') ? 'is-invalid' : '' }}" placeholder="0.00" type="number" step="0.01" value="{{ old('weight', $shipment->weight) }}">
                                        @error('weight')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Dimensions (LxWxH cm)</label>
                                        <input name="dimensions" class="form-control rounded-3 {{ $errors->has('dimensions') ? 'is-invalid' : '' }}" placeholder="30x20x10" type="text" value="{{ old('dimensions', $shipment->dimensions) }}">
                                        @error('dimensions')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="row g-3 mt-2">
                                    <div class="col-md-4">
                                        <label class="form-label">Payment Type</label>
                                        <select name="payment_type" class="form-select rounded-3 {{ $errors->has('payment_type') ? 'is-invalid' : '' }}">
                                            <option value="">Select payment</option>
                                            @foreach($payments as $key => $label)
                                                <option value="{{ $key }}" @selected(old('payment_type', $shipment->payment_type) === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('payment_type')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Shipment Status</label>
                                        <select name="status" class="form-select rounded-3 {{ $errors->has('status') ? 'is-invalid' : '' }}">
                                            <option value="">Select status</option>
                                            @foreach($statuses as $key => $label)
                                                <option value="{{ $key }}" @selected(old('status', $shipment->status) === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('status')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div><!-- ================= NOTES ================= -->
                            <div class="card card-shadow p-4 mb-4">
                                <h5 class="section-title">Additional Notes</h5>
                                <textarea name="notes" class="form-control rounded-3 {{ $errors->has('notes') ? 'is-invalid' : '' }}" placeholder="Add special instructions..." rows="4">{{ old('notes', $shipment->notes) }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div><!-- ================= ACTION BUTTONS ================= -->
                            <div class="d-flex justify-content-end gap-3">
                                <a class="btn btn-light rounded-pill px-4" href="{{ route('seller.shipments') }}">Cancel</a>
                                <button class="btn btn-primary rounded-pill px-4" type="submit">
                                    <i class="bi bi-check-circle me-2"></i>{{ $submitLabel }}
                                </button>
                            </div>
                        </form>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Row end -->

            </div>
            <!-- Container ends -->

          </div>
          <!-- App body ends -->

@push('page-scripts')
<script>
    function initSellerShipmentAutocomplete() {
        if (!window.google || !window.google.maps || !window.google.maps.places) {
            return;
        }

        function fillAddressFields(prefix, place) {
            if (!place) {
                return;
            }

            const addressField = document.querySelector('[name="' + prefix + '_address"]');
            const cityField = document.querySelector('[name="' + prefix + '_city"]');
            const postalField = document.querySelector('[name="' + prefix + '_postal_code"]');
            const searchField = document.getElementById(prefix + '_address_search');

            if (searchField && place.formatted_address) {
                searchField.value = place.formatted_address;
            }

            if (addressField && place.formatted_address) {
                addressField.value = place.formatted_address;
            }

            if (!Array.isArray(place.address_components)) {
                return;
            }

            let city = '';
            let postalCode = '';

            place.address_components.forEach(function (component) {
                const types = component.types || [];

                if (!city && (types.includes('locality') || types.includes('postal_town'))) {
                    city = component.long_name;
                }

                if (!city && types.includes('administrative_area_level_2')) {
                    city = component.long_name;
                }

                if (!postalCode && types.includes('postal_code')) {
                    postalCode = component.long_name;
                }
            });

            if (cityField && city) {
                cityField.value = city;
            }

            if (postalField && postalCode) {
                postalField.value = postalCode;
            }
        }

        [
            { prefix: 'pickup', inputId: 'pickup_address_search' },
            { prefix: 'delivery', inputId: 'delivery_address_search' },
        ].forEach(function (config) {
            const input = document.getElementById(config.inputId);

            if (!input || input.dataset.autocompleteReady === 'true') {
                return;
            }

            const autocomplete = new google.maps.places.Autocomplete(input, {
                fields: ['formatted_address', 'address_components', 'geometry', 'name'],
                types: ['geocode'],
            });

            autocomplete.addListener('place_changed', function () {
                fillAddressFields(config.prefix, autocomplete.getPlace());
            });

            input.dataset.autocompleteReady = 'true';
        });
    }

    $(function () {
        initSellerShipmentAutocomplete();
    });
</script>
@if ($googleMapsApiKey)
<script
    src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&libraries=places&loading=async&callback=initSellerShipmentAutocomplete"
    async
    defer
></script>
@endif
@endpush

@include('spedfly.include.footer')
