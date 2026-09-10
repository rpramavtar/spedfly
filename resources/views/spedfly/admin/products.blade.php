@include('spedfly.include.header')

<div class="app-body">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                    <h3 class="fw-bold mb-1"><i class="bi bi-box-seam me-2 text-primary"></i>{{ __('ui.products') }}</h3>
                    <p class="text-muted mb-0">{{ __('ui.all_products_from_all_sellers_in_one_place') }}</p>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success js-auto-hide-alert">{{ session('success') }}</div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning js-auto-hide-alert">{{ session('warning') }}</div>
        @endif

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card card-kpi p-3">
                    <h6>{{ __('ui.total_products') }}</h6>
                    <h4>{{ $totalProducts }}</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-kpi p-3">
                    <h6>{{ __('ui.active_products') }}</h6>
                    <h4 class="text-success">{{ $activeProductsCount }}</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-kpi p-3">
                    <h6>{{ __('ui.low_stock') }}</h6>
                    <h4 class="text-warning">{{ $lowStockCount }}</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-kpi p-3">
                    <h6>{{ __('ui.out_of_stock') }}</h6>
                    <h4 class="text-danger">{{ $outOfStockCount }}</h4>
                </div>
            </div>
        </div>

        <div class="card card-elegant p-4">
            <form class="row g-3 mb-3" method="get" action="{{ route('admin.products') }}">
                <div class="col-md-5">
                    <label class="form-label">{{ __('ui.search') }}</label>
                    <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="{{ __('ui.sku_product_name_category_seller') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('ui.seller') }}</label>
                    <select name="seller" class="form-select">
                        <option value="">{{ __('ui.all_sellers') }}</option>
                        @foreach ($sellerOptions as $seller)
                            <option value="{{ $seller->id }}" @selected((string) $sellerId === (string) $seller->id)>{{ $seller->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('ui.status') }}</label>
                    <select name="status" class="form-select">
                        <option value="all" @selected($statusFilter === 'all')>{{ __('ui.all') }}</option>
                        <option value="active" @selected($statusFilter === 'active')>{{ __('ui.active') }}</option>
                        <option value="inactive" @selected($statusFilter === 'inactive')>{{ __('ui.inactive') }}</option>
                        <option value="draft" @selected($statusFilter === 'draft')>{{ __('ui.draft') }}</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100">{{ __('ui.filter') }}</button>
                    <a href="{{ route('admin.products') }}" class="btn btn-outline-secondary w-100">{{ __('ui.reset') }}</a>
                </div>
            </form>

            <div class="table-responsive">
                <table id="productsTable" class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('ui.sku') }}</th>
                            <th>{{ __('ui.image') }}</th>
                            <th>{{ __('ui.product') }}</th>
                            <th>{{ __('ui.seller') }}</th>
                            <th>{{ __('ui.description') }}</th>
                            <th>{{ __('ui.price') }}</th>
                            <th>{{ __('ui.stock') }}</th>
                            <th>{{ __('ui.reserved') }}</th>
                            <th>{{ __('ui.available') }}</th>
                            <th>{{ __('ui.status') }}</th>
                            <th>{{ __('ui.shopify') }}</th>
                            <th>{{ __('ui.last_updated') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            @php
                                $reserved = 0;
                                $available = max(0, (int) $product->stock - $reserved);
                                if ($product->stock <= 0) {
                                    $badgeClass = 'bg-danger';
                                    $badgeText = __('ui.out_of_stock');
                                } elseif ($product->low_stock_alert > 0 && $product->stock <= $product->low_stock_alert) {
                                    $badgeClass = 'bg-warning text-dark';
                                    $badgeText = __('ui.low_stock');
                                } else {
                                    $badgeClass = 'bg-success';
                                    $badgeText = __('ui.in_stock');
                                }
                                $shopifyBadge = match ($product->shopify_sync_status) {
                                    'synced' => ['bg-success', __('ui.synced')],
                                    'failed' => ['bg-danger', __('ui.failed')],
                                    default => ['bg-warning text-dark', __('ui.pending')],
                                };
                            @endphp
                            <tr>
                                <td>{{ $product->sku }}</td>
                                <td>
                                    <img
                                        src="{{ $product->image_url ?? asset('assets/admin/images/user.png') }}"
                                        alt="{{ $product->name }}"
                                        style="width:56px;height:56px;object-fit:cover;border-radius:10px;"
                                    >
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $product->name }}</div>
                                    <small class="text-muted">{{ $product->category ?: '-' }}</small>
                                </td>
                                <td>{{ $product->seller?->name ?: '-' }}</td>
                                <td>{{ $product->description ?: '-' }}</td>
                                <td>{{ system_currency_format($product->price) }}</td>
                                <td>{{ $product->stock }}</td>
                                <td>{{ $reserved }}</td>
                                <td>{{ $available }}</td>
                                <td><span class="badge {{ $badgeClass }}">{{ $badgeText }}</span></td>
                                <td><span class="badge {{ $shopifyBadge[0] }}">{{ $shopifyBadge[1] }}</span></td>
                                <td>{{ optional($product->updated_at)->format('d M Y') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4">{{ __('ui.no_products_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('page-scripts')
<script>
  $(function () {
    $('#productsTable').DataTable({
      aLengthMenu: [[10, 25, 50, -1], [10, 25, 50, @json(__('ui.all'))]],
      iDisplayLength: 10,
      order: []
    });

    var $autoHideAlert = $('.js-auto-hide-alert');
    if ($autoHideAlert.length) {
      setTimeout(function () {
        $autoHideAlert.fadeOut(300);
      }, 5000);
    }
  });
</script>
@endpush

@include('spedfly.include.footer')





