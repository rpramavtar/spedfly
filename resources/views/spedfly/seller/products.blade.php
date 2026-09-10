@include('spedfly.include.header')

<div
    class="app-body"
    data-products-feed-url="{{ route('seller.products.feed') }}"
    data-products-signature="{{ $productsFeedSignature ?? '' }}"
>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-box me-2 text-primary"></i>{{ __('ui.my_products') }}</h3>
            </div>
            <a href="{{ route('seller.add-product') }}" class="btn btn-primary"><i class="bi bi-box"></i> {{ __('ui.add_product') }}</a>
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
                    <h6>{{ __('ui.in_stock') }}</h6>
                    <h4 class="text-success">{{ $inStockCount }}</h4>
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

        <div class="row g-4">
            <div class="col-lg-12">
                <div class="card card-elegant p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h6 class="fw-semibold mb-0">{{ __('ui.products') }}</h6>
                        <form method="post" action="{{ route('seller.products.sync-shopify') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-arrow-repeat me-1"></i>{{ __('ui.sync_shopify') }}
                            </button>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table id="productsTable" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">ID</th>
                                    <th>{{ __('ui.sku') }}</th>
                                    <th>{{ __('ui.image') }}</th>
                                    <th>{{ __('ui.product_name') }}</th>
                                    <th>{{ __('ui.description') }}</th>
                                    <th>{{ __('ui.price') }}</th>
                                    <th>{{ __('ui.total_stock') }}</th>
                                    <th>{{ __('ui.reserved') }}</th>
                                    <th>{{ __('ui.available') }}</th>
                                    <th>{{ __('ui.status') }}</th>
                                    <th>{{ __('ui.shopify') }}</th>
                                    <th>{{ __('ui.last_updated') }}</th>
                                    <th>{{ __('ui.action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($products as $product)
                                    @php
                                        $reserved = 0;
                                        $available = max(0, $product->stock - $reserved);
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
                                        <td data-order="{{ $product->id }}"><span class="badge bg-light text-dark border fw-bold">#{{ $product->id }}</span></td>
                                        <td>{{ $product->sku }}</td>
                                        <td>
                                            <img
                                                src="{{ $product->image_url ?? asset('assets/admin/images/user.png') }}"
                                                alt="{{ $product->name }}"
                                                style="width:56px;height:56px;object-fit:cover;border-radius:10px;"
                                            >
                                        </td>
                                        <td>{{ $product->name }}</td>
                                        <td>{{ $product->description ?: '-' }}</td>
                                        <td>{{ system_currency_format($product->price) }}</td>
                                        <td>{{ $product->stock }}</td>
                                        <td>{{ $reserved }}</td>
                                        <td>{{ $available }}</td>
                                        <td><span class="badge {{ $badgeClass }}">{{ $badgeText }}</span></td>
                                        <td><span class="badge {{ $shopifyBadge[0] }}">{{ $shopifyBadge[1] }}</span></td>
                                        <td>{{ $product->updated_at->format('d M Y') }}</td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-2">
                                            <a href="{{ route('seller.products.edit', $product) }}" class="btn btn-outline-primary btn-sm" title="{{ __('ui.edit_product') }}">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="post" action="{{ route('seller.products.destroy', $product) }}" onsubmit="return confirm('{{ __('ui.confirm_delete_product') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-outline-danger btn-sm" type="submit" title="{{ __('ui.delete_product') }}">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center">{{ __('ui.no_products_found') }}</td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
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
</div>

@push('page-scripts')
<script>
  $(function () {
    var $appBody = $('.app-body');
    var feedUrl = $appBody.data('products-feed-url');
    var currentSignature = String($appBody.data('products-signature') || '');

    $('#productsTable').DataTable({
      aLengthMenu: [[5, 10, 25, -1], [5, 10, 25, '{{ __('ui.all') }}']],
      iDisplayLength: 5,
      order: []
    });

    var $autoHideAlert = $('.js-auto-hide-alert');
    if ($autoHideAlert.length) {
      setTimeout(function () {
        $autoHideAlert.fadeOut(300);
      }, 5000);
    }

    if (feedUrl) {
      setInterval(function () {
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

