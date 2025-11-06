<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
   /* protected function schedule(Schedule $schedule): void
    {
        // Esegui il worker ogni minuto
        $schedule->command('queue:work database --queue=excel --sleep=1 --tries=3 --timeout=7200 --memory=3500')
            ->withoutOverlapping(5)
            ->everyMinute();
    }*/

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
