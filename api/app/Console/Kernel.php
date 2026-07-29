<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('subscriptions:check-payments')
            ->dailyAt('02:00')
            ->withoutOverlapping(60)
            ->onOneServer()
            ->runInBackground();

        // Billing & Payments: recurring services (hosting, retainers).
        $schedule->command('billing:charge-recurring')
            ->dailyAt('03:00')
            ->withoutOverlapping(60)
            ->onOneServer()
            ->runInBackground();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
