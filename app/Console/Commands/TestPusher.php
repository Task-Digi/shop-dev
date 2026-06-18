<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestPusher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-pusher';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $event = new \App\Events\ScanBroadcast('test', ['action' => 'test-action', 'foo' => 'bar']);
        
        $broadcaster = app(\Illuminate\Contracts\Broadcasting\Factory::class)->connection('pusher');
        $this->info(get_class($broadcaster));
        
        $broadcastEvent = new \Illuminate\Broadcasting\BroadcastEvent($event);
        
        $reflection = new \ReflectionMethod($broadcaster, 'formatChannels');
        $reflection->setAccessible(true);
        $channels = $reflection->invoke($broadcaster, $event->broadcastOn());
        
        $this->info("Formatted Channels list: " . json_encode($channels));
    }
}
