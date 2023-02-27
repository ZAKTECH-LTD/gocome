<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Coupon;
use App\Models\CouponUser;
use App\Models\OrderService;
use App\Models\PaymentMethod;
use App\Models\Wallet;
use App\Models\Vendor;
use App\Traits\OrderTrait;
use App\Traits\WalletTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;


class ServiceOrderService
{
    use OrderTrait, WalletTrait;

    public function __constuct()
    {
        //
    }


    public function placeOrder(Request $request)
    {
        DB::beginTransaction();
        $order = new order();
        //DON'T TRANSLATE
        $order->vendor_id = $request->vendor_id;
        $order->payment_method_id = $request->payment_method_id;
        $order->delivery_address_id = $request->delivery_address_id;
        $order->note = $request->note ?? '';
        //
        $order->pickup_date = $request->pickup_date;
        $order->pickup_time = $request->pickup_time;
        //
        $order->sub_total = $request->sub_total;
        $order->discount = $request->discount;
        $order->delivery_fee = $request->delivery_fee;
        $order->tax = $request->tax;
        $order->tax_rate = $request->tax_rate ?? Vendor::find($order->vendor_id)->tax ?? 0.00;
        $order->total = $request->total;
        if (\Schema::hasColumn("orders", 'fees')) {
            $order->fees = json_encode($request->fees ?? []);
        }
        $order->save();
        $order->setStatus($this->getNewOrderStatus($request));

        // allow old apps to still place order [Will be removed in future update]
        $orderService = new OrderService();
        $orderService->order_id = $order->id;
        $orderService->service_id = $request->service_id;
        $orderService->hours = $request->hours;
        $orderService->price = $request->service_price;
        $orderService->save();


        //save the coupon used
        $coupon = Coupon::where("code", $request->coupon_code)->first();
        if (!empty($coupon)) {
            $couponUser = new CouponUser();
            $couponUser->coupon_id = $coupon->id;
            $couponUser->user_id = \Auth::id();
            $couponUser->order_id = $order->id;
            $couponUser->save();
        }


        //
        $paymentMethod = PaymentMethod::find($request->payment_method_id);
        $paymentLink = "";
        $message = "";

        if ($paymentMethod->is_cash) {
            //
            $order->payment_status = "pending";
            //wallet check 
            if ($paymentMethod->slug == "wallet") {
                //
                $wallet = Wallet::mine()->first();
                if (empty($wallet) || $wallet->balance < $request->total) {
                    throw new \Exception(__("Wallet Balance is less than order total amount"), 1);
                } else {
                    //
                    $this->orderWalletPaymentProcess($wallet, $request->total, $order);
                    $order->payment_status = "successful";
                }
            }


            $message = __("Order placed successfully. Relax while the vendor process your order");
        } else {
            $message = __("Order placed successfully. Please follow the link to complete payment.");
            $paymentLink = route('order.payment', ["code" => $order->code]);
        }

        //
        $order->save();

        //
        DB::commit();

        return response()->json([
            "message" => $message,
            "link" => $paymentLink,
        ], 200);
    }
}
