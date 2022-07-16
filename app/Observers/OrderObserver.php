<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\Voucher;

use App\Services\Xbot;
use Illuminate\Support\Str;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function created(Order $order)
    {
        if(!$order->customer->deliver) return; //首单无地址
        $this->sendMessage($order);
    }
    /**
     * Handle the Order "updated" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function updated(Order $order)
    {
        if($order->wasChanged('deliver_id')) $this->sendMessage($order);
    }

    private function sendMessage(Order $order)
    {
        $message = "[订单跟踪]\n" . $order->product->name . ":" . $order->amount . $order->product->unit . ":" . $order->id;
        
        // 购买水票
        $productIsVoucher = Str::contains($order->product->name, ['水票'])?true:false;
        
        $voucher = Voucher::where('customer_id', $order->customer_id)->where('left', ">" , 0)->first();
        $payInfo = $order->price?($order->price/100 * ($productIsVoucher?1:$order->amount)).'元':'水票:剩余:'.$voucher->left;
        $message .= "\n支付:" . $payInfo;
        $message .= "\n客户:" . $order->customer->name. ':'. $order->customer_id ;
        $message .= "\n电话:" . $order->customer->telephone;
        $message .= "\n地址:" . $order->customer->address_detail;

        $to = $order->customer->deliver->wxid; //分群
        // sq水票订单
        if($productIsVoucher){
            $to = '20779741807@chatroom';
        }else{
            $message .= "\n如客户已签收，请转至本群更新订单状态";
        }
        return app(Xbot::class)->send($message, $to);
    }
}
