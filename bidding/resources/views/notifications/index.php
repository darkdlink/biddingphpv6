<?php
$title = 'Notificações - Sistema de Licitações';

$content = ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-bell me-2"></i>Notificações</h1>
    <div>
        <form method="POST" action="<?= route('notifications.mark-all-read') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-check-double me-1"></i> Marcar Todas como Lidas
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (count($notifications) > 0): ?>
            <div class="list-group">
                <?php foreach ($notifications as $notification): ?>
                    <div class="list-group-item list-group-item-action <?= !$notification->is_read ? 'list-group-item-primary' : '' ?>">
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
            <div class="text-center py-5">
                <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                <h4>Você não tem notificações</h4>
                <p class="text-muted">As notificações aparecerão aqui quando houver novidades sobre licitações.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();

// Renderizar o layout base
include(resource_path('views/layout/app.php'));
?>
