<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Executar scraping diariamente às 8h da manhã
        $schedule->command('bids:scrape')->dailyAt('08:00');

        // Enviar alertas de licitações que vencem em 3 dias
        $schedule->command('bids:send-alerts --days=3')->dailyAt('09:00');
    }
}
