<?php
$title = 'Empresas - Sistema de Licitações';

$content = ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-building me-2"></i>Empresas</h1>
    <a href="<?= route('companies.create') ?>" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Nova Empresa
    </a>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Buscar</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="<?= route('companies.index') ?>" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search"
                       value="<?= request('search') ?>" placeholder="Nome, CNPJ ou email da empresa">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-1"></i> Buscar
                </button>
                <a href="<?= route('companies.index') ?>" class="btn btn-secondary">
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
                        <th>Nome</th>
                        <th>CNPJ</th>
                        <th>Email</th>
                        <th>Telefone</th>
                        <th>Licitações</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($companies) > 0): ?>
                        <?php foreach ($companies as $company): ?>
                            <tr>
                                <td><?= $company->name ?></td>
                                <td><?= $company->cnpj ?></td>
                                <td><?= $company->email ?: '-' ?></td>
                                <td><?= $company->phone ?: '-' ?></td>
                                <td><?= $company->biddings->count() ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?= route('companies.show', $company->id) ?>" class="btn btn-sm btn-info" title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= route('companies.edit', $company->id) ?>" class="btn btn-sm btn-warning" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger"
                                                onclick="confirmDelete(<?= $company->id ?>)" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <i class="fas fa-building fa-2x text-muted mb-3 d-block"></i>
                                <p>Nenhuma empresa encontrada</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-4">
            <?= $companies->links() ?>
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
                <p>Tem certeza que deseja excluir esta empresa?</p>
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
        document.getElementById('deleteForm').action = `/companies/${id}`;
        deleteModal.show();
    }
</script>
HTML;

// Renderizar o layout base
include(resource_path('views/layout/app.php'));
?>
