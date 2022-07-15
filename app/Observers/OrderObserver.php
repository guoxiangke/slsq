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
        $message = "订单:" . $order->product->name . ":" . $order->amount . $order->product->unit . ":" . $order->id;
        
        // 购买水票
        $productIsVoucher = Str::contains($order->product->name, ['水票'])?true:false;
        //接单群 or 水票订单群
        // $to = $productIsVoucher?'20779741807@chatroom':'21182221243@chatroom';
        $to = '21182221243@chatroom'; //分单群
        if($order->customer->deliver){
            $to = $order->customer->deliver->wxid; //分群
        }
        
        // 水票购买
        $isVoucher = !$order->price;
        $voucher = Voucher::where('customer_id', $order->customer_id)->where('left', ">" , 0)->first();
        $payInfo = $order->price?($order->price/100 * ($productIsVoucher?1:$order->amount)).'元':'水票:剩余:'.$voucher->left;
        $message .= "\n支付:" . $payInfo;
        $message .= "\n客户:" . $order->customer->name. ':'. $order->customer_id ;
        $message .= "\n电话:" . $order->customer->telephone;
        $message .= "\n地址:" . $order->customer->address_detail;

        app(Xbot::class)->send($message, $to);


    }

    /**
     * Handle the Order "updated" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function updated(Order $order)
    {
        // return app(Xbot::class)->send($content, $this->wxid);
    }
}
