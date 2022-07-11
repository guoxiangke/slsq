<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Order;
use App\Models\Voucher;



class MessageController extends Controller
{
    // - 定一桶水
    //     - 定N桶水
    // - 支付1桶水
    //     - 支付N桶水
    
    // order.need.pay
    // order.need.amount
    // order.need.product

    // {"msgid":104730,"type":"text","wxid":"bluesky_still","remark":"AI天空蔚蓝","seat_user_id":1,"self":false,"content":"good"}
    private $wxid = '';
    private $cache;
    private $menu = '';
    public function __invoke(Request $request){
        // 验证消息
        if(!isset($request['msgid']) || $request['self'] == true)  return response()->json(null);
        
        $this->wxid = $request['wxid'];
        $this->cache = Cache::tags($this->wxid);
        // 群消息处理 
        if(Str::endsWith($this->wxid, '@chatroom')){
            // 
            // $this->sendMessage('群消息处理 TODO');
            return $this->_return();
        }
        $keyword = $request['content'];
        // 如果是 995, 自由聊天5分钟
        // stop.service.and.chat.as.human
        if($keyword == '995'){
            $this->cache->put('stop.service', true, 300);
            return $this->sendMessage('现在暂时退出订水系统，如需订水，请5分钟再试，如有任何问题，请和我留言，稍后回复您，谢谢！');
        }
        if($keyword == '999'){
            // 转发消息 到 客服群！
            return $this->sendMessage('我们正在处理您退款请求，一般24小时内到账，谢谢！');
        }

        if($this->cache->get('stop.service')){
            // TODO 消息都转发到 服务质量反馈群
            // xx说 “您的服务真/不好！”
            return $this->_return();
        }


        // 查找或存储用户
        $customer = Customer::firstOrCreate(['wxid'=> $this->wxid]); // "wxid":"bluesky_still","remark":"AI天空蔚蓝"
        $this->customer = $customer;
        // if($customer->wasRecentlyCreated) {
        //     // $this->_askForAddress(); 
        //     return $this->_return();
        // }

        // 更新用户的备注
        if($customer->name !== $request['remark']) $customer->update(['name'=>$request['remark']]);
        
        // 处理 送水工人的 消息
        if($customer->isDeliver()) {
            return $this->_return();
        }

        ////////////////////////////Menu//////////////////////////////
        $vouchers = Voucher::where('customer_id', $customer->id)->where('left', ">" , 0)->get();
        $hasVouchers = $vouchers->count()?true:false;

        $products = [];
        $priceKeyMap = [];// 8900 =>9391
        $menu = "";
        foreach (Product::all() as $product) {
            $name = $product->name;
            $price = $product->price;
            $productKey = "939{$product->id}";
            $products[$productKey] = compact('name', 'price');
            $priceKeyMap[$price] = $productKey;
            // 有的产品不展示 && 如果有水票了，不显示水票menu
            if($product->show){
                if($hasVouchers && Str::contains($product->name, ['水票'])){

                }else{
                    $menu .="\n【{$productKey}】{$name} ¥" . $price/100 . '元';        
                }
                
                
            }
        }
        $menu = "请回复产品编号或微支付对应金额" . $menu;
        $voucher = null;
        if($hasVouchers) {
            // 水票Left: 多个电子水票账户！
            foreach ($vouchers as $voucher) {
                $menu .="\n您的No:{$voucher->id}账户有电子水票{$voucher->left} 张，回复【9391】可自动抵付";
            }
            $voucher = $vouchers->first(); //后面使用第一个账户
        }
        $this->menu = $menu;
        ////////////////////////////Menu//////////////////////////////
        

        // 既有地址，又有手机号，下面处理老客户
        // 模拟支付测试： [收到转账]:￥44.0:附言:测试
        if(Str::contains($request['content'], ['[收到转账]:￥','.0:附言:测试']) 
            || $request['type'] == 'wcpay'){
            // $request['content'] = "[收到转账]:￥44.0:附言:测试";//todo  delete!
            $tmp = explode('￥', $request['content']);
            $tmp = explode(':', $tmp[1]);
            $paidMoney = (int)$tmp[0]*100; //8.0 => 800
            Log::error(__LINE__, [$paidMoney]);
            // ✅ 缓存内容中有订单数据，且支付金额一致！
            $orderData = $this->cache->get('order.need.pay');
            if($orderData && $orderData['price'] * 100 == $paidMoney){
                // 支付成功，创建订单，发货！
                $order = Order::create($orderData);
                $this->cache->flush();
                // get address or telephone!
                return $this->sendMessage('支付成功1，创建订单{$order->id} ，发货！');
            }

            // ✅ 如果直接转24元，来3桶的情况！ // 2400%800
            // $productKey = $this->cache->get('order.product.key');
            // if($productKey && $paidMoney%$products[$productKey]['price']==0){
            //     Log::error(__LINE__, [$paidMoney, $productKey, $products[$productKey]['price']]);
            //     $amount = $paidMoney/$products[$productKey]['price'];//3桶  2400/800
            //     $productId = (int) $productKey-9390;
            //     $product = Product::find($productId);
            //     $productIsVoucher = Str::contains($product->name, ['水票'])?true:false;
            //     $orderData = [
            //         'customer_id' => $customer->id,
            //         'product_id' => $productId,
            //         'voucher_id' => null, //没有水票
            //         'price' => $paidMoney, //总价格
            //         'amount' => $amount, //几桶
            //         'deliver_id' => null, //todo
            //         'status' => 1, // 信息整全
            //     ];

            //     $message = $productIsVoucher?'':"{$amount}桶 "."【{$products[$productKey]['name']}】马上送达！\n支付成功2，创建订单，发货！";
            //     $this->sendMessage($message);
            //     return $this->createOrder($orderData);
            // }
            
            // ✅ 直接转 准确的 单价 金额
            //  付款 8 16 24 8的倍数的金额
            $next = false;
            for ($i=1; $i < 20; $i++) {
                $nextprice = (int) $paidMoney/$i;
                $next = in_array($nextprice, array_keys($priceKeyMap));
                if($next){
                    $nextAmount = $i;
                    break;
                }
            }
            if($next){
                $productKey = (int) $priceKeyMap[$nextprice];
                $productId = $productKey-9390;

                // 购买水票
                $product = Product::find($productId);
                if(Str::contains($product->name, ['水票'])){
                    // TODO 无需购买水票的情况，您已有x张水票，无需购买 (不做了，允许多个水票账户！)
                    $tickets = $product->amount; //15 或 30+2 张
                    $voucher = Voucher::create([
                        'customer_id' => $customer->id,
                        'amount' => $tickets,
                        'left' => $tickets,
                        'price' => $nextprice,
                    ]);

                    // 创建订单
                    $orderData =[
                        'customer_id' => $customer->id,
                        'product_id' => $productId,
                        'amount' => $tickets, //数量
                        // 'deliver_id' => $deliverId,
                        'price' => $nextprice,
                        'status' => 1, //1 已wx支付
                    ];
                    $this->sendMessage("{$tickets}张水票已入您的电子账户，编号No:{$voucher->id}\n回复【9391】进行水票订水！");
                    return $this->createOrder($orderData);
                }else{
                    // 购买桶水
                    // 分配工人
                    // 工人马上出发 ，订单ID：（2）207011
                }

                // 创建订单
                $orderData = [
                    'customer_id' => $customer->id,
                    'product_id' => $productId,
                    'amount' => $nextAmount, //数量
                    // 'deliver_id' => $deliverId,
                    'status' => 1, //1 已wx支付
                    'price' => $nextprice,
                ];
                $this->sendMessage("师傅马上出发！" . "\n【{$products[$productKey]['name']}】{$nextAmount}桶");
                return $this->createOrder($orderData);
            }else{
                // 转账金额 不在 所有的价格范围里
                return $this->sendMessage("转账金额有误，请发起语音通话后, 回复【999】，24小时内退款！");
            }
        }
        //product 9391~9397
        if(in_array($keyword, array_values($priceKeyMap))) {
            $this->cache->put('order.product.key', $keyword, 60);
        }



        // 获取telephone后，存储
        $is_cache_request_telephone = $this->cache->get('wait.telephone');
        if($is_cache_request_telephone){
            $telephone = Str::of($keyword)->replaceMatches('/[^0-9]++/', '');
            if(Str::length($telephone)==11 && Str::startsWith($telephone,[1])){
                $customer->update(['telephone'=>$telephone]);
                $this->cache->forget('wait.telephone');
                return $this->sendMessage('谢谢，手机信息已收到！');
            }else{
                return $this->sendMessage('手机号不正确，请检查一下。。。');
            }
        }

        // 获取地址后，存储
        $is_cache_request_address = $this->cache->get('wait.address');
        if($is_cache_request_address){
            // TODO 验证地址后
                // 提取小区/大院名字，是否在数据库中
                // 从送水师傅那里 确认地址 或再次请求地址修正？
            $customer->update(['address_detail'=>$keyword]);
            $this->cache->forget('wait.address');
            $this->sendMessage('谢谢，地址信息已收到！');
            return $this->getAddressOrTelephone();
        }

        // 联系方式完整后 的下一个 对话
        $needAmount =  $this->cache->get('order.need.amount');
        $productKey = $this->cache->get('order.product.key', false);
        if($productKey) {
            // 请问要几桶？ 
            // TODO 提取回复的数量
            $productId = (int) $productKey-9390;
            $product = Product::find($productId);

            $amount = $keyword;
            if($needAmount && is_numeric($amount) && $amount>0){  //todo
                $priceInDB = $products[$productKey]['price'] * $amount; //8900x3
                $orderData = [
                    'customer_id' => $customer->id,
                    'product_id' => $productId,
                    'voucher_id' => null, //没有水票
                    'price' => $priceInDB, //总价格
                    'amount' => $amount, //几桶
                    'deliver_id' => null, //todo
                    'status' => 1, // 信息整全
                ];
                $this->cache->put('order.need.pay', $orderData, 300);
                return $this->sendMessage("ok, {$amount}桶水，微信转账".($priceInDB/100)."元\n，师傅马上送到！5分钟后失效，需要重新下单");
            }

            // 水票购水
            if($voucher && $productKey==9391){
                $left = --$voucher->left;
                $voucher->update(['left' => $left]);//1桶 默认
                $message = "您的水票账户No:{$voucher->id}剩余{$left}张，派单已发送师傅, 马上出发配送！";
                $orderData = [
                    'customer_id' => $customer->id,
                    'product_id' => 1, //product_id: "9391",
                    'amount' => 1, //几桶
                    'voucher_id' => $voucher->id,
                    // 'deliver_id' => $deliverId,
                    'status' => 1, // 信息整全
                ];

                $this->sendMessage($message);
                return $this->createOrder($orderData);
            }
            // 已知道用户要什么水
            {
                // 没有水票的情况 或者 有水票，但定的不是桶水的情况
                // $productIsVoucher 要买的产品是水票，不用出发
                $productIsVoucher = Str::contains($product->name, ['水票'])?true:false;
                {
                    $price = $products[$productKey]['price']/100;
                    $message = "微信直接转账 ¥{$price}，". ($productIsVoucher?"您将获得":"师傅马上出发！") ."\n【{$products[$productKey]['name']}】" . ($productIsVoucher?"\n购买成功后自动入账、自动抵付":"\n若定多{$product->unit}，请转 {$product->unit}数X{$price}元");
                    if($product->unit == '桶'){
                        $message .= "\n请问要几桶？";
                    }
                    $message.="\n红包拒收，24小时自动退回";
                    $this->cache->put('order.need.amount', true, 60);
                    return $this->sendMessage($message);
                }
            }
        }else{
            $is_cache_request_telephone = $this->cache->get('wait.telephone');
            if(!$is_cache_request_telephone){
                return $this->sendMessage($this->menu);
            }
            // $this->sendMessage($menu);
        }

        $this->getAddressOrTelephone();
    }

    private function _return(){
        return response()->json(null);
    }

    private function createOrder($data)
    {
        Order::create($data);
        $this->cache->flush();
        return $this->getAddressOrTelephone();
    }
    private function getAddressOrTelephone()
    { 
        // 请求存储地址与手机号
        if(!$this->customer->addressIsOk()){
            // $this->sendMessage($this->menu);
            $this->cache->put('wait.address', true, 360);
            return $this->sendMessage("请问送到哪里？（例如：城市花园 20-3-201）");
            // 2.获取地址后，存储
        }
        if(!$this->customer->telephone){
            // 1.发送请求手机号消息
            $this->cache->put('wait.telephone', true, 360);
            return $this->sendMessage('请问您的手机号是。。。');
        }
    }

    public function sendMessage($content, $wxid=null)
    {
        $wxid = $wxid?:$this->wxid;
        return Http::withToken(config('services.xbot.token'))
            ->post(config('services.xbot.endpoint'), [
                'type' => 'text',
                'to' => $wxid,
                'data' => [
                    'content' => $content
                ],
            ]);
    }

}
