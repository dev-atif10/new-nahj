@extends(getTemplate().'.layouts.app')


@section('content')


<div class="no-result status-failed my-50 d-flex align-items-center justify-content-center flex-column">
            <div class="no-result-logo">
                <img src="/assets/default/img/no-results/failed_pay.png" alt="">
            </div>
            <div class="d-flex align-items-center flex-column mt-30 text-center">
                <h2>{{ trans('cart.failed_pay_title') }}</h2>
                <p class="mt-5 text-center">{!! $message !!}</p>
                <a class="btn btn-primary mt-20" href="{{ route('cart.index.view') }}">الرجوع إلى السلة</a>
            </div>
        </div>


@endsection
