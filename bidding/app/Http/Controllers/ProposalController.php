<?php
// app/Http/Controllers/ProposalController.php
namespace App\Http\Controllers;

use App\Models\Bidding;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProposalController extends Controller
{
    public function index(Request $request, $biddingId)
    {
        $bidding = Bidding::findOrFail($biddingId);
        $proposals = $bidding->proposals()->orderBy('created_at', 'desc')->get();

        $content = $this->renderIndex($bidding, $proposals);
        return response($content);
    }

    public function create(Request $request, $biddingId)
    {
        $bidding = Bidding::findOrFail($biddingId);

        $content = $this->renderCreate($bidding);
        return response($content);
    }

    public function store(Request $request, $biddingId)
    {
        $bidding = Bidding::findOrFail($biddingId);

        $validator = Validator::make($request->all(), [
            'value' => 'required|numeric|min:0',
            'profit_margin' => 'nullable|numeric|min:0|max:100',
            'total_cost' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'required|in:draft,submitted,won,lost',
        ]);

        if ($validator->fails()) {
            return redirect()->route('biddings.proposals.create', $biddingId)
                ->withErrors($validator)
                ->withInput();
        }

        $proposal = new Proposal($request->all());
        $bidding->proposals()->save($proposal);

        return redirect()->route('biddings.show', $biddingId)
            ->with('success', 'Proposta criada com sucesso!');
    }

    public function show(Request $request, $biddingId, $id)
    {
        $bidding = Bidding::findOrFail($biddingId);
        $proposal = $bidding->proposals()->findOrFail($id);

        $content = $this->renderShow($bidding, $proposal);
        return response($content);
    }

    public function edit(Request $request, $biddingId, $id)
    {
        $bidding = Bidding::findOrFail($biddingId);
        $proposal = $bidding->proposals()->findOrFail($id);

        $content = $this->renderEdit($bidding, $proposal);
        return response($content);
    }

    public function update(Request $request, $biddingId, $id)
    {
        $bidding = Bidding::findOrFail($biddingId);
        $proposal = $bidding->proposals()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'value' => 'required|numeric|min:0',
            'profit_margin' => 'nullable|numeric|min:0|max:100',
            'total_cost' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'required|in:draft,submitted,won,lost',
            'submission_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return redirect()->route('biddings.proposals.edit', [$biddingId, $id])
                ->withErrors($validator)
                ->withInput();
        }

        $proposal->update($request->all());

        return redirect()->route('biddings.proposals.show', [$biddingId, $id])
            ->with('success', 'Proposta atualizada com sucesso!');
    }

    public function destroy(Request $request, $biddingId, $id)
    {
        $bidding = Bidding::findOrFail($biddingId);
        $proposal = $bidding->proposals()->findOrFail($id);

        $proposal->delete();

        return redirect()->route('biddings.show', $biddingId)
            ->with('success', 'Proposta removida com sucesso!');
    }

    public function generatePdf(Request $request, $id)
    {
        $proposal = Proposal::with('bidding.company')->findOrFail($id);

        // Aqui implementaríamos a geração do PDF
        // usando bibliotecas como DOMPDF ou similar

        return redirect()->back()
            ->with('info', 'Função de geração de PDF em desenvolvimento.');
    }

    private function renderCreate($bidding)
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="pt-br">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Nova Proposta - Sistema de Licitações</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        </head>
        <body>
            <?php include(resource_path('views/layout/header.php')); ?>

            <div class="container-fluid py-4">
                <div class="row mb-4">
                    <div class="col-12">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="<?= route('dashboard') ?>">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="<?= route('biddings.index') ?>">Licitações</a></li>
                                <li class="breadcrumb-item"><a href="<?= route('biddings.show', $bidding->id) ?>"><?= $bidding->bidding_number ?></a></li>
                                <li class="breadcrumb-item active">Nova Proposta</li>
                            </ol>
                        </nav>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Nova Proposta para Licitação <?= $bidding->bidding_number ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?= route('biddings.proposals.store', $bidding->id) ?>">
                            <?= csrf_field() ?>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="value" class="form-label">Valor da Proposta (R$) *</label>
                                    <input type="number" step="0.01" min="0" class="form-control <?= $errors->has('value') ? 'is-invalid' : '' ?>"
                                           id="value" name="value" value="<?= old('value') ?>" required>
                                    <?php if ($errors->has('value')): ?>
                                        <div class="invalid-feedback">
                                            <?= $errors->first('value') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <label for="total_cost" class="form-label">Custo Total (R$)</label>
                                    <input type="number" step="0.01" min="0" class="form-control <?= $errors->has('total_cost') ? 'is-invalid' : '' ?>"
                                           id="total_cost" name="total_cost" value="<?= old('total_cost') ?>">
                                    <?php if ($errors->has('total_cost')): ?>
                                        <div class="invalid-feedback">
                                            <?= $errors->first('total_cost') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <label for="profit_margin" class="form-label">Margem de Lucro (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control <?= $errors->has('profit_margin') ? 'is-invalid' : '' ?>"
                                           id="profit_margin" name="profit_margin" value="<?= old('profit_margin') ?>">
                                    <?php if ($errors->has('profit_margin')): ?>
                                        <div class="invalid-feedback">
                                            <?= $errors->first('profit_margin') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="submission_date" class="form-label">Data de Submissão</label>
                                    <input type="datetime-local" class="form-control <?= $errors->has('submission_date') ? 'is-invalid' : '' ?>"
                                           id="submission_date" name="submission_date" value="<?= old('submission_date') ?>">
                                    <?php if ($errors->has('submission_date')): ?>
                                        <div class="invalid-feedback">
                                            <?= $errors->first('submission_date') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="status" class="form-label">Status *</label>
                                    <select class="form-select <?= $errors->has('status') ? 'is-invalid' : '' ?>"
                                            id="status" name="status" required>
                                        <option value="draft" <?= old('status', 'draft') == 'draft' ? 'selected' : '' ?>>
                                            Rascunho
                                        </option>
                                        <option value="submitted" <?= old('status') == 'submitted' ? 'selected' : '' ?>>
                                            Enviada
                                        </option>
                                        <option value="won" <?= old('status') == 'won' ? 'selected' : '' ?>>
                                            Ganhadora
                                        </option>
                                        <option value="lost" <?= old('status') == 'lost' ? 'selected' : '' ?>>
                                            Perdida
                                        </option>
                                    </select>
                                    <?php if ($errors->has('status')): ?>
                                        <div class="invalid-feedback">
                                            <?= $errors->first('status') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Descrição</label>
                                <textarea class="form-control <?= $errors->has('description') ? 'is-invalid' : '' ?>"
                                          id="description" name="description" rows="4"><?= old('description') ?></textarea>
                                <?php if ($errors->has('description')): ?>
                                    <div class="invalid-feedback">
                                        <?= $errors->first('description') ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-end">
                                <a href="<?= route('biddings.show', $bidding->id) ?>" class="btn btn-secondary me-2">Cancelar</a>
                                <button type="submit" class="btn btn-primary">Salvar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php include(resource_path('views/layout/footer.php')); ?>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
            <script>
                // Calculadora automática de margem de lucro
                document.addEventListener('DOMContentLoaded', function() {
                    const valueInput = document.getElementById('value');
                    const totalCostInput = document.getElementById('total_cost');
                    const profitMarginInput = document.getElementById('profit_margin');

                    function calculateProfitMargin() {
                        const value = parseFloat(valueInput.value) || 0;
                        const totalCost = parseFloat(totalCostInput.value) || 0;

                        if (value > 0 && totalCost > 0) {
                            const profit = value - totalCost;
                            const profitMargin = (profit / value) * 100;
                            profitMarginInput.value = profitMargin.toFixed(2);
                        }
                    }

                    function calculateValue() {
                        const totalCost = parseFloat(totalCostInput.value) || 0;
                        const profitMargin = parseFloat(profitMarginInput.value) || 0;

                        if (totalCost > 0 && profitMargin >= 0) {
                            const value = totalCost / (1 - (profitMargin / 100));
                            valueInput.value = value.toFixed(2);
                        }
                    }

                    valueInput.addEventListener('change', calculateProfitMargin);
                    totalCostInput.addEventListener('change', calculateProfitMargin);
                    profitMarginInput.addEventListener('change', calculateValue);
                });
            </script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    private function renderShow($bidding, $proposal)
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="pt-br">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Detalhes da Proposta - Sistema de Licitações</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        </head>
        <body>
            <?php include(resource_path('views/layout/header.php')); ?>

            <div class="container-fluid py-4">
                <div class="row mb-4">
                    <div class="col-12">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="<?= route('dashboard') ?>">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="<?= route('biddings.index') ?>">Licitações</a></li>
                                <li class="breadcrumb-item"><a href="<?= route('biddings.show', $bidding->id) ?>"><?= $bidding->bidding_number ?></a></li>
                                <li class="breadcrumb-item active">Detalhes da Proposta</li>
                            </ol>
                        </nav>
                    </div>
                </div>

                <?php if (session('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= session('success') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (session('info')): ?>
                    <div class="alert alert-info alert-dismissible fade show">
                        <?= session('info') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Informações da Proposta</h5>
                                <div>
                                    <a href="<?= route('biddings.proposals.edit', [$bidding->id, $proposal->id]) ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <a href="<?= route('proposals.generate-pdf', $proposal->id) ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-file-pdf"></i> Gerar PDF
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger"
                                            onclick="confirmDelete(<?= $proposal->id ?>)">
                                        <i class="fas fa-trash"></i> Excluir
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h6>Valor da Proposta:</h6>
                                        <p class="fw-bold fs-4">R$ <?= number_format($proposal->value, 2, ',', '.') ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Status:</h6>
                                        <p>
                                            <?php if ($proposal->status == 'draft'): ?>
                                                <span class="badge bg-secondary">Rascunho</span>
                                            <?php elseif ($proposal->status == 'submitted'): ?>
                                                <span class="badge bg-primary">Enviada</span>
                                            <?php elseif ($proposal->status == 'won'): ?>
                                                <span class="badge bg-success">Ganhadora</span>
                                            <?php elseif ($proposal->status == 'lost'): ?>
                                                <span class="badge bg-danger">Perdida</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <h6>Custo Total:</h6>
                                        <p><?= $proposal->total_cost
                                            ? 'R$ ' . number_format($proposal->total_cost, 2, ',', '.')
                                            : 'Não informado'
                                        ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <h6>Margem de Lucro:</h6>
                                        <p><?= $proposal->profit_margin !== null
                                            ? number_format($proposal->profit_margin, 2, ',', '.') . '%'
                                            : 'Não informada'
                                        ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <h6>Data de Submissão:</h6>
                                        <p><?= $proposal->submission_date
                                            ? $proposal->submission_date->format('d/m/Y H:i')
                                            : 'Não informada'
                                        ?></p>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <h6>Descrição:</h6>
                                    <p><?= $proposal->description ?: 'Sem descrição' ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Informações da Licitação</h5>
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
                                        <h6>Valor Estimado:</h6>
                                        <p><?= $bidding->estimated_value
                                            ? 'R$ ' . number_format($bidding->estimated_value, 2, ',', '.')
                                            : 'Não informado'
                                        ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Documentos da Proposta</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($proposal->documents) > 0): ?>
                                    <ul class="list-group">
                                        <?php foreach ($proposal->documents as $document): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="fas fa-file-alt me-2"></i>
                                                    <?= $document->name ?>
                                                </div>
                                                <div>
                                                    <a href="<?= asset('storage/' . $document->file_path) ?>"
                                                       class="btn btn-sm btn-primary" target="_blank">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger"
                                                            onclick="confirmDeleteDocument(<?= $document->id ?>)">
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
                                    <input type="hidden" name="documentable_id" value="<?= $proposal->id ?>">
                                    <input type="hidden" name="documentable_type" value="App\Models\Proposal">

                                    <div class="mb-3">
                                        <label for="name" class="form-label">Nome do Documento</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="file" class="form-label">Arquivo</label>
                                        <input type="file" class="form-control" id="file" name="file" required>
                                    </div>

                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-upload"></i> Enviar Documento
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Análise Financeira</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $lucro = $proposal->value - ($proposal->total_cost ?: 0);
                                $percentualSobreEstimado = $bidding->estimated_value
                                    ? ($proposal->value / $bidding->estimated_value * 100)
                                    : null;
                                ?>

                                <div class="mb-3">
                                    <h6>Lucro Bruto:</h6>
                                    <p class="fs-5 <?= $lucro > 0 ? 'text-success' : 'text-danger' ?>">
                                        R$ <?= number_format($lucro, 2, ',', '.') ?>
                                    </p>
                                </div>

                                <?php if ($percentualSobreEstimado !== null): ?>
                                    <div class="mb-3">
                                        <h6>Percentual sobre valor estimado:</h6>
                                        <p class="fs-5 <?= $percentualSobreEstimado <= 100 ? 'text-success' : 'text-danger' ?>">
                                            <?= number_format($percentualSobreEstimado, 2, ',', '.') ?>%
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($proposal->total_cost && $proposal->profit_margin): ?>
                                    <div class="mb-3">
                                        <h6>Análise Gráfica:</h6>
                                        <canvas id="propostaPieChart"></canvas>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
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
                            <p>Tem certeza que deseja excluir esta proposta?</p>
                            <p class="text-danger"><small>Esta ação não pode ser desfeita.</small></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <form id="deleteForm" method="POST" action="<?= route('biddings.proposals.destroy', [$bidding->id, $proposal->id]) ?>">
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

            <?php include(resource_path('views/layout/footer.php')); ?>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Gráfico de pizza para a proposta
                    <?php if ($proposal->total_cost && $proposal->profit_margin): ?>
                        const ctx = document.getElementById('propostaPieChart').getContext('2d');
                        const propostaPieChart = new Chart(ctx, {
                            type: 'pie',
                            data: {
                                labels: ['Custo', 'Lucro'],
                                datasets: [{
                                    data: [
                                        <?= $proposal->total_cost ?>,
                                        <?= $proposal->value - $proposal->total_cost ?>
                                    ],
                                    backgroundColor: [
                                        '#6c757d',
                                        '#28a745'
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
                    <?php endif; ?>

                    function confirmDeleteDocument(id) {
                        const deleteDocumentModal = new bootstrap.Modal(document.getElementById('deleteDocumentModal'));
                        document.getElementById('deleteDocumentForm').action = `/documents/${id}`;
                        deleteDocumentModal.show();
                    }
                });
            </script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
