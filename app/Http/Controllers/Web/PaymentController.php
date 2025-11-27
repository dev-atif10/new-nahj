<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mixins\Cashback\CashbackAccounting;
use App\Models\Accounting;
use App\Models\BecomeInstructor;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentChannel;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ReserveMeeting;
use App\Models\Reward;
use App\Models\RewardAccounting;
use App\Models\Sale;
use App\Models\TicketUser;
use App\PaymentChannels\ChannelManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    protected $order_session_key = 'payment.order_id';

    /* this function for move order details of the order to payment */
    public function paymentRequest(Request $request)
    {
        $this->validate($request, [
            'gateway' => 'required'
        ]); // payment type

        
        $user = auth()->user();
        $gateway = $request->input('gateway');
        $orderId = $request->input('order_id');

        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->first();

        if ($order->type === Order::$meeting) {
            $orderItem = OrderItem::where('order_id', $order->id)->first();
            $reserveMeeting = ReserveMeeting::where('id', $orderItem->reserve_meeting_id)->first();
            $reserveMeeting->update(['locked_at' => time()]);
        }

        /* payment type by credit card */
        if ($gateway === 'credit') {

            if ($user->getAccountingCharge() < $order->total_amount) {
                $order->update(['status' => Order::$fail]);

                session()->put($this->order_session_key, $order->id);

                return redirect('/payments/status');
            }

            $order->update([
                'payment_method' => Order::$credit
            ]);

            $this->setPaymentAccounting($order, 'credit');

            $order->update([
                'status' => Order::$paid
            ]);

            session()->put($this->order_session_key, $order->id);

            return redirect('/payments/status');
        }

        $paymentChannel = PaymentChannel::where('id', $gateway)
            ->where('status', 'active')
            ->first();

        if (!$paymentChannel) {
            $toastData = [
                'title' => trans('cart.fail_purchase'),
                'msg' => trans('public.channel_payment_disabled'),
                'status' => 'error'
            ];
            return back()->with(['toast' => $toastData]);
        }

        $order->payment_method = Order::$paymentChannel;
        $order->save();

        try {
            $channelManager = ChannelManager::makeChannel($paymentChannel);
            $redirect_url = $channelManager->paymentRequest($order);

            

            if (in_array($paymentChannel->class_name, PaymentChannel::$gatewayIgnoreRedirect)) {
                return $redirect_url;
            }

            return Redirect::away($redirect_url);

        } catch (\Exception $exception) {

            $toastData = [
                'title' => trans('cart.fail_purchase'),
                'msg' => trans('cart.gateway_error'),
                'status' => 'error'
            ];
            return back()->with(['toast' => $toastData]);
        }
    }

    public function paymentVerify(Request $request, $gateway)
    {
        $paymentChannel = PaymentChannel::where('class_name', $gateway)
            ->where('status', 'active')
            ->first();

        try {
            $channelManager = ChannelManager::makeChannel($paymentChannel);
            
            $order = $channelManager->verify($request);

            return $this->paymentOrderAfterVerify($order);

        } catch (\Exception $exception) {
            $toastData = [
                'title' => trans('cart.fail_purchase'),
                'msg' => trans('cart.gateway_error'),
                'status' => 'error'
            ];
            return redirect('cart')->with(['toast' => $toastData]);
        }
    }

    public function tabbyMessage(Request $request, $status)
    {
        $messages = [
            'cancel' => 'لقد ألغيت الدفعة. فضلاً حاول مجددًا أو اختر طريقة دفع أخرى.',
            'failure' => 'نأسف، تابي غير قادرة على الموافقة على هذه العملية. الرجاء استخدام طريقة دفع أخرى.'
        ];
        
        if(!isset($messages[$status])){
            abort(404);
        }

        if($request->order_id){
            Order::where('id', $request->order_id)->where('user_id', auth()->id())->update(['status' => Order::$fail]);
        }

        return view('web.default.cart.channels.tabby_message', ['message' => $messages[$status]]);
    }


    // public function tamaraWebhook(Request $request)
    // {
    //     $this->authToken = env("TAMARA_TOKEN");
    //     $this->endPointUrl = env("TAMARA_URL");

    //     if($request->order_id){
    //         $response = Http::withHeaders([
    //             'Accept'        => 'application/json',
    //             'Content-Type'  => 'application/json',
    //             'Authorization' => 'Bearer ' . $this->authToken
    //         ])->get("{$this->endPointUrl}/orders/".$request->order_id);

            
    //         $responseResult = json_decode($response->getBody()->getContents(), true);


    //         if(isset($responseResult['order_reference_id'])){
    //             $order = Order::where('id', $responseResult['order_reference_id'])
    //             ->first();

                
    //             if($order && $order->status != 'paid' && $order->online_payment_method){
    //                 $data2 = [
    //                     'order_id' => request()->order_id,
    //                     'total_amount' =>
    //                     [
    //                         'amount'   => $order->total_amount,
    //                         'currency' => 'SAR',
    //                     ]

    //                 ];
    //                 $response = Http::withHeaders([
    //                     'Accept'        => 'application/json',
    //                     'Content-Type'  => 'application/json',
    //                     'Authorization' => 'Bearer ' . $this->authToken
    //                 ])->post("{$this->endPointUrl}/payments/capture", $data2);

    //                 $order->update(['status' => Order::$paying]);

    //                 $this->paymentOrderAfterVerify($order,false);

    //             }


    //             return response()->json(['status' => 'success'], 200);
    //         }else {
    //             return response()->json(['message' => 'Order not found'], 404);
    //         }
    //     }else {
    //         return response()->json(['message' => 'Order not found'], 404);
    //     }
    // }


   public function tamaraWebhook(Request $request)
{
    $this->authToken = env("TAMARA_TOKEN");
    $this->endPointUrl = env("TAMARA_URL");

    $payload = $request->all();
    $event = $payload['event_type'] ?? null;
    $orderReferenceId = $payload['order_reference_id'] ?? null;

    if (!$orderReferenceId) {
        return response()->json(['message' => 'Missing order reference'], 400);
    }

    $order = Order::find($orderReferenceId);
    if (!$order) {
        return response()->json(['message' => 'Order not found'], 404);
    }

    if ($event === 'order_authorised') {
        // Capture payment
        $data = [
            'order_id' => $orderReferenceId,
            'total_amount' => [
                'amount' => $order->total_amount,
                'currency' => 'SAR',
            ]
        ];

        Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->authToken,
            'Content-Type' => 'application/json',
        ])->post("{$this->endPointUrl}/payments/capture", $data);

        $order->update(['status' => Order::$paying]);
        $this->paymentOrderAfterVerify($order, false);
    } elseif ($event === 'order_declined' || $event === 'order_canceled') {
        $order->update(['status' => Order::$fail]);
    }

    return response()->json(['status' => 'ok']);
}



    public function handleTabbyWebhook(Request $request)
    {
        // // Log the request for debugging
        // Log::info('Received webhook from Tabby:', $request->all());

        // // Here you would add your logic to handle the webhook data
        // // For example, updating an order status or handling a payment event

        // return response()->json(['status' => 'success'], 200);

        $data = $request->all();
        $eventType = $request->input('event');
        $order_id = $request->input('id');

        // Log the webhook data
        Log::info('Tabby webhook received with event:', $data);

        // Verify that the event is order_approved
        if (!empty($data['id']) && !empty($data['status'])) {
            // Find the order by the order_id received from Tamara
            // $order = Order::where('order_id', $data['order_id'])->first();
            $payment = Payment::where('order_id', $data['id'])->first();



            if ($payment) {
                // Update the order status to approved
                $payment->status = 'approved';
                $payment->portal_status = $data['status'];
                $payment->save();

                // Optionally: Trigger further actions such as order fulfillment or sending notifications
                // $this->processOrderFulfillment($data['order_id']);

                // if ($payment->portal_status == 'approved') {
                // }
                // if ($payment->portal_status == 'authorised') {
                // }
                // if ($payment->portal_status == 'fully_captured') {
                // }
                // if ($payment->portal_status == 'fully_refunded') {
                // }
                // if ($payment->portal_status == 'canceled') {
                // }

                // Respond back to Tamara , Always respond with a 200 OK response to acknowledge receipt
                return response()->json(['status' => 'success'], 200);
            } else {
                Log::warning('payment not found for the given order_id:', ['order_id' => $data['id']]);
                return response()->json(['message' => 'Order not found'], 404);
            }
        }

        return response()->json(['status' => 'unhandled event'], 200);
    }


    public function tabbyWebhook(Request $request)
    {
        $this->secretKey = env("TABBY_SECRET_KEY");
        $this->publicKey = env("TABBY_PUBLIC_KEY");
        $this->endPointUrl = env("TABBY_URL");

        $data = $request->all();
        \Log::info('Tabby webhook received with event:', $data);

        if($request->id){
            $response = Http::withHeaders([
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->secretKey
            ])->get("{$this->endPointUrl}/payments/".$request->id);

            
            $responseResult = json_decode($response->getBody()->getContents(), true);

            if(isset($responseResult['order']['reference_id']) && isset($responseResult['status']) && $responseResult['status'] == 'AUTHORIZED'){
                $order = Order::where('id', $responseResult['order']['reference_id'])
                ->first();
                
                $data2 = [
                    'amount' => (string) $order->total_amount
                ];

                $response = Http::withHeaders([
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $this->secretKey
                ])->post("{$this->endPointUrl}/payments/".request()->id."/captures", $data2);

                if($order && $order->status != 'paid' && $order->online_payment_method){

                    $order->update(['status' => Order::$paying]);

                    $this->paymentOrderAfterVerify($order,false);

                }
                return response()->json(['status' => 'success'], 200);
            }else {
                if(isset($responseResult['status']) && $responseResult['status'] != 'AUTHORIZED'){
                    return response()->json(['status' => 'Current status not AUTHORIZED but '.$responseResult['status']], 404);
                }
                return response()->json(['status' => 'failed'], 404);
            }
        }else {
            return response()->json(['status' => 'failed'], 404);
        }
    }

    private function paymentOrderAfterVerify($order,$isRedirect = true)
    {
        if (!empty($order) && $order->online_payment_method) {

            if ($order->status == Order::$paying) {
                $this->setPaymentAccounting($order);

                $order->update(['status' => Order::$paid]);
            } else {
                // if ($order->type === Order::$meeting) {
                //     $orderItem = OrderItem::where('order_id', $order->id)->first();

                //     if ($orderItem && $orderItem->reserve_meeting_id) {
                //         $reserveMeeting = ReserveMeeting::where('id', $orderItem->reserve_meeting_id)->first();

                //         if ($reserveMeeting) {
                //             $reserveMeeting->update(['locked_at' => null]);
                //         }
                //     }
                // }
            }

            session()->put($this->order_session_key, $order->id);

            if($isRedirect){
                return redirect('/payments/status');
            }
        } else {
            $toastData = [
                'title' => trans('cart.fail_purchase'),
                'msg' => trans('cart.gateway_error'),
                'status' => 'error'
            ];

            return redirect('cart')->with($toastData);
        }
    }

    public function setPaymentAccounting($order, $type = null)
    {
        
        $cashbackAccounting = new CashbackAccounting();

        if ($order->is_charge_account) {
            Accounting::charge($order);

            $cashbackAccounting->rechargeWallet($order);
        } else {
            foreach ($order->orderItems as $orderItem) {

                $seller_id = OrderItem::getSeller($orderItem);

                $checkSale = Sale::where('buyer_id',$orderItem->user_id)
                ->where('seller_id',$seller_id);

                if($orderItem->webinar_id){
                    $checkSale = $checkSale->where('webinar_id',$orderItem->webinar_id);
                }else {
                    $checkSale = $checkSale->where('bundle_id',$orderItem->bundle_id);
                }

                $checkSale = $checkSale->count();

                if(!$checkSale){

                    $sale = Sale::createSales($orderItem, $order->payment_method,$order->online_payment_method,$order->online_payment_method_id);
    
                    if (!empty($orderItem->reserve_meeting_id)) {
                        $reserveMeeting = ReserveMeeting::where('id', $orderItem->reserve_meeting_id)->first();
                        $reserveMeeting->update([
                            'sale_id' => $sale->id,
                            'reserved_at' => time()
                        ]);
    
                        $reserver = $reserveMeeting->user;
    
                        if ($reserver) {
                            $this->handleMeetingReserveReward($reserver);
                        }
                    }
    
                    if (!empty($orderItem->gift_id)) {
                        $gift = $orderItem->gift;
    
                        $gift->update([
                            'status' => 'active'
                        ]);
    
                        $gift->sendNotificationsWhenActivated($orderItem->total_amount);
                    }
    
                    if (!empty($orderItem->subscribe_id)) {
                        Accounting::createAccountingForSubscribe($orderItem, $type);
                    } elseif (!empty($orderItem->promotion_id)) {
                        Accounting::createAccountingForPromotion($orderItem, $type);
                    } elseif (!empty($orderItem->registration_package_id)) {
                        Accounting::createAccountingForRegistrationPackage($orderItem, $type);
    
                        if (!empty($orderItem->become_instructor_id)) {
                            BecomeInstructor::where('id', $orderItem->become_instructor_id)
                                ->update([
                                    'package_id' => $orderItem->registration_package_id
                                ]);
                        }
                    } elseif (!empty($orderItem->installment_payment_id)) {
                        Accounting::createAccountingForInstallmentPayment($orderItem, $type);
    
                        $this->updateInstallmentOrder($orderItem, $sale);
                    } else {
                        // webinar and meeting and product and bundle
    
                        Accounting::createAccounting($orderItem, $type);
                        TicketUser::useTicket($orderItem);
    
                        if (!empty($orderItem->product_id)) {
                            $this->updateProductOrder($sale, $orderItem);
                        }
                    }

                }

            }

            // Set Cashback Accounting For All Order Items
            $cashbackAccounting->setAccountingForOrderItems($order->orderItems);
        }

        Cart::emptyCart($order->user_id);
    }

    public function payStatus(Request $request)
    {
        $orderId = $request->get('order_id', null);

        if (!empty(session()->get($this->order_session_key, null))) {
            $orderId = session()->get($this->order_session_key, null);
            session()->forget($this->order_session_key);
        }

        $order = Order::where('id', $orderId)
            ->where('user_id', auth()->id())
            ->first();

        if (!empty($order)) {
            $data = [
                'pageTitle' => trans('public.cart_page_title'),
                'order' => $order,
            ];

            return view('web.default.cart.status_pay', $data);
        }

        return redirect('/panel');
    }

    private function handleMeetingReserveReward($user)
    {
        if ($user->isUser()) {
            $type = Reward::STUDENT_MEETING_RESERVE;
        } else {
            $type = Reward::INSTRUCTOR_MEETING_RESERVE;
        }

        $meetingReserveReward = RewardAccounting::calculateScore($type);

        RewardAccounting::makeRewardAccounting($user->id, $meetingReserveReward, $type);
    }

    private function updateProductOrder($sale, $orderItem)
    {
        $product = $orderItem->product;

        $status = ProductOrder::$waitingDelivery;

        if ($product and $product->isVirtual()) {
            $status = ProductOrder::$success;
        }

        ProductOrder::where('product_id', $orderItem->product_id)
            ->where(function ($query) use ($orderItem) {
                $query->where(function ($query) use ($orderItem) {
                    $query->whereNotNull('buyer_id');
                    $query->where('buyer_id', $orderItem->user_id);
                });

                $query->orWhere(function ($query) use ($orderItem) {
                    $query->whereNotNull('gift_id');
                    $query->where('gift_id', $orderItem->gift_id);
                });
            })
            ->update([
                'sale_id' => $sale->id,
                'status' => $status,
            ]);

        if ($product and $product->getAvailability() < 1) {
            $notifyOptions = [
                '[p.title]' => $product->title,
            ];
            sendNotification('product_out_of_stock', $notifyOptions, $product->creator_id);
        }
    }

    private function updateInstallmentOrder($orderItem, $sale)
    {
        $installmentPayment = $orderItem->installmentPayment;

        if (!empty($installmentPayment)) {
            $installmentOrder = $installmentPayment->installmentOrder;

            $installmentPayment->update([
                'sale_id' => $sale->id,
                'status' => 'paid',
            ]);

            /* Notification Options */
            $notifyOptions = [
                '[u.name]' => $installmentOrder->user->full_name,
                '[installment_title]' => $installmentOrder->installment->main_title,
                '[time.date]' => dateTimeFormat(time(), 'j M Y - H:i'),
                '[amount]' => handlePrice($installmentPayment->amount),
            ];

            if ($installmentOrder and $installmentOrder->status == 'paying' and $installmentPayment->type == 'upfront') {
                $installment = $installmentOrder->installment;

                if ($installment) {
                    if ($installment->needToVerify()) {
                        $status = 'pending_verification';

                        sendNotification("installment_verification_request_sent", $notifyOptions, $installmentOrder->user_id);
                        sendNotification("admin_installment_verification_request_sent", $notifyOptions, 1); // Admin
                    } else {
                        $status = 'open';

                        sendNotification("paid_installment_upfront", $notifyOptions, $installmentOrder->user_id);
                    }

                    $installmentOrder->update([
                        'status' => $status
                    ]);

                    if ($status == 'open' and !empty($installmentOrder->product_id) and !empty($installmentOrder->product_order_id)) {
                        $productOrder = ProductOrder::query()->where('installment_order_id', $installmentOrder->id)
                            ->where('id', $installmentOrder->product_order_id)
                            ->first();

                        $product = Product::query()->where('id', $installmentOrder->product_id)->first();

                        if (!empty($product) and !empty($productOrder)) {
                            $productOrderStatus = ProductOrder::$waitingDelivery;

                            if ($product->isVirtual()) {
                                $productOrderStatus = ProductOrder::$success;
                            }

                            $productOrder->update([
                                'status' => $productOrderStatus
                            ]);
                        }
                    }
                }
            }


            if ($installmentPayment->type == 'step') {
                sendNotification("paid_installment_step", $notifyOptions, $installmentOrder->user_id);
                sendNotification("paid_installment_step_for_admin", $notifyOptions, 1); // For Admin
            }

        }
    }

}
