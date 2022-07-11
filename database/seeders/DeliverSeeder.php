<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\Deliver::factory()->create([
            'name' => "厂1～秦汉",
            'wxid' => 'LI18810929805',
            'telephone' => '137111',
        ]);
        \App\Models\Deliver::factory()->create([
            'name' => "厂2～董文平",
            'wxid' => 'wxid19930311',
            'telephone' => '137111',
        ]);
        \App\Models\Deliver::factory()->create([
            'name' => "厂3～祁总",
            'wxid' => 'wxid_uhmuim1bsyhl21',
            'telephone' => '137111',
        ]);
        \App\Models\Deliver::factory()->create([
            'name' => "厂4～秦汉",
            'wxid' => 'z18393130989',
            'telephone' => '137111',
        ]);
    }
}
