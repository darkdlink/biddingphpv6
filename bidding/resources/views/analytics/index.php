<?php
$title = 'Análises Financeiras - Sistema de Licitações';

$content = ob_start();
?>
<div class="row mb-4">
    <div class="col-12">
        <h1><i class="fas fa-chart-line me-2"></i>Análise Financeira</h1>
        <p class="text-muted">Visualize métricas e estatísticas sobre licitações e propostas</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Total de Licitações</h6>
                        <h2 class="mb-0"><?= $totalBiddings ?></h2>
                    </div>
                    <i class="fas fa-file-contract fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Propostas Ganhas</h6>
                        <h2 class="mb-0"><?= $wonProposals ?></h2>
                    </div>
                    <i class="fas fa-trophy fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Valor Total Ganho</h6>
                        <h2 class="mb-0">R$ <?= number_format($totalWonValue, 2, ',', '.') ?></h2>
                    </div>
                    <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase">Margem Média de Lucro</h6>
                        <h2 class="mb-0"><?= number_format($avgProfitMargin, 2, ',', '.') ?>%</h2>
                    </div>
                    <i class="fas fa-chart-line fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="fas fa-calendar-alt me-2"></i>Licitações por Mês</h5>
            </div>
            <div class="card-body">
                <canvas id="biddingsByMonthChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="fas fa-coins me-2"></i>Valor Ganho por Mês</h5>
            </div>
            <div class="card-body">
                <canvas id="wonValueByMonthChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="fas fa-chart-pie me-2"></i>Distribuição de Status</h5>
            </div>
            <div class="card-body">
                <canvas id="statusDistributionChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="fas fa-list-alt me-2"></i>Distribuição de Propostas</h5>
            </div>
            <div class="card-body">
                <canvas id="proposalsDistributionChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-award me-2"></i>Top 5 Maiores Licitações Ganhas</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Licitação</th>
                        <th>Número</th>
                        <th>Empresa</th>
                        <th>Valor Proposta</th>
                        <th>Margem de Lucro</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topWonBiddings as $proposal): ?>
                        <tr>
                            <td><?= $proposal->bidding->title ?></td>
                            <td><?= $proposal->bidding->bidding_number ?></td>
                            <td><?= $proposal->bidding->company->name ?></td>
                            <td>R$ <?= number_format($proposal->value, 2, ',', '.') ?></td>
                            <td><?= $proposal->profit_margin ? number_format($proposal->profit_margin, 2, ',', '.') . '%' : 'N/D' ?></td>
                            <td><?= $proposal->created_at->format('d/m/Y') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

$scripts = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Preparar dados para gráficos
        const months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        // Gráfico de licitações por mês
        const biddingsByMonthCtx = document.getElementById('biddingsByMonthChart').getContext('2d');
        const biddingsByMonthData = {
            labels: [
                <?php
                $labels = [];
                foreach ($biddingsByMonth as $item) {
                    $labels[] = "'{$months[$item->month - 1]}/{$item->year}'";
                }
                echo implode(', ', $labels);
                ?>
            ],
            datasets: [{
                label: 'Quantidade de Licitações',
                data: [
                    <?php
                    $data = [];
                    foreach ($biddingsByMonth as $item) {
                        $data[] = $item->count;
                    }
                    echo implode(', ', $data);
                    ?>
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        };
        new Chart(biddingsByMonthCtx, {
            type: 'bar',
            data: biddingsByMonthData,
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Gráfico de valor ganho por mês
        const wonValueByMonthCtx = document.getElementById('wonValueByMonthChart').getContext('2d');
        const wonValueByMonthData = {
            labels: [
                <?php
                $labels = [];
                foreach ($wonValueByMonth as $item) {
                    $labels[] = "'{$months[$item->month - 1]}/{$item->year}'";
                }
                echo implode(', ', $labels);
                ?>
            ],
            datasets: [{
                label: 'Valor Total (R$)',
                data: [
                    <?php
                    $data = [];
                    foreach ($wonValueByMonth as $item) {
                        $data[] = $item->total;
                    }
                    echo implode(', ', $data);
                    ?>
                ],
                fill: false,
                borderColor: 'rgba(75, 192, 192, 1)',
                tension: 0.1
            }]
        };
        new Chart(wonValueByMonthCtx, {
            type: 'line',
            data: wonValueByMonthData,
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Gráfico de distribuição de status
        const statusDistributionCtx = document.getElementById('statusDistributionChart').getContext('2d');
        const statusDistributionData = {
            labels: ['Ativas', 'Finalizadas', 'Outras'],
            datasets: [{
                data: [<?= $activeBiddings ?>, <?= $finishedBiddings ?>, <?= $totalBiddings - $activeBiddings - $finishedBiddings ?>],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(201, 203, 207, 0.6)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(201, 203, 207, 1)'
                ],
                borderWidth: 1
            }]
        };
        new Chart(statusDistributionCtx, {
            type: 'pie',
            data: statusDistributionData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Gráfico de distribuição de propostas
        const proposalsDistributionCtx = document.getElementById('proposalsDistributionChart').getContext('2d');
        const proposalsDistributionData = {
            labels: ['Ganhas', 'Perdidas', 'Outras'],
            datasets: [{
                data: [<?= $wonProposals ?>, <?= $lostProposals ?>, <?= $totalProposals - $wonProposals - $lostProposals ?>],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.6)',
                    'rgba(220, 53, 69, 0.6)',
                    'rgba(108, 117, 125, 0.6)'
                ],
                borderColor: [
                    'rgba(40, 167, 69, 1)',
                    'rgba(220, 53, 69, 1)',
                    'rgba(108, 117, 125, 1)'
                ],
                borderWidth: 1
            }]
        };
        new Chart(proposalsDistributionCtx, {
            type: 'doughnut',
            data: proposalsDistributionData,
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
