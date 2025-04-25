<?php
use Illuminate\Support\Facades\Auth;
$currentRoute = request()->route() ? request()->route()->getName() : '';

// Função auxiliar para verificar se há notificações não lidas
function getUnreadNotificationsCount() {
    if (Auth::check()) {
        // Verificar se o model Notification existe e fazer a consulta diretamente
        if (class_exists('App\Models\Notification')) {
            return \App\Models\Notification::where('user_id', Auth::id())
                ->where('is_read', false)
                ->count();
        }
    }
    return 0;
}

// Obter contagem de notificações não lidas
$unreadCount = getUnreadNotificationsCount();
?>
<header class="bg-dark text-white">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= route('dashboard') ?>">
                <i class="fas fa-gavel me-2"></i> Bidding System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= $currentRoute == 'dashboard' ? 'active' : '' ?>" href="<?= route('dashboard') ?>">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= str_contains($currentRoute, 'companies') ? 'active' : '' ?>" href="<?= route('companies.index') ?>">
                            <i class="fas fa-building me-1"></i> Empresas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (str_contains($currentRoute, 'biddings') && $currentRoute != 'biddings.search') ? 'active' : '' ?>" href="<?= route('biddings.index') ?>">
                            <i class="fas fa-file-contract me-1"></i> Licitações
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentRoute == 'biddings.search' ? 'active' : '' ?>" href="<?= route('biddings.search') ?>">
                            <i class="fas fa-search me-1"></i> Buscar Licitações
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentRoute == 'analytics.index' ? 'active' : '' ?>" href="<?= route('analytics.index') ?>">
                            <i class="fas fa-chart-bar me-1"></i> Análise Financeira
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <?php if (Auth::check()): ?>
                        <a href="<?= route('notifications.index') ?>" class="btn btn-dark position-relative me-2">
                            <i class="fas fa-bell"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= $unreadCount ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-dark dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i>
                                <?= Auth::user()->name ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#">
                                        <i class="fas fa-user-cog me-1"></i> Perfil
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="<?= route('logout') ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="dropdown-item">
                                            <i class="fas fa-sign-out-alt me-1"></i> Sair
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="<?= route('login') ?>" class="btn btn-outline-light">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>
