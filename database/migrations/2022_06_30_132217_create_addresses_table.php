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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('abbr')->unique();
            $table->foreignId('deliver_id')->comment('配送工人编号1～4+');
            $table->unsignedInteger('max_building')->default(0)->comment('最多几栋');
            $table->unsignedInteger('max_unit')->default(0)->comment('最大单元');
            $table->unsignedInteger('max_floor')->default(0)->comment('最高层数');
            $table->unsignedInteger('max_number')->nullable()->default(null)->comment('最多几户');
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
        Schema::dropIfExists('addresses');
    }
};
