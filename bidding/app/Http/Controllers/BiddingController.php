<?php
// app/Http/Controllers/BiddingController.php
namespace App\Http\Controllers;

use App\Models\Bidding;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\ScrapingService;


class BiddingController extends Controller
{

    public function index(Request $request)
    {
        $query = Bidding::query();

        // Filtragem
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('bidding_number', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%");
            });
        }

        $biddings = $query->orderBy('opening_date', 'desc')->paginate(10);

        $content = $this->renderIndex($biddings, $request);
        return response($content);
    }

    public function create()
    {
        $companies = Company::orderBy('name')->get();

        $content = $this->renderCreate($companies);
        return response($content);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|max:255',
            'bidding_number' => 'required|unique:biddings',
            'company_id' => 'required|exists:companies,id',
            'modality' => 'required',
            'opening_date' => 'required|date',
            'estimated_value' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return redirect()->route('biddings.create')
                ->withErrors($validator)
                ->withInput();
        }

        Bidding::create($request->all());

        return redirect()->route('biddings.index')
            ->with('success', 'Licitação cadastrada com sucesso!');
    }

    public function show($id)
    {
        $bidding = Bidding::with(['company', 'proposals', 'documents'])->findOrFail($id);

        $content = ob_start();
        include(resource_path('views/biddings/show.php'));
        $content = ob_get_clean();

        return response($content);
    }

    public function edit($id)
    {
        $bidding = Bidding::findOrFail($id);
        $companies = Company::orderBy('name')->get();

        $content = $this->renderEdit($bidding, $companies);
        return response($content);
    }

    public function update(Request $request, $id)
    {
        $bidding = Bidding::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|max:255',
            'bidding_number' => 'required|unique:biddings,bidding_number,' . $id,
            'company_id' => 'required|exists:companies,id',
            'modality' => 'required',
            'opening_date' => 'required|date',
            'estimated_value' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return redirect()->route('biddings.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $bidding->update($request->all());

        return redirect()->route('biddings.show', $id)
            ->with('success', 'Licitação atualizada com sucesso!');
    }

    public function destroy($id)
    {
        $bidding = Bidding::findOrFail($id);
        $bidding->delete();

        return redirect()->route('biddings.index')
            ->with('success', 'Licitação removida com sucesso!');
    }

    public function scrape($id)
    {
        $bidding = Bidding::findOrFail($id);

        $scrapingService = new ScrapingService();
        $result = $scrapingService->updateBiddingFromSource($bidding);

        if ($result['success']) {
            return redirect()->route('biddings.show', $id)
                ->with('success', $result['message']);
        } else {
            return redirect()->route('biddings.show', $id)
                ->with('error', $result['message']);
        }
    }

    public function search(Request $request)
    {
    $source = $request->input('source', 'comprasnet');
    $filters = $request->except(['_token', 'source']);

    $scrapingService = new ScrapingService();
    $result = $scrapingService->searchBiddings($source, $filters);

    if ($result['success']) {
        $searchResults = $result['data'];
        $content = $this->renderSearchResults($searchResults, $source);
        return response($content);
    } else {
        return redirect()->route('biddings.index')
            ->with('error', $result['message']);
    }
    }

    /**
     * Importa uma licitação para o sistema
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function import(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'bidding_number' => 'required|unique:biddings,bidding_number',
            'title' => 'required',
            'opening_date' => 'nullable|date',
            'modality' => 'required',
            'status' => 'required',
        ]);

        if ($validator->fails()) {
            return redirect()->route('biddings.search')
                ->withErrors($validator)
                ->with('error', 'Erro ao importar licitação: esta licitação já pode estar cadastrada.');
        }

        // Obter a empresa padrão (ou permitir seleção pelo usuário)
        $defaultCompany = \App\Models\Company::first();

        if (!$defaultCompany) {
            return redirect()->route('biddings.search')
                ->with('error', 'Erro ao importar licitação: nenhuma empresa cadastrada para associar à licitação.');
        }

        try {
            \Illuminate\Support\Facades\Log::info('Importando licitação', [
                'bidding_number' => $request->bidding_number,
                'title' => $request->title
            ]);

            $bidding = new \App\Models\Bidding();
            $bidding->bidding_number = $request->bidding_number;
            $bidding->title = $request->title;
            $bidding->opening_date = $request->opening_date ?: now();
            $bidding->closing_date = $request->closing_date;
            $bidding->modality = $request->modality;
            $bidding->status = $request->status;
            $bidding->url_source = $request->url_source;
            $bidding->estimated_value = $request->estimated_value;
            $bidding->description = $request->description;
            $bidding->company_id = $defaultCompany->id;
            $bidding->save();

            \Illuminate\Support\Facades\Log::info('Licitação importada com sucesso', [
                'bidding_id' => $bidding->id
            ]);

            return redirect()->route('biddings.show', $bidding->id)
                ->with('success', 'Licitação importada com sucesso!');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao importar licitação', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('biddings.search')
                ->with('error', 'Erro ao importar licitação: ' . $e->getMessage());
        }
    }

/**
 * Busca de licitações com filtro por segmento
 */
public function showSearchForm(Request $request)
{
    // Instanciar o serviço de scraping
    $scrapingService = new \App\Services\ScrapingService();

    // Obter fontes e segmentos
    $sources = $scrapingService->getSources();
    $segments = $scrapingService->getSegments();

    // Parâmetros da requisição
    $selectedSources = $request->input('sources', ['comprasnet']);
    if (!is_array($selectedSources) && $selectedSources != 'all') {
        $selectedSources = [$selectedSources];
    }

    $segment = $request->input('segment', '');
    $searchResults = null;

    // Verificar se é uma busca
    if ($request->has('search') && $request->search == '1') {
        // Preparar filtros
        $filters = [
            'bidding_number' => $request->input('bidding_number'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'segment' => $segment,
            'limit' => 50 // Limitar resultados para melhor desempenho
        ];

        // Adicionar filtros específicos por fonte
        if ($request->has('codigo')) {
            $filters['codigo'] = $request->input('codigo');
        }

        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        }

        if ($request->has('texto')) {
            $filters['texto'] = $request->input('texto');
        }

        if ($request->has('modalidade')) {
            $filters['modalidade'] = $request->input('modalidade');
        }

        // Log para debug
        \Illuminate\Support\Facades\Log::info('Iniciando busca de licitações', [
            'sources' => $selectedSources,
            'segment' => $segment,
            'filters' => $filters
        ]);

        // Realizar busca
        $searchResult = $scrapingService->searchBiddings($selectedSources, $filters);

        \Illuminate\Support\Facades\Log::info('Resultado da busca', [
            'success' => $searchResult['success'],
            'count' => $searchResult['count'] ?? 0
        ]);

        if ($searchResult['success']) {
            $searchResults = $searchResult['data'];
        }
    }

    // Retornar página HTML
    $html = '<!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Busca de Licitações - Sistema de Licitações</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
        <style>
            .search-container {
                background-color: #f8f9fa;
                border-radius: 10px;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .result-card {
                transition: transform 0.3s;
                height: 100%;
            }
            .result-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            .source-badge {
                position: absolute;
                top: 10px;
                right: 10px;
                font-size: 0.8rem;
            }
            .segment-badge {
                border-radius: 15px;
                padding: 5px 10px;
                font-size: 0.8rem;
                margin-right: 5px;
                margin-bottom: 5px;
                display: inline-block;
            }
            .select2-container {
                width: 100% !important;
            }
            .loader {
                display: none;
                border: 5px solid #f3f3f3;
                border-radius: 50%;
                border-top: 5px solid #3498db;
                width: 30px;
                height: 30px;
                animation: spin 2s linear infinite;
                margin-left: 10px;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    </head>';

    // Incluir o header
    if (file_exists(resource_path('views/layout/header.php'))) {
        ob_start();
        include(resource_path('views/layout/header.php'));
        $html .= ob_get_clean();
    } else {
        $html .= '<header class="bg-dark text-white p-3 mb-4">
            <div class="container">
                <h1>Sistema de Licitações</h1>
            </div>
        </header>';
    }

    $html .= '<div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h1><i class="fas fa-search me-2"></i>Busca Avançada de Licitações</h1>
                <p class="text-muted">Encontre licitações em diversas fontes e filtre por segmento de negócio</p>
            </div>
        </div>';

    // Mensagens de sucesso/erro
    if (session('success')) {
        $html .= '<div class="alert alert-success alert-dismissible fade show">
            ' . session('success') . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    }

    if (session('error')) {
        $html .= '<div class="alert alert-danger alert-dismissible fade show">
            ' . session('error') . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    }

    $html .= '<div class="search-container card">
        <div class="card-body">
            <form method="GET" action="' . route('biddings.search') . '" id="searchForm">
                <input type="hidden" name="search" value="1">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="sources" class="form-label">Fontes de Licitações</label>
                        <select class="form-select select2" id="sources" name="sources[]" multiple>
                            <option value="all" ' . ($selectedSources == 'all' ? 'selected' : '') . '>Todas as Fontes</option>';

    // Agrupar fontes por tipo
    $sourcesByType = [];
    foreach ($sources as $key => $source) {
        $type = $source['type'];
        if (!isset($sourcesByType[$type])) {
            $sourcesByType[$type] = [];
        }
        $sourcesByType[$type][$key] = $source;
    }

    // Exibir fontes agrupadas
    foreach ($sourcesByType as $type => $typeItems) {
        $typeName = ucwords(str_replace('-', ' ', $type));
        $html .= '<optgroup label="' . $typeName . '">';

        foreach ($typeItems as $key => $source) {
            $selected = (is_array($selectedSources) && in_array($key, $selectedSources)) ? 'selected' : '';
            $html .= '<option value="' . $key . '" ' . $selected . '>' . $source['name'] . '</option>';
        }

        $html .= '</optgroup>';
    }

    $html .= '</select>
                    </div>
                    <div class="col-md-6">
                        <label for="segment" class="form-label">Segmento de Negócio</label>
                        <select class="form-select select2" id="segment" name="segment">
                            <option value="">Todos os Segmentos</option>';

    foreach ($segments as $key => $segmentData) {
        $selected = $segment == $key ? 'selected' : '';
        $html .= '<option value="' . $key . '" ' . $selected . '>' . $segmentData['name'] . '</option>';
    }

    $html .= '</select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="bidding_number" class="form-label">Número/Código da Licitação</label>
                        <input type="text" class="form-control" id="bidding_number" name="bidding_number"
                               placeholder="Ex: 123456" value="' . $request->input('bidding_number', '') . '">
                    </div>
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Data Inicial</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="' . $request->input('start_date', '') . '">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">Data Final</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="' . $request->input('end_date', '') . '">
                    </div>
                </div>

                <div class="d-flex justify-content-end align-items-center">
                    <button type="submit" class="btn btn-primary d-flex align-items-center" id="searchButton">
                        <i class="fas fa-search me-1"></i> Buscar Licitações
                        <div class="loader ms-2" id="searchLoader"></div>
                    </button>
                </div>
            </form>
        </div>
    </div>';

    // Resultados da busca, se disponíveis
    if ($searchResults) {
        $html .= '<div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-list me-2"></i>Resultados da Busca</h5>
                <span class="badge bg-primary">' . count($searchResults) . ' licitações encontradas</span>
            </div>
            <div class="card-body">
                <div class="row">';

        if (count($searchResults) > 0) {
            foreach ($searchResults as $result) {
                // Determinar classe de status
                $statusClass = '';
                switch ($result['status']) {
                    case 'active':
                        $statusClass = 'bg-primary';
                        $statusLabel = 'Ativa';
                        break;
                    case 'pending':
                        $statusClass = 'bg-warning text-dark';
                        $statusLabel = 'Pendente';
                        break;
                    case 'finished':
                        $statusClass = 'bg-success';
                        $statusLabel = 'Finalizada';
                        break;
                    case 'canceled':
                        $statusClass = 'bg-danger';
                        $statusLabel = 'Cancelada';
                        break;
                    default:
                        $statusClass = 'bg-secondary';
                        $statusLabel = ucfirst($result['status']);
                }

                // Detectar segmentos relacionados
                $relatedSegments = [];
                foreach ($segments as $segKey => $segData) {
                    $text = strtolower($result['title'] . ' ' . ($result['description'] ?? ''));
                    foreach ($segData['keywords'] as $keyword) {
                        if (strpos($text, strtolower($keyword)) !== false) {
                            $relatedSegments[$segKey] = $segData['name'];
                            break;
                        }
                    }
                }

                $html .= '<div class="col-md-6 col-lg-4 mb-4">
                    <div class="card result-card">
                        <div class="card-header position-relative">
                            <span class="badge ' . $statusClass . '">
                                ' . $statusLabel . '
                            </span>
                            <span class="badge bg-secondary source-badge">
                                ' . ($result['source_name'] ?? ucfirst($result['source'])) . '
                            </span>
                            <h6 class="mt-2 mb-0">' . htmlspecialchars($result['bidding_number']) . '</h6>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">' . htmlspecialchars($result['title']) . '</h5>
                            <p class="card-text text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                ' . (isset($result['opening_date']) ? date('d/m/Y H:i', strtotime($result['opening_date'])) : 'Data não informada') . '
                            </p>
                            <p class="card-text">
                                <i class="fas fa-tag me-1"></i>
                                ' . str_replace('_', ' ', ucfirst($result['modality'])) . '
                            </p>';

                if (!empty($result['estimated_value'])) {
                    $html .= '<p class="card-text">
                        <i class="fas fa-dollar-sign me-1"></i>
                        Valor estimado: R$ ' . number_format($result['estimated_value'], 2, ',', '.') . '
                    </p>';
                }

                if (!empty($result['description'])) {
                    $desc = strlen($result['description']) > 150 ? substr($result['description'], 0, 147) . '...' : $result['description'];
                    $html .= '<p class="card-text small">' . htmlspecialchars($desc) . '</p>';
                }

                if (!empty($relatedSegments)) {
                    $html .= '<div class="mt-2 mb-2">';
                    foreach ($relatedSegments as $segKey => $segName) {
                        $html .= '<span class="segment-badge bg-light text-dark"><i class="fas fa-briefcase me-1"></i>' . $segName . '</span> ';
                    }
                    $html .= '</div>';
                }

                $html .= '</div>
                        <div class="card-footer d-flex justify-content-between">
                            <form method="POST" action="' . route('biddings.import') . '" class="import-form">
                                ' . csrf_field() . '
                                <input type="hidden" name="bidding_number" value="' . htmlspecialchars($result['bidding_number']) . '">
                                <input type="hidden" name="title" value="' . htmlspecialchars($result['title']) . '">
                                <input type="hidden" name="opening_date" value="' . ($result['opening_date'] ?? '') . '">
                                <input type="hidden" name="closing_date" value="' . ($result['closing_date'] ?? '') . '">
                                <input type="hidden" name="modality" value="' . $result['modality'] . '">
                                <input type="hidden" name="status" value="' . $result['status'] . '">
                                <input type="hidden" name="url_source" value="' . htmlspecialchars($result['url_source']) . '">
                                <input type="hidden" name="estimated_value" value="' . ($result['estimated_value'] ?? '') . '">
                                <input type="hidden" name="description" value="' . htmlspecialchars($result['description'] ?? '') . '">

                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fas fa-download me-1"></i> Importar
                                </button>
                            </form>';

                if (isset($result['url_source']) && $result['url_source']) {
                    $html .= '<a href="' . htmlspecialchars($result['url_source']) . '" target="_blank" class="btn btn-info btn-sm">
                        <i class="fas fa-external-link-alt me-1"></i> Ver Original
                    </a>';
                }

                $html .= '</div>
                    </div>
                </div>';
            }
        } else {
            $html .= '<div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> Nenhuma licitação encontrada com os filtros informados.
                </div>
            </div>';
        }
	$html .= '</div>
            </div>
        </div>';
    }

    $html .= '</div>';

    // Incluir o footer
    if (file_exists(resource_path('views/layout/footer.php'))) {
        ob_start();
        include(resource_path('views/layout/footer.php'));
        $html .= ob_get_clean();
    } else {
        $html .= '<footer class="bg-light py-3 mt-5">
            <div class="container">
                <p>&copy; ' . date('Y') . ' Sistema de Licitações</p>
            </div>
        </footer>';
    }

    $html .= '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inicializar Select2
            $(".select2").select2({
                theme: "bootstrap-5",
                width: "100%"
            });

            // Configurar seleção de "Todas as Fontes"
            $("#sources").on("select2:select", function (e) {
                if (e.params.data.id === "all") {
                    $(this).val("all").trigger("change");
                } else {
                    // Se selecionar uma fonte específica, remover a opção "Todas"
                    var values = $(this).val();
                    if (values && values.includes("all")) {
                        values = values.filter(v => v !== "all");
                        $(this).val(values).trigger("change");
                    }
                }
            });

            // Mostrar loader ao submeter o formulário
            $("#searchForm").on("submit", function() {
                $("#searchButton").prop("disabled", true);
                $("#searchLoader").show();
            });

            // Mostrar loader ao importar licitação
            $(".import-form").on("submit", function() {
                $(this).find("button").prop("disabled", true).html(\'<i class="fas fa-spinner fa-spin"></i> Importando...\');
            });

            // Atualizar datas com valores padrão se estiverem vazias
            const startDateInput = document.getElementById("start_date");
            const endDateInput = document.getElementById("end_date");

            if (startDateInput && !startDateInput.value) {
                const oneMonthAgo = new Date();
                oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
                startDateInput.value = oneMonthAgo.toISOString().split("T")[0];
            }

            if (endDateInput && !endDateInput.value) {
                const today = new Date();
                endDateInput.value = today.toISOString().split("T")[0];
            }
        });
    </script>
    </body>
    </html>';

    return response($html);
}


    private function renderSearchResults($searchResults, $source)
    {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Busca de Licitações - Sistema de Licitações</title>
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
                            <li class="breadcrumb-item active">Resultados da Busca</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Resultados da Busca - <?= ucfirst($source) ?></h5>
                    <a href="<?= route('biddings.index') ?>" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <?php if (count($searchResults) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Número</th>
                                        <th>Título</th>
                                        <th>Abertura</th>
                                        <th>Modalidade</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($searchResults as $result): ?>
                                        <tr>
                                            <td><?= $result['bidding_number'] ?></td>
                                            <td><?= $result['title'] ?></td>
                                            <td><?= $result['opening_date'] ? date('d/m/Y H:i', strtotime($result['opening_date'])) : 'Não informada' ?></td>
                                            <td><?= str_replace('_', ' ', ucfirst($result['modality'])) ?></td>
                                            <td>
                                                <?php if ($result['status'] == 'active'): ?>
                                                    <span class="badge bg-primary">Ativa</span>
                                                <?php elseif ($result['status'] == 'pending'): ?>
                                                    <span class="badge bg-warning">Pendente</span>
                                                <?php elseif ($result['status'] == 'finished'): ?>
                                                    <span class="badge bg-success">Finalizada</span>
                                                <?php elseif ($result['status'] == 'canceled'): ?>
                                                    <span class="badge bg-danger">Cancelada</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" action="<?= route('biddings.import') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="bidding_number" value="<?= $result['bidding_number'] ?>">
                                                    <input type="hidden" name="title" value="<?= $result['title'] ?>">
                                                    <input type="hidden" name="opening_date" value="<?= $result['opening_date'] ?>">
                                                    <input type="hidden" name="modality" value="<?= $result['modality'] ?>">
                                                    <input type="hidden" name="status" value="<?= $result['status'] ?>">
                                                    <input type="hidden" name="url_source" value="<?= $result['url_source'] ?>">

                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        <i class="fas fa-download me-1"></i> Importar
                                                    </button>

                                                    <?php if ($result['url_source']): ?>
                                                        <a href="<?= $result['url_source'] ?>" target="_blank" class="btn btn-sm btn-info">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Nenhuma licitação encontrada com os filtros informados.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Nova Busca</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="<?= route('biddings.search') ?>">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="source" class="form-label">Fonte</label>
                                <select class="form-select" id="source" name="source">
                                    <option value="comprasnet" <?= $source == 'comprasnet' ? 'selected' : '' ?>>ComprasNet</option>
                                    <option value="licitacoes-e" <?= $source == 'licitacoes-e' ? 'selected' : '' ?>>Licitações-e (Banco do Brasil)</option>
                                    <option value="compras-gov" <?= $source == 'compras-gov' ? 'selected' : '' ?>>Compras Gov</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Filtros</label>
                            <div id="filters-container">
                                <!-- Filtros específicos para cada fonte serão carregados via JavaScript -->

                                <!-- ComprasNet -->
                                <div class="comprasnet-filters" style="display: <?= $source == 'comprasnet' ? 'block' : 'none' ?>;">
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="numprp" class="form-label">Número da Licitação</label>
                                            <input type="text" class="form-control" id="numprp" name="numprp">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="dt_publ_ini" class="form-label">Data Inicial</label>
                                            <input type="date" class="form-control" id="dt_publ_ini" name="dt_publ_ini">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="dt_publ_fim" class="form-label">Data Final</label>
                                            <input type="date" class="form-control" id="dt_publ_fim" name="dt_publ_fim">
                                        </div>
                                    </div>
                                </div>

                                <!-- Licitações-e -->
                                <div class="licitacoes-e-filters" style="display: <?= $source == 'licitacoes-e' ? 'block' : 'none' ?>;">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="cod" class="form-label">Código da Licitação</label>
                                            <input type="text" class="form-control" id="cod" name="cod">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="consulta" class="form-label">Tipo de Consulta</label>
                                            <select class="form-select" id="consulta" name="consulta">
                                                <option value="todas">Todas</option>
                                                <option value="em_andamento">Em Andamento</option>
                                                <option value="encerradas">Encerradas</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Compras Gov -->
                                <div class="compras-gov-filters" style="display: <?= $source == 'compras-gov' ? 'block' : 'none' ?>;">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="texto" class="form-label">Texto para Busca</label>
                                            <input type="text" class="form-control" id="texto" name="texto">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="modalidade" class="form-label">Modalidade</label>
                                            <select class="form-select" id="modalidade" name="modalidade">
                                                <option value="">Todas</option>
                                                <option value="pregao_eletronico">Pregão Eletrônico</option>
                                                <option value="pregao_presencial">Pregão Presencial</option>
                                                <option value="concorrencia">Concorrência</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> Buscar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include(resource_path('views/layout/footer.php')); ?>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const sourceSelect = document.getElementById('source');

                function showFilters() {
                    const source = sourceSelect.value;

                    document.querySelector('.comprasnet-filters').style.display = 'none';
                    document.querySelector('.licitacoes-e-filters').style.display = 'none';
                    document.querySelector('.compras-gov-filters').style.display = 'none';

                    document.querySelector('.' + source + '-filters').style.display = 'block';
                }

                sourceSelect.addEventListener('change', showFilters);
            });
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
    }

    private function renderIndex($biddings, $request)
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="pt-br">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Licitações - Sistema de Licitações</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        </head>
        <body>
            <?php include(resource_path('views/layout/header.php')); ?>

            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Licitações</h1>
                    <a href="<?= route('biddings.create') ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Nova Licitação
                    </a>
                </div>

                <?php if (session('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= session('success') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Filtros</h5>
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
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i> Filtrar
                                </button>
                                <a href="<?= route('biddings.index') ?>" class="btn btn-secondary ms-2">
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
                                                <td><?= $bidding->modality ?></td>
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
                                                    <span class="badge bg-warning">Pendente</span>
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
                                                    <a href="<?= route('biddings.show', $bidding->id) ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="<?= route('biddings.edit', $bidding->id) ?>" class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger"
                                                            onclick="confirmDelete(<?= $bidding->id ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Nenhuma licitação encontrada</td>
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

        <?php include(resource_path('views/layout/footer.php')); ?>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            function confirmDelete(id) {
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                document.getElementById('deleteForm').action = `/biddings/${id}`;
                deleteModal.show();
            }
        </script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

private function renderCreate($companies)
{
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Nova Licitação - Sistema de Licitações</title>
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
                            <li class="breadcrumb-item active">Nova Licitação</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Nova Licitação</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= route('biddings.store') ?>">
                        <?= csrf_field() ?>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="bidding_number" class="form-label">Número da Licitação *</label>
                                <input type="text" class="form-control <?= $errors->has('bidding_number') ? 'is-invalid' : '' ?>"
                                       id="bidding_number" name="bidding_number" value="<?= old('bidding_number') ?>" required>
                                <?php if ($errors->has('bidding_number')): ?>
                                    <div class="invalid-feedback">
                                        <?= $errors->first('bidding_number') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="title" class="form-label">Título *</label>
                                <input type="text" class="form-control <?= $errors->has('title') ? 'is-invalid' : '' ?>"
                                       id="title" name="title" value="<?= old('title') ?>" required>
                                <?php if ($errors->has('title')): ?>
                                    <div class="invalid-feedback">
                                        <?= $errors->first('title') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="company_id" class="form-label">Empresa *</label>
                                <select class="form-select <?= $errors->has('company_id') ? 'is-invalid' : '' ?>"
                                        id="company_id" name="company_id" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?= $company->id ?>" <?= old('company_id') == $company->id ? 'selected' : '' ?>>
                                            <?= $company->name ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($errors->has('company_id')): ?>
                                    <div class="invalid-feedback">
                                        <?= $errors->first('company_id') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="modality" class="form-label">Modalidade *</label>
                                <select class="form-select <?= $errors->has('modality') ? 'is-invalid' : '' ?>"
                                        id="modality" name="modality" required>
                                    <option value="">Selecione...</option>
                                    <option value="pregao_eletronico" <?= old('modality') == 'pregao_eletronico' ? 'selected' : '' ?>>
                                        Pregão Eletrônico
                                    </option>
                                    <option value="pregao_presencial" <?= old('modality') == 'pregao_presencial' ? 'selected' : '' ?>>
                                        Pregão Presencial
                                    </option>
                                    <option value="concorrencia" <?= old('modality') == 'concorrencia' ? 'selected' : '' ?>>
                                        Concorrência
                                    </option>
                                    <option value="tomada_precos" <?= old('modality') == 'tomada_precos' ? 'selected' : '' ?>>
                                        Tomada de Preços
                                    </option>
                                    <option value="convite" <?= old('modality') == 'convite' ? 'selected' : '' ?>>
                                        Convite
                                    </option>
                                    <option value="leilao" <?= old('modality') == 'leilao' ? 'selected' : '' ?>>
                                        Leilão
                                    </option>
                                    <option value="concurso" <?= old('modality') == 'concurso' ? 'selected' : '' ?>>
                                        Concurso
                                    </option>
                                </select>
                                <?php if ($errors->has('modality')): ?>
                                    <div class="invalid-feedback">
                                        <?= $errors->first('modality') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="opening_date" class="form-label">Data de Abertura *</label>
                                <input type="datetime-local" class="form-control <?= $errors->has('opening_date') ? 'is-invalid' : '' ?>"
                                       id="opening_date" name="opening_date" value="<?= old('opening_date') ?>" required>
                                <?php if ($errors->has('opening_date')): ?>
                                    <div class="invalid-feedback">
                                        <?= $errors->first('opening_date') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="closing_date" class="form-label">Data de Encerramento</label>
                                <input type="datetime-local" class="form-control <?= $errors->has('closing_date') ? 'is-invalid' : '' ?>"
                                       id="closing_date" name="closing_date" value="<?= old('closing_date') ?>">
                                <?php if ($errors->has('closing_date')): ?>
                                    <div class="invalid-feedback">
                                        <?= $errors->first('closing_date') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="publication_date" class="form-label">Data de Publicação</label>
                                <input type="date" class="form-control <?= $errors->has('publication_date') ? 'is-invalid' : '' ?>"
                                       id="publication_date" name="publication_date" value="<?= old('publication_date') ?>">
                                <?php if ($errors->has('publication_date')): ?>
                                    <div class="invalid-feedback">
                                        <?= $errors->first('publication_date') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="estimated_value" class="form-label">Valor Estimado (R$)</label>
                                <input type="number" step="0.01" min="0" class="form-control <?= $errors->has('estimated_value') ? 'is-invalid' : '' ?>"
                                       id="estimated_value" name="estimated_value" value="<?= old('estimated_value') ?>">
                                <?php if ($errors->has('estimated_value')): ?>
                                    <div class="invalid-feedback">
                                        <?= $errors->first('estimated_value') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select <?= $errors->has('status') ? 'is-invalid' : '' ?>"
                                        id="status" name="status">
                                    <option value="pending" <?= old('status', 'pending') == 'pending' ? 'selected' : '' ?>>
                                        Pendente
                                    </option>
                                    <option value="active" <?= old('status') == 'active' ? 'selected' : '' ?>>
                                        Ativa
                                    </option>
                                    <option value="finished" <?= old('status') == 'finished' ? 'selected' : '' ?>>
                                        Finalizada
                                    </option>
                                    <option value="canceled" <?= old('status') == 'canceled' ? 'selected' : '' ?>>
                                        Cancelada
                                    </option>
                                </select>
                                <?php if ($errors->has('status')): ?>
                                    <div class="invalid-feedback">
                                        <?= $errors->first('status') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="url_source" class="form-label">URL da Fonte</label>
                                <input type="url" class="form-control <?= $errors->has('url_source') ? 'is-invalid' : '' ?>"
                                       id="url_source" name="url_source" value="<?= old('url_source') ?>">
                                <?php if ($errors->has('url_source')): ?>
                                    <div class="invalid-feedback">
                                        <?= $errors->first('url_source') ?>
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
                            <a href="<?= route('biddings.index') ?>" class="btn btn-secondary me-2">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include(resource_path('views/layout/footer.php')); ?>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

private function renderShow($bidding)
{
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Detalhes da Licitação - Sistema de Licitações</title>
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
                            <li class="breadcrumb-item active">Detalhes da Licitação</li>
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

            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Informações da Licitação</h5>
                            <div>
                                <a href="<?= route('biddings.edit', $bidding->id) ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <button type="button" class="btn btn-sm btn-danger"
                                        onclick="confirmDelete(<?= $bidding->id ?>)">
                                    <i class="fas fa-trash"></i> Excluir
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
                                            <span class="badge bg-warning">Pendente</span>
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
                                            <a href="<?= $bidding->url_source ?>" target="_blank">
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
                                    <i class="fas fa-sync"></i> Atualizar via Scraping
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Documentos</h5>
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
                                    <i class="fas fa-upload"></i> Enviar Documento
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Propostas</h5>
                            <a href="<?= route('biddings.proposals.create', $bidding->id) ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> Nova Proposta
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
                                                               class="btn btn-sm btn-info">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="<?= route('biddings.proposals.edit', [$bidding->id, $proposal->id]) ?>"
                                                               class="btn btn-sm btn-warning">
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
                            <script>
                                function confirmDelete(id) {
                                    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                                    document.getElementById('deleteForm').action = `/biddings/${id}`;
                                    deleteModal.show();
                                }

                                function confirmDeleteDocument(id) {
                                    const deleteDocumentModal = new bootstrap.Modal(document.getElementById('deleteDocumentModal'));
                                    document.getElementById('deleteDocumentForm').action = `/documents/${id}`;
                                    deleteDocumentModal.show();
                                }
                            </script>
                        </body>
                        </html>
                        <?php
                        return ob_get_clean();
                    }
                }
