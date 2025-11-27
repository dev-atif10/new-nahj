@extends(getTemplate().'.layouts.app')

@push('styles_top')
    <link rel="stylesheet" href="/assets/default/vendors/select2/select2.min.css">
@endpush

@section('content')
    <div class="container">
        @if(!empty(session()->has('msg')))
            <div class="alert alert-info alert-dismissible fade show mt-30" role="alert">
                {{ session()->get('msg') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <div class="row">
            <div class="col-12 col-md-6" style="margin: auto;">
                <div class="login-card">
                    <h1 class="font-20 font-weight-bold text-center">{{ trans('auth.otp_verification') }}</h1>

                    <div class="alert alert-success mt-20" role="alert">
                        {{ trans('auth.otp_sent_to_email', ['email' => $email]) }}
                    </div>

                    <form method="Post" action="/login" class="mt-35">
                        @csrf
                        <input type="hidden" name="email" value="{{ $email }}">
                        @if(!empty($remember))
                            <input type="hidden" name="remember" value="{{ $remember }}">
                        @endif

                        <div class="form-group">
                            <label class="input-label" for="otp">{{ trans('auth.enter_otp') }}:</label>
                            <input name="otp" type="text" class="form-control @error('otp') is-invalid @enderror"
                                   id="otp" required autofocus>

                            @error('otp')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                            @enderror

                            <div class="text-muted mt-5 font-12">
                                {{ trans('auth.otp_expire_note', ['minutes' => 15]) }}
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block mt-20 rounded-pill">
                            {{ trans('auth.verify_otp') }}
                        </button>
                    </form>

                    <!--<form method="Post" action="/resend-otp" class="mt-20 text-center">-->
                    <!--    @csrf-->
                    <!--    <input type="hidden" name="email" value="{{ $email }}">-->
                    <!--    <button type="submit" class="btn btn-link p-0">-->
                    <!--        {{ trans('auth.didnt_receive_code') }} <strong>{{ trans('auth.resend_otp') }}</strong>-->
                    <!--    </button>-->
                    <!--</form>-->

                    @if(session()->has('otp_error'))
                        <div class="d-flex align-items-center mt-20 p-15 danger-transparent-alert">
                            <div class="danger-transparent-alert__icon d-flex align-items-center justify-content-center">
                                <i data-feather="alert-octagon" width="18" height="18"></i>
                            </div>
                            <div class="ml-10">
                                <div class="font-14 font-weight-bold">{{ trans('auth.verification_failed') }}</div>
                                <div class="font-12">{{ session()->get('otp_error') }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts_bottom')
    <script src="/assets/default/vendors/select2/select2.min.js"></script>
@endpush