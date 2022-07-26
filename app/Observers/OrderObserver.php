<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\Voucher;

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
        if(Order::where('customer_id', $order->customer_id)->count() ==1){
            $voucher = Voucher::create([
                'customer_id' => $order->customer_id,
                'amount' => 1,
                'left' => 1,
                'price' => 0,
            ]);
            $message = "恭喜您，完成首单买一送一活动\n已赠送1张电子水票到您的账户，编号No:{$voucher->id}\n回复【9391】即可水票订水！\n请继续完善送水地址电话";
            $to = $order->customer->wxid; //分群
            return app(Xbot::class)->send($message, $to);
        }
        Log::error(__LINE__,[$order]);
        if(!$order->customer->deliver) return; //首单无地址
        Log::error(__LINE__,[$order->customer->deliver]);
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
