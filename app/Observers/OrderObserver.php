<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\Voucher;
use App\Models\Deliver;

use App\Services\Xbot;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

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
        // 首单赠送1张水票
        // product_id=8 赠送老师20张水票活动

        // Fix Bug: 什么是首单水票？
        $count = Order::where('customer_id', $order->customer_id)->whereIn('product_id', [4,5])->count();
        // if($count == 1 && $order->product_id != 8){ 
        // 就是首单买水票送、八块钱一桶就不送了
        // 系统里有一个水票订单，就是当前的这个
        if($count == 1 && in_array($order->product_id,[4,5])){
            $voucher = Voucher::create([
                'customer_id' => $order->customer_id,
                'amount' => 1,
                'left' => 1,
                'price' => 0,
            ]);
            $message = "恭喜您，完成水票首单买一送一活动[庆祝]\n已赠送1张电子水票到您的账户，编号No.{$voucher->id}[烟花]\n订水优先使用电子水票抵付\n请继续完善送水地址电话[握手]";
            $to = $order->customer->wxid; //分群
            return app(Xbot::class)->send($message, $to);
        }
        // 补认领, 再次分单
        if(!$order->customer->deliver){
            $msg = $order->customer->getClaimParagraph();
            $msg .= "\n⚠️漏认领顾客，请再次转发";
            return app(Xbot::class)->send($msg, '21182221243@chatroom');
        }
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
        if($order->wasChanged('deliver_id')&&$order->status!=4) $this->sendMessage($order);
    }

    private function sendMessage(Order $order)
    {
        $message = $order->product->name . ":" . $order->amount . $order->product->unit . ":" . $order->id;

        // 购买水票
        $productIsVoucher = Str::contains($order->product->name, ['水票'])?true:false;

        $voucher = Voucher::where('customer_id', $order->customer_id)->where('left', ">" , 0)->first();
        $payInfo = $order->price?($order->price/100 * ($productIsVoucher?1:$order->amount)).'元':'水票:剩余:'.($voucher?$voucher->left:0);
        $message .= "\n支付:" . $payInfo;
        $message .= "\n客户:" . $order->customer->name. ':'. $order->customer_id ;
        $message .= "\n电话:" . $order->customer->telephone;
        $message .= "\n地址:" . $order->customer->address_detail;

        // sq水票订单
        if($productIsVoucher){
            $to = '20779741807@chatroom';
            $message = "[水票订单]\n" . $message;
        }else{
            $message = "[订单跟踪]\n" . $message;
            $deliverId = $order->customer->deliver->id; //分群
            $deliver = Deliver::find($deliverId);
            $to = $deliver->wxid; // 发到对应的4个群里！
            $message .= "\n如客户已签收，请转至本群更新订单状态";
        }
        return app(Xbot::class)->send($message, $to);
    }
}
