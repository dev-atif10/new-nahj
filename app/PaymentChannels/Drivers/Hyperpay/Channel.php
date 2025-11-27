<?php

namespace App\PaymentChannels\Drivers\Hyperpay;

use App\Models\Order;
use App\Models\PaymentChannel;
use App\PaymentChannels\BasePaymentChannel;
use App\PaymentChannels\IChannel;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Http\Request;


class Channel extends BasePaymentChannel implements IChannel
{
    protected $currency;
    protected $order_session_key;
    protected $api;

    private $entityId, $endPointUrl, $authToken;

    public function __construct(PaymentChannel $paymentChannel)
    {
        $this->endPointUrl = env("HYPERPAY_URL");
        $this->entityId = env("HYPERPAY_ENTITY_ID");
        $this->authToken = env("HYPERPAY_AUTH_KEY");
        $this->order_session_key = "hayperpay.payments.order_id";
    }


    public function paymentRequest(Order $order)
    {
        try {
            $user = auth()->user();
            session()->put($this->order_session_key, $order->id);

            $url = $this->endPointUrl."/checkouts";
            $data = "entityId=".$this->entityId .
                        "&amount=" . $order->total_amount.
                        "&currency=SAR" .
                        "&paymentType=DB" .
                        "&customer.givenName=" .$user->full_name.
                        "&customer.surname=" .$user->full_name.
                        "&customer.phone=" .$user->mobile.
                        "&customer.email=" .$user->email.
                        "&billing.street1=Ash-Sharai".
                        "&billing.city=Makkah".
                        "&billing.state=Makkah Region".
                        "&billing.country=SA".
                        "&billing.postcode=24432".
                        "&merchantTransactionId=" .$order->id;
        
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                           'Authorization:Bearer '.$this->authToken));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// this should be set to true in production
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $responseArray = curl_exec($ch);

            if(curl_errno($ch)) {
                return curl_error($ch);
            }
            curl_close($ch);


            $responseArray = json_decode($responseArray, true);

            



            return view("web.default.cart.channels.hayperPay", [
                "checkoutId" => $responseArray["id"],
                'pageTitle' => 'الدفع الألكتروني',
                'paymentBrand' => 'VISA MASTER',
                'gateway' => 'Hyperpay'
            ]);
        } catch (\Exception $e) {
            // print ('Error: ' . $e->getMessage());
            info ('Error: ' . $e->getMessage());
            throw $e;
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
            $request = new Psr7Request('GET', "{$this->endPointUrl}/checkouts/{$id}/payment?entityId={$this->entityId}", $headers);
            $res = $client->sendAsync($request, ['verify' => false])->wait();
            $responseJson = $res->getBody()->getContents();
            $responseArray = json_decode($responseJson, true);


            $order_id = session()->get($this->order_session_key, null);
            session()->forget($this->order_session_key);

            $user = auth()->user();

            $order = Order::where('id', $order_id)
                ->where('user_id', $user->id)
                ->first();

            
            $pattern = "/^(000.000.|000.100.1|000.[36]|000.400.[1][12]0)/";

            $order->online_payment_method = $responseArray["paymentBrand"];
            $order->online_payment_method_id = $responseArray["id"];

            if(isset($responseArray["result"]) && preg_match($pattern, $responseArray["result"]["code"])){
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
