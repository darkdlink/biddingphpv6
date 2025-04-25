<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Executa a busca de licitações diariamente
        $schedule->command('biddings:fetch')->daily();

        // Gera notificações diariamente
        $schedule->command('notifications:generate')->daily();

        // Limpa os arquivos de log a cada semana e mantém apenas os últimos 7 dias
        $schedule->command('log:clear')->weekly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /**
     * The Artisan commands provided by your application.
     */
    protected $commands = [
        \App\Console\Commands\FetchBiddings::class,
        \App\Console\Commands\GenerateNotifications::class,
    ];
}
