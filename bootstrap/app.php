<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function (Illuminate\Console\Scheduling\Schedule $schedule) {
        // Excel: 1 solo worker (serializza), limiti alti
        $schedule->command('queue:work database --queue=excel --sleep=1 --tries=3 --timeout=7200 --memory=4096')
            ->withoutOverlapping(5)   // lock max 5 minuti se crasha
            ->everyMinute();

        // PDF: separato (puoi anche tenerlo seriale con un altro servizio)
        $schedule->command('queue:work database --queue=pdf --sleep=1 --tries=3 --timeout=3600 --memory=4096')
            ->withoutOverlapping(5)
            ->everyMinute();

        // Default: limiti più bassi
        $schedule->command('queue:work database --queue=default --sleep=1 --tries=3 --timeout=600 --memory=1024')
            ->withoutOverlapping(5)
            ->everyMinute();
    })
    ->create();
