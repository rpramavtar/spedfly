<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.forgot_password') }} - Spedfly</title>
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
              <h3 class="mb-2">{{ __('ui.forgot_password') }}</h3>
              <p class="text-muted mb-4">{{ __('ui.enter_your_admin_email_to_receive_a_reset_link') }}</p>

              @if (session('status'))
                <div class="alert alert-success py-2 text-start">{{ session('status') }}</div>
              @endif

              @if ($errors->any())
                <div class="alert alert-danger py-2 text-start">
                  @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                  @endforeach
                </div>
              @endif

              <form action="{{ route('admin.password.email') }}" method="post">
                @csrf
                <div class="mb-3 text-start">
                  <label class="form-label">{{ __('ui.email_address') }}</label>
                  <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
                </div>
                <button class="btn btn-primary w-100">{{ __('ui.send_reset_link') }}</button>
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
