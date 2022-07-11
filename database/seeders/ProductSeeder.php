<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\Product::factory()->create([
            'name' => "18.9升桶装纯净水",
            'price' => 800,
            'amount' => 18900,
            'show' => true,
            'unit' => "桶",
        ]);
        \App\Models\Product::factory()->create([
            'name' => "18.9升桶装水 电子水票15张",
            'price' => 10000, // 100
            'amount' => 15,
            'show' => true,
            'unit' => "张",
        ]);
        \App\Models\Product::factory()->create([
            'name' => "18.9升桶装水 电子水票32张",
            'price' => 20000, // 200
            'amount' => 32,
            'show' => true,
            'unit' => "张",
        ]);
        \App\Models\Product::factory()->create([
            'name' => "一次性10升纯净水",
            'price' => 1100, // 11
            'amount' => 10000,
            'show' => true,
            'unit' => "桶",
        ]);
        \App\Models\Product::factory()->create([
            'name' => "一次性15升纯净水",
            'price' => 1300, // 13
            'amount' => 15000,
            'show' => false,
            'unit' => "桶",
        ]);
        \App\Models\Product::factory()->create([
            'name' => "空桶",
            'price' => 4000, // 40
            'amount' => 1,
            'show' => true,
            'unit' => "个",
        ]);
        //手压泵10块，空桶40
        \App\Models\Product::factory()->create([
            'name' => "手压泵",
            'price' => 1000, // 13
            'amount' => 1,
            'show' => false,
            'unit' => "个",
        ]);
    }
}
