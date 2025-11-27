@extends('admin.auth.auth_layout')

@section('content')
    @php
        $siteGeneralSettings = getGeneralSettings();
    @endphp

    <div class="p-4 m-3">
        <img src="{{ $siteGeneralSettings['logo'] ?? '' }}" alt="logo" width="40%" class="mb-5 mt-2">

        <h4 class="text-dark font-weight-normal">{{ trans('admin/main.welcome') }} <span class="font-weight-bold">{{ $siteGeneralSettings['site_name'] ?? '' }}</span></h4>

        <p class="text-muted">{{ trans('auth.otp_verification_message') }}</p>

        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ getAdminPanelUrl() }}/login" class="needs-validation" novalidate="">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <input type="hidden" name="remember" value="{{ $remember }}">

            <div class="form-group">
                <label for="otp">{{ trans('auth.otp_code') }}</label>
                <input id="otp" type="text" class="form-control @error('otp') is-invalid @enderror"
                       name="otp" tabindex="1" required autofocus>
                @error('otp')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
                @enderror
                <small class="text-muted">{{ trans('auth.otp_expire_note', ['minutes' => 15]) }}</small>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-lg btn-block" tabindex="2">
                    {{ trans('auth.verify_otp') }}
                </button>
            </div>
        </form>

        <div class="mt-3">
            <form method="POST" action="{{ getAdminPanelUrl() }}/resend-otp">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <button type="submit" class="btn btn-link p-0">
                    {{ trans('auth.resend_otp') }}
                </button>
            </form>
        </div>
    </div>
@endsection