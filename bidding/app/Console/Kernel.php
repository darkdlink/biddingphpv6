<?php
// app/Console/Kernel.php
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
        // Buscar licitações diariamente
        $schedule->command('biddings:fetch')->dailyAt('03:00')
            ->appendOutputTo(storage_path('logs/biddings-fetch.log'));

        // Buscar licitações específicas para cada segmento uma vez por semana
        $segments = ['tecnologia', 'saude', 'educacao', 'construcao', 'servicos'];
        foreach ($segments as $index => $segment) {
            // Distribuir ao longo da semana
            $dayOfWeek = ($index % 5) + 1; // 1=Monday, 5=Friday
            $schedule->command("biddings:fetch --segment={$segment} --days=30")
                ->weeklyOn($dayOfWeek, '04:00')
                ->appendOutputTo(storage_path("logs/biddings-fetch-{$segment}.log"));
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
