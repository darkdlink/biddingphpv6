<?php
$title = 'Detalhes da Licitação - Sistema de Licitações';

$content = ob_start();
?>
<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= route('dashboard') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= route('biddings.index') ?>">Licitações</a></li>
                <li class="breadcrumb-item active">Detalhes da Licitação</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informações da Licitação</h5>
                <div>
                    <a href="<?= route('biddings.edit', $bidding->id) ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-1"></i> Editar
                    </a>
                    <button type="button" class="btn btn-danger"
                            onclick="confirmDelete(<?= $bidding->id ?>)">
                        <i class="fas fa-trash me-1"></i> Excluir
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Número da Licitação:</h6>
                        <p><?= $bidding->bidding_number ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Título:</h6>
                        <p><?= $bidding->title ?></p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Empresa:</h6>
                        <p><?= $bidding->company->name ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Modalidade:</h6>
                        <p><?= str_replace('_', ' ', ucfirst($bidding->modality)) ?></p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Data de Abertura:</h6>
                        <p><?= $bidding->opening_date->format('d/m/Y H:i') ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Data de Encerramento:</h6>
                        <p><?= $bidding->closing_date ? $bidding->closing_date->format('d/m/Y H:i') : 'Não definida' ?></p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Data de Publicação:</h6>
                        <p><?= $bidding->publication_date ? $bidding->publication_date->format('d/m/Y') : 'Não definida' ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Valor Estimado:</h6>
                        <p><?= $bidding->estimated_value
                            ? 'R$ ' . number_format($bidding->estimated_value, 2, ',', '.')
                            : 'Não informado'
                        ?></p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Status:</h6>
                        <p>
                            <?php if ($bidding->status == 'active'): ?>
                                <span class="badge bg-primary">Ativa</span>
                            <?php elseif ($bidding->status == 'pending'): ?>
                                <span class="badge bg-warning text-dark">Pendente</span>
                            <?php elseif ($bidding->status == 'finished'): ?>
                                <span class="badge bg-success">Finalizada</span>
                            <?php elseif ($bidding->status == 'canceled'): ?>
                                <span class="badge bg-danger">Cancelada</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= $bidding->status ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>URL da Fonte:</h6>
                        <p>
                            <?php if ($bidding->url_source): ?>
                                <a href="<?= $bidding->url_source ?>" target="_blank" class="text-break">
                                    <?= $bidding->url_source ?>
                                </a>
                            <?php else: ?>
                                Não informada
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="mb-3">
                    <h6>Descrição:</h6>
                    <p><?= $bidding->description ?: 'Sem descrição' ?></p>
                </div>

                <div class="d-flex justify-content-end">
                    <a href="<?= route('biddings.scrape', $bidding->id) ?>" class="btn btn-info">
                        <i class="fas fa-sync me-1"></i> Atualizar via Scraping
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Análise de Propostas</h5>
            </div>
            <div class="card-body">
                <?php if (count($bidding->proposals) > 0): ?>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <canvas id="proposalStatusChart"></canvas>
                        </div>
                        <div class="col-md-6">
                            <canvas id="proposalValueChart"></canvas>
                        </div>
                    </div>

                    <?php
                    $totalValue = 0;
                    $minValue = PHP_FLOAT_MAX;
                    $maxValue = 0;

                    foreach ($bidding->proposals as $proposal) {
                        $totalValue += $proposal->value;
                        $minValue = min($minValue, $proposal->value);
                        $maxValue = max($maxValue, $proposal->value);
                    }

                    $avgValue = count($bidding->proposals) > 0 ? $totalValue / count($bidding->proposals) : 0;
                    ?>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Valor Médio</h6>
                                    <h4>R$ <?= number_format($avgValue, 2, ',', '.') ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Menor Valor</h6>
                                    <h4>R$ <?= number_format($minValue, 2, ',', '.') ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Maior Valor</h6>
                                    <h4>R$ <?= number_format($maxValue, 2, ',', '.') ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                        <h4>Sem dados para análise</h4>
                        <p class="text-muted">Ainda não existem propostas para esta licitação.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Documentos</h5>
            </div>
            <div class="card-body">
                <?php if (count($bidding->documents) > 0): ?>
                    <ul class="list-group">
                        <?php foreach ($bidding->documents as $document): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-file-alt me-2"></i>
                                    <?= $document->name ?>
                                </div>
                                <div>
                                    <a href="<?= route('documents.show', $document->id) ?>" class="btn btn-sm btn-primary" target="_blank" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?= route('documents.download', $document->id) ?>" class="btn btn-sm btn-success" title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteDocument(<?= $document->id ?>)" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-center">Nenhum documento cadastrado</p>
                <?php endif; ?>

                <hr>

                <form method="POST" action="<?= route('documents.upload') ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="documentable_id" value="<?= $bidding->id ?>">
                    <input type="hidden" name="documentable_type" value="App\Models\Bidding">

                    <div class="mb-3">
                        <label for="name" class="form-label">Nome do Documento</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="file" class="form-label">Arquivo</label>
                        <input type="file" class="form-control" id="file" name="file" required>
                    </div>

                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-upload me-1"></i> Enviar Documento
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Propostas</h5>
                <a href="<?= route('biddings.proposals.create', $bidding->id) ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus me-1"></i> Nova Proposta
                </a>
            </div>
            <div class="card-body">
                <?php if (count($bidding->proposals) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bidding->proposals as $proposal): ?>
                                    <tr>
                                        <td>R$ <?= number_format($proposal->value, 2, ',', '.') ?></td>
                                        <td>
                                            <?php if ($proposal->status == 'draft'): ?>
                                                <span class="badge bg-secondary">Rascunho</span>
                                            <?php elseif ($proposal->status == 'submitted'): ?>
                                                <span class="badge bg-primary">Enviada</span>
                                            <?php elseif ($proposal->status == 'won'): ?>
                                                <span class="badge bg-success">Ganhadora</span>
                                            <?php elseif ($proposal->status == 'lost'): ?>
                                                <span class="badge bg-danger">Perdida</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="<?= route('biddings.proposals.show', [$bidding->id, $proposal->id]) ?>"
                                                   class="btn btn-sm btn-info" title="Visualizar">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?= route('biddings.proposals.edit', [$bidding->id, $proposal->id]) ?>"
                                                   class="btn btn-sm btn-warning" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center">Nenhuma proposta cadastrada</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmação de exclusão de licitação -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir esta licitação?</p>
                <p class="text-danger"><small>Esta ação não pode ser desfeita.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="<?= route('biddings.destroy', $bidding->id) ?>">
                    <?= csrf_field() ?>
                    <?= method_field('DELETE') ?>
                    <button type="submit" class="btn btn-danger">Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmação de exclusão de documento -->
