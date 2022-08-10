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
use App\Services\Icr;



class MessageController extends Controller
{
    private $wxid = '';
    private $remark = '';
    private $cache;
    private $isPaid = false;
    private $groups = [
        'statistics' => "20388549423@chatroom", // sq对账群 统计群 上下班时间设置
        'address_update' => '17746965832@chatroom', // 地址更新更新群
        'customer_owner' => '21182221243@chatroom', // 客户认领群
        'refund' => '20479347997@chatroom', // 客服退款群
    ];
    public function __invoke(Request $request){
        // 验证消息
        if(!isset($request['msgid']) || $request['self'] == true)  return response()->json(null);

        $wxidOrCurrentRoom = $request['wxid'];
        $isRoom = Str::endsWith($wxidOrCurrentRoom, '@chatroom');
        // personal
        $this->wxid = $wxidOrCurrentRoom;
        $this->remark = $request['remark'];
        if($isRoom){
             $this->wxid = $request['from'];
             $this->remark = $request['from_remark'];
        }
        $this->cache = Cache::tags($this->wxid);

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
        // $keyword = 'sq2212345';
        // Coupon 8位，以sq22开头，后5位为数字，共6位数字
        if(Str::startsWith($keyword,'sq22') ){
            $re = '/sq22\d{5}/';
            preg_match($re, $keyword, $matches);
            if($matches){
                $code = $matches[0];
                try {
                    $customer->redeem($code);
                    // 兑奖 送20张水票
                    $tickets = 20;
                    $voucher = Voucher::create([
                        'customer_id' => $customer->id,
                        'amount' => $tickets,
                        'left' => $tickets,
                        'price' => 0,
                    ]);
                    // 创建订单
                    $orderData =[
                        'customer_id' => $customer->id,
                        'product_id' => 8, //product_id=8 赠送老师20张水票活动
                        'amount' => $tickets, //数量
                        'deliver_id' => null,
                        'price' => 0,
                        'status' => 1, //1 已wx支付
                    ];
                    $msg = "兑换成功，{$tickets}张水票已入您的电子账户，编号No:{$voucher->id}\n回复【9391】即可水票订水！";
                    $this->createOrder($orderData);
                    return $this->sendMessage($msg);
                }catch (\Exception $e){
                    $msg = "兑换码有误，请检查后再试\n" . $e->getMessage();
                    $this->sendMessage($msg);
                }
            }
            return $this->_return();
        }
        // 群消息处理
        if($isRoom){
            $contents = explode("\n", $keyword);
            if($wxidOrCurrentRoom == $this->groups['address_update']){
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
            if($wxidOrCurrentRoom == $this->groups['customer_owner']){
                if($contents[0] == '[客户认领]'){
                    // 厂~1~小懂~下车站
                    if(!Str::startsWith($this->remark, '厂~')){
                        return $this->sendMessage("认领师傅备注不正确！应为：\n厂~1~xxx\n厂~2~xxx", $wxidOrCurrentRoom);
                        // 请备注好师傅后，让师傅发1~2条消息给 机器人
                    }
                    $fromRemark = explode("~", $this->remark);// 厂~1~xxx
                    $deliverId = $fromRemark[1];// 1~4群
                    // $deliverId = 2;

                    $secondLine = explode(":", $contents[1]); //客户:AI天空蔚蓝:1
                    $customer = Customer::find($secondLine[2]);
                    $customer->update(['deliver_id' => $deliverId]);//1~4
                    $this->sendMessage("[认领成功]->{$deliverId}群\n{$contents[1]}\n{$contents[2]}\n{$contents[3]}\n快去【sq师傅{$deliverId}群】接单吧[胜利][强]", $wxidOrCurrentRoom);
                    // TODO 认领成功前，不可再次下单！
                    // 把首单发送到指定的群！
                    Order::where('customer_id', $customer->id)->latest()->first()->update(['deliver_id'=>$deliverId]); // 暂时借用 deliver_id 字段
                }
            }
            // sq对账群 统计群 上下班时间设置
            if($wxidOrCurrentRoom == $this->groups['statistics']){
                switch ($keyword) {
                    case '今日统计':
                        return Artisan::call('order:overview 0 --byday');
                        break;
                    case '昨日统计':
                        return Artisan::call('order:overview 1 --byday');
                        break;
                    case '本月统计':
                        return Artisan::call('order:overview 0');
                        break;
                    case '上月统计':
                        return Artisan::call('order:overview 1');
                        break;

                    default:
                        // code...
                        break;
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
                if(Str::startsWith($this->remark,'厂~')){
                    $customer = Customer::where(['wxid'=> $request['from']])->first();
                    $secondLine = explode(":", $contents[1]); //产品名字:1个:1
                    $orderId = $secondLine[2];
                    $order = Order::find($orderId);
                    $order->deliver_id = $customer->id;
                    $order->status = 4; //4 配送完毕，收到配送人员反馈
                    $order->saveQuietly(); // 不要OrderObserver
                    $this->sendMessage("[订单完成]\n{$contents[1]}\n{$contents[2]}\n{$contents[3]}\n{$contents[4]}\n{$contents[5]}\n谢谢师傅，辛苦了[抱拳][强]", $wxidOrCurrentRoom);
                }else{
                    return $this->sendMessage("认领师傅备注不正确！应为：\n厂~1~xxx\n厂~2~xxx", $wxidOrCurrentRoom);
                }
            }
            return $this->_return();
        }

        // 如果是 995, 自由聊天5分钟
        // stop.service.and.chat.as.human
        if($keyword == '995'){
            $this->cache->put('stop.service', true, 300);
            return $this->sendMessage('现在暂时退出订水系统，如需订水，请5分钟再试，如有任何问题，请和我留言，稍后回复您，谢谢！');
        }
        // 【讲个笑话】或【石岭天气】
        if(Str::contains($keyword, [
            '讲个笑话',
            '石岭天气',
        ])){

            $this->cache->flush();
            $res = app(Icr::class)->run($keyword);
            if($res) {
                return $this->sendMessage($res->Reply);
            }
        }

        // 上下班 时间处理
        $now = date('G.i'); // 0-24 (7.30)
        $on = option('on', 8);
        $off = option('off', 21);
        if($now >= $off || $now <= $on){
            if($this->cache->has('menu.count')){ //出现1次！
                $this->sendMessage("请注意：\n师傅上班时间：{$on}-{$off}\n非营业期间可正常下单，开工后优先派送[抱拳]");
            }
        }

        switch ($keyword) {
            case '一桶':
            case '1桶':
            case '送一桶':
            case '来一桶':
            case '送一桶水':
            case '来一桶水':
                $this->sendMessage("想定18.9升桶装纯净水[疑问]\n请回复【9391】给我[强]\n或直接微信转账8元过来[勾引]\n师傅马上送到[强]");
                return $this->_return();
                break;
            case '999':
                $this->sendMessage("很抱歉，给您带来不便[难过]\n我们正在处理您的请求，一般24小时内到账[抱拳]");
                $message = '[客户请求退款]';
                $message .= "\n客户:" . $customer->name. ':'. $customer->id;
                $message .= "\n电话:" . $customer->telephone;
                $message .= "\n地址:" . $customer->address_detail;
                $this->sendMessage($message,  $this->groups['refund']);
                return $this->_return();
                break;

            case '收到红包，请在手机上查看':
                $this->sendMessage("请使用微信转账，暂不支持红包支付！\n红包将会在24小时内自动退还到您的微信\n不好意思，小泉还在成长中[害羞]\n给您带来的不便，谢谢理解[抱拳]");
                return $this->_return();
                break;
            default:
                // code...
                break;
        }

        if($this->cache->get('stop.service')){
            // TODO 消息都转发到 服务质量反馈群
            // xx说 “您的服务真/不好！”
            return $this->_return();
        }

        // 好友拉黑信息处理：不然死循环
        if(Str::contains($keyword, '请先发送朋友验证请求')){
            return $this->_return();
        }

        // 处理 送水工人的 消息
        if($customer->isDeliver()) {
            // return $this->_return();
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
        $menu = "您好，我是订水智能客服小泉[调皮]\n请回复编号订水[强]" . $menu;
        $voucher = null;
        if($hasVouchers) {
            // 水票Left: 多个电子水票账户！
            foreach ($vouchers as $voucher) {
                $menu .="\n水票账户No.{$voucher->id}剩余：{$voucher->left}张，回复【9391】可自动抵付[Peace]";
            }
            $voucher = $vouchers->first(); //后面使用第一个账户
        }else{
            $menu .="\n极速订水？微信支付对应金额即可[呲牙]";
        }
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
                return $this->sendMessage("派单已发给师傅, 马上出发配送🏃\n请耐心等待[抱拳]");
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
                $message = "【{$products[$productKey]['name']}】{$nextAmount}桶";
                if($now >= $off || $now <= $on){
                    $message .= "不好意思，师傅上班时间：{$on}-{$off}\n开工后优先派送🏃请耐心等待[抱拳]";
                }else{
                    $message .= "\n马上送到🏃";
                }
                $this->sendMessage($message);
                $this->isPaid = true;
                return $this->createOrder($orderData);
            }else{
                // $isSelf "wxid_i5qnb05xy9522"
                if($this->wxid == 'wxid_i5qnb05xy9522'){
                    // 自己转账出去
                    $message = "[对外转账]";
                    $message .= "\n金额:" . $paidMoney/100;
                    $message .= "\n发起人:" . $customer->name. ':'. $customer->id;
                    $message .= "\n接收人:？";
                    // sq对账群 统计群 上下班时间设置
                    $this->sendMessage($message, $this->groups['statistics']);
                }
                // 转账金额 不在 所有的价格范围里
                $message = "转账金额有误：";
                $message .= "\n金额:" . $paidMoney/100 ;
                $message .= "\n客户:" . $customer->name. ':'. $customer->id;
                $message .= "\n电话:" . $customer->telephone;
                $message .= "\n地址:" . $customer->address_detail;
                // 退款群
                $this->sendMessage($message, $this->groups['refund']);
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
                    'deliver_id' => $customer->deliver_id,
                    'status' => 1, // 信息整全
                ];
                $this->cache->put('order.need.pay', $orderData, 180);
                return $this->sendMessage("[OK]{$amount}桶水，微信转账".($priceInDB/100)."元\n支付后师傅马上出发！3分钟后失效，需要重新下单支付");
            }

            // 水票购水
            if($voucher && $productKey==9391){
                $left = --$voucher->left;
                $voucher->update(['left' => $left]);//1桶 默认
                $message = "您的水票账户No.{$voucher->id}剩余{$left}张，派单已发送师傅, 马上出发配送！";
                $orderData = [
                    'customer_id' => $customer->id,
                    'product_id' => 1, //product_id: "9391",
                    'amount' => 1, //几桶
                    'voucher_id' => $voucher->id,
                    'deliver_id' => $customer->deliver_id,
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
                // 智能AI机器人
                    // 是普通的桶吗
                    // 正常桶吗？
                    // 桶多大的？
                    // 多大的桶？
                    // 纸质水票能不能用？
               if(Str::contains($keyword, ['桶吗', '多大', '多少升', '几升', '饮水机能'])){
                    return $this->sendMessage("我们使用的是标准18.9升的桶，兼容市面标准饮水机，您可以放心下单订购。");
               }
               if(Str::contains($keyword, ['纸', '纸质', '纸质水票', '能'])){
                    return $this->sendMessage("不好意思，暂时不支持纸质水票下单，请使用传统方式下单，谢谢您的理解");
               }
                // 如果用户收到1次菜单了，不再发送菜单，随意聊天
                if($this->cache->get('menu.count')>0){
                    $this->cache->forget('menu.count'); // 交替出现
                    return $this->sendMessage("对不起，小泉还在学习中[抱拳]\n请按菜单指示操作定水[握手]\n回复【讲个笑话】或【石岭天气】试试[强]");
                }else{
                    $this->cache->increment('menu.count');
                    return $this->sendMessage($menu);
                }

            }
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
