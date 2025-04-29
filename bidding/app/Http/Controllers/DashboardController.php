<?php
// app/Http/Controllers/DashboardController.php
namespace App\Http\Controllers;

use App\Models\Bidding;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;

class DashboardController extends Controller
{
    public function index()
    {
        // Obter dados das licitações
        $activeBiddings = Bidding::where('status', 'active')->count();
        $pendingBiddings = Bidding::where('status', 'pending')->count();
        $finishedBiddings = Bidding::where('status', 'finished')->count();

        // Obter dados das propostas
        $submittedProposals = Proposal::where('status', 'submitted')->count();
        $wonProposals = Proposal::where('status', 'won')->count();
        $lostProposals = Proposal::where('status', 'lost')->count();

        // Obter licitações recentes
        $recentBiddings = Bidding::orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Inicializar a variável $errors
        $errors = session()->get('errors', new MessageBag);

        // Renderizar o dashboard
        $content = $this->renderDashboard(
            $activeBiddings,
            $pendingBiddings,
            $finishedBiddings,
            $submittedProposals,
            $wonProposals,
            $lostProposals,
            $recentBiddings,
            $errors
        );

        return response($content);
    }

    private function renderDashboard(
        $activeCount,
        $pendingCount,
        $finishedCount,
        $submittedProposals,
        $wonProposals,
        $lostProposals,
        $recentBiddings,
        $errors = null
    ) {
        // Se $errors não for fornecido, crie um MessageBag vazio
        if (!$errors) {
            $errors = new MessageBag;
        }

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="pt-br">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Dashboard - Sistema de Licitações</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
            <style>
                .stat-card {
                    border-radius: 10px;
                    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                    transition: transform 0.3s;
                }
                .stat-card:hover {
                    transform: translateY(-5px);
                }
                .bidding-progress {
                    height: 8px;
                    border-radius: 4px;
                }
            </style>
        </head>
        <body>
            <?php include(resource_path('views/layout/header.php')); ?>

            <div class="container-fluid py-4">
                <div class="row mb-4">
                    <div class="col-12">
                        <h1>Dashboard</h1>
                        <p class="text-muted">Visão geral do sistema de licitações</p>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="stat-card card bg-primary text-white p-3">
                            <div class="card-body">
                                <h5>Licitações Ativas</h5>
                                <h2><?= $activeCount ?></h2>
                                <p>Licitações em andamento</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card card bg-warning text-white p-3">
                            <div class="card-body">
                                <h5>Licitações Pendentes</h5>
                                <h2><?= $pendingCount ?></h2>
                                <p>Aguardando processamento</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-card card bg-success text-white p-3">
                            <div class="card-body">
                                <h5>Licitações Finalizadas</h5>
                                <h2><?= $finishedCount ?></h2>
                                <p>Processos concluídos</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card stat-card">
                            <div class="card-header">
                                Desempenho de Propostas
                            </div>
                            <div class="card-body">
                                <canvas id="proposalsChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card stat-card">
                            <div class="card-header">
                                Licitações Recentes
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
                                                <td>
                                                    <?= $bidding->opening_date ? date('d/m/Y', strtotime($bidding->opening_date)) : 'Não informada' ?>
                                                </td>
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
            </div>

            <?php include(resource_path('views/layout/footer.php')); ?>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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
                                    <?= $submittedProposals ?>,
                                    <?= $wonProposals ?>,
                                    <?= $lostProposals ?>
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
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
