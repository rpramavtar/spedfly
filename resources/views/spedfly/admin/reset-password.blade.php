<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.reset_password') }} - Spedfly</title>
    <link rel="shortcut icon" href="{{ asset('assets/admin/images/favicon.png') }}" />
    <link rel="stylesheet" href="{{ asset('assets/admin/css/main.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/fonts/bootstrap/bootstrap-icons.css') }}">
  </head>

  <body class="d-flex align-items-center" style="min-height:100vh; background:#ead9ae;">
    <div class="container py-5">
      <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
          <div class="card shadow-sm border-0" style="background:#e3e3e3;">
            <div class="card-body p-4 text-center">
              <img src="{{ asset('assets/admin/images/logo.png') }}" alt="logo" class="mb-3" style="max-width:170px">
              <h3 class="mb-2">{{ __('ui.reset_password') }}</h3>
              <p class="text-muted mb-4">{{ __('ui.create_a_new_password_for_your_admin_account') }}</p>

              @if ($errors->any())
                <div class="alert alert-danger py-2 text-start">
                  @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                  @endforeach
                </div>
              @endif

              <form action="{{ route('admin.password.update') }}" method="post">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="mb-3 text-start">
                  <label class="form-label">{{ __('ui.email_address') }}</label>
                  <input type="email" name="email" value="{{ old('email', $email) }}" class="form-control" required>
                </div>
                <div class="mb-3 text-start">
                  <label class="form-label">{{ __('ui.new_password') }}</label>
                  <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3 text-start">
                  <label class="form-label">{{ __('ui.confirm_password') }}</label>
                  <input type="password" name="password_confirmation" class="form-control" required>
                </div>
                <button class="btn btn-primary w-100">{{ __('ui.update_password') }}</button>
              </form>

              <div class="mt-3">
                <a href="{{ route('admin.login') }}" class="text-decoration-none fw-semibold" style="color:#b8860b;">{{ __('ui.back_to_login') }}</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
