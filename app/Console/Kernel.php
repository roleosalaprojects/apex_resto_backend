<?php

namespace App\Console;

use App\Console\Commands\FixProfitOnSale;
use App\Mail\OrderShipped;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Mail;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
      \App\Console\Commands\FixProfitOnSale::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
//         $schedule->command('inspire')->everyFiveSeconds();
//        $schedule->command('sendEmail')->everyFifteenSeconds();
        // This is for test only
        $schedule->call(function(){
            Mail::to('roleosala@gmail.com')->send(new \App\Mail\PurchaseOrderNotification(rand(1, 4)));
        })->everyTenSeconds();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
