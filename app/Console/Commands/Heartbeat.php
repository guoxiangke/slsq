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
        app(Xbot::class)->send(now()."\n掉线检测，每小时一次", 'filehelper');
        return 0;
    }
}
