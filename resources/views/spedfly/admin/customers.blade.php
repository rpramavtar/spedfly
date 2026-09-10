@include('spedfly.include.header')

<div class="app-body">
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <h1 class="mt-4">{{ __('ui.customers') }}</h1>
            <p>{{ __('ui.manage_customer_records_segments_and_contact_lists') }}</p>
          </div>
        </div>

        @if (session('success'))
          <div class="alert alert-success js-auto-hide-alert">{{ session('success') }}</div>
        @endif

        <div class="row mb-3">
          <div class="col-md-4">
            <div class="card p-3 mb-2">
              <h6>{{ __('ui.total_customers') }}</h6>
              <h4>{{ number_format($totalCustomers) }}</h4>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card p-3 mb-2">
              <h6>{{ __('ui.new_this_month') }}</h6>
              <h4>{{ number_format($newCustomersThisMonth) }}</h4>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card p-3 mb-2">
              <h6>{{ __('ui.sellers_with_customers') }}</h6>
              <h4>{{ number_format($sellersWithCustomers) }}</h4>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-body">
            <form class="row g-2" method="get" action="{{ route('admin.customers') }}">
              <div class="col-md-4">
                <input
                  type="text"
                  class="form-control"
                  name="search"
                  value="{{ $search }}"
                  placeholder="{{ __('ui.customer_name_email_or_phone') }}"
                >
              </div>
              <div class="col-md-3">
                <select class="form-select" name="seller">
                  <option value="">{{ __('ui.all_sellers') }}</option>
                  @foreach ($sellerOptions as $sellerOption)
                    <option value="{{ $sellerOption->id }}" {{ (string) $sellerOption->id === (string) $sellerId ? 'selected' : '' }}>
                      {{ $sellerOption->name }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-3">
                <select class="form-select" name="status">
                  <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>{{ __('ui.all_statuses') }}</option>
                  <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>{{ __('ui.active') }}</option>
                  <option value="blocked" {{ $statusFilter === 'blocked' ? 'selected' : '' }}>{{ __('ui.blocked') }}</option>
                </select>
              </div>
              <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">{{ __('ui.filter') }}</button>
              </div>
            </form>
          </div>
        </div>

        <div class="table-responsive">
          <table id="customersTable" class="table table-bordered">
            <thead class="table-light">
              <tr>
                <th>{{ __('ui.customer') }}</th>
                <th>{{ __('ui.email') }}</th>
                <th>{{ __('ui.phone') }}</th>
                <th>{{ __('ui.seller') }}</th>
                <th>{{ __('ui.orders') }}</th>
                <th>{{ __('ui.status') }}</th>
                <th>{{ __('ui.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($customers as $customer)
                @php
                  $status = $customer->status ?? 'active';
                  $statusLabel = ucfirst($status);
                  $badgeClass = $status === 'blocked' ? 'bg-danger' : 'bg-success';

                  $viewPayload = [
                      'name' => $customer->name ?? __('ui.customer') . ' #' . $customer->id,
                      'email' => $customer->email ?? '—',
                      'phone' => $customer->phone ?? '—',
                      'address' => $customer->address ?? '—',
                      'seller' => $customer->seller?->name ?? '—',
                      'orders' => $customer->orders_count,
                      'status' => $statusLabel,
                      'created_at' => optional($customer->created_at)->format('M d, Y h:i A'),
                  ];
                  $editPayload = [
                      'name' => $customer->name,
                      'email' => $customer->email,
                      'phone' => $customer->phone,
                      'address' => $customer->address,
                      'seller_id' => $customer->seller_id,
                      'status' => $status,
                  ];
                @endphp
                <tr>
                  <td class="d-flex align-items-center">
                    <img class="rounded-circle me-2" src="{{ asset('assets/admin/images/user.png') }}" width="40" alt="customer">
                    <div>
                      <strong>{{ $customer->name ?? __('ui.customer') . ' #' . $customer->id }}</strong><br>
                      <small class="text-muted">{{ $customer->seller?->name ?? __('ui.marketplace') }}</small>
                    </div>
                  </td>
                  <td>{{ $customer->email ?? '—' }}</td>
                  <td>{{ $customer->phone ?? '—' }}</td>
                  <td>{{ $customer->seller?->name ?? '—' }}</td>
                  <td>{{ $customer->orders_count }}</td>
                  <td><span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span></td>
                  <td>
                    <button
                      class="btn btn-sm btn-outline-primary me-1"
                      type="button"
                      data-bs-toggle="modal"
                      data-bs-target="#viewCustomerModal"
                      data-customer='@json($viewPayload)'
                    >
                      {{ __('ui.view') }}
                    </button>
                    <button
                      class="btn btn-sm btn-outline-warning me-1 edit-customer-btn"
                      type="button"
                      data-bs-toggle="modal"
                      data-bs-target="#editCustomerModal"
                      data-update-url="{{ route('admin.customers.update', $customer) }}"
                      data-customer='@json($editPayload)'
                    >
                      {{ __('ui.edit') }}
                    </button>
                    <form
                      action="{{ route('admin.customers.status', $customer) }}"
                      method="post"
                      class="d-inline"
                    >
                      @csrf
                      @method('PATCH')
                      <input type="hidden" name="status" value="{{ $status === 'blocked' ? 'active' : 'blocked' }}">
                      <button class="btn btn-sm btn-outline-danger me-1" type="submit">
                        {{ $status === 'blocked' ? __('ui.unblock') : __('ui.block') }}
                      </button>
                    </form>
                    <form
                      action="{{ route('admin.customers.destroy', $customer) }}"
                      method="post"
                      class="d-inline"
                      onsubmit="return confirm(@json(__('ui.confirm_delete_customer')));"
                    >
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-sm btn-outline-dark" type="submit">{{ __('ui.delete') }}</button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr class="empty-row">
                  <td class="text-center" colspan="8">{{ __('ui.no_customers_found') }}</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-3 px-4">
        <h4 class="modal-title fw-semibold" id="addCustomerModalLabel">{{ __('ui.add_new_customer') }}</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
      </div>
      <form action="{{ route('admin.customers.store') }}" method="post">
        @csrf
        <div class="modal-body px-4 py-3">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.customer_name') }}</label>
              <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="{{ __('ui.enter_customer_name') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.email') }}</label>
              <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="customer@email.com">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.phone') }}</label>
              <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="{{ __('ui.enter_phone_number') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.seller') }}</label>
              <select name="seller_id" class="form-select" required>
                <option value="">{{ __('ui.select_seller') }}</option>
                @foreach ($sellerOptions as $sellerOption)
                  <option value="{{ $sellerOption->id }}" {{ old('seller_id') == $sellerOption->id ? 'selected' : '' }}>
                    {{ $sellerOption->name }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">{{ __('ui.address') }}</label>
              <textarea name="address" class="form-control" rows="3" placeholder="{{ __('ui.customer_address_placeholder') }}">{{ old('address') }}</textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.status') }}</label>
              <select class="form-select" name="status">
                <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>{{ __('ui.active') }}</option>
                <option value="blocked" {{ old('status') === 'blocked' ? 'selected' : '' }}>{{ __('ui.blocked') }}</option>
              </select>
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
          <button type="submit" class="btn btn-primary">{{ __('ui.save_customer') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="viewCustomerModal" tabindex="-1" aria-labelledby="viewCustomerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewCustomerModalLabel">{{ __('ui.customer_details') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
      </div>
      <div class="modal-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">{{ __('ui.name') }}</dt>
          <dd class="col-sm-8" id="viewCustomerName">—</dd>
          <dt class="col-sm-4">{{ __('ui.email') }}</dt>
          <dd class="col-sm-8" id="viewCustomerEmail">—</dd>
          <dt class="col-sm-4">{{ __('ui.phone') }}</dt>
          <dd class="col-sm-8" id="viewCustomerPhone">—</dd>
          <dt class="col-sm-4">{{ __('ui.seller') }}</dt>
          <dd class="col-sm-8" id="viewCustomerSeller">—</dd>
          <dt class="col-sm-4">{{ __('ui.status') }}</dt>
          <dd class="col-sm-8" id="viewCustomerStatus">—</dd>
          <dt class="col-sm-4">{{ __('ui.orders') }}</dt>
          <dd class="col-sm-8" id="viewCustomerOrders">—</dd>
          <dt class="col-sm-4">{{ __('ui.address') }}</dt>
          <dd class="col-sm-8" id="viewCustomerAddress">—</dd>
          <dt class="col-sm-4">{{ __('ui.created_at') }}</dt>
          <dd class="col-sm-8" id="viewCustomerCreatedAt">—</dd>
        </dl>
      </div>
    </div>
  </div>
</div>



<div class="modal fade" id="editCustomerModal" tabindex="-1" aria-labelledby="editCustomerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-3 px-4">
        <h4 class="modal-title fw-semibold" id="editCustomerModalLabel">{{ __('ui.edit_customer') }}</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
      </div>
      <form id="editCustomerForm" method="post">
        @csrf
        @method('PATCH')
        <div class="modal-body px-4 py-3">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.customer_name') }}</label>
              <input type="text" name="name" class="form-control" placeholder="{{ __('ui.enter_customer_name') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.email') }}</label>
              <input type="email" name="email" class="form-control" placeholder="customer@email.com">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.phone') }}</label>
              <input type="text" name="phone" class="form-control" placeholder="{{ __('ui.enter_phone_number') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.seller') }}</label>
              <select name="seller_id" class="form-select" required>
                <option value="">{{ __('ui.select_seller') }}</option>
                @foreach ($sellerOptions as $sellerOption)
                  <option value="{{ $sellerOption->id }}">{{ $sellerOption->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">{{ __('ui.address') }}</label>
              <textarea name="address" class="form-control" rows="3" placeholder="{{ __('ui.customer_address_placeholder') }}"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('ui.status') }}</label>
              <select class="form-select" name="status">
                <option value="active">{{ __('ui.active') }}</option>
                <option value="blocked">{{ __('ui.blocked') }}</option>
              </select>
            </div>
          </div>
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
  $(function () {
    var hasEmptyRow = $('#customersTable tbody tr.empty-row').length > 0;

    if (!hasEmptyRow) {
      $('#customersTable').DataTable({
        "aLengthMenu": [[5, 10, 25, -1], [5, 10, 25, @json(__('ui.all'))]],
        "iDisplayLength": 5
      });
    }

    $('#viewCustomerModal').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget);
      var customer = button.data('customer') || {};

      $('#viewCustomerName').text(customer.name || '—');
      $('#viewCustomerEmail').text(customer.email || '—');
      $('#viewCustomerPhone').text(customer.phone || '—');
      $('#viewCustomerAddress').text(customer.address || '—');
      $('#viewCustomerSeller').text(customer.seller || '—');
      $('#viewCustomerStatus').text(customer.status || '—');
      $('#viewCustomerOrders').text(typeof customer.orders === 'number' ? customer.orders : '0');
      $('#viewCustomerCreatedAt').text(customer.created_at || '—');
    });

    $('#editCustomerModal').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget);
      var customer = button.data('customer') || {};
      var actionUrl = button.data('update-url');

      var form = $('#editCustomerForm');
      form.attr('action', actionUrl);
      form.find('[name="name"]').val(customer.name || '');
      form.find('[name="email"]').val(customer.email || '');
      form.find('[name="phone"]').val(customer.phone || '');
      form.find('[name="address"]').val(customer.address || '');
      form.find('[name="seller_id"]').val(customer.seller_id || '');
      form.find('[name="status"]').val(customer.status || 'active');
    });



    @if ($errors->any())
      var addCustomerModal = new bootstrap.Modal(document.getElementById('addCustomerModal'));
      addCustomerModal.show();
    @endif

    var $autoHide = $('.js-auto-hide-alert');
    if ($autoHide.length) {
      setTimeout(function () {
        $autoHide.fadeOut(300);
      }, 5000);
    }
  });
</script>
@endpush

@include('spedfly.include.footer')
