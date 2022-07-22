<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Carbon\Carbon;
use App\Services\Xbot;
use Illuminate\Support\Str;

class TodayOverview extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overview:today';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get statics of orders';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // if($keyword == '今日统计'){
        $orders = Order::whereDate('created_at', Carbon::today())->get();
        $message = "订单总数：" . $orders->count();
        $total = $orders->reduce(function ($carry, $order) {
            $productIsVoucher = Str::contains($order->product->name, ['水票'])?true:false;
            if($productIsVoucher){
                $price = $order->price;
            }else{
                $price = $order->amount * $order->price??0;
            }
            return $carry + $price;
        });

        $message .= "\n收款总数：¥" . $total/100;
        // orders orderByProductId 
        $orders1 = $orders->mapToGroups(function ($item, $key) {
            return [$item['product_id'] => $item];
        });
        foreach($orders1 as $productId => $orders2){
            $amount = 0;
            $price = 0;
            $paidByVoucher = 0; //是否是水票支付
            foreach ($orders2 as $key => $order) {
                
                $productIsVoucher = Str::contains($order->product->name, ['水票'])?true:false;
                if($productIsVoucher){
                    $price += $order->price;
                    $amount += $order->amount/$order->product->amount;
                }else{
                    $amount += $order->amount;
                    $price += $order->amount * $order->price??0;
                }
                if($order->voucher_id) {
                    $paidByVoucher += $order->amount;
                }
            }
            $price = $price/100;

            $message .= "\n==================";
            $message .= "\n{$order->product->name}：";
            $message .= "\n数量：{$amount}".($paidByVoucher?"(水票{$paidByVoucher})":'');
            $message .= "\n金额：¥{$price}";
        }
        // $this->sendMessage($message);
        app(Xbot::class)->send($message, "20388549423@chatroom");

        return 0;
    }
}
