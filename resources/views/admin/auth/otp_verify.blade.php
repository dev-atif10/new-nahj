@extends('web.default.layouts.email')

@section('body')
    <!-- content -->
    <td valign="top" class="bodyContent" mc:edit="body_content">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">{{ trans('auth.otp_verification') }}</div>
                        <div class="card-body">
                            <div class="alert alert-success" role="alert">
                                {{ trans('auth.otp_has_been_sent_to_your_email') }}
                            </div>

                            <p>{{ trans('auth.your_otp_code_is') }}:</p>

                            <div style="font-size: 24px; font-weight: bold; text-align: center; margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 5px;">
                                {{ $otp }}
                            </div>

                            <p>{{ trans('auth.otp_expire_notice', ['minutes' => 15]) }}</p>

                            <p>{{ trans('auth.if_you_did_not_request_this') }}, {{ trans('auth.please_ignore_this_email') }}.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </td>
@endsection