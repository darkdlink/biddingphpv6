<?php
// app/Services/NotificationService.php
namespace App\Services;

use App\Models\Bidding;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;

class NotificationService
{
    public function createBiddingNotifications()
    {
        $today = Carbon::today();
        $threeDaysFromNow = Carbon::today()->addDays(3);

        // Buscar licitações que estão a menos de 3 dias da data de abertura
        $biddings = Bidding::where('status', 'active')
            ->whereDate('opening_date', '>=', $today)
            ->whereDate('opening_date', '<=', $threeDaysFromNow)
            ->get();

        // Buscar usuários para notificar (poderia ser filtrado por papel/permissão)
        $users = User::all();

        foreach ($biddings as $bidding) {
            $daysRemaining = Carbon::now()->diffInDays($bidding->opening_date, false);

            if ($daysRemaining <= 0) {
                $message = "A licitação {$bidding->bidding_number} abre hoje!";
                $type = 'danger';
            } else if ($daysRemaining == 1) {
                $message = "A licitação {$bidding->bidding_number} abre amanhã!";
                $type = 'warning';
            } else {
                $message = "A licitação {$bidding->bidding_number} abre em {$daysRemaining} dias.";
                $type = 'info';
            }

            // Criar notificação para cada usuário
            foreach ($users as $user) {
                // Verificar se já existe uma notificação similar não lida
                $existingNotification = Notification::where('user_id', $user->id)
                    ->where('bidding_id', $bidding->id)
                    ->where('type', $type)
                    ->where('is_read', false)
                    ->exists();

                if (!$existingNotification) {
                    Notification::create([
                        'user_id' => $user->id,
                        'bidding_id' => $bidding->id,
                        'title' => 'Lembrete de Licitação',
                        'message' => $message,
                        'type' => $type,
                        'is_read' => false
                    ]);
                }
            }
        }
    }
}
