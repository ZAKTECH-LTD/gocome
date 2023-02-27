<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Order;
use App\Models\AutoAssignment;

trait OrderTrait
{
    use GoogleMapApiTrait;
    use FirebaseAuthTrait;
    use WalletTrait;

    public function getNewOrderStatus(Request $request)
    {

        $orderDate = Carbon::parse("" . $request->pickup_date . " " . $request->pickup_time . "");
        $hoursDiff = Carbon::now()->diffInHours($orderDate);

        if (!empty($request->pickup_date) && $hoursDiff > setting('minScheduledTime', 2)) {
            return "scheduled";
        } else {
            return "pending";
        }
    }


    public function resetOrderQty($model)
    {
        if (!empty($model->products())  && in_array($model->status, ['failed', 'cancelled'])) {
            foreach ($model->products() as $orderProduct) {
                $orderProduct->product->available_qty += $orderProduct->quantity;
                $orderProduct->product->save();
            }
        }
    }


    public function clearAutoAssignment(Order $order)
    {
        //
        $order->refresh();
        if (!empty($order->driver_id) || in_array($order->status, ["ready", "enroute"])) {
            $autoAssignments = AutoAssignment::where('order_id', $order->id)->get();
            if (count($autoAssignments) > 0) {
                AutoAssignment::where('order_id', $order->id)->delete();
            }
        }
    }


    function generateRandomString($length = 25)
    {
        $characters = '123456789abcdefghijklmnpqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    function generateOrderCode($length = 25, $check = true)
    {
        $characters = '1234567890';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        //
        if ($check) {
            $order = Order::whereCode($randomString)->first();
            if (!empty($order)) {
                $randomString = $this->generateOrderCode($length);
            }
        }
        return $randomString;
    }


    public function orderWalletPaymentProcess($wallet, $total, $order)
    {
        $walletDebit = $total;
        //remove delivery fee from total to allow driver be paid in cash
        if ((bool) setting('finance.delivery.collectDeliveryCash', 0)) {
            $walletDebit = $total - $order->delivery_fee;
        }
        //
        $wallet->balance -= $walletDebit;
        $wallet->save();

        //RECORD WALLET TRANSACTION
        $this->recordWalletDebit($wallet, $walletDebit);
    }
}
