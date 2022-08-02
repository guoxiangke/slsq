<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Voucher>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'wxid' => $this->faker->name(),
            'address_detail' => $this->faker->name(),
//        $table->string('telephone')->nullable();
//        $table->foreignId('deliver_id')->nullable()->comment('配送工人群编号1～4+');

        ];
    }
}
