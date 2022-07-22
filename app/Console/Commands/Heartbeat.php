<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Xbot;

class Heartbeat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'heartbeat:hit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // app(Xbot::class)->send(now()."\n心跳检测，5分钟一次\n如超过5分钟，说明掉线了，请及时处理", 'filehelper');
        app(Xbot::class)->send('同步通讯录', 'filehelper');
        return 0;
    }
}
