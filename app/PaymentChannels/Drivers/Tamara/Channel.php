<?php

namespace App\PaymentChannels\Drivers\Tamara;

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

    private $entityId, $endPointUrl, $authToken;

    public function __construct(PaymentChannel $paymentChannel)
    {
        $this->authToken = env("TAMARA_TOKEN");
        $this->endPointUrl = env("TAMARA_URL");
        $this->order_session_key = "tamara.payments.order_id";
    }


    public function paymentRequest(Order $order)
    {

        session()->put($this->order_session_key, $order->id);
        $generalSettings = getGeneralSettings();
        $user = $order->user;

        $callbackUrl = route('payment_verify', [
            'gateway' => 'Tamara',
            'order_id' => $order->id,
        ]);


        $this->endPointUrl .= "/checkout";
    
        
        $consumer    = ['first_name' => $user->full_name,'last_name' => 'buyer' ,'phone' => $user->mobile,'email' => $user->email ?? $generalSettings['site_email']];
        $billing_address  = ['first_name' => $user->full_name,'last_name' => 'buyer','line1' => $user->address ?? 'NA' ,'city' => $user->address ?? 'NA','phone' => $user->mobile];
        $shipping_address = ['first_name' =>  $user->full_name,'last_name' => 'buyer','line1' =>$user->address ?? 'NA','city' => $user->address ?? 'NA','phone' => $user->mobile];
        $urls = ['success' => $callbackUrl,'failure' => $callbackUrl,'cancel' => $callbackUrl,'notification' => $callbackUrl];

        $items = [
            [
                'reference_id' => $order->id,
                'type' => 'Education',
                'name' => 'Nahj Almarifa Order',
                'sku' => $order->id,
                'image_url' => null,
                'quantity' => 1,
                'discount_amount' => [
                    'amount' => 0,
                    'currency' => 'SAR'
                ],
                'tax_amount' => [
                    'amount' => 0,
                    'currency' => 'SAR'
                ],
                'unit_price' => [
                    'amount' => $order->total_amount,
                    'currency' => 'SAR'
                ],
                'total_amount' => [
                    'amount' => $order->total_amount,
                    'currency' => 'SAR'
                ]
            ]
        ];

        $data = [
            'order_reference_id' => $order->id,
            'order_number'       => $order->id,
            'total_amount'       =>
            [
                'amount'   => $order->total_amount,
                'currency' => 'SAR',
            ],
            'description'        => 'Nahj Almarifa Order',
            'country_code'       => 'SA',
            'payment_type'       => 'PAY_BY_INSTALMENTS',
            'instalments'        => NULL,
            'locale'             => 'en_US',
            'items'              => $items,
            'consumer'           =>
                [
                    'first_name'   => $user->full_name,
                    'last_name'    => 'buyer',
                    'phone_number' => $user->mobile,
                    'email'        => $consumer['email'],
                ],
            'billing_address'    =>
                [
                    'first_name'   => $user->full_name,
                    'last_name'    => 'buyer',
                    'line1'        => $user->address ?? 'NA',
                    'city'         => $user->address ?? 'NA',
                    'country_code' => 'SA',
                    'phone_number' => $user->mobile,
                ],
            'shipping_address'   =>
                [
                    'first_name'   => $user->full_name,
                    'last_name'    => 'buyer',
                    'line1'        => $user->address ?? 'NA',
                    'city'         => $user->address ?? 'NA',
                    'country_code' => 'SA',
                    'phone_number' => $user->mobile,
                ],
            'discount'           =>
                [
                    'name'   => "",
                    'amount' =>
                        [
                            'amount'   => 0,
                            'currency' => 'SAR',
                        ],
                ],
            'tax_amount'         =>
                [
                    'amount'   => 0,
                    'currency' => 'SAR',
                ],
            'shipping_amount'    =>
                [
                    'amount'   => 0,
                    'currency' => 'SAR',
                ],
            'merchant_url'       =>
                [
                    'success'      => $urls['success'],
                    'failure'      => $urls['failure'],
                    'cancel'       => $urls['cancel']
                ]
        ];

        $response = Http::withHeaders([
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->authToken
        ])->post($this->endPointUrl, $data);

        
        $responseResult = json_decode($response->getBody()->getContents(), true);


        return redirect()->to($responseResult['checkout_url']);
    }


    public static function showTamara($orderValue = 0){

        if(auth()->user()->mobile && $orderValue){

            $orderValue = (float) $orderValue;

            $response = Http::withHeaders([
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . env("TAMARA_TOKEN")
            ])->get(env("TAMARA_URL")."/checkout/payment-types", [
                'country' => 'SA',
                'phone' => auth()->user()->mobile,
                'currency' => 'SAR',
                'order_value' => $orderValue
            ]);
    
            $responseResult = json_decode($response->getBody()->getContents(), true);

            
            if(count($responseResult) == 2 && $responseResult[0]['name'] == 'PAY_BY_INSTALMENTS'){
                return true;
            }else {
                return false;
            }
        }else {
            return false;
        }
    }

    public function verify(Request $request)
    {


        try {
            $id = request()->id;

            $client = new Client();
            $headers = [
                'Authorization' => "Bearer {$this->authToken}",
            ];
            $request = new Psr7Request('POST', "{$this->endPointUrl}/orders/{$request->orderId}/authorise", $headers);
            $res = $client->sendAsync($request, ['verify' => false])->wait();
            $responseJson = $res->getBody()->getContents();
            $responseArray = json_decode($responseJson, true);

            $order_id = request()->order_id;
            session()->forget($this->order_session_key);


            $user = auth()->user();

            $order = Order::where('id', $order_id)
                ->where('user_id', $user->id)
                ->first();

            $order->online_payment_method = 'Tamara';
            $order->online_payment_method_id = request()->orderId;

            if(isset($responseArray["status"]) && $responseArray['status'] == 'authorised'){


                $data2 = [
                    'order_id' => request()->orderId,
                    'total_amount' =>
                    [
                        'amount'   => $order->total_amount,
                        'currency' => 'SAR',
                    ]
                ];

                $response = Http::withHeaders([
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $this->authToken
                ])->post("{$this->endPointUrl}/payments/capture", $data2);


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


}
