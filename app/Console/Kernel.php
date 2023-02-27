<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('order:dispatch')->everyTenMinutes();
        $schedule->command('order:cancel')->everyMinute();
        $schedule->command('taxi:cancel')->everyMinute();
        $schedule->command('order:manage')->everySixHours();
        if((bool) setting('autoassignmentsystem', 0)){
            $schedule->command('order:assign:regular')->everyMinute();
        }else{
            $schedule->command('order:assign')->everyMinute();
        }
        $schedule->command('order:auto_assignment_cancel')->everyMinute();
        $schedule->command('subscription:manage')->hourly();
        $schedule->command('order:driver:clear')->hourly();
        //
        setting([
            "cronJobLastRun" => \Carbon\Carbon::now()->translatedFormat("d M Y \\a\\t h:i:s a"),
            "cronJobLastRunRaw" => \Carbon\Carbon::now(),
        ])->save();
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
