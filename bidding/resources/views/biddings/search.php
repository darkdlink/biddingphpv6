<?php
$title = 'Busca de Licitações - Sistema de Licitações';

$content = ob_start();
?>
<div class="row mb-4">
    <div class="col-12">
        <h1><i class="fas fa-search me-2"></i>Busca de Licitações</h1>
        <p class="text-muted">Encontre e importe licitações de fontes externas</p>
    </div>
</div>

<div class="search-container card">
    <div class="card-body">
        <ul class="nav nav-tabs mb-3">
            <?php foreach ($sources as $sourceKey => $sourceLabel): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $source == $sourceKey ? 'active' : '' ?>"
                        href="#" onclick="changeSource('<?= $sourceKey ?>')">
                        <?= $sourceLabel ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <form method="GET" action="<?= route('biddings.search') ?>" id="searchForm">
            <input type="hidden" name="source" id="sourceInput" value="<?= $source ?>">

            <div class="source-filters" id="comprasnet-filters" style="display: <?= $source == 'comprasnet' ? 'block' : 'none' ?>;">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="bidding_number" class="form-label">Número da Licitação</label>
                        <input type="text" class="form-control" id="bidding_number" name="bidding_number"
                                placeholder="Ex: 52021" value="<?= request('bidding_number') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Data Inicial</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= request('start_date') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">Data Final</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= request('end_date') ?>">
                    </div>
                </div>
            </div>

            <div class="source-filters" id="licitacoes-e-filters" style="display: <?= $source == 'licitacoes-e' ? 'block' : 'none' ?>;">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="codigo" class="form-label">Código da Licitação</label>
                        <input type="text" class="form-control" id="codigo" name="codigo"
                                placeholder="Ex: 123456" value="<?= request('codigo') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Todos</option>
                            <option value="abertas" <?= request('status') == 'abertas' ? 'selected' : '' ?>>Em andamento</option>
                            <option value="encerradas" <?= request('status') == 'encerradas' ? 'selected' : '' ?>>Encerradas</option>
                            <option value="adiadas" <?= request('status') == 'adiadas' ? 'selected' : '' ?>>Adiadas</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="source-filters" id="compras-gov-filters" style="display: <?= $source == 'compras-gov' ? 'block' : 'none' ?>;">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="texto" class="form-label">Texto para Busca</label>
                        <input type="text" class="form-control" id="texto" name="texto"
                                placeholder="Ex: equipamentos médicos" value="<?= request('texto') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="modalidade" class="form-label">Modalidade</label>
                        <select class="form-select" id="modalidade" name="modalidade">
                            <option value="">Todas</option>
                            <option value="pregao_eletronico" <?= request('modalidade') == 'pregao_eletronico' ? 'selected' : '' ?>>
                                Pregão Eletrônico
                            </option>
                            <option value="pregao_presencial" <?= request('modalidade') == 'pregao_presencial' ? 'selected' : '' ?>>
                                Pregão Presencial
                            </option>
                            <option value="concorrencia" <?= request('modalidade') == 'concorrencia' ? 'selected' : '' ?>>
                                Concorrência
                            </option>
                            <option value="tomada_precos" <?= request('modalidade') == 'tomada_precos' ? 'selected' : '' ?>>
                                Tomada de Preços
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i> Buscar Licitações
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (isset($searchResults)): ?>
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-list me-2"></i>Resultados da Busca</h5>
            <span class="badge bg-primary"><?= count($searchResults) ?> licitações encontradas</span>
        </div>
        <div class="card-body">
            <?php if (count($searchResults) > 0): ?>
                <div class="row">
                    <?php foreach ($searchResults as $result): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <span class="badge
                                        <?= $result['status'] == 'active' ? 'bg-primary' :
                                          ($result['status'] == 'pending' ? 'bg-warning' :
                                          ($result['status'] == 'finished' ? 'bg-success' : 'bg-danger')) ?>">
                                        <?= ucfirst($result['status']) ?>
                                    </span>
                                    <h6 class="mt-2 mb-0"><?= $result['bidding_number'] ?></h6>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?= $result['title'] ?></h5>
                                    <p class="card-text text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?= $result['opening_date'] ? date('d/m/Y H:i', strtotime($result['opening_date'])) : 'Data não informada' ?>
                                    </p>
                                    <p class="card-text">
                                        <i class="fas fa-tag me-1"></i>
                                        <?= str_replace('_', ' ', ucfirst($result['modality'])) ?>
                                    </p>
                                </div>
                                <div class="card-footer d-flex justify-content-between">
                                    <form method="POST" action="<?= route('biddings.import') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="bidding_number" value="<?= $result['bidding_number'] ?>">
                                        <input type="hidden" name="title" value="<?= $result['title'] ?>">
                                        <input type="hidden" name="opening_date" value="<?= $result['opening_date'] ?>">
                                        <input type="hidden" name="modality" value="<?= $result['modality'] ?>">
                                        <input type="hidden" name="status" value="<?= $result['status'] ?>">
                                        <input type="hidden" name="url_source" value="<?= $result['url_source'] ?>">

                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-download me-1"></i> Importar
                                        </button>
                                    </form>

                                    <?php if ($result['url_source']): ?>
                                        <a href="<?= $result['url_source'] ?>" target="_blank" class="btn btn-info">
                                            <i class="fas fa-external-link-alt me-1"></i> Ver Original
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h4>Nenhuma licitação encontrada</h4>
                    <p class="text-muted">Tente outras palavras-chave ou filtros para encontrar resultados.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();

$styles = <<<HTML
<style>
    .search-container {
        background-color: #f8f9fa;
        margin-bottom: 20px;
    }
    .card-header .badge {
        float: right;
    }
</style>
HTML;

$scripts = <<<HTML
<script>
    function changeSource(source) {
        document.getElementById('sourceInput').value = source;

        // Esconder todos os formulários de filtro
        const filterDivs = document.querySelectorAll('.source-filters');
        filterDivs.forEach(div => {
            div.style.display = 'none';
        });

        // Mostrar apenas o formulário relevante
        document.getElementById(source + '-filters').style.display = 'block';

        // Submeter o formulário se mudar a fonte
        // document.getElementById('searchForm').submit();
    }

    // Atualizar datas com valores padrão
    document.addEventListener('DOMContentLoaded', function() {
        if (!document.getElementById('start_date').value) {
            const today = new Date();
            const oneMonthAgo = new Date();
            oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);

            const formatDate = date => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `\${year}-\${month}-\${day}`;
            };

            if (document.getElementById('start_date')) {
                document.getElementById('start_date').value = formatDate(oneMonthAgo);
            }

            if (document.getElementById('end_date')) {
                document.getElementById('end_date').value = formatDate(today);
            }
        }
    });
</script>
HTML;

// Renderizar o layout base
include(resource_path('views/layout/app.php'));
?>
