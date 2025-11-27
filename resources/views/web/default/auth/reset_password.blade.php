@extends(getTemplate().'.layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            

            <div class="col-12 col-md-6" style="margin: auto;">
                <div class="login-card">
                    <h1 class="font-20 font-weight-bold text-center">تحديث كلمة المرور</h1>
                    <form method="post" action="/reset-password" class="mt-35">
                        {{ csrf_field() }}

                        <div class="form-group">
                            <label class="input-label" for="email">{{ trans('auth.email') }}:</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" id="email"
                                   value="{{ request()->get('email') }}" aria-describedby="emailHelp">
                            @error('email')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="input-label" for="password">كلمة المرور الجديدة:</label>
                            <input name="password" type="password"
                                   class="form-control @error('password') is-invalid @enderror" id="password"
                                   aria-describedby="passwordHelp">
                            @error('password')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="input-label" for="confirm_password">تأكيد كلمة المرور الجديدة:</label>
                            <input name="password_confirmation" type="password"
                                   class="form-control @error('password_confirmation') is-invalid @enderror" id="confirm_password"
                                   aria-describedby="confirmPasswordHelp">
                            @error('password_confirmation')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>

                        <input hidden name="token" placeholder="token" value="{{ $token }}">

                        <button type="submit" class="btn btn-primary btn-block mt-20">حفظ كلمة المرور</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
