<?php
$title = 'Licitações - Sistema de Licitações';

$content = ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-file-contract me-2"></i>Licitações</h1>
    <a href="<?= route('biddings.create') ?>" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Nova Licitação
    </a>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="<?= route('biddings.index') ?>" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Buscar</label>
                <input type="text" class="form-control" id="search" name="search"
                    value="<?= request('search') ?>" placeholder="Número, título ou descrição">
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos</option>
                    <option value="pending" <?= request('status') == 'pending' ? 'selected' : '' ?>>Pendente</option>
                    <option value="active" <?= request('status') == 'active' ? 'selected' : '' ?>>Ativa</option>
                    <option value="finished" <?= request('status') == 'finished' ? 'selected' : '' ?>>Finalizada</option>
                    <option value="canceled" <?= request('status') == 'canceled' ? 'selected' : '' ?>>Cancelada</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-1"></i> Filtrar
                </button>
                <a href="<?= route('biddings.index') ?>" class="btn btn-secondary">
                    <i class="fas fa-sync me-1"></i> Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Título</th>
                        <th>Empresa</th>
                        <th>Modalidade</th>
                        <th>Abertura</th>
                        <th>Valor Estimado</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($biddings) > 0): ?>
                        <?php foreach ($biddings as $bidding): ?>
                            <tr>
                                <td><?= $bidding->bidding_number ?></td>
                                <td><?= $bidding->title ?></td>
                                <td><?= $bidding->company->name ?></td>
                                <td><?= str_replace('_', ' ', ucfirst($bidding->modality)) ?></td>
                                <td><?= $bidding->opening_date->format('d/m/Y H:i') ?></td>
                                <td>
                                    <?= $bidding->estimated_value
                                        ? 'R$ ' . number_format($bidding->estimated_value, 2, ',', '.')
                                        : 'Não informado'
                                    ?>
                                </td>
                                <td>
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
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?= route('biddings.show', $bidding->id) ?>" class="btn btn-sm btn-info" title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= route('biddings.edit', $bidding->id) ?>" class="btn btn-sm btn-warning" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger"
                                                onclick="confirmDelete(<?= $bidding->id ?>)" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="fas fa-search fa-2x text-muted mb-3 d-block"></i>
                                <p>Nenhuma licitação encontrada</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-4">
            <?= $biddings->links() ?>
        </div>
    </div>
</div>

<!-- Modal de confirmação de exclusão -->
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
                <form id="deleteForm" method="POST" action="">
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
<script>
    function confirmDelete(id) {
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        document.getElementById('deleteForm').action = `/biddings/${id}`;
        deleteModal.show();
    }
</script>
HTML;

// Renderizar o layout base
include(resource_path('views/layout/app.php'));
?>
