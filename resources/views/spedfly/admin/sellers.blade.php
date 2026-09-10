@include('spedfly.include.header')

<style>
  .pac-container {
    z-index: 20000 !important;
  }
</style>

<div class="app-body">
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <h1 class="mt-4">{{ __('ui.sellers') }}</h1>
            <p>{{ __('ui.manage_merchants_and_seller_profiles') }}</p>
          </div>
          <div>
            <button class="btn btn-primary me-3" data-bs-toggle="modal" data-bs-target="#addSellerModal">
              <i class="bi bi-plus-circle me-2"></i>{{ __('ui.add_seller') }}
            </button>
          </div>
        </div>

        @if (session('success'))
          <div class="alert alert-success js-auto-hide-alert">{{ session('success') }}</div>
        @endif

        <div class="row mb-3">
          <div class="col-md-4"><div class="card p-3 mb-2"><h6>{{ __('ui.active_sellers') }}</h6><h4>{{ $activeSellersCount }}</h4></div></div>
          <div class="col-md-4"><div class="card p-3 mb-2"><h6>{{ __('ui.new_this_month') }}</h6><h4>{{ $newThisMonthCount }}</h4></div></div>
          <div class="col-md-4"><div class="card p-3 mb-2"><h6>{{ __('ui.top_seller') }}</h6><h4>{{ $topSeller?->name ?? 'N/A' }}</h4></div></div>
        </div>

        <div class="card mb-3">
          <div class="card-body">
            <form class="row g-2" method="get" action="{{ route('admin.sellers') }}">
              <div class="col-md-6"><input type="text" class="form-control" name="name" value="{{ $search }}" placeholder="{{ __('ui.seller_name_or_email') }}"></div>
              <div class="col-md-4">
                <select class="form-select" name="status">
                  <option value="all" {{ $status === 'all' ? 'selected' : '' }}>{{ __('ui.all') }}</option>
                  <option value="active" {{ $status === 'active' ? 'selected' : '' }}>{{ __('ui.active') }}</option>
                  <option value="deactive" {{ $status === 'deactive' ? 'selected' : '' }}>{{ __('ui.deactive') }}</option>
                </select>
              </div>
              <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">{{ __('ui.search') }}</button></div>
            </form>
          </div>
        </div>

        <div class="table-responsive">
          <table id="sellersTable" class="table table-bordered">
            <thead class="table-light">
              <tr>
                <th>{{ __('ui.seller') }}</th>
                <th>{{ __('ui.email') }}</th>
                <th>{{ __('ui.orders') }}</th>
                <th>{{ __('ui.revenue') }}</th>
                <th>{{ __('ui.status') }}</th>
                <th>{{ __('ui.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($sellers as $seller)
                @php
                  $sellerStatus = strtolower((string) ($seller->status ?? 'active'));
                  if ($sellerStatus === 'suspended') {
                    $sellerStatus = 'deactive';
                  }
                  $editPayload = [
                      'name' => $seller->name,
                      'email' => $seller->email,
                      'company_name' => $seller->company_name,
                      'status' => $sellerStatus,
                      'iban_code' => $seller->iban_code,
                      'telephone_number' => $seller->support_phone,
                      'vat_number' => $seller->vat_number,
                      'pickup_address' => $seller->pickup_address,
                      'pickup_latitude' => $seller->pickup_latitude,
                      'pickup_longitude' => $seller->pickup_longitude,
                  ];
                @endphp
                <tr>
                  <td class="d-flex align-items-center">
                    <img class="rounded-circle me-2" src="{{ asset('assets/admin/images/user.png') }}" width="40" alt="seller">
                    <div>
                      <strong>{{ $seller->name }}</strong><br>
                      <small class="text-muted">{{ __('ui.seller_account') }}</small>
                    </div>
                  </td>
                  <td>{{ $seller->email }}</td>
                  <td>{{ number_format((int) ($seller->orders_count ?? 0)) }}</td>
                  <td>{{ system_currency_format((float) ($seller->orders_revenue ?? 0)) }}</td>
                  <td>
                    <form action="{{ route('admin.sellers.status', $seller) }}" method="post">
                      @csrf
                      @method('PATCH')
                      <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                        <option value="active" {{ $sellerStatus === 'active' ? 'selected' : '' }}>{{ __('ui.active') }}</option>
                        <option value="deactive" {{ $sellerStatus === 'deactive' ? 'selected' : '' }}>{{ __('ui.deactive') }}</option>
                      </select>
                    </form>
                  </td>
                  <td>
                    <a href="{{ route('admin.sellers.show', $seller) }}" class="btn btn-sm btn-outline-primary">{{ __('ui.view') }}</a>
                    <button
                      class="btn btn-sm btn-outline-warning edit-seller-btn"
                      type="button"
                      data-bs-toggle="modal"
                      data-bs-target="#editSellerModal"
                      data-update-url="{{ route('admin.sellers.update', $seller) }}"
                      data-seller='@json($editPayload)'
                    >
                      {{ __('ui.edit') }}
                    </button>
                    <a href="{{ route('admin.sellers.wallet', $seller) }}" class="btn btn-sm btn-outline-info">Wallet</a>
                    <form action="{{ route('admin.sellers.destroy', $seller) }}" method="post" class="d-inline" onsubmit="return confirm('{{ __('ui.are_you_sure_you_want_to_delete_this_seller') }}');">
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('ui.delete') }}</button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr>
                  <td class="text-center">{{ __('ui.no_sellers_found') }}</td>
                  <td></td>
                  <td></td>
                  <td></td>
                  <td></td>
                  <td></td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addSellerModal" tabindex="-1" aria-labelledby="addSellerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-3 px-4">
        <h4 class="modal-title fw-semibold" id="addSellerModalLabel">{{ __('ui.add_new_seller') }}</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
      </div>
      <form action="{{ route('admin.sellers.store') }}" method="post">
        @csrf
        <div class="modal-body px-4 py-3">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.business_name') }}</label>
              <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="{{ __('ui.enter_seller_name') }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.email') }}</label>
              <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="seller@email.com" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.company_name') }}</label>
              <input type="text" name="company_name" class="form-control" value="{{ old('company_name') }}" placeholder="{{ __('ui.company_name') }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.telephone_number') }}</label>
              <input type="text" name="telephone_number" class="form-control" value="{{ old('telephone_number') }}" placeholder="{{ __('ui.telephone_number') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.vat_number') }}</label>
              <input type="text" name="vat_number" class="form-control" value="{{ old('vat_number') }}" placeholder="{{ __('ui.vat_number') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.iban_code') }}</label>
              <input type="text" name="iban_code" class="form-control" value="{{ old('iban_code') }}" placeholder="{{ __('ui.iban_code') }}">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold" for="pickup_address_create">{{ __('ui.address') }}</label>
              <textarea id="pickup_address_create" name="pickup_address" class="form-control" rows="3" placeholder="{{ __('ui.start_typing_address_to_autocomplete') }}" required>{{ old('pickup_address') }}</textarea>
              <input type="hidden" name="pickup_latitude" value="{{ old('pickup_latitude') }}">
              <input type="hidden" name="pickup_longitude" value="{{ old('pickup_longitude') }}">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">{{ __('ui.password') }}</label>
              <input type="password" name="password" class="form-control" placeholder="{{ __('ui.leave_blank_for_default_seller') }}">
            </div>
          </div>

          @if ($errors->any())
            <div class="alert alert-danger mt-3 mb-0">
              <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif
        </div>
        <div class="modal-footer px-4 py-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.close') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('ui.save_seller') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editSellerModal" tabindex="-1" aria-labelledby="editSellerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-3 px-4">
        <h4 class="modal-title fw-semibold" id="editSellerModalLabel">{{ __('ui.edit_seller') }}</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
      </div>
      <form id="editSellerForm" method="post">
        @csrf
        @method('PATCH')
        <div class="modal-body px-4 py-3">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.business_name') }}</label>
              <input type="text" name="name" class="form-control" placeholder="{{ __('ui.enter_seller_name') }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.email') }}</label>
              <input type="email" name="email" class="form-control" placeholder="seller@email.com" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.company_name') }}</label>
              <input type="text" name="company_name" class="form-control" placeholder="{{ __('ui.company_name') }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.status') }}</label>
              <select class="form-select" name="status" required>
                <option value="active">{{ __('ui.active') }}</option>
                <option value="deactive">{{ __('ui.deactive') }}</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.telephone_number') }}</label>
              <input type="text" name="telephone_number" class="form-control" placeholder="{{ __('ui.telephone_number') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.vat_number') }}</label>
              <input type="text" name="vat_number" class="form-control" placeholder="{{ __('ui.vat_number') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.iban_code') }}</label>
              <input type="text" name="iban_code" class="form-control" placeholder="{{ __('ui.iban_code') }}">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold" for="pickup_address_edit">{{ __('ui.address') }}</label>
              <textarea id="pickup_address_edit" name="pickup_address" class="form-control" rows="3" placeholder="{{ __('ui.start_typing_address_to_autocomplete') }}" required></textarea>
              <input type="hidden" name="pickup_latitude">
              <input type="hidden" name="pickup_longitude">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">{{ __('ui.password') }}</label>
              <input type="password" name="password" class="form-control" placeholder="{{ __('ui.leave_blank_to_keep_current_password') }}">
            </div>
          </div>

          @if ($errors->any())
            <div class="alert alert-danger mt-3 mb-0">
              <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif
        </div>
        <div class="modal-footer px-4 py-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.close') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('ui.save_changes') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('page-scripts')
<script>
  function bindSellerAddressAutocomplete(input, latField, lngField) {
    if (!input || input.dataset.autocompleteReady === 'true') {
      return;
    }

    var autocomplete = new google.maps.places.Autocomplete(input, {
      fields: ['geometry', 'formatted_address', 'name'],
      types: ['address'],
    });

    autocomplete.addListener('place_changed', function () {
      var place = autocomplete.getPlace();
      if (!place) {
        return;
      }

      input.value = place.formatted_address || place.name || input.value;

      if (place.geometry && place.geometry.location) {
        if (latField) {
          latField.value = place.geometry.location.lat();
        }
        if (lngField) {
          lngField.value = place.geometry.location.lng();
        }
      }
    });

    input.addEventListener('input', function () {
      if (latField) {
        latField.value = '';
      }
      if (lngField) {
        lngField.value = '';
      }
    });

    input.dataset.autocompleteReady = 'true';
  }

  window.initAdminSellerAddressAutocomplete = function () {
    if (!window.google || !window.google.maps || !window.google.maps.places) {
      return;
    }

    var createInput = document.getElementById('pickup_address_create');
    bindSellerAddressAutocomplete(
      createInput,
      document.querySelector('#addSellerModal input[name="pickup_latitude"]'),
      document.querySelector('#addSellerModal input[name="pickup_longitude"]')
    );

    var editInput = document.getElementById('pickup_address_edit');
    bindSellerAddressAutocomplete(
      editInput,
      document.querySelector('#editSellerModal input[name="pickup_latitude"]'),
      document.querySelector('#editSellerModal input[name="pickup_longitude"]')
    );
  };

  $(function () {
    $('#sellersTable').DataTable({
      "aLengthMenu": [[5, 10, 25, -1], [5, 10, 25, "All"]],
      "iDisplayLength": 5
    });

    $('#editSellerModal').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget);
      var seller = button.data('seller') || {};
      var actionUrl = button.data('update-url');

      var form = $('#editSellerForm');
      form.attr('action', actionUrl);
      form.find('[name="name"]').val(seller.name || '');
      form.find('[name="email"]').val(seller.email || '');
      form.find('[name="company_name"]').val(seller.company_name || '');
      form.find('[name="status"]').val(seller.status || 'active');
      form.find('[name="telephone_number"]').val(seller.telephone_number || '');
      form.find('[name="vat_number"]').val(seller.vat_number || '');
      form.find('[name="iban_code"]').val(seller.iban_code || '');
      form.find('[name="pickup_address"]').val(seller.pickup_address || '');
      form.find('[name="pickup_latitude"]').val(seller.pickup_latitude || '');
      form.find('[name="pickup_longitude"]').val(seller.pickup_longitude || '');
      form.find('[name="password"]').val('');
    });

    $('#addSellerModal, #editSellerModal').on('shown.bs.modal', function () {
      if (window.google && window.google.maps && window.google.maps.places) {
        window.initAdminSellerAddressAutocomplete();
      }
    });

    @if ($errors->any() && ! session('openEditSellerModal'))
      var addSellerModal = new bootstrap.Modal(document.getElementById('addSellerModal'));
      addSellerModal.show();
    @endif

    @if (session('openEditSellerModal'))
      var editSellerForm = $('#editSellerForm');
      editSellerForm.attr('action', '{{ route('admin.sellers.update', session('openEditSellerModal')) }}');
      editSellerForm.find('[name="name"]').val(@json(old('name', '')));
      editSellerForm.find('[name="email"]').val(@json(old('email', '')));
      editSellerForm.find('[name="company_name"]').val(@json(old('company_name', '')));
      editSellerForm.find('[name="status"]').val(@json(old('status', 'active')));
      editSellerForm.find('[name="telephone_number"]').val(@json(old('telephone_number', '')));
      editSellerForm.find('[name="vat_number"]').val(@json(old('vat_number', '')));
      editSellerForm.find('[name="iban_code"]').val(@json(old('iban_code', '')));
      editSellerForm.find('[name="pickup_address"]').val(@json(old('pickup_address', '')));
      editSellerForm.find('[name="pickup_latitude"]').val(@json(old('pickup_latitude', '')));
      editSellerForm.find('[name="pickup_longitude"]').val(@json(old('pickup_longitude', '')));
      editSellerForm.find('[name="password"]').val('');
      var editSellerModal = new bootstrap.Modal(document.getElementById('editSellerModal'));
      editSellerModal.show();
    @endif

    var $autoHideAlert = $('.js-auto-hide-alert');
    if ($autoHideAlert.length) {
      setTimeout(function () {
        $autoHideAlert.fadeOut(300);
      }, 5000);
    }

    if (window.google && window.google.maps && window.google.maps.places) {
      window.initAdminSellerAddressAutocomplete();
    }
  });
</script>
@if (config('services.google_maps.key'))
  <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places&loading=async&callback=initAdminSellerAddressAutocomplete" async defer></script>
@endif
@endpush

@include('spedfly.include.footer')
