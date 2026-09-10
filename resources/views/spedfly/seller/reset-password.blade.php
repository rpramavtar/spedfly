<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ __('ui.reset_password') }} - Spedfly</title>
    <link rel="shortcut icon" href="{{ asset('assets/seller/images/favicon.svg') }}" />
    <link rel="stylesheet" href="{{ asset('assets/seller/fonts/bootstrap/bootstrap-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/seller/css/main.min.css') }}" />
</head>
<body style="background: #ffec84;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-5 col-lg-5 col-sm-6 col-12">
                <form action="{{ route('seller.password.update') }}" method="post" class="my-5" style="background: #fff; border: 5px solid #f5cf00;">
                    @csrf
                    <div class="rounded-2 p-4">
                        <div class="login-form">
                            <center>
                                <a href="{{ route('seller.index') }}" class="mb-4">
                                    <img src="{{ asset('assets/seller/images/logo.png') }}" class="logo" alt="Spedfly" style="max-width:180px; max-height:100px;" />
                                </a>
                            </center>
                            <center>
                                <h5 class="fw-bold mb-2">{{ __('ui.reset_password') }}</h5>
                                <p class="mb-4 text-muted">{{ __('ui.create_a_new_password_for_your_seller_account') }}</p>
                            </center>

                            @if ($errors->any())
                                <div class="alert alert-danger py-2">
                                    @foreach ($errors->all() as $error)
                                        <div>{{ $error }}</div>
                                    @endforeach
                                </div>
                            @endif

                            <input type="hidden" name="token" value="{{ $token }}">

                            <div class="mb-3">
                                <label class="form-label">{{ __('ui.email_address') }}</label>
                                <input type="email" name="email" value="{{ old('email', $email) }}" class="form-control" placeholder="{{ __('ui.enter_your_email') }}" required />
                            </div>

                            <div class="mb-3">
                                <label class="form-label">{{ __('ui.new_password') }}</label>
                                <input type="password" name="password" class="form-control" placeholder="{{ __('ui.enter_new_password') }}" required />
                            </div>

                            <div class="mb-3">
                                <label class="form-label">{{ __('ui.confirm_password') }}</label>
                                <input type="password" name="password_confirmation" class="form-control" placeholder="{{ __('ui.repeat_new_password') }}" required />
                            </div>

                            <div class="d-grid py-3 mt-4">
                                <button type="submit" class="btn btn-lg btn-primary">{{ __('ui.update_password') }}</button>
                            </div>

                            <div class="text-center">
                                <a href="{{ route('seller.index') }}" class="text-decoration-none fw-semibold" style="color:#c98f00;">{{ __('ui.back_to_login') }}</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
