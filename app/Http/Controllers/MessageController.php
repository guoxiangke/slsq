<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Order;
use App\Models\Voucher;
use App\Services\Xbot;



class MessageController extends Controller
{
    private $wxid = '';
    private $remark = '';
    private $cache;
    private $menu = '';
    private $isPaid = false;
    public function __invoke(Request $request){
        Log::error(__LINE__,[$request->all()]);
        // 验证消息
        if(!isset($request['msgid']) || $request['self'] == true)  return response()->json(null);
        
        $wxidOrCurrentRoom = $request['wxid'];
        $isRoom = Str::endsWith($wxidOrCurrentRoom, '@chatroom');
        Log::error(__LINE__,[$isRoom,'$isRoom']);
        // personal
        $this->wxid = $wxidOrCurrentRoom;
        $this->remark = $request['remark'];
        if($isRoom){
             $this->wxid = $request['from'];
             $this->remark = $request['from_remark'];
        }

        Log::error(__LINE__,[$this->remark,'$this->remark']);
        // 查找或存储用户
        $customer = Customer::firstOrCreate(['wxid'=> $this->wxid]); // "wxid":"bluesky_still","remark":"AI天空蔚蓝"
        $this->customer = $customer;

        // 更新用户的备注
        if($customer->name !== $this->remark){
            $customer->name = $this->remark;
            // Saving A Single Model Without Events
            $customer->saveQuietly();
        }

        $keyword = $request['content'];
        // 群消息处理 
        if($isRoom){
            $contents = explode("\n", $keyword);
            if($wxidOrCurrentRoom == '17746965832@chatroom'){
                if($contents[0] == '[地址更新]'){
                    $secondLine = explode(":", $contents[1]); //客户:AI天空蔚蓝:1
                    $customer = Customer::find($secondLine[2]);
                    $this->cache = Cache::tags($customer->wxid); //这时的cache tag有变化
                    $this->getAddress('不好意思，地址请再详细一点', $customer->wxid);
                }
                if($contents[0] == '[电话更新]'){
                    $secondLine = explode(":", $contents[1]); //客户:AI天空蔚蓝:1
                    $customer = Customer::find($secondLine[2]);
                    $this->cache = Cache::tags($customer->wxid); //这时的cache tag有变化
                    $this->getTelephone('不好意思，手机号好像有误', $customer->wxid);
                }
            }
            if($wxidOrCurrentRoom == '21182221243@chatroom'){
                if($contents[0] == '[客户认领]'){
                    // 厂～1～小懂～下车站
                    Log::error(__LINE__,[$this->remark,'$this->remark'], Str::startsWith($this->remark, '厂～'));
                    if(!Str::startsWith($this->remark, '厂～')){
                        return $this->sendMessage("认领师傅备注不正确！应为：\n厂～1～xxx\n厂～2～xxx", $wxidOrCurrentRoom);
                        // 请备注好师傅后，让师傅发1～2条消息给 机器人
                    }
                    $fromRemark = explode("～", $this->remark);// 厂～1～xxx
                    $deliverId = $fromRemark[1];// 1~4群
                    // $deliverId = 2;

                    $secondLine = explode(":", $contents[1]); //客户:AI天空蔚蓝:1
                    $customer = Customer::find($secondLine[2]);
                    $customer->update(['deliver_id' => $deliverId]);//1~4
                    $this->sendMessage("认领成功！此客户定单将发送到{$deliverId}群", $wxidOrCurrentRoom);
                    // TODO 认领成功前，不可再次下单！
                    // 把首单发送到指定的群！
                    Order::where('customer_id', $customer->id)->latest()->first()->update(['deliver_id'=>$deliverId]); // 暂时借用 deliver_id 字段
                }
            }
            // sq对账群 统计群 上下班时间设置
            if($wxidOrCurrentRoom == '20388549423@chatroom'){
                if($keyword == '今日统计'){
                    return Artisan::call('overview:today');
                }
                // 上下班时间设置:on:7
                // 上下班时间设置:off:23
                if(Str::startsWith($keyword,'上下班时间设置:')){
                    $tmpArr = explode(':', $keyword);
                    $type = $tmpArr[1];
                    $value = $tmpArr[2];
                    // TODO 时间0-24小时设定
                    option([$type => $value]);
                    $this->sendMessage('设置成功！', $wxidOrCurrentRoom);
                }
            }

            // 1~4群，订单跟踪
            if($contents[0] == '[订单跟踪]'){
                if(Str::startsWith($this->remark,'厂～')){
                    $customer = Customer::where(['wxid'=> $request['from']])->first();
                    $secondLine = explode(":", $contents[1]); //产品名字:1个:1
                    $orderId = $secondLine[2];
                    $order = Order::find($orderId);
                    $order->deliver_id = $customer->id;
                    $order->status = 4; //4 配送完毕，收到配送人员反馈
                    $order->saveQuietly(); // 不要OrderObserver
                    $this->sendMessage("[抱拳]辛苦了", $wxidOrCurrentRoom);
                }else{
                    return $this->sendMessage("认领师傅备注不正确！应为：\n厂～1～xxx\n厂～2～xxx", $wxidOrCurrentRoom);
                }
            }            
            return $this->_return();
        }

        $this->cache = Cache::tags($this->wxid);
        // 如果是 995, 自由聊天5分钟
        // stop.service.and.chat.as.human
        if($keyword == '995'){
            $this->cache->put('stop.service', true, 300);
            return $this->sendMessage('现在暂时退出订水系统，如需订水，请5分钟再试，如有任何问题，请和我留言，稍后回复您，谢谢！');
        }
        if($keyword == '999'){
            // 转发消息 到 客服群！
            $this->sendMessage('客户发送999请求退款！', "20479347997@chatroom");
            return $this->sendMessage('我们正在处理您退款请求，一般24小时内到账，谢谢！');
        }

        if($this->cache->get('stop.service')){
            // TODO 消息都转发到 服务质量反馈群
            // xx说 “您的服务真/不好！”
            return $this->_return();
        }


        
        // 处理 送水工人的 消息
        if($customer->isDeliver()) {
            // return $this->_return();
        }

        // 上下班 时间处理
        $now = date('G.i'); // 0-24 (7.30)
        $on = option('on', 8);
        $off = option('off', 21);
        if($now >= $off || $now <= $on){
            return $this->sendMessage("不好意思，上班时间：{$on}-{$off}");
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
                    // do nothing!
                }else{
                    $menu .="\n【{$productKey}】{$name} ¥" . $price/100 . '元';        
                }
            }
        }
        $menu = "您好，我是订水智能客服小泉[微笑]\n请回复编号订水[ThumbsUp]" . $menu;
        $voucher = null;
        if($hasVouchers) {
            // 水票Left: 多个电子水票账户！
            foreach ($vouchers as $voucher) {
                $menu .="\n水票剩余{$voucher->left}张，回复【9391】可自动抵付";
            }
            $voucher = $vouchers->first(); //后面使用第一个账户
        }
        $menu .="\n极速订水？微信支付对应金额即可[呲牙]";
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
            // ✅ 缓存内容中有订单数据，且支付金额一致！
            $orderData = $this->cache->get('order.need.pay');
            if($orderData && $orderData['price'] * 100 == $paidMoney){
                // 支付成功，创建订单，发货！
                $order = Order::create($orderData);
                $this->cache->flush();
                // get address or telephone!
                return $this->sendMessage('支付成功，创建订单{$order->id} ，发货！');
            }
                
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
                        'deliver_id' => null,
                        'price' => $nextprice,
                        'status' => 1, //1 已wx支付
                    ];
                    $this->sendMessage("{$tickets}张水票已入您的电子账户，编号No:{$voucher->id}\n回复【9391】即可水票订水！");
                    $this->isPaid = true;
                    return $this->createOrder($orderData);
                }

                // 创建订单
                $orderData = [
                    'customer_id' => $customer->id,
                    'product_id' => $productId,
                    'amount' => $nextAmount, //数量
                    // 'deliver_id' => $customer->deliver_id,
                    'status' => 1, //1 已wx支付
                    'price' => $nextprice,
                ];
                $this->sendMessage("【{$products[$productKey]['name']}】{$nextAmount}桶"."\n马上送到！");
                $this->isPaid = true;
                return $this->createOrder($orderData);
            }else{
                // 转账金额 不在 所有的价格范围里
                $message = "转账金额有误：";
                $message .= "\n金额:" . $paidMoney/100 ;
                $message .= "\n客户:" . $customer->name. ':'. $customer->id ;
                $message .= "\n电话:" . $customer->telephone;
                $message .= "\n地址:" . $customer->address_detail;

                $this->sendMessage($message, "20479347997@chatroom");
                return $this->sendMessage("转账金额有误, 回复【999】，24小时内退款！");
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
                $message = "[抱拳]谢谢，收到";

                // 把last单子发一遍！
                $order = Order::where(['customer_id' => $customer->id])->first();
                if($order) {
                    $productIsVoucher = Str::contains($order->product->name, ['水票'])?true:false;
                    if($productIsVoucher){
                        $message .= "\n使用水票订水，请回复【9391】！"; 
                    }else{
                        $message .= "\n师傅已接单，正在快马加鞭！";    
                    }
                }
                return $this->sendMessage($message);
            }else{
                return $this->sendMessage('❌手机号错误，请重新回复准确手机号码！');
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
            $this->sendMessage("[抱拳]谢谢，收到");
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
            // 一个师傅1次最多装几桶水？ 18
            if($needAmount && is_numeric($amount) && $amount>0 && $amount<=18){
                $priceInDB = $products[$productKey]['price'] * $amount; //8900x3
                $orderData = [
                    'customer_id' => $customer->id,
                    'product_id' => $productId,
                    'voucher_id' => null, //没有水票
                    'price' => $priceInDB, //总价格
                    'amount' => $amount, //几桶
                    // 'deliver_id' => $customer->deliver_id,
                    'status' => 1, // 信息整全
                ];
                $this->cache->put('order.need.pay', $orderData, 180);
                return $this->sendMessage("[OK]{$amount}桶水，微信转账".($priceInDB/100)."元\n支付后师傅马上出发！3分钟后失效，需要重新下单支付");
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
                    // 'deliver_id' => $customer->deliver_id,
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
                    $message = "微信转账 ¥{$price}，". ($productIsVoucher?"您将获得":"师傅马上出发！") ."\n【{$products[$productKey]['name']}】" . ($productIsVoucher?"\n购买成功后自动入账、自动抵付":"\n若定多{$product->unit}，请转¥{$product->unit}数X{$price}元");
                    if(!$productIsVoucher) $this->cache->put('order.need.amount', true, 60);
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
        if($this->isPaid == true){
            $this->cache->put('order.isPaid', true);//如果支付成功后，没有提供地址电话，则一直，等待地址和电话
        }
        $this->getAddressOrTelephone();
    }

    protected function getAddressOrTelephone()
    {
        // 请求存储地址与手机号
        if(!$this->customer->addressIsOk()){
            // $this->sendMessage($this->menu);
            return $this->getAddress();
            // 2.获取地址后，存储
        }
        if(!$this->customer->telephone){
            // 1.发送请求手机号消息
            return $this->getTelephone();
        }
    }

    protected function getTelephone($msg = '请留下手机号', $wxid=null){
        $isPaid =  $this->cache->get('order.isPaid');
        $this->cache->flush();
        if($isPaid){
            $this->cache->put('wait.telephone', true); //一直等电话，直到成功！
        }else{
            $this->cache->put('wait.telephone', true, 360);
        }
        return $this->sendMessage($msg, $wxid);
    }

    protected function getAddress($msg = "请留下送水地址", $wxid=null){
        $isPaid =  $this->cache->get('order.isPaid');
        $this->cache->flush();
        if($isPaid){
            //一直等地址，直到成功！
            $this->cache->put('wait.address', true);
            $this->cache->put('order.isPaid', true);
        }else{
            $this->cache->put('wait.address', true, 360);
        }
        return $this->sendMessage($msg, $wxid);
    }

    protected function sendMessage($content, $wxid=null)
    {
        $wxid = $wxid?:$this->wxid;
        return app(Xbot::class)->send($content, $wxid);
    }

}
