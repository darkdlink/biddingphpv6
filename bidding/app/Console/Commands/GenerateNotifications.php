<?php
namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class GenerateNotifications extends Command
{
    protected $signature = 'notifications:generate';
    protected $description = 'Gera notificações para licitações próximas do prazo';

    public function handle()
    {
        $this->info('Gerando notificações...');

        $notificationService = new NotificationService();
        $notificationService->createBiddingNotifications();

        $this->info('Notificações geradas com sucesso!');

        return 0;
    }
}
