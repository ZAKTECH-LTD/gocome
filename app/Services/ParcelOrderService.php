<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStop;
use App\Models\Coupon;
use App\Models\CouponUser;
use App\Models\PaymentMethod;
use App\Models\Wallet;
use App\Models\Vendor;
use App\Traits\OrderTrait;
use App\Traits\WalletTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;


class ParcelOrderService
{
    use OrderTrait, WalletTrait;

    public function __constuct()
    {
        //
    }


    public function placeOrder(Request $request)
    {

        //verify token
        //TODO: remove this in like future update
        if (!empty($request->token)) {

            try {
                $orderSummary = \Crypt::decrypt($request->token);
                //check if the total are the same
                if ($orderSummary["total"] != $request->total) {
                    throw new \Exception(__("Invalid Order Summary. Please contact support"), 1);
                }
            } catch (Illuminate\Contracts\Encryption\DecryptException $ex) {
                throw new \Exception(__("Invalid Order Summary. Please contact support"), 1);
            }
        }


        DB::beginTransaction();
        $order = new order();
        //DON'T TRANSLATE
        $order->vendor_id = $request->vendor_id;
        $order->payment_method_id = $request->payment_method_id;
        $order->note = $request->note ?? '';
        //
        $order->package_type_id = $request->package_type_id;
        $order->pickup_date = $request->pickup_date;
        $order->pickup_time = $request->pickup_time;
        // TODO take extra infos
        $order->weight = $request->weight ?? 0;
        $order->width = $request->width ?? 0;
        $order->length = $request->length ?? 0;
        $order->height = $request->height ?? 0;

        //
        if (\Schema::hasColumn('orders', 'payer')) {
            $order->payer = $request->payer;
        }

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
        if (!empty($request->pickup_location_id)) {
            $orderStop = new OrderStop();
            $orderStop->order_id = $order->id;
            $orderStop->stop_id = $request->pickup_location_id;
            $orderStop->save();
        }

        //stops
        if (!empty($request->stops)) {
            foreach ($request->stops as $stop) {

                $orderStop = new OrderStop();
                $orderStop->order_id = $order->id;
                $orderStop->stop_id = $stop['stop_id'] ?? $stop['id'];
                $orderStop->price = $stop['price'] ?? 0.00;
                if (!empty($stop["name"])) {
                    $orderStop->name = $stop['name'] ?? '';
                    $orderStop->phone = $stop['phone'] ?? '';
                    $orderStop->note = $stop['note'] ?? '';
                }

                $orderStop->save();
            }
        }

        // allow old apps to still place order [Will be removed in future update]
        if (!empty($request->dropoff_location_id)) {
            $orderStop = new OrderStop();
            $orderStop->order_id = $order->id;
            $orderStop->stop_id = $request->dropoff_location_id;
            $orderStop->name = $request->recipient_name;
            $orderStop->phone = $request->recipient_phone;
            $orderStop->note = $request->note ?? '';
            $orderStop->save();
        }


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
