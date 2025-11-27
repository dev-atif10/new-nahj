@extends(getTemplate() . '.layouts.app')
@section('content')
    <div class="payment-container" style=" margin: auto; margin-top: 50px; padding: 20px; box-sizing: border-box; overflow: hidden;">
    <div class="container">
        <div class="text-center">
            <div class="mb-2">
                <img width="120" src="/store/1/default_images/payment%20gateways/Tabby.svg" alt="" />
            </div>
            <div class="d-flex justify-content-center align-items-center mt-2" style="margin-bottom: 30px;">
                <div class="ml-2">قسّمها على 4. بدون أي فوائد، أو رسوم.</div>
                <button type="button" class="btn btn-light btn-sm" style="border-radius: 50px;" data-tabby-info="installments" data-tabby-price="{{ $order->total_amount }}" data-tabby-currency="SAR">!</button>
            </div>

            @if($isRejected)
                <div class="row justify-content-center mb-25">
                    <div class="col-lg-8">
                        <div class="alert alert-outline-danger">
                        {{ $isRejectedReason }}
                        </div>
                    </div>
                </div>

            @endif
        </div>
        <div class="d-flex justify-content-center">
            <div id="tabbyCard"></div>
        </div>
        @if($redirect_url)
        <div class="d-flex justify-content-center">
            <a class="btn btn-primary" href="{{ $redirect_url }}">أدفع الآن</a>
        </div>
        @endif
        @if($isRejected)
        <div class="d-flex justify-content-center">
            <a class="btn btn-light" href="{{ route('cart.index.view') }}">الرجوع إلى السلة</a>
        </div>
        @endif


    </div>    

    </div>
@endsection
@push('scripts_bottom')
<script src="https://checkout.tabby.ai/tabby-card.js"></script>
<script>
new TabbyCard({
  selector: '#tabbyCard',
  currency: 'SAR',
  lang: 'ar',
  price: {{ $order->total_amount }},
  size: 'narrow',
  theme: 'black',
  header: false
});
</script>
<script src="https://checkout.tabby.ai/tabby-promo.js"></script>
    <script>
      new TabbyPromo({
        lang: 'ar'
      });
    </script>
@endpush






