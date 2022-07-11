<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id');

            // order.need.pay
            // order.need.amount
            // order.need.product
            $table->foreignId('product_id')->nullable();
            $table->foreignId('voucher_id')->nullable();// 默认位Null，不为Null是水票支付，扣除水票剩余数量后，再更新order
            $table->unsignedInteger('price')->nullable()->comment("成交价格"); // 支付的订单价格（单价*数量）
            $table->unsignedInteger('amount')->nullable()->comment('几桶');
            $table->foreignId('deliver_id')->nullable();
            $table->unsignedTinyInteger('status')->default(0);

            // 状态
                // 0 刚创建（缺少信息）
                // 1 信息整全
                // 2 已通知 配送人员
                // 3 配送人员 已出发
                // 4 配送完毕，收到配送人员反馈
                // 5 订单取消 / 人工手动退款wx｜退已扣除的电子水票
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
