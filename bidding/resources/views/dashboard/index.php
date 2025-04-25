<?php
$title = 'Dashboard - Sistema de Licitações';

$content = ob_start();
?>
<div class="row mb-4">
    <div class="col-12">
        <h1>Dashboard</h1>
        <p class="text-muted">Visão geral do sistema de licitações</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5>Licitações Ativas</h5>
                        <h2><?= $activeCount ?></h2>
                        <p>Licitações em andamento</p>
                    </div>
                    <i class="fas fa-file-contract fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5>Licitações Pendentes</h5>
                        <h2><?= $pendingCount ?></h2>
                        <p>Aguardando processamento</p>
                    </div>
                    <i class="fas fa-clock fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5>Licitações Finalizadas</h5>
                        <h2><?= $finishedCount ?></h2>
                        <p>Processos concluídos</p>
                    </div>
                    <i class="fas fa-check-circle fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Desempenho de Propostas</h5>
            </div>
            <div class="card-body">
                <canvas id="proposalsChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Licitações Recentes</h5>
                <a href="<?= route('biddings.index') ?>" class="btn btn-sm btn-primary">Ver Todas</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Título</th>
                                <th>Abertura</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBiddings as $bidding): ?>
                            <tr>
                                <td><?= $bidding->bidding_number ?></td>
                                <td><?= $bidding->title ?></td>
                                <td><?= $bidding->opening_date->format('d/m/Y') ?></td>
                                <td>
                                    <?php if ($bidding->status == 'active'): ?>
                                        <span class="badge bg-primary">Ativa</span>
                                    <?php elseif ($bidding->status == 'pending'): ?>
                                        <span class="badge bg-warning">Pendente</span>
                                    <?php elseif ($bidding->status == 'finished'): ?>
                                        <span class="badge bg-success">Finalizada</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= $bidding->status ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

$scripts = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gráfico de propostas
        const ctx = document.getElementById('proposalsChart').getContext('2d');
        const proposalsChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Enviadas', 'Ganhas', 'Perdidas'],
                datasets: [{
                    data: [
                        {$submittedProposals},
                        {$wonProposals},
                        {$lostProposals}
                    ],
                    backgroundColor: [
                        '#ffc107',
                        '#28a745',
                        '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    });
</script>
HTML;

// Renderizar o layout base
include(resource_path('views/layout/app.php'));
?>
