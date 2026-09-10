@include('spedfly.include.header')

@php
    $shopifyConnection = $shopifyConnection ?? [
        'connected' => false,
        'shop_domain' => '',
        'scope' => '',
        'connected_at' => null,
        'has_product_write_scope' => false,
        'has_order_write_scope' => false,
    ];

    $activeTab = old('tab', request()->query('tab', 'profile'));
    if (! in_array($activeTab, ['profile', 'integration', 'api', 'notification', 'payment', 'security', 'team'], true)) {
        $activeTab = 'profile';
    }

    $profileName = $user?->name ?? 'Seller';
    $profileEmail = $user?->email ?? '';
    $profileInitial = strtoupper(substr($profileName, 0, 1));
    $profileAvatarUrl = $user?->avatar_path ? asset($user->avatar_path) : null;
    $companyProfile = $companyProfile ?? [
        'company_name' => old('company_name', $user?->company_name ?? ''),
        'support_email' => old('support_email', $user?->email ?? $profileEmail),
        'support_phone' => old('support_phone', $user?->support_phone ?? ''),
        'vat_number' => old('vat_number', $user?->vat_number ?? ''),
        'iban_code' => old('iban_code', $user?->iban_code ?? ''),
        'pickup_address' => old('pickup_address', $user?->pickup_address ?? ''),
        'pickup_latitude' => old('pickup_latitude', $user?->pickup_latitude ?? ''),
        'pickup_longitude' => old('pickup_longitude', $user?->pickup_longitude ?? ''),
    ];

    $googleMapsApiKey = config('services.google_maps.key');

@endphp

<style>
    .settings-shell {
        background: #fbf3dd;
        min-height: calc(100vh - 72px);
        padding: 12px 0 72px;
    }

    .settings-card,
    .settings-side-card {
        border: 0;
        border-radius: 22px;
        box-shadow: 0 10px 30px rgba(34, 34, 34, 0.06);
    }

    .settings-side-card {
        padding: 18px;
        position: sticky;
        top: 16px;
    }

    .settings-nav .nav-link {
        border: 0;
        color: #4b5563;
        border-radius: 16px;
        padding: 14px 16px;
        font-weight: 500;
        text-align: left;
    }

    .settings-nav .nav-link.active {
        background: #fff4d6;
        color: #f59e0b;
        box-shadow: inset 4px 0 0 #f59e0b;
    }

    .settings-nav .nav-link i {
        width: 18px;
        display: inline-block;
        margin-right: 10px;
        text-align: center;
    }

    .settings-avatar {
        width: 100%;
        max-width: 172px;
        aspect-ratio: 1 / 1;
        object-fit: cover;
        border-radius: 12px;
        background: #d8f3f6;
    }

    .settings-title {
        color: #25304a;
        font-weight: 700;
        letter-spacing: -0.02em;
    }

    .settings-page-btn {
        background: #f8ab00;
        border-color: #f8ab00;
        color: #fff;
        border-radius: 8px;
        font-weight: 600;
    }

    .settings-page-btn:hover,
    .settings-page-btn:focus {
        background: #e29700;
        border-color: #e29700;
        color: #fff;
    }

    .settings-main {
        padding-bottom: 24px;
    }

    .settings-card {
        padding: 1.5rem !important;
    }

    @media (max-width: 991.98px) {
        .settings-side-card {
            position: static;
            top: auto;
        }
        .settings-shell {
            padding-bottom: 96px;
        }
    }
</style>

