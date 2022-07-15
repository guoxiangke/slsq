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
            'name' => "sq师傅1群",
            'wxid' => '19047653243@chatroom',
            'telephone' => '13711112222',
        ]);
        \App\Models\Deliver::factory()->create([
            'name' => "sq师傅2群",
            'wxid' => '20208552364@chatroom',
            'telephone' => '13711112222',
        ]);
        \App\Models\Deliver::factory()->create([
            'name' => "sq师傅3群",
            'wxid' => '21103823454@chatroom',
            'telephone' => '13711112222',
        ]);
        \App\Models\Deliver::factory()->create([
            'name' => "sq师傅4群",
            'wxid' => '19928756226@chatroom',
            'telephone' => '13711112222',
        ]);
    }
}
