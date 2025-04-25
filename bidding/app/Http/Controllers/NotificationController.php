<?php
namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = \App\Models\Notification::where('user_id', Auth::id())
        ->orderBy('created_at', 'desc')
        ->paginate(15);

        $content = $this->renderIndex($notifications);
        return response($content);
    }

    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);

        // Verificar se a notificação pertence ao usuário atual
        if ($notification->user_id != Auth::id()) {
            abort(403, 'Acesso não autorizado.');
        }

        $notification->update(['is_read' => true]);

        return redirect()->back()
            ->with('success', 'Notificação marcada como lida.');
    }

    public function markAllAsRead()
    {
        // Em vez de usar a relação notifications()
        \App\Models\Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return redirect()->back()
            ->with('success', 'Todas as notificações foram marcadas como lidas.');
    }

    private function renderIndex($notifications)
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="pt-br">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Notificações - Sistema de Licitações</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        </head>
        <body>
            <?php include(resource_path('views/layout/header.php')); ?>

            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Notificações</h1>
                    <div>
                        <form method="POST" action="<?= route('notifications.mark-all-read') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-secondary">
                                <i class="fas fa-check-double me-1"></i> Marcar Todas como Lidas
                            </button>
                        </form>
                    </div>
                </div>

                <?php if (session('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= session('success') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <?php if (count($notifications) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="list-group-item list-group-item-action <?= !$notification->is_read ? 'active' : '' ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1">
                                                <?php if ($notification->type == 'info'): ?>
                                                    <i class="fas fa-info-circle text-info me-2"></i>
                                                <?php elseif ($notification->type == 'warning'): ?>
                                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                                <?php elseif ($notification->type == 'danger'): ?>
                                                    <i class="fas fa-exclamation-circle text-danger me-2"></i>
                                                <?php elseif ($notification->type == 'success'): ?>
                                                    <i class="fas fa-check-circle text-success me-2"></i>
                                                <?php endif; ?>
                                                <?= $notification->title ?>
                                            </h5>
                                            <small><?= $notification->created_at->format('d/m/Y H:i') ?></small>
                                        </div>
                                        <p class="mb-1"><?= $notification->message ?></p>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <small>
                                                <?php if ($notification->bidding): ?>
                                                    <a href="<?= route('biddings.show', $notification->bidding_id) ?>">
                                                        Ver licitação
                                                    </a>
                                                <?php endif; ?>
                                            </small>
                                            <?php if (!$notification->is_read): ?>
                                                <form method="POST" action="<?= route('notifications.mark-read', $notification->id) ?>">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-sm btn-light">
                                                        <i class="fas fa-check me-1"></i> Marcar como lida
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-flex justify-content-center mt-4">
                                <?= $notifications->links() ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Você não tem notificações.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php include(resource_path('views/layout/footer.php')); ?>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
