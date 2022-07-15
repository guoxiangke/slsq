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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('wxid');
            $table->string('telephone')->nullable();
            $table->foreignId('deliver_id')->nullable()->comment('配送工人群编号1～4+');
            $table->string('address_detail')->nullable()->comment('详细地址备注：20-1-904');
            $table->string('location')->nullable()->comment('wx定位的xml');
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
        Schema::dropIfExists('customers');
    }
};
