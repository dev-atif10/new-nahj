@extends(getTemplate() . '.layouts.app')
@section('content')
    <div class="payment-container" style=" margin: auto; margin-top: 50px; padding: 20px; box-sizing: border-box; overflow: hidden;">
        <h2 class="text-secondary font-weight-bold text-center" style="margin-bottom: 30px;">الدفع الألكتروني</h2>
        <div style="direction: ltr !important;">
            <form action="{{ route('payment_verify', ['gateway' => $gateway]) }}" class="paymentWidgets" data-brands="{{ $paymentBrand }}"></form>
        </div>


    </div>
@endsection
@push('scripts_bottom')
<script>

var wpwlOptions = {
    style:"card",
    locale: "ar"
};

</script>

<script src="{{ env('HYPERPAY_URL') }}/paymentWidgets.js?checkoutId={{ $checkoutId }}"></script>



@endpush



