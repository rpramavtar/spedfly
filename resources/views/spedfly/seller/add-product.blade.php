@include('spedfly.include.header')

@php
    $pageTitle = $isEdit ? __('ui.edit_product') : __('ui.add_new_product');
    $cardTitle = $isEdit ? __('ui.edit_product') : __('ui.add_product');
    $submitLabel = $isEdit ? __('ui.update_product') : __('ui.save_product');
    $formAction = $isEdit ? route('seller.products.update', $product) : route('seller.add-product.store');
@endphp

<div
    class="app-body"
    @if ($isEdit)
        data-product-feed-url="{{ route('seller.products.single.feed', $product) }}"
        data-product-signature="{{ $product->id . '|' . optional($product->updated_at)?->timestamp }}"
    @endif
>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-box me-2 text-primary"></i>{{ $pageTitle }}</h3>
            </div>
            <a href="{{ route('seller.products') }}" class="btn btn-primary"><i class="bi bi-eye"></i> {{ __('ui.view_all') }}</a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning js-auto-hide-alert">{{ session('warning') }}</div>
        @endif

        <div class="row g-4">
            <div class="col-lg-12">
                <div class="card card-elegant p-4">
                    <h6 class="fw-semibold mb-3">{{ $cardTitle }}</h6>
                    <form method="post" action="{{ $formAction }}" enctype="multipart/form-data">
                        @csrf
                        @if ($isEdit)
                            @method('PUT')
                        @endif
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('ui.sku') }}</label>
                                <input type="text" name="sku" class="form-control" value="{{ old('sku', $product->sku) }}" placeholder="{{ __('ui.enter_sku_code') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('ui.product_name') }}</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $product->name) }}" placeholder="{{ __('ui.enter_product_name') }}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('ui.description') }}</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="{{ __('ui.enter_product_description') }}">{{ old('description', $product->description) }}</textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('ui.category') }}</label>
                                <select name="category" class="form-select">
                                    <option value="">{{ __('ui.select_category') }}</option>
                                    <option value="Electronics" @selected(old('category', $product->category) === 'Electronics')>{{ __('ui.electronics') }}</option>
                                    <option value="Fashion" @selected(old('category', $product->category) === 'Fashion')>{{ __('ui.fashion') }}</option>
                                    <option value="Home" @selected(old('category', $product->category) === 'Home')>{{ __('ui.home_category') }}</option>
                                    <option value="Accessories" @selected(old('category', $product->category) === 'Accessories')>{{ __('ui.accessories') }}</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('ui.price') }}</label>
                                <input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ old('price', $product->price ?? '0.00') }}" placeholder="0.00" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('ui.total_stock') }}</label>
                                <input type="number" min="0" name="stock" class="form-control" value="{{ old('stock', $product->stock) }}" placeholder="{{ __('ui.enter_quantity') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('ui.low_stock_alert_qty') }}</label>
                                <input type="number" min="0" name="low_stock_alert" class="form-control" value="{{ old('low_stock_alert', $product->low_stock_alert) }}" placeholder="{{ __('ui.example_value', ['value' => 10]) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('ui.product_image') }}</label>
                                <input type="file" name="image" class="form-control">
                                @if ($isEdit && $product->image_path)
                                    <small class="text-muted d-block mt-2">{{ __('ui.current_image_path', ['path' => $product->image_path]) }}</small>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label d-block">{{ __('ui.status') }}</label>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="status" value="deactive">
                                    <input class="form-check-input" type="checkbox" name="status" value="active" @checked(old('status', $product->status ?: 'active') === 'active')>
                                    <label class="form-check-label">{{ __('ui.active_product') }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-5 text-end">
                            <a href="{{ route('seller.products') }}" class="btn btn-light me-2">{{ __('ui.cancel') }}</a>
                            <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('page-scripts')
<script>
  $(function () {
    var $appBody = $('.app-body');
    var feedUrl = $appBody.data('product-feed-url');
    var currentSignature = String($appBody.data('product-signature') || '');
    var isDirty = false;

    if (feedUrl) {
      var $form = $('form[action="{{ $formAction }}"]');
      $form.on('input change', 'input, textarea, select', function () {
        isDirty = true;
      });

      setInterval(function () {
        if (isDirty) {
          return;
        }

        fetch(feedUrl, { headers: { 'Accept': 'application/json' } })
          .then(function (response) {
            return response.ok ? response.json() : null;
          })
          .then(function (payload) {
            if (!payload || !payload.signature) {
              return;
            }

            if (String(payload.signature) !== currentSignature) {
              window.location.reload();
            }
          })
          .catch(function () {
            // Ignore transient polling failures.
          });
      }, 15000);
    }
  });
</script>
@endpush

@include('spedfly.include.footer')
