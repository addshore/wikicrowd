<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\GenerateAliasQuestions;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();

        // GenerateAliasQuestions
        $schedule->job(new GenerateAliasQuestions( 'enwiki', '300' ), "low")->hourly();
        $schedule->job(new GenerateAliasQuestions( 'dewiki', '200' ), "low")->hourly();
        $schedule->job(new GenerateAliasQuestions( 'plwiki', '100' ), "low")->hourly();
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
