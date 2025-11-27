<?php

namespace App\PaymentChannels\Drivers\Tabby;

use App\Models\Order;
use App\Models\PaymentChannel;
use App\PaymentChannels\BasePaymentChannel;
use App\PaymentChannels\IChannel;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class Channel extends BasePaymentChannel implements IChannel
{
    protected $currency;
    protected $order_session_key;
    protected $api;

    private $entityId, $endPointUrl, $secretKey, $publicKey;

    public function __construct(PaymentChannel $paymentChannel)
    {
        $this->secretKey = env("TABBY_SECRET_KEY");
        $this->publicKey = env("TABBY_PUBLIC_KEY");
        $this->endPointUrl = env("TABBY_URL");
        $this->order_session_key = "tabby.payments.order_id";
    }


    public function paymentRequest(Order $order)
    {

        session()->put($this->order_session_key, $order->id);
        $generalSettings = getGeneralSettings();
        $user = $order->user;

        $successUrl = route('payment_verify', [
            'order_id' => $order->id,
            'gateway' => 'tabby',
            'status' => 'success'
        ]);

        $cancelUrl = route('tabby_message', [
            'order_id' => $order->id,
            'status' => 'cancel'
        ]);

        $failureUrl = route('tabby_message', [
            'order_id' => $order->id,
            'status' => 'failure'
        ]);


        $this->endPointUrl .= "/checkout";
    
        
        $urls = ['success' => $successUrl,'cancel' => $cancelUrl,'failure' => $failureUrl];

        $getPrevOrders = \App\Models\Sale::where('buyer_id',auth()->user()->id)->select('total_amount','created_at')->take(10)->get();

        $buyerInfo = [
            // "phone" => '500000001',
            "phone" => $user->mobile,
            // "email" => 'card.success@tabby.ai',
            "email" => $user->email ?? $generalSettings['site_email'],
            "name" => $user->full_name
        ];

        $shippingAddress = [
            "city" => $user->address ?? null,
            "address" => $user->address ?? null,
            "zip" => null
        ];

        $orderHistory = [];
        foreach($getPrevOrders as $prevOrder){
            $orderHistory[] = [
                "purchased_at" => date('Y-m-d\TH:i:s+03:00',$prevOrder->created_at),
                "amount" => number_format($prevOrder->total_amount,2,'.',''),
                "status" => "complete",
                "buyer" => $buyerInfo,
                'shipping_address' => $shippingAddress
            ];
        }

        $items = [
            [
                'title' => 'Nahj Almarifa Order',
                'quantity' => 1,
                'unit_price' => number_format($order->total_amount,2,'.',''),
                'discount_amount' => "0.00",
                'reference_id' => (string) $order->id,
                'image_url' => 'test',
                'product_url' => env('APP_URL'),
                'category' => 'Courses'
            ]
        ];


        $data = [
            "payment" => [
                "amount" =>  number_format($order->total_amount,2,'.',''),
                "currency" => 'SAR',
                "description" => null,
                "buyer" => $buyerInfo,
                "shipping_address" => $shippingAddress,
                "order" => [
                    "discount_amount" => "0.00",
                    "reference_id" => (string) $order->id,
                    "items" => $items
                ],
                "buyer_history" => [
                    "registered_since" => date('Y-m-d\TH:i:s+03:00',auth()->user()->created_at),
                    "loyalty_level" => $getPrevOrders->count()
                ],
                "order_history" => $orderHistory,
                "meta" => [
                    "order_id" => $order->id,
                    "customer" => auth()->user()->id
                ]
            ],
            "lang" => "ar",
            "merchant_code" => "nahj",
            "merchant_urls" => $urls,
            'create_token' => false,
            "token" => null
        ];


        $response = Http::acceptJson()->contentType('application/json')->withToken($this->publicKey)->post($this->endPointUrl, $data);

        $responseResult = json_decode($response->getBody()->getContents(), true);


        $redirect_url = '';
        $isRejected = false;
        $isRejectedReason = null;
        $rejectedReasons = [
            'not_available' => 'نأسف، تابي غير قادرة على الموافقة على هذه العملية. الرجاء استخدام طريقة دفع أخرى.',
            'order_amount_too_high' => 'قيمة الطلب تفوق الحد الأقصى المسموح به حاليًا مع تابي. يُرجى تخفيض قيمة السلة أو استخدام وسيلة دفع أخرى.',
            'order_amount_too_low' => 'قيمة الطلب أقل من الحد الأدنى المطلوب لاستخدام خدمة تابي. يُرجى زيادة قيمة الطلب أو استخدام وسيلة دفع أخرى.'
        ];

        if(isset($responseResult['status']) && $responseResult['status'] == 'created' && isset($responseResult['configuration']['available_products']['installments']) && count($responseResult['configuration']['available_products']['installments'])){
            $redirect_url = $responseResult['configuration']['available_products']['installments'][0]['web_url'];
        }

        if(isset($responseResult['status']) && $responseResult['status'] == 'rejected'){
            $isRejected = true;
            $isRejectedReason = $rejectedReasons[$responseResult['configuration']['products']['installments']['rejection_reason']];
        }



        return view("web.default.cart.channels.tabby", [
            'order' => $order,
            'redirect_url' => $redirect_url,
            'pageTitle' => 'الدفع الألكتروني',
            'isRejected' => $isRejected,
            'isRejectedReason' => $isRejectedReason,
            'gateway' => 'Tabby'
        ]);
    }


    public function verify(Request $request)
    {


        try {

            $client = new Client();
            $headers = [
                'Authorization' => "Bearer {$this->secretKey}",
            ];

            $request = new Psr7Request('GET', "{$this->endPointUrl}/payments/".$request->payment_id, $headers);
            $res = $client->sendAsync($request, ['verify' => false])->wait();
            $responseJson = $res->getBody()->getContents();
            $responseArray = json_decode($responseJson, true);

            $order_id = request()->order_id;
            session()->forget($this->order_session_key);


            $user = auth()->user();

            $order = Order::where('id', $order_id)
                ->where('user_id', $user->id)
                ->first();

            $order->online_payment_method = 'Tabby';
            $order->online_payment_method_id = request()->payment_id;

            if(isset($responseArray["status"]) && $responseArray['status'] == 'AUTHORIZED'){

                $data2 = [
                    'amount' => (string) $order->total_amount
                ];

                $response = Http::withHeaders([
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $this->secretKey
                ])->post("{$this->endPointUrl}/payments/".request()->payment_id."/captures", $data2);


                $order->status = Order::$paying;
                $order->save();

                return $order;
            }else {
                $order->status = Order::$fail;
                $order->save();

                return null;
            }

        } catch (\Exception $exception) {
            info ('Error: ' . $exception->getMessage());
            throw $exception;
        }

    }


    public static function showTabby($orderValue = 0){

        $orderValue = (float) $orderValue;
        // if(auth()->user()->mobile && $orderValue){


        //     $response = Http::withHeaders([
        //         'Accept'        => 'application/json',
        //         'Content-Type'  => 'application/json',
        //         'Authorization' => 'Bearer ' . env("TABBY_SECRET_KEY")
        //     ])->get(env("TAMARA_URL")."/checkout/payment-types", [
        //         'country' => 'SA',
        //         'phone' => auth()->user()->mobile,
        //         'currency' => 'SAR',
        //         'order_value' => $orderValue
        //     ]);
    
        //     $responseResult = json_decode($response->getBody()->getContents(), true);

            
        //     if(count($responseResult) == 2 && $responseResult[0]['name'] == 'PAY_BY_INSTALMENTS'){
        //         return true;
        //     }else {
        //         return false;
        //     }
        // }else {
        //     return false;
        // }
        if($orderValue <= 2500){
            return true;
        }else {
            return false;
        }

    }

}