<div class="modal fade" id="deleteDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir este documento?</p>
                <p class="text-danger"><small>Esta ação não pode ser desfeita.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form id="deleteDocumentForm" method="POST" action="">
                    <?= csrf_field() ?>
                    <?= method_field('DELETE') ?>
                    <button type="submit" class="btn btn-danger">Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

$scripts = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    function confirmDelete(id) {
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }

    function confirmDeleteDocument(id) {
        const deleteDocumentModal = new bootstrap.Modal(document.getElementById('deleteDocumentModal'));
        document.getElementById('deleteDocumentForm').action = `/documents/${id}`;
        deleteDocumentModal.show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Gráficos de análise de propostas apenas se houver propostas
        if (document.getElementById('proposalStatusChart')) {
            // Contar propostas por status
            const statusCounts = {
                draft: <?= $bidding->proposals->where('status', 'draft')->count() ?>,
                submitted: <?= $bidding->proposals->where('status', 'submitted')->count() ?>,
                won: <?= $bidding->proposals->where('status', 'won')->count() ?>,
                lost: <?= $bidding->proposals->where('status', 'lost')->count() ?>
            };

            // Gráfico de status
            const statusCtx = document.getElementById('proposalStatusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'pie',
                data: {
                    labels: ['Rascunho', 'Enviada', 'Ganhadora', 'Perdida'],
                    datasets: [{
                        data: [statusCounts.draft, statusCounts.submitted, statusCounts.won, statusCounts.lost],
                        backgroundColor: ['#6c757d', '#0d6efd', '#198754', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        title: {
                            display: true,
                            text: 'Status das Propostas'
                        }
                    }
                }
            });

            // Preparar dados para gráfico de valores
            const proposalValues = [
                <?php foreach ($bidding->proposals as $proposal): ?>
                {
                    value: <?= $proposal->value ?>,
                    status: '<?= $proposal->status ?>'
                },
                <?php endforeach; ?>
            ];

            // Gráfico de valores
            const valueCtx = document.getElementById('proposalValueChart').getContext('2d');
            new Chart(valueCtx, {
                type: 'bar',
                data: {
                    labels: proposalValues.map((_, i) => `Proposta ${i+1}`),
                    datasets: [{
                        label: 'Valor da Proposta (R$)',
                        data: proposalValues.map(p => p.value),
                        backgroundColor: proposalValues.map(p => {
                            switch(p.status) {
                                case 'draft': return '#6c757d';
                                case 'submitted': return '#0d6efd';
                                case 'won': return '#198754';
                                case 'lost': return '#dc3545';
                                default: return '#6c757d';
                            }
                        })
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Valores das Propostas'
                        }
                    }
                }
            });
        }
    });
</script>
HTML;

// Renderizar o layout base
include(resource_path('views/layout/app.php'));
?>
