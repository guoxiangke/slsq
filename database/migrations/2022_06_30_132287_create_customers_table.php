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
            // name  telephone address_id|小区  Build|_号楼   Unit|_单元 address_detail|详细地址101   is_ok
            $table->string('name')->nullable();
            $table->string('wxid');
            $table->string('telephone')->nullable();
            $table->foreignId('address_id')->nullable()->comment('小区');
            // $table->foreignId('deliver_id')->nullable()->comment('配送工人编号1～4+');
            $table->string('address_detail')->nullable()->comment('详细地址备注：20-1-904');
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
