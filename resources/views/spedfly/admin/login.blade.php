<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.login') }} - Spedfly</title>
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
              <h3 class="mb-4">{{ __('ui.admin_login') }}</h3>
              @if (session('status'))
                <div class="alert alert-success py-2 text-start">{{ session('status') }}</div>
              @endif
              <form action="{{ route('admin.authenticate') }}" method="post">
                @csrf
                <div class="mb-3 text-start">
                  <label class="form-label">{{ __('ui.email_address') }}</label>
                  <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
                  @error('email')
                    <small class="text-danger">{{ $message }}</small>
                  @enderror
                </div>
                <div class="mb-3 text-start">
                  <label class="form-label">{{ __('ui.password') }}</label>
                  <input type="password" name="password" class="form-control" required>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                    <label class="form-check-label" for="remember">{{ __('ui.remember_me') }}</label>
                  </div>
                  <a href="{{ route('admin.password.request') }}" class="text-decoration-none fw-semibold" style="color:#b8860b;">{{ __('ui.forgot_password') }}</a>
                </div>
                <button class="btn btn-primary w-100">{{ __('ui.login') }}</button>
              </form>
             
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>






