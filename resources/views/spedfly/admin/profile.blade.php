@include('spedfly.include.header')

@php
    $name = old('name', $user?->name ?? 'Admin');
    $email = old('email', $user?->email ?? '-');
    $initial = strtoupper(substr($name, 0, 1));
    $status = strtolower((string) ($user?->status ?? 'active'));
    $statusClass = $status === 'active' ? 'bg-success' : 'bg-danger';
    $avatarUrl = $avatarUrl ?? null;
    $avatarClass = 'profile-avatar-image mx-auto mb-3';
@endphp

<div class="app-body">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h3 class="fw-bold mb-1">{{ __('ui.profile') }}</h3>
                <small class="text-muted">{{ __('ui.admin_account_overview_and_edit_profile_settings') }}</small>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-1"></i> {{ __('ui.back_to_dashboard') }}
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success js-auto-hide-alert">{{ session('success') }}</div>
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
            <div class="col-lg-4">
                <div class="card p-4">
                    <div class="text-center">
                        @if ($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="{{ $name }}" class="{{ $avatarClass }}" style="width: 96px; height: 96px; border-radius: 50%;">
                        @else
                            <div class="profile-avatar-fallback mx-auto mb-3" style="width: 96px; height: 96px; border-radius: 50%;">{{ $initial }}</div>
                        @endif
                        <h4 class="mb-1">{{ $name }}</h4>
                        <p class="text-muted mb-2">{{ $email }}</p>
                        <span class="badge {{ $statusClass }}">{{ ucfirst($status) }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <form method="post" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="card p-4 mb-4">
                        <h5 class="mb-3">{{ __('ui.edit_profile') }}</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted">{{ __('ui.name') }}</label>
                                <input type="text" name="name" value="{{ $name }}" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">{{ __('ui.email') }}</label>
                                <input type="email" name="email" value="{{ $email }}" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">{{ __('ui.account_type') }}</label>
                                <input type="text" class="form-control bg-light" value="{{ $user?->type ?? 'admin' }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">{{ __('ui.member_since') }}</label>
                                <input type="text" class="form-control bg-light" value="{{ $memberSince }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">{{ __('ui.current_password') }}</label>
                                <input type="password" name="current_password" class="form-control" placeholder="Required only for password change">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">{{ __('ui.new_password') }}</label>
                                <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">{{ __('ui.confirm_password') }}</label>
                                <input type="password" name="password_confirmation" class="form-control" placeholder="Repeat new password">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted">{{ __('ui.profile_image') }}</label>
                                <input type="file" name="avatar" class="form-control @error('avatar') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                                @error('avatar')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <small class="text-muted d-block mt-2">{{ __('ui.allowed_formats_jpg_png_webp_max_2_mb') }}</small>
                            </div>
                            @if ($avatarUrl)
                                <div class="col-12">
                                    <small class="text-muted d-block mb-2">{{ __('ui.current_image_preview') }}</small>
                                    <img src="{{ $avatarUrl }}" alt="{{ $name }}" class="profile-avatar-image rounded-3" style="width: 180px; height: 180px; border-radius: 18px;">
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary px-5">{{ __('ui.save_profile') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('page-scripts')
<script>
    $(function () {
        var $autoHideAlert = $('.js-auto-hide-alert');
        if ($autoHideAlert.length) {
            setTimeout(function () {
                $autoHideAlert.fadeOut(300);
            }, 4000);
        }
    });
</script>
@endpush

@include('spedfly.include.footer')
