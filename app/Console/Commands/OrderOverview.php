<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Carbon\Carbon;
use App\Services\Xbot;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class OrderOverview extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * 默认按月统计 By Month
     * order:overview 0 #当月
     * order:overview 1 #上月
     * 按天统计 --byday
     * order:overview 0 --byday #当天
     * order:overview 1 --byday #1天前
     * order:overview 2 --byday #2天前
     */
    protected $signature = 'order:overview {offset?} {--byday}';

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
        $offset = $this->argument('offset')??'0';
        $isByDay = $this->option('byday')??false;
        if($isByDay){
            $from = Carbon::now()->subDays($offset)->startOfDay();
            if($offset == 0){ //今天的话，截止到当前时间，而非endOfDay
                $to = Carbon::now()->subDays($offset);
            }else{
                $to = Carbon::now()->subDays($offset)->endOfDay();
            }
        }else{ // By Month
            $from = Carbon::now()->subMonths($offset)->startOfMonth();
            if($offset == 0){ //当月的话，截止到当前时间，而非endOfMonth
                $to = Carbon::now()->subMonths($offset);
            }else{
                $to = Carbon::now()->subMonths($offset)->endOfMonth();
            }
        }

        // if($keyword == '今日统计'){
        $orders = Order::whereBetween('created_at', [$from, $to])->get();
        $message = "====-订单统计-====";
        $message .= "\n订单总数：" . $orders->count();
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
            $message .= "\n{$order->product->name}：{$amount}".($paidByVoucher?"(含{$paidByVoucher}张水票)":'');
            $message .= "\n金额：¥{$price}";
        }
        $message .= "\n==================";
        $message .= "\n起始时间：" . $from->format('M-d H:i');
        $message .= "\n截止时间：" . $to->format('M-d H:i');
        // $this->sendMessage($message);
        app(Xbot::class)->send($message, "20388549423@chatroom");

        $message = "====-按师傅统计-====";
        // 按师傅统计
        $orders = Order::whereBetween('created_at', [$from, $to])
            // whereDate('created_at', Carbon::today())
            ->where('status', 4) // 4 配送完毕，收到配送人员反馈
            ->get()
            ->groupBy('deliver_id');
        foreach ($orders as $deliverId => $items) {
            $deliver = $items->first()->deliver->name;
            $total = $items->reduce(function ($carry, $order) {
                return $carry + $order->amount;
            });
            $message .= "\n{$deliver}：{$total}";

            $orders1 = $items->mapToGroups(function ($item, $key) {
                return [$item['product_id'] => $item];
            });
            foreach($orders1 as $productId => $orders2){
                $amount = 0;
                foreach ($orders2 as $key => $order) {
                    $amount += $order->amount;
                }
                $message .= "\n{$order->product->name}：{$amount}";
            }
            $message .= "\n--------------------------";
        }
        $message .= "\n起始时间：" . $from->format('M-d H:i');
        $message .= "\n截止时间：" . $to->format('M-d H:i');
        app(Xbot::class)->send($message, "20388549423@chatroom");

        return 0;
    }
}
