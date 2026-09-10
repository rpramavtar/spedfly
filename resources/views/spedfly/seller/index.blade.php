<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">


<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ __('ui.login') }} - Spedfly</title>

    
    <link rel="shortcut icon" href="{{ asset('assets/seller/images/favicon.svg') }}" />

    <!-- *************
			************ CSS Files *************
		************* -->
    <link rel="stylesheet" href="{{ asset('assets/seller/fonts/bootstrap/bootstrap-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/seller/css/main.min.css') }}" />
    <script>
      (function () {
        const params = new URLSearchParams(window.location.search);
        const embedded = params.get('embedded') === '1';

        if (embedded && window.top !== window.self) {
          params.delete('embedded');
          const cleanedUrl = window.location.origin + window.location.pathname + (params.toString() ? '?' + params.toString() : '');
          window.top.location.href = cleanedUrl;
        }
      })();
    </script>
  </head>

  <body style="background: #ffec84;">
<!-- Container start -->
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-xl-5 col-lg-5 col-sm-6 col-12">
          <form action="{{ route('seller.authenticate') }}" method="post" class="my-5" style="background: #fff; border: 5px solid #f5cf00;">
            @csrf
            <div class="rounded-2 p-4">
              <div class="login-form">
                <center><a href="{{ route('seller.index') }}" class="mb-4">
                  <img src="{{ asset('assets/seller/images/logo.png') }}" class="logo" alt="Bootstrap Gallery"  style="max-width:180px; max-height:100px;"/>
                </a></center>
                <center><h5 class="fw-bold mb-5">{{ __('ui.login_to_access_dashboard') }}</h5></center>

                @if (session('status'))
                  <div class="alert alert-success py-2">{{ session('status') }}</div>
                @endif


             <input type="hidden" name="type" value="Seller">
             
               <!-- <div class="mb-3">
                  <label class="form-label">User Type</label>
                  <select class="form-control" name="type" required>
                    <option value="seller" {{ old('type', 'seller') === 'seller' ? 'selected' : '' }}>Seller</option>
                    <option value="admin" {{ old('type') === 'admin' ? 'selected' : '' }}>Admin</option>
                  </select>
                </div>-->
                <div class="mb-3">
                  <label class="form-label">{{ __('ui.email_address') }}</label>
                  <input type="email" name="email" value="{{ old('email') }}" class="form-control" placeholder="{{ __('ui.enter_your_email') }}" required />
                  @error('email')
                    <small class="text-danger">{{ $message }}</small>
                  @enderror
                </div>
                <div class="mb-3">
                  <label class="form-label">{{ __('ui.password') }}</label>
                  <input type="password" name="password" class="form-control" placeholder="{{ __('ui.enter_password') }}" required />
                </div>
                <div class="d-flex align-items-center justify-content-between">
                  <div class="form-check m-0">
                    <input class="form-check-input" type="checkbox" value="1" id="rememberPassword" name="remember" />
                    <label class="form-check-label" for="rememberPassword">{{ __('ui.remember_me') }}</label>
                  </div>
                  <a href="{{ route('seller.password.request') }}" class="text-decoration-none fw-semibold" style="color:#c98f00;">{{ __('ui.forgot_password') }}</a>
                </div>
                <div class="d-grid py-3 mt-4">
                  <button type="submit" class="btn btn-lg btn-primary">
                    {{ __('ui.login') }}
                  </button>
                </div>
                
               
              
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
    <!-- Container end -->
</body>


</html>