<div class="app-body settings-shell">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="settings-title mb-0"><i class="bi bi-gear-fill me-2 text-warning"></i>{{ __('ui.settings') }}</h4>
            </div>
            <a class="btn settings-page-btn px-3" href="{{ route('seller.dashboard') }}">
                <i class="bi bi-gear-fill me-2"></i>{{ __('ui.back_to_dashboard') }}
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success js-auto-hide-alert">{{ session('success') }}</div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning js-auto-hide-alert">{{ session('warning') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-xl-3 col-lg-4">
                <div class="card settings-side-card mb-4">
                    <div class="nav nav-pills flex-column gap-2 settings-nav">
                        <button class="nav-link {{ $activeTab === 'profile' ? 'active' : '' }}"
                            data-bs-target="#profile"
                            data-bs-toggle="pill"
                            type="button">
                            <i class="bi bi-building"></i>{{ __('ui.company_profile') }}
                        </button>
                        <button class="nav-link {{ $activeTab === 'integration' ? 'active' : '' }}"
                            data-bs-target="#integration"
                            data-bs-toggle="pill"
                            type="button">
                            <i class="bi bi-plug"></i>{{ __('ui.integrations') }}
                        </button>
                        <button class="nav-link {{ $activeTab === 'api' ? 'active' : '' }}" data-bs-target="#api" data-bs-toggle="pill" type="button"><i class="bi bi-key"></i>{{ __('ui.api_webhooks') }}</button>
                        <button class="nav-link {{ $activeTab === 'notification' ? 'active' : '' }}" data-bs-target="#notification" data-bs-toggle="pill" type="button"><i class="bi bi-bell"></i>{{ __('ui.notifications') }}</button>
                        <button class="nav-link {{ $activeTab === 'payment' ? 'active' : '' }}" data-bs-target="#payment" data-bs-toggle="pill" type="button"><i class="bi bi-credit-card"></i>{{ __('ui.payment') }}</button>
                        <button class="nav-link {{ $activeTab === 'security' ? 'active' : '' }}" data-bs-target="#security" data-bs-toggle="pill" type="button"><i class="bi bi-shield-lock"></i>{{ __('ui.security') }}</button>
                      <!--  <button class="nav-link {{ $activeTab === 'team' ? 'active' : '' }}" data-bs-target="#team" data-bs-toggle="pill" type="button"><i class="bi bi-people"></i>Team</button> -->
                    </div>
                </div>
            </div>

            <div class="col-xl-9 col-lg-8 settings-main">
                <div class="tab-content">
                    <div class="tab-pane fade {{ $activeTab === 'profile' ? 'show active' : '' }}" id="profile">
                        <form method="post" action="{{ route('seller.settings.update') }}" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="tab" value="profile">

                            <div class="card settings-card mb-4">
                                <h5 class="settings-title mb-3">{{ __('ui.company_profile') }}</h5>
                                <div class="row g-3 align-items-start mb-4">
                                <div class="col-md-3 text-center">
                                    @if ($profileAvatarUrl)
                                        <img class="settings-avatar mb-3" src="{{ $profileAvatarUrl }}" alt="{{ $profileName }}">
                                    @else
                                        <div class="settings-avatar mb-3 d-flex align-items-center justify-content-center mx-auto text-primary fw-bold" style="font-size:54px;">
                                            {{ $profileInitial }}
                                        </div>
                                    @endif
                                    <label class="form-label w-100 text-start" for="avatar">{{ __('ui.image') }}</label>
                                    <input class="form-control @error('avatar') is-invalid @enderror" id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp">
                                    @error('avatar')
                                        <div class="invalid-feedback d-block text-start">{{ $message }}</div>
                                    @enderror
                                </div>
                                    <div class="col-md-9">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label" for="company_name">{{ __('ui.company_name') }}</label>
                                                <input class="form-control @error('company_name') is-invalid @enderror" id="company_name" name="company_name" type="text" value="{{ $companyProfile['company_name'] }}" placeholder="{{ __('ui.company_name') }}">
                                                @error('company_name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="support_email">{{ __('ui.email_address') }}</label>
                                                <input class="form-control @error('support_email') is-invalid @enderror" id="support_email" name="support_email" type="email" value="{{ $companyProfile['support_email'] }}" placeholder="{{ __('ui.email_address') }}">
                                                @error('support_email')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="support_phone">{{ __('ui.telephone_number') }}</label>
                                                <input class="form-control @error('support_phone') is-invalid @enderror" id="support_phone" name="support_phone" type="text" value="{{ $companyProfile['support_phone'] }}" placeholder="{{ __('ui.telephone_number') }}">
                                                @error('support_phone')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="vat_number">{{ __('ui.vat_number') }}</label>
                                                <input class="form-control @error('vat_number') is-invalid @enderror" id="vat_number" name="vat_number" type="text" value="{{ $companyProfile['vat_number'] }}" placeholder="{{ __('ui.vat_number') }}">
                                                @error('vat_number')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="iban_code">{{ __('ui.iban_code') }}</label>
                                                <input class="form-control @error('iban_code') is-invalid @enderror" id="iban_code" name="iban_code" type="text" value="{{ $companyProfile['iban_code'] }}" placeholder="{{ __('ui.iban_code') }}">
                                                @error('iban_code')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label" for="pickup_address">{{ __('ui.address') }}</label>
                                        <textarea class="form-control @error('pickup_address') is-invalid @enderror" id="pickup_address" name="pickup_address" rows="4" placeholder="{{ __('ui.address') }}">{{ $companyProfile['pickup_address'] }}</textarea>
                                        @error('pickup_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <input type="hidden" id="pickup_latitude" name="pickup_latitude" value="{{ $companyProfile['pickup_latitude'] }}">
                                        <input type="hidden" id="pickup_longitude" name="pickup_longitude" value="{{ $companyProfile['pickup_longitude'] }}">
                                    </div>
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn settings-page-btn px-5">
                                    <i class="bi bi-check-circle me-2"></i>{{ __('ui.save_profile') }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade {{ $activeTab === 'integration' ? 'show active' : '' }}" id="integration">
                        <div class="card settings-card mb-4">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h5 class="settings-title mb-1">{{ __('ui.store_integrations') }}</h5>
                                    <p class="text-muted mb-0">{{ __('ui.connect_shopify_to_sync_products_orders_and_webhooks_with_your_seller_account') }}</p>
                                </div>
                                @if ($shopifyConnection['connected'])
                                    <span class="badge bg-success align-self-start">{{ __('ui.connected') }}</span>
                                @else
                                    <span class="badge bg-warning text-dark align-self-start">{{ __('ui.not_connected') }}</span>
                                @endif
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="border rounded-3 p-3 h-100 bg-white">
                                        <div class="text-muted small mb-1">{{ __('ui.shop_domain') }}</div>
                                        <div class="fw-semibold">{{ $shopifyConnection['shop_domain'] ?: '-' }}</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded-3 p-3 h-100 bg-white">
                                        <div class="text-muted small mb-1">{{ __('ui.connected_at') }}</div>
                                        <div class="fw-semibold">{{ optional($shopifyConnection['connected_at'])->format('M d, Y h:i A') ?? __('ui.not_connected') }}</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded-3 p-3 h-100 bg-white">
                                        <div class="text-muted small mb-1">{{ __('ui.scopes') }}</div>
                                        <div class="fw-semibold">
                                            {{ filled($shopifyConnection['scope']) ? $shopifyConnection['scope'] : __('ui.none') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card settings-card">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div>
                                    <h6 class="settings-title mb-1">{{ __('ui.shopify') }}</h6>
                                    <p class="text-muted mb-0">
                                        @if ($shopifyConnection['connected'])
                                            {{ __('ui.connected_to') }} {{ $shopifyConnection['shop_domain'] }}. {{ __('ui.reconnect_anytime_if_you_change_the_app_scopes') }}
                                        @else
                                            {{ __('ui.start_an_oauth_connection_by_entering_your_shopify_store_domain') }}
                                        @endif
                                    </p>
                                </div>
                                @if ($shopifyConnection['connected'])
                                    <span class="badge bg-success">{{ __('ui.active') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ __('ui.inactive') }}</span>
                                @endif
                            </div>

                            <form method="post" action="{{ route('seller.shopify.connect') }}" class="row g-3 align-items-end">
                                @csrf
                                <input type="hidden" name="tab" value="integration">
                                <div class="col-md-8">
                                    <label class="form-label" for="integration_shop_domain">{{ __('ui.shop_domain') }}</label>
                                    <input
                                        type="text"
                                        class="form-control @error('shop_domain') is-invalid @enderror"
                                        id="integration_shop_domain"
                                        name="shop_domain"
                                        value="{{ old('shop_domain', $shopifyConnection['shop_domain']) }}"
                                        placeholder="your-store.myshopify.com"
                                    >
                                    @error('shop_domain')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-success w-100">
                                        {{ $shopifyConnection['connected'] ? __('ui.reconnect_shopify') : __('ui.connect_shopify') }}
                                    </button>
                                </div>
                            </form>

                            @if ($shopifyConnection['connected'])
                                <div class="d-flex justify-content-between align-items-center border-top mt-4 pt-4 flex-wrap gap-2">
                                    <div class="text-muted small">
                                        {{ __('ui.shopify_is_connected_and_can_sync_inventory_and_order_webhooks') }}
                                    </div>
                                    <form method="post" action="{{ route('seller.shopify.disconnect') }}">
                                        @csrf
                                        <input type="hidden" name="tab" value="integration">
                                        <button type="submit" class="btn btn-outline-danger">
                                            {{ __('ui.disconnect_shopify') }}
                                        </button>
                                    </form>
                                </div>
                            @endif

                            <div class="d-flex justify-content-between align-items-center mt-4 pt-4 border-top">
                                <span>{{ __('ui.woocommerce') }}</span>
                                <button type="button" class="btn btn-success">{{ __('ui.connect') }}</button>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade {{ $activeTab === 'api' ? 'show active' : '' }}" id="api">
                        <form method="post" action="{{ route('seller.settings.update') }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="tab" value="api">

                            <div class="card settings-card p-4">
                                <h6 class="settings-title">{{ __('ui.api_webhooks') }}</h6>

                                <label class="form-label mt-3">{{ __('ui.api_key') }}</label>
                                <div class="input-group mb-3">
                                    <input class="form-control" name="api_key" readonly type="text" value="{{ $apiSettings['api_key'] }}">
                                    <button class="btn btn-outline-secondary" type="button" id="copyApiKeyBtn">{{ __('ui.copy') }}</button>
                                </div>

                                <label class="form-label">{{ __('ui.webhook_url') }}</label>
                                <input class="form-control @error('webhook_url') is-invalid @enderror" name="webhook_url" type="url" value="{{ $apiSettings['webhook_url'] }}" placeholder="https://your-domain.com/webhooks/spedfly">
                                @error('webhook_url')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror

                                <button class="btn btn-primary px-4 mt-3" type="submit">{{ __('ui.save_settings') }}</button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade {{ $activeTab === 'notification' ? 'show active' : '' }}" id="notification">
                        <form method="post" action="{{ route('seller.settings.update') }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="tab" value="notification">

                            <div class="card settings-card p-4">
                                <h6 class="settings-title">{{ __('ui.notification_settings') }}</h6>

                                <div class="form-check form-switch mb-2 mt-3">
                                    <input class="form-check-input" id="order_created" name="order_created" type="checkbox" value="1" {{ $notificationSettings['order_created'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="order_created">{{ __('ui.order_created') }}</label>
                                </div>

                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" id="shipment_delayed" name="shipment_delayed" type="checkbox" value="1" {{ $notificationSettings['shipment_delayed'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="shipment_delayed">{{ __('ui.shipment_delayed') }}</label>
                                </div>

                                <button class="btn btn-primary px-4 mt-3" type="submit">{{ __('ui.save_settings') }}</button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade {{ $activeTab === 'payment' ? 'show active' : '' }}" id="payment">
                        <form method="post" action="{{ route('seller.settings.update') }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="tab" value="payment">

                            <div class="card settings-card p-4">
                                <h6 class="settings-title">{{ __('ui.payment_settings') }}</h6>

                                <select class="form-select mb-3 mt-3" name="cod_status">
                                    <option value="enable" {{ $paymentSettings['cod_status'] === 'enable' ? 'selected' : '' }}>{{ __('ui.enable_cod') }}</option>
                                    <option value="disable" {{ $paymentSettings['cod_status'] === 'disable' ? 'selected' : '' }}>{{ __('ui.disable_cod') }}</option>
                                </select>

                                <select class="form-select" name="online_payment_status">
                                    <option value="enable" {{ $paymentSettings['online_payment_status'] === 'enable' ? 'selected' : '' }}>{{ __('ui.enable_online_payments') }}</option>
                                    <option value="disable" {{ $paymentSettings['online_payment_status'] === 'disable' ? 'selected' : '' }}>{{ __('ui.disable_online_payments') }}</option>
                                </select>

                                <button class="btn btn-primary px-4 mt-3" type="submit">{{ __('ui.save_settings') }}</button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade {{ $activeTab === 'security' ? 'show active' : '' }}" id="security">
                        <form method="post" action="{{ route('seller.settings.update') }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="tab" value="security">

                            <div class="card settings-card p-4">
                                <h6 class="settings-title">{{ __('ui.security_settings') }}</h6>
                                <input class="form-control mb-3 mt-3 @error('new_password') is-invalid @enderror" name="new_password" placeholder="{{ __('ui.new_password') }}" type="password">
                                @error('new_password')
                                    <div class="text-danger small mb-2">{{ $message }}</div>
                                @enderror

                                <div class="form-check form-switch">
                                    <input class="form-check-input" id="two_factor_enabled" name="two_factor_enabled" type="checkbox" value="1" {{ $securitySettings['two_factor_enabled'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="two_factor_enabled">{{ __('ui.enable_two_factor_auth') }}</label>
                                </div>

                                <button class="btn btn-danger px-4 mt-3" type="submit">{{ __('ui.update_security') }}</button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade {{ $activeTab === 'team' ? 'show active' : '' }}" id="team">
                        <div class="card settings-card p-4">
                            <h6 class="settings-title">{{ __('ui.team_members') }}</h6>
                            <table class="table align-middle mt-3">
                                <thead>
                                    <tr>
                                        <th>{{ __('ui.name') }}</th>
                                        <th>{{ __('ui.role') }}</th>
                                        <th>{{ __('ui.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>John Doe</td>
                                        <td>{{ __('ui.admin') }}</td>
                                        <td><span class="badge bg-success">{{ __('ui.active') }}</span></td>
                                    </tr>
                                    <tr>
                                        <td>Emma Smith</td>
                                        <td>{{ __('ui.manager') }}</td>
                                        <td><span class="badge bg-warning">{{ __('ui.pending') }}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                            <button class="btn btn-primary px-4" type="button">{{ __('ui.add_member') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function initSellerAddressAutocomplete() {
        if (!window.google || !window.google.maps || !window.google.maps.places) {
            return;
        }

        var input = document.getElementById('pickup_address');
        var latField = document.getElementById('pickup_latitude');
        var lngField = document.getElementById('pickup_longitude');

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

            if (place.formatted_address) {
                input.value = place.formatted_address;
            } else if (place.name) {
                input.value = place.name;
            }

            if (place.geometry && place.geometry.location) {
                latField.value = place.geometry.location.lat();
                lngField.value = place.geometry.location.lng();
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

    document.addEventListener('DOMContentLoaded', function () {
        var copyBtn = document.getElementById('copyApiKeyBtn');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var apiInput = document.querySelector('input[name="api_key"]');
                if (!apiInput) return;
                apiInput.select();
                apiInput.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(apiInput.value);
            });
        }
    });
</script>
@if ($googleMapsApiKey)
<script
    src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&libraries=places&loading=async&callback=initSellerAddressAutocomplete"
    async
    defer
></script>
@endif

@include('spedfly.include.footer')
