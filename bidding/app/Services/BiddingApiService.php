<?php

namespace App\Services;

use App\Models\Bidding;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;
use Illuminate\Http\Client\RequestException;

/**
 * Serviço para busca e atualização de Licitações usando APIs oficiais.
 * Evita scraping para garantir maior robustez e confiabilidade.
 */
class BiddingApiService
{
    /**
     * Fontes de API de licitação configuradas.
     * Status: 'active', 'experimental', 'disabled'
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $sources = [
        'dados-abertos-compras' => [
            'name' => 'API Dados Abertos Compras Gov (Oficial)',
            'url' => 'http://compras.dados.gov.br',
            'api_endpoint' => 'http://compras.dados.gov.br/licitacoes/v1/licitacoes.json',
            'detail_api_endpoint_pattern' => 'http://compras.dados.gov.br/licitacoes/doc/licitacao/%s.json',
            'type' => 'api',
            'status' => 'active',
            'description' => 'API Oficial do Governo Federal para dados de licitações',
        ],
        'pncp' => [
            'name' => 'PNCP (Portal Nacional - API)',
            'url' => 'https://pncp.gov.br/',
            'api_endpoint' => 'https://api.pncp.gov.br/api/pncp/v1/licitacoes',
            'detail_api_endpoint_pattern' => 'https://api.pncp.gov.br/api/pncp/v1/licitacoes/%s',
            'type' => 'api',
            'status' => 'experimental',
            'description' => 'API do Portal Nacional de Contratações Públicas',
        ],
        // APIs estaduais podem ser adicionadas conforme documentação disponível
        'bec-sp-api' => [
            'name' => 'API BEC-SP (São Paulo)',
            'url' => 'https://www.bec.sp.gov.br',
            'api_endpoint' => null, // Preencher se e quando a API for disponibilizada
            'type' => 'api',
            'status' => 'disabled',
            'description' => 'API da Bolsa Eletrônica de Compras de SP (verificar disponibilidade)',
        ],
    ];

    /**
     * Segmentos de negócio e palavras-chave associadas.
     *
     * @var array<string, array<string, string|array<string>>>
     */
    protected array $segments = [
        'tecnologia' => ['name' => 'Tecnologia da Informação', 'keywords' => ['tecnologia', 'software', 'hardware', 'computador', 'servidor', 'rede', 'ti', 'suporte', 'sistema', 'informática', 'digital', 'cloud', 'desenvolvimento', 'segurança da informação']],
        'construcao' => ['name' => 'Construção Civil', 'keywords' => ['construção', 'obra', 'engenharia', 'reforma', 'infraestrutura', 'pavimentação', 'edificação', 'projeto', 'civil', 'elétrica', 'hidráulica', 'manutenção predial', 'arquitetura', 'terraplenagem']],
        'saude' => ['name' => 'Saúde', 'keywords' => ['saúde', 'hospital', 'médico', 'medicamento', 'enfermagem', 'equipamento hospitalar', 'equipamento médico', 'ambulância', 'laboratório', 'clínico', 'insumo hospitalar', 'ppi', 'odontológico']],
        'alimentacao' => ['name' => 'Alimentação', 'keywords' => ['alimento', 'merenda', 'refeição', 'restaurante', 'comida', 'alimentício', 'gênero alimentício', 'nutrição', 'hortifruti', 'panificação', 'catering', 'cozinha industrial']],
        'educacao' => ['name' => 'Educação', 'keywords' => ['educação', 'escola', 'ensino', 'professor', 'didático', 'material escolar', 'livro', 'uniforme', 'capacitação', 'treinamento', 'curso', 'plataforma educacional']],
        'servicos' => ['name' => 'Serviços Gerais', 'keywords' => ['serviço', 'limpeza', 'vigilância', 'segurança', 'manutenção', 'conservação', 'portaria', 'jardinagem', 'copeiragem', 'terceirização', 'consultoria', 'auditoria', 'assessoria', 'gráfico', 'publicidade']],
        'transporte' => ['name' => 'Transporte e Logística', 'keywords' => ['transporte', 'veículo', 'ônibus', 'caminhão', 'combustível', 'frete', 'logística', 'locação de veículos', 'passagem aérea', 'manutenção de frota', 'armazenagem', 'mudança']],
        'mobiliario' => ['name' => 'Mobiliário e Equipamentos', 'keywords' => ['mobiliário', 'móvel', 'cadeira', 'mesa', 'armário', 'estante', 'prateleira', 'equipamento de escritório', 'eletrodoméstico', 'ar condicionado']],
    ];

    /**
     * Tempo de cache em segundos (padrão: 1 hora).
     *
     * @var int
     */
    protected int $cacheTime;

    /**
     * Opções base para requisições HTTP.
     *
     * @var array<string, mixed>
     */
    protected array $httpOptions = [
        'timeout' => 45,
        'connect_timeout' => 15,
        'verify' => false, // Mudar para true em produção com CAs configurados!
        'headers' => [
            'User-Agent' => 'BiddingApiClient/1.0',
            'Accept' => 'application/json',
            'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
        ],
    ];

    /**
     * Construtor
     */
    public function __construct()
    {
        // Permite sobrescrever o tempo de cache via variável de ambiente
        $this->cacheTime = (int) env('API_CACHE_TIME', 3600);

        // Configura macro global para cliente HTTP se não existir
        if (!Http::hasMacro('apiClient')) {
            Http::macro('apiClient', function () {
                return Http::withOptions($this->httpOptions)->retry(3, 1000);
            });
        }
    }

    /**
     * Retorna a lista de fontes configuradas.
     * @return array<string, array<string, mixed>>
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * Retorna a lista de segmentos configurados.
     * @return array<string, array<string, string|array<string>>>
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    /**
     * Busca licitações em uma ou mais fontes configuradas.
     *
     * @param string|array<string> $sourceKeys Chave(s) da(s) fonte(s) a buscar. Use 'all' para todas as habilitadas.
     * @param array<string, mixed> $filters Filtros de busca (ex: 'bidding_number', 'start_date', 'end_date', 'segment', 'limit').
     * @return array<string, mixed> Resultado da busca com status, mensagem, contagem e dados.
     */
    public function searchBiddings(string|array $sourceKeys, array $filters = []): array
    {
        $targetSourcesInput = is_array($sourceKeys) ? $sourceKeys : ($sourceKeys === 'all' ? array_keys($this->sources) : [$sourceKeys]);
        $allResults = [];
        $validSourcesToTry = [];
        $messages = [];
        $skippedSources = [];

        // Filtra e valida as fontes a serem tentadas
        $allowExperimental = env('ALLOW_EXPERIMENTAL_API', true);
        foreach ($targetSourcesInput as $key) {
            if (!isset($this->sources[$key])) {
                Log::warning("Chave de fonte inválida fornecida: {$key}");
                $messages[] = "Fonte inválida: {$key}";
                continue;
            }

            $sourceConfig = $this->sources[$key];
            $status = $sourceConfig['status'] ?? 'active';

            // Pular fontes explicitamente desabilitadas
            if ($status === 'disabled') {
                $skippedSources[] = "{$sourceConfig['name']} (Motivo: desabilitada)";
                continue;
            }

            // Pular experimentais se configurado para não permitir
            if ($status === 'experimental' && !$allowExperimental) {
                $skippedSources[] = "{$sourceConfig['name']} (Motivo: experimental não permitido)";
                continue;
            }

            // Valida se tem endpoint configurado
            if (empty($sourceConfig['api_endpoint'])) {
                $skippedSources[] = "{$sourceConfig['name']} (Motivo: endpoint não configurado)";
                continue;
            }

            $validSourcesToTry[] = $key;
        }

        if (empty($validSourcesToTry)) {
            $msg = 'Nenhuma fonte válida selecionada ou habilitada para busca.';
             if (!empty($skippedSources)) {
                 $msg .= ' Fontes puladas: ' . implode(', ', $skippedSources);
             }
            return $this->formatResult(false, $msg, 0, [], $messages);
        }

        Log::info('Iniciando busca de licitações via API', ['sources_to_try' => $validSourcesToTry, 'filters' => $filters]);

        // Lógica de Cache
        $cacheKey = $this->generateCacheKey($validSourcesToTry, $filters);
        if ($this->cacheTime > 0) {
            $cachedResults = Cache::get($cacheKey);
            if ($cachedResults !== null && is_array($cachedResults) &&
                (!empty($cachedResults) && isset($cachedResults[0]['bidding_number']) || empty($cachedResults))) {
                 Log::info('Resultados encontrados no cache', ['cache_key' => $cacheKey, 'count' => count($cachedResults)]);
                 if (!empty($skippedSources)) {
                    $messages = array_merge($messages, ['Fontes puladas (cache): ' . implode(', ', $skippedSources)]);
                 }
                 return $this->formatResult(true, 'Busca concluída com sucesso (do cache).', count($cachedResults), $cachedResults, $messages);
             } elseif ($cachedResults !== null) {
                 Cache::forget($cacheKey);
             }
        }

        $successfulSourcesCount = 0;
        // Loop pelas fontes válidas para tentar
        foreach ($validSourcesToTry as $sourceKey) {
            try {
                Log::info("Consultando API: {$sourceKey}", ['filters' => $filters]);
                $sourceResult = $this->searchInSource($sourceKey, $filters);

                if ($sourceResult['success']) {
                    $count = count($sourceResult['data']);
                    Log::info("Consulta à API {$sourceKey} completada", ['count' => $count]);
                    $allResults = array_merge($allResults, $sourceResult['data']);
                    $successfulSourcesCount++;

                    if (!empty($sourceResult['message']) && !in_array($sourceResult['message'], ['Query successful.', 'Nenhuma licitação encontrada.'])) {
                        $messages[] = "{$this->sources[$sourceKey]['name']}: {$sourceResult['message']}";
                    }
                } else {
                    Log::warning("Falha na consulta à API: {$sourceKey}", ['message' => $sourceResult['message']]);
                    $messages[] = "Falha em {$this->sources[$sourceKey]['name']}: {$sourceResult['message']}";
                }
            } catch (Exception $e) {
                Log::error("Erro ao consultar API: {$sourceKey}", [
                    'error' => $e->getMessage(),
                    'trace_snippet' => substr($e->getTraceAsString(), 0, 500)
                ]);
                $messages[] = "Erro interno ao consultar API {$this->sources[$sourceKey]['name']}.";
            }
        }

        // Pós-processamento: deduplicar, filtrar, ordenar, limitar
        $processedResults = $this->postProcessResults($allResults, $filters);
        $finalCount = count($processedResults);

        // Determina mensagem final baseada nos resultados e falhas
        $success = $finalCount > 0 || $successfulSourcesCount > 0;
        $message = $success ? 'Busca concluída.' : 'Nenhum resultado encontrado ou falha em todas as APIs consultadas.';
        if ($successfulSourcesCount > 0 && $successfulSourcesCount < count($validSourcesToTry)) {
            $message = 'Busca parcialmente concluída (algumas APIs falharam ou não retornaram dados).';
        } elseif ($successfulSourcesCount == 0 && count($validSourcesToTry) > 0) {
            $message = 'Falha ao consultar todas as APIs selecionadas e habilitadas.';
        }

        if (!empty($skippedSources)) {
             $message .= ' Fontes puladas: ' . implode(', ', $skippedSources);
        }

        // Salva no cache se houver resultados e o cache estiver habilitado
        if ($finalCount > 0 && $this->cacheTime > 0) {
            Cache::put($cacheKey, $processedResults, $this->cacheTime);
            Log::info("Resultados salvos no cache", ['cache_key' => $cacheKey, 'count' => $finalCount]);
        }

        return $this->formatResult($success, $message, $finalCount, $processedResults, $messages);
    }

    /**
     * Formata a estrutura de retorno padrão para os métodos do serviço.
     *
     * @param bool $success Status da operação.
     * @param string $message Mensagem principal.
     * @param int $count Contagem de itens nos dados.
     * @param array|null $data Dados retornados (opcional).
     * @param array $details Mensagens ou detalhes adicionais (opcional).
     * @return array{success: bool, message: string, count: int, data: array|null, details: array}
     */
    protected function formatResult(bool $success, string $message, int $count, ?array $data, array $details = []): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'count' => $count,
            'data' => $data,
            'details' => $details,
        ];
    }

    /**
     * Aplica pós-processamento aos resultados agregados.
     * (Deduplicação, filtragem por segmento, ordenação, limite)
     *
     * @param array $results Resultados brutos agregados.
     * @param array $filters Filtros aplicados na busca.
     * @return array Resultados processados.
     */
    protected function postProcessResults(array $results, array $filters): array
    {
        // 1. Deduplicação (baseado na fonte e número da licitação)
        $uniqueResults = [];
        $seenKeys = [];
        foreach ($results as $result) {
            $source = $result['source'] ?? 'unknown_source';
            $number = $result['bidding_number'] ?? null;
            if ($number === null) continue;
            $key = $source . '_' . $number;
            if (!isset($seenKeys[$key])) {
                $uniqueResults[] = $result;
                $seenKeys[$key] = true;
            }
        }
        $results = $uniqueResults;

        // 2. Filtragem por Segmento (se aplicado)
        if (!empty($filters['segment'])) {
            $initialCount = count($results);
            $results = $this->filterBySegment($results, $filters['segment']);
            Log::info("Filtro por segmento aplicado", [
                'segment' => $filters['segment'],
                'before' => $initialCount,
                'after' => count($results)
            ]);
        }

        // 3. Ordenação (por data de abertura, decrescente)
        usort($results, function ($a, $b) {
            $dateA = !empty($a['opening_date']) ? strtotime((string) $a['opening_date']) : 0;
            $dateB = !empty($b['opening_date']) ? strtotime((string) $b['opening_date']) : 0;
            return $dateB <=> $dateA; // Mais recentes primeiro
        });

        // 4. Limite de Resultados
        $limit = isset($filters['limit']) && is_numeric($filters['limit']) ? max(1, (int) $filters['limit']) : 100;
        if (count($results) > $limit) {
             $results = array_slice($results, 0, $limit);
             Log::info("Limite de resultados aplicado", ['limit' => $limit, 'final_count' => count($results)]);
        }

        return $results;
    }

    /**
     * Gera uma chave de cache única baseada nas fontes e filtros.
     *
     * @param array $sources Lista de chaves das fontes.
     * @param array $filters Filtros aplicados.
     * @return string Chave de cache MD5.
     */
    protected function generateCacheKey(array $sources, array $filters): string
    {
        sort($sources);
        ksort($filters);
        $version = 'v1.0';
        $keyData = ['v' => $version, 'sources' => $sources, 'filters' => $filters];
        return 'biddings_api_search_' . md5(json_encode($keyData));
    }

    /**
     * Despacha a busca para o método específico da fonte API.
     *
     * @param string $sourceKey Chave da fonte.
     * @param array $filters Filtros.
     * @return array Resultado da busca da fonte específica.
     */
    protected function searchInSource(string $sourceKey, array $filters = []): array
    {
        $methodName = 'searchIn' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $sourceKey)));

        $sourceConfig = $this->sources[$sourceKey];
        $status = $sourceConfig['status'] ?? 'active';

        if (method_exists($this, $methodName) && $status !== 'disabled') {
            return $this->$methodName($filters);
        }

        if ($status === 'disabled') {
             $msg = "API '{$sourceConfig['name']}' está desabilitada e não pode ser consultada.";
             Log::warning("Tentativa de chamar API desabilitada: '{$sourceKey}'");
             return $this->formatResult(false, $msg, 0, []);
        } else {
            $msg = "Método de consulta para API '{$sourceConfig['name']}' não implementado ({$methodName}).";
            Log::warning("Método de API não implementado: {$sourceKey} ({$methodName})");
            return $this->formatResult(false, $msg, 0, []);
        }
    }

    /**
     * Busca na API Dados Abertos Compras Gov.
     * @param array $filters Filtros.
     * @return array Resultado.
     */
    protected function searchInDadosAbertosCompras(array $filters = []): array
    {
        $sourceKey = 'dados-abertos-compras';
        $sourceConfig = $this->sources[$sourceKey];
        $apiUrl = $sourceConfig['api_endpoint'];

        try {
            $queryParams = [];
            $apiMaxLimit = 500;

            if (!empty($filters['bidding_number'])) {
                $queryParams['numero_licitacao'] = $filters['bidding_number'];
            } else {
                if (!empty($filters['start_date'])) $queryParams['data_publicacao_min'] = $this->parseDateForApi($filters['start_date']);
                if (!empty($filters['end_date'])) $queryParams['data_publicacao_max'] = $this->parseDateForApi($filters['end_date']);
                // Adicione outros filtros conforme documentação da API
            }

            // Paginação
            $queryParams['offset'] = $filters['offset'] ?? 0;
            $queryParams['limit'] = min($filters['limit'] ?? 100, $apiMaxLimit);

            Log::info("Consultando API Dados Abertos", ['url' => $apiUrl, 'params' => $queryParams]);
            $response = Http::apiClient()->get($apiUrl, $queryParams);

            if (!$response->successful()) {
                Log::error("Falha na requisição à API Dados Abertos", [
                    'status' => $response->status(),
                    'url' => $apiUrl,
                    'params' => $queryParams,
                    'body' => substr($response->body(), 0, 500)
                ]);
                return $this->formatResult(false, "Erro HTTP {$response->status()} ao acessar API Dados Abertos.", 0, []);
            }

            $responseData = $response->json();

            if (empty($responseData) || !isset($responseData['_embedded']['licitacoes']) || !is_array($responseData['_embedded']['licitacoes'])) {
                if (isset($responseData['count']) && $responseData['count'] === 0) {
                    Log::info("Nenhum resultado encontrado na API Dados Abertos para os filtros.");
                    return $this->formatResult(true, 'Nenhuma licitação encontrada.', 0, []);
                }

                Log::warning("Formato de resposta inesperado da API Dados Abertos", [
                    'url' => $apiUrl,
                    'params' => $queryParams,
                    'keys' => array_keys($responseData ?? [])
                ]);
                return $this->formatResult(false, 'Formato de resposta inesperado da API Dados Abertos.', 0, []);
            }

            // Formata os itens encontrados
            $licitacoes = $responseData['_embedded']['licitacoes'];
            $results = [];
            foreach ($licitacoes as $item) {
                $formattedItem = $this->formatApiItem($item, $sourceKey);
                if ($formattedItem) {
                    $results[] = $formattedItem;
                }
            }

            Log::info("Resultados da API Dados Abertos processados", ['count' => count($results)]);
            return $this->formatResult(true, 'Query successful.', count($results), $results);

        } catch (RequestException $e) {
             Log::error("Erro de Conexão/HTTP ao consultar API Dados Abertos", [
                'error' => $e->getMessage(),
                'url' => $apiUrl,
                'trace_snippet' => substr($e->getTraceAsString(), 0, 500)
             ]);
             $statusCode = ($e->response instanceof \Illuminate\Http\Client\Response) ? $e->response->status() : 'N/A';
             return $this->formatResult(false, "Erro de conexão ({$statusCode}) ao acessar API Dados Abertos.", 0, []);
        } catch (Exception $e) {
            Log::error("Erro ao consultar API Dados Abertos", [
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace_snippet' => substr($e->getTraceAsString(), 0, 500)
            ]);
            return $this->formatResult(false, 'Erro interno ao processar consulta na API Dados Abertos: '.$e->getMessage(), 0, []);
        }
    }

    /**
     * Busca na API do PNCP.
     * @param array $filters Filtros.
     * @return array Resultado.
     */
    protected function searchInPncp(array $filters = []): array
    {
        $sourceKey = 'pncp';
        $sourceConfig = $this->sources[$sourceKey];
        $apiUrl = $sourceConfig['api_endpoint'];

        try {
            $queryParams = [];

            // Mapeamento de filtros para parâmetros da API PNCP
            // (Ajustar conforme documentação oficial quando disponível)
            if (!empty($filters['bidding_number'])) {
                $queryParams['numeroLicitacao'] = $filters['bidding_number'];
            }

            if (!empty($filters['start_date'])) {
                $queryParams['dataPublicacaoInicio'] = $this->parseDateForApi($filters['start_date']);
            }

            if (!empty($filters['end_date'])) {
                $queryParams['dataPublicacaoFim'] = $this->parseDateForApi($filters['end_date']);
            }

            // Limites e paginação (adaptar conforme documentação)
            $queryParams['pagina'] = $filters['page'] ?? 1;
            $queryParams['tamanhoPagina'] = min($filters['limit'] ?? 50, 100);

            Log::info("Consultando API PNCP", ['url' => $apiUrl, 'params' => $queryParams]);

            // Pode ser necessário incluir token de autenticação dependendo da API
            $response = Http::apiClient()
                // ->withToken($token) // Adicionar se necessário
                ->get($apiUrl, $queryParams);

            if (!$response->successful()) {
                Log::error("Falha na requisição à API PNCP", [
                    'status' => $response->status(),
                    'url' => $apiUrl,
                    'params' => $queryParams,
                    'body' => substr($response->body(), 0, 500)
                ]);
                return $this->formatResult(false, "Erro HTTP {$response->status()} ao acessar API PNCP.", 0, []);
            }

            $responseData = $response->json();

            // Valida estrutura da resposta (ajustar conforme formato real)
            if (empty($responseData) || !isset($responseData['licitacoes']) || !is_array($responseData['licitacoes'])) {
                if (isset($responseData['quantidade']) && $responseData['quantidade'] === 0) {
                    Log::info("Nenhum resultado encontrado na API PNCP para os filtros.");
                    return $this->formatResult(true, 'Nenhuma licitação encontrada.', 0, []);
                }

                Log::warning("Formato de resposta inesperado da API PNCP", [
                    'url' => $apiUrl,
                    'params' => $queryParams,
                    'keys' => array_keys($responseData ?? [])
                ]);
                return $this->formatResult(false, 'Formato de resposta inesperado da API PNCP.', 0, []);
            }

            // Formata os itens encontrados
            $licitacoes = $responseData['licitacoes'];
            $results = [];

            foreach ($licitacoes as $item) {
                $formattedItem = $this->formatPncpItem($item);
                if ($formattedItem) {
                    $results[] = $formattedItem;
                }
            }

            Log::info("Resultados da API PNCP processados", ['count' => count($results)]);
            return $this->formatResult(true, 'Consulta PNCP concluída com sucesso.', count($results), $results);

        } catch (RequestException $e) {
            Log::error("Erro de Conexão/HTTP ao consultar API PNCP", [
                'error' => $e->getMessage(),
                'url' => $apiUrl,
                'trace_snippet' => substr($e->getTraceAsString(), 0, 500)
            ]);
            $statusCode = ($e->response instanceof \Illuminate\Http\Client\Response) ? $e->response->status() : 'N/A';
            return $this->formatResult(false, "Erro de conexão ({$statusCode}) ao acessar API PNCP.", 0, []);
        } catch (Exception $e) {
            Log::error("Erro ao consultar API PNCP", [
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace_snippet' => substr($e->getTraceAsString(), 0, 500)
            ]);
            return $this->formatResult(false, 'Erro interno ao processar consulta na API PNCP: '.$e->getMessage(), 0, []);
        }
    }

    /**
     * Formata um item de licitação vindo da API Dados Abertos.
     * @param array $item Dados brutos do item da API.
     * @param string $sourceKey Chave da fonte.
     * @return array|null Item formatado ou null se inválido.
     */
    protected function formatApiItem(array $item, string $sourceKey): ?array
    {
        try {
            $sourceConfig = $this->sources[$sourceKey];
            $formatted = [];

            // Número/Identificador (essencial)
            $formatted['bidding_number'] = (string) ($item['numero_licitacao'] ?? $item['numero_aviso'] ?? $item['identificador'] ?? null);
            if (empty($formatted['bidding_number'])) {
                 Log::warning("Identificador ausente no item da API", ['source' => $sourceKey, 'item_keys' => array_keys($item)]);
                 return null;
            }
            $identifier = $item['identificador'] ?? $item['id'] ?? null; // Usado para URL de detalhe

            // Título e Descrição
            $formatted['title'] = $item['objeto'] ?? 'Objeto não informado';
            $desc = $item['objeto'] ?? '';
            if (!empty($item['informacoes_gerais'])) $desc .= "\n" . $item['informacoes_gerais'];
            if (!empty($item['descricao_objeto'])) $desc .= "\n" . $item['descricao_objeto'];
            $formatted['description'] = trim($desc);

            // Datas
            $formatted['opening_date'] = $this->parseDateTime($item['data_abertura_proposta'] ?? $item['data_publicacao'] ?? $item['data_entrega_proposta'] ?? null);
            $formatted['closing_date'] = $this->parseDateTime($item['data_encerramento'] ?? null);
            $formatted['publication_date'] = $this->parseDateTime($item['data_publicacao'] ?? null);

            // Modalidade e Status
            $formatted['modality'] = $this->mapModalityFromText($item['modalidade'] ?? $item['modalidade_licitacao'] ?? null);
            $formatted['status'] = $this->mapStatusFromText($item['situacao_aviso'] ?? $item['situacao_licitacao'] ?? null);

            // Valor Estimado
            $formatted['estimated_value'] = isset($item['valor_estimado']) && is_numeric($item['valor_estimado'])
                ? (float) $item['valor_estimado'] : null;

            // URL da Fonte (detalhe)
            $formatted['url_source'] = null;
            if ($identifier && !empty($sourceConfig['detail_api_endpoint_pattern'])) {
                 $formatted['url_source'] = sprintf(rtrim($sourceConfig['detail_api_endpoint_pattern'], '/'), $identifier);
             } elseif (!empty($item['_links']['self']['href'])) {
                 $formatted['url_source'] = $item['_links']['self']['href']; // Link direto da API, se houver
             }

            // Informações da Fonte
            $formatted['source'] = $sourceKey;
            $formatted['source_name'] = $sourceConfig['name'];

            return $formatted;

        } catch (Exception $e) {
            Log::error("Erro ao formatar item da API", ['error' => $e->getMessage(), 'source' => $sourceKey, 'item_snippet' => json_encode(array_slice($item, 0, 3))]);
            return null;
        }
    }

    /**
     * Formata um item de licitação da API PNCP.
     * @param array $item Dados brutos do item da API PNCP.
     * @return array|null Item formatado ou null se inválido.
     */
    protected function formatPncpItem(array $item): ?array
    {
        try {
            $sourceKey = 'pncp';
            $sourceConfig = $this->sources[$sourceKey];
            $formatted = [];

            // Número/Identificador (essencial)
            $formatted['bidding_number'] = (string) ($item['numeroLicitacao'] ?? $item['id'] ?? null);
            if (empty($formatted['bidding_number'])) {
                 Log::warning("Identificador ausente no item da API PNCP", ['item_keys' => array_keys($item)]);
                 return null;
            }

            $identifier = $item['id'] ?? $item['numeroSequencial'] ?? null;

            // Título e Descrição
            $formatted['title'] = $item['objeto'] ?? $item['titulo'] ?? 'Objeto não informado';
            $desc = $item['objeto'] ?? $item['descricao'] ?? '';
            if (!empty($item['informacoesGerais'])) $desc .= "\n" . $item['informacoesGerais'];
            $formatted['description'] = trim($desc);

            // Datas
            $formatted['opening_date'] = $this->parseDateTime($item['dataAbertura'] ?? $item['dataInicioLances'] ?? null);
            $formatted['closing_date'] = $this->parseDateTime($item['dataEncerramento'] ?? $item['dataFechamento'] ?? null);
            $formatted['publication_date'] = $this->parseDateTime($item['dataPublicacao'] ?? null);

            // Modalidade e Status
            $formatted['modality'] = $this->mapModalityFromText($item['modalidade'] ?? null);
            $formatted['status'] = $this->mapStatusFromText($item['situacao'] ?? $item['status'] ?? null);

            // Valor Estimado
            $formatted['estimated_value'] = isset($item['valorEstimado']) && is_numeric($item['valorEstimado'])
                ? (float) $item['valorEstimado'] : null;

            // URL da Fonte (detalhe)
            $formatted['url_source'] = null;
            if ($identifier && !empty($sourceConfig['detail_api_endpoint_pattern'])) {
                 $formatted['url_source'] = sprintf(rtrim($sourceConfig['detail_api_endpoint_pattern'], '/'), $identifier);
             } elseif (!empty($item['urlDetalhes'])) {
                 $formatted['url_source'] = $item['urlDetalhes']; // Link direto da API, se houver
             }

            // Informações da Fonte
            $formatted['source'] = $sourceKey;
            $formatted['source_name'] = $sourceConfig['name'];

            return $formatted;

        } catch (Exception $e) {
            Log::error("Erro ao formatar item da API PNCP", ['error' => $e->getMessage(), 'item_snippet' => json_encode(array_slice($item, 0, 3))]);
            return null;
        }
    }

    /**
     * Tenta parsear uma string de data/hora em vários formatos comuns.
     * @param string|null $dateString String da data.
     * @return string|null Data formatada (Y-m-d H:i:s) ou null.
     */
    protected function parseDateTime(?string $dateString): ?string
    {
        if (empty($dateString)) return null;

        $dateString = trim($dateString);
        // Formatos comuns a tentar
        $formats = [
            'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP', 'Y-m-d H:i:s', // ISO e DB
            'd/m/Y H:i:s', // Brasil com segundos
            'd/m/Y H:i',   // Brasil sem segundos
            'Y-m-d',       // Apenas data
            'd/m/Y',       // Brasil apenas data
        ];

        foreach ($formats as $format) {
            try {
                // Tenta criar a data a partir do formato
                return Carbon::createFromFormat($format, $dateString)->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Ignora e tenta o próximo formato
                continue;
            }
        }

        // Última tentativa: Deixar o Carbon tentar adivinhar
        try {
             return Carbon::parse($dateString)->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            Log::debug("Falha ao parsear string de data/hora", ['date_string' => $dateString, 'error' => $e->getMessage()]);
            return null; // Não foi possível parsear
        }
    }

    /**
     * Formata uma data especificamente para parâmetros de API (Y-m-d).
     * @param string|null $dateString Data em qualquer formato reconhecível pelo Carbon.
     * @return string|null Data formatada ou null.
     */
    protected function parseDateForApi(?string $dateString): ?string
    {
        if (empty($dateString)) return null;
        try {
            // Tenta parsear e formata para Y-m-d
            return Carbon::parse(trim($dateString))->format('Y-m-d');
        } catch (Exception $e) {
            Log::warning("Formato de data inválido para filtro de API", ['date' => $dateString, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Mapeia texto de status para códigos padronizados.
     * @param string|null $statusText Texto do status.
     * @return string Código padronizado ('pending', 'active', 'finished', 'canceled', 'unknown').
     */
    protected function mapStatusFromText(?string $statusText): string
    {
         if (empty($statusText)) return 'unknown';
         $lowerStatus = mb_strtolower(trim($statusText));

         // Mapeamentos (ajustar conforme os textos reais encontrados)
         if (str_contains($lowerStatus, 'em disputa') || str_contains($lowerStatus, 'sessão pública') || str_contains($lowerStatus, 'em acolhimento') || str_contains($lowerStatus, 'aberto p/ lances')) return 'active';
         if (str_contains($lowerStatus, 'homologad') || str_contains($lowerStatus, 'adjudicad') || str_contains($lowerStatus, 'contrato assinado') || $lowerStatus === 'concluída') return 'finished';
         if (str_contains($lowerStatus, 'cancelad') || str_contains($lowerStatus, 'anulad') || str_contains($lowerStatus, 'revogad')) return 'canceled';
         if (str_contains($lowerStatus, 'fracassad') || str_contains($lowerStatus, 'desert')) return 'canceled'; // Falhou
         if (str_contains($lowerStatus, 'suspens')) return 'pending'; // Ou um status específico 'suspended'?
         if (str_contains($lowerStatus, 'publicad') || str_contains($lowerStatus, 'divulgad') || str_contains($lowerStatus, 'agendad') || $lowerStatus === 'a realizar') return 'pending';
         if (str_contains($lowerStatus, 'abert') || str_contains($lowerStatus, 'andamento') || str_contains($lowerStatus, 'em análise') || str_contains($lowerStatus, 'julgamento') ) return 'active'; // Mais genéricos
         if (str_contains($lowerStatus, 'encerrad') || str_contains($lowerStatus, 'finaliza')) return 'finished'; // Mais genéricos

         Log::debug("Texto de status não mapeado encontrado", ['status_text' => $statusText]);
         return 'unknown'; // Padrão se nenhum match
    }

    /**
     * Mapeia texto de modalidade para códigos padronizados.
     * @param string|null $modalityText Texto da modalidade.
     * @return string Código padronizado (ex: 'pregao_eletronico', 'concorrencia', 'unknown').
     */
    protected function mapModalityFromText(?string $modalityText): string
    {
        if (empty($modalityText)) return 'unknown';
        $lowerModality = mb_strtolower(trim($modalityText));

        // Códigos padronizados (alinhar com o Model Bidding e banco de dados)
        if (str_contains($lowerModality, 'pregão') && (str_contains($lowerModality, 'eletrônico') || str_contains($lowerModality, 'eletronico'))) return 'pregao_eletronico';
        if (str_contains($lowerModality, 'pregão') && str_contains($lowerModality, 'presencial')) return 'pregao_presencial';
        if (str_contains($lowerModality, 'concorrência') || str_contains($lowerModality, 'concorrencia')) return 'concorrencia';
        if (str_contains($lowerModality, 'tomada de preço')) return 'tomada_precos';
        if (str_contains($lowerModality, 'convite')) return 'convite';
        if (str_contains($lowerModality, 'leilão') || str_contains($lowerModality, 'leilao')) return 'leilao';
        if (str_contains($lowerModality, 'concurso')) return 'concurso';
        if (str_contains($lowerModality, 'dispensa')) return 'dispensa';
        if (str_contains($lowerModality, 'inexigibilidade')) return 'inexigibilidade';
        if (str_contains($lowerModality, 'rdc') || str_contains($lowerModality, 'regime diferenciado')) return 'rdc';
        if (str_contains($lowerModality, 'credenciamento')) return 'credenciamento';
        if (str_contains($lowerModality, 'diálogo competitivo')) return 'dialogo_competitivo';

        Log::debug("Texto de modalidade não mapeado encontrado", ['modality_text' => $modalityText]);
        return 'unknown'; // Padrão se nenhum match específico
    }

    /**
     * Filtra resultados por palavras-chave de segmento.
     * @param array $results Lista de licitações formatadas.
     * @param string $segmentKey Chave do segmento (ex: 'tecnologia').
     * @return array Lista filtrada.
     */
    protected function filterBySegment(array $results, string $segmentKey): array
    {
        if (!isset($this->segments[$segmentKey])) {
             Log::warning("Tentativa de filtrar por segmento desconhecido", ['segment_key' => $segmentKey]);
             return $results; // Retorna sem filtrar se segmento não existe
         }
         $keywords = $this->segments[$segmentKey]['keywords'] ?? [];
         if (empty($keywords)) {
            Log::debug("Nenhuma palavra-chave definida para o segmento", ['segment_key' => $segmentKey]);
            return $results; // Retorna sem filtrar se não há palavras-chave
         }

         $lowerKeywords = array_map('mb_strtolower', $keywords);

         return array_filter($results, function ($result) use ($lowerKeywords, $segmentKey) {
             // Combina campos relevantes para busca (título e descrição) em minúsculas
             $searchText = mb_strtolower(
                 ($result['title'] ?? '') . ' ' . ($result['description'] ?? '')
             );

             if (empty(trim($searchText))) {
                 return false; // Não pode dar match se não houver texto
             }

             // Verifica se alguma palavra-chave existe no texto
             foreach ($lowerKeywords as $keyword) {
                 if (str_contains($searchText, $keyword)) {
                     return true; // Encontrou match
                 }
             }

             return false; // Nenhuma palavra-chave encontrada
         });
    }

    /**
     * Atualiza os dados de uma licitação buscando informações da fonte API.
     *
     * @param Bidding $bidding Instância do Model Bidding a ser atualizada.
     * @return array Resultado da operação de atualização.
     */
    public function updateBiddingFromSource(Bidding $bidding): array
    {
        // Detecta a fonte
        $sourceKey = $bidding->source ?? $this->detectSourceFromUrl($bidding->url_source);

        // Validação da fonte
        $sourceConfig = $this->sources[$sourceKey] ?? null;
        $status = $sourceConfig['status'] ?? 'invalid';
        if (!$sourceConfig || $status === 'disabled') {
             $reason = 'inválida/não detectada';
             if($sourceConfig) {
                $reason = 'desabilitada';
             }
             $msg = "Não é possível atualizar licitação: fonte {$reason}.";
             Log::warning($msg, ['bidding_id' => $bidding->id, 'source' => $sourceKey]);
             return $this->formatResult(false, $msg, 0, null);
         }

        // Precisa de um identificador (URL ou ID específico da fonte)
        $identifier = $bidding->source_identifier ?? $bidding->url_source;
        if (empty($identifier)) {
            Log::warning("Não é possível atualizar licitação: URL ou identificador da fonte ausente.", ['bidding_id' => $bidding->id]);
            return $this->formatResult(false, 'URL ou identificador da fonte ausente para atualização.', 0, null);
        }

        Log::info("Tentando atualizar licitação via API", [
            'bidding_id' => $bidding->id, 'source' => $sourceKey, 'identifier' => $identifier
        ]);

        // Busca os detalhes mais recentes
        $detailsResult = $this->getBiddingDetails($sourceKey, $identifier);

        if (!$detailsResult['success'] || empty($detailsResult['data'])) {
             Log::warning("Falha ao buscar detalhes para atualização", [
                'bidding_id' => $bidding->id, 'source' => $sourceKey, 'message' => $detailsResult['message'] ?? 'Erro desconhecido'
             ]);
             return $this->formatResult(false, 'Falha ao buscar detalhes da API para atualização: ' . ($detailsResult['message'] ?? 'Erro desconhecido'), 0, null);
        }

        // Compara dados novos com os existentes no Model
        $newData = $detailsResult['data'];
        $updateData = []; // Campos que realmente mudaram

        foreach ($newData as $field => $newValue) {
             // Pula campos que não pertencem diretamente ao Bidding
             if (in_array($field, ['source', 'source_name'])) continue;

             // Verifica se o campo existe no Model Bidding
             if (property_exists($bidding, $field)) {
                $currentValue = $bidding->$field;

                // Normalização para comparação justa
                if ($field === 'estimated_value') {
                     $currentValue = is_numeric($currentValue) ? round((float)$currentValue, 2) : null;
                     $newValue = is_numeric($newValue) ? round((float)$newValue, 2) : null;
                } elseif (in_array($field, ['opening_date', 'closing_date', 'publication_date'])) {
                    // Compara apenas se ambos são datas válidas após parse
                    try {
                         $currentCarbon = $currentValue ? Carbon::parse($currentValue) : null;
                         $newCarbon = $newValue ? Carbon::parse($newValue) : null;

                         if (($currentCarbon === null && $newCarbon !== null) ||
                             ($currentCarbon !== null && $newCarbon === null) ||
                             ($currentCarbon !== null && $newCarbon !== null && !$currentCarbon->eq($newCarbon)))
                         {
                            $updateData[$field] = $newValue;
                         }
                         continue;

                    } catch (Exception $e) {
                         Log::debug("Erro ao comparar datas para atualização", ['field' => $field, 'current' => $currentValue, 'new' => $newValue]);
                         if ($currentValue != $newValue) {
                             $updateData[$field] = $newValue;
                         }
                         continue;
                    }
                } elseif (is_string($currentValue) && is_string($newValue)) {
                    // Compara strings após trim
                     if (trim((string)$currentValue) !== trim((string)$newValue)) {
                         $updateData[$field] = $newValue;
                     }
                     continue;
                }

                // Comparação genérica para outros tipos
                if ($currentValue != $newValue) {
                     Log::debug("Mudança detectada no campo", ['bidding_id' => $bidding->id, 'field' => $field, 'old' => $currentValue, 'new' => $newValue]);
                     $updateData[$field] = $newValue;
                }
             }
        }

        // Atualiza o Model se houver mudanças
        if (!empty($updateData)) {
             $updateData['last_checked_at'] = now(); // Sempre atualiza o timestamp de verificação
             try {
                 $bidding->update($updateData);
                 Log::info("Licitação atualizada com sucesso via API", [
                    'bidding_id' => $bidding->id, 'updated_fields' => array_keys($updateData)
                 ]);
                 return $this->formatResult(true, 'Licitação atualizada com sucesso.', 0, ['updated_fields' => array_keys($updateData)]);
             } catch (Exception $e) {
                  Log::error("Erro ao salvar atualizações da licitação no banco", [
                    'bidding_id' => $bidding->id, 'error' => $e->getMessage()
                 ]);
                  return $this->formatResult(false, 'Erro ao salvar atualizações no banco de dados.', 0, null);
             }
        } else {
             // Se não houve mudanças, apenas atualiza o timestamp de verificação
             try {
                $bidding->forceFill(['last_checked_at' => now()])->saveQuietly();
                Log::info("Nenhuma atualização necessária para a licitação", ['bidding_id' => $bidding->id]);
                return $this->formatResult(true, 'Nenhuma alteração encontrada nos dados.', 0, null);
             } catch (Exception $e) {
                  Log::error("Erro ao atualizar last_checked_at da licitação", [
                    'bidding_id' => $bidding->id, 'error' => $e->getMessage()
                 ]);
                 return $this->formatResult(true, 'Nenhuma alteração encontrada (erro ao atualizar timestamp).', 0, null);
             }
        }
    }

    /**
     * Busca informações detalhadas de uma licitação específica de uma fonte.
     *
     * @param string $sourceKey Chave da fonte.
     * @param string $identifier Identificador da licitação (URL ou ID da fonte).
     * @return array Resultado da busca de detalhes.
     */
    public function getBiddingDetails(string $sourceKey, string $identifier): array
    {
         // Validação da fonte
         $sourceConfig = $this->sources[$sourceKey] ?? null;
         $status = $sourceConfig['status'] ?? 'invalid';
         if (!$sourceConfig || $status === 'disabled') {
             $reason = 'inválida/não detectada';
             if($sourceConfig) {
                $reason = 'desabilitada';
             }
             $msg = "Não é possível buscar detalhes: fonte {$reason}.";
             return $this->formatResult(false, $msg, 0, null);
         }

         // Determina o método para buscar detalhes
         $methodName = 'fetchDetailsFrom' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $sourceKey)));

         // Verifica se o método existe
         if (method_exists($this, $methodName)) {
             try {
                 Log::info("Buscando detalhes da licitação", ['source' => $sourceKey, 'identifier' => $identifier]);
                 return $this->$methodName($identifier);
             } catch (Exception $e) {
                 Log::error("Erro ao buscar detalhes", [
                    'source' => $sourceKey, 'identifier' => $identifier, 'error' => $e->getMessage()
                 ]);
                 return $this->formatResult(false, 'Erro interno ao buscar detalhes: ' . $e->getMessage(), 0, null);
             }
         }

         // Se o método não existe
         $msg = "Busca de detalhes para '{$sourceConfig['name']}' não implementada.";
         Log::warning($msg, ['source' => $sourceKey]);
         return $this->formatResult(false, $msg, 0, null);
    }

    /**
     * Busca detalhes da API Dados Abertos usando o identificador.
     * @param string $identifier ID da licitação na API.
     * @return array Resultado.
     */
    protected function fetchDetailsFromDadosAbertosCompras(string $identifier): array
    {
        $sourceKey = 'dados-abertos-compras';
        $sourceConfig = $this->sources[$sourceKey];
        $detailUrlPattern = $sourceConfig['detail_api_endpoint_pattern'] ?? null;

        if (!$detailUrlPattern) {
             return $this->formatResult(false, "Padrão de URL de detalhe não configurado para {$sourceKey}.", 0, null);
        }

        // Monta a URL da API de detalhe
        if(filter_var($identifier, FILTER_VALIDATE_URL)) {
            $detailUrl = $identifier; // Usa a URL diretamente se for o caso
        } else {
            // Assume que é o ID e monta a URL
            $detailUrl = sprintf(rtrim($sourceConfig['detail_api_endpoint_pattern'], '/'), $identifier);
        }

        try {
            Log::info("Buscando detalhes da API Dados Abertos", ['url' => $detailUrl]);
            $response = Http::apiClient()->get($detailUrl);

            if (!$response->successful()) {
                Log::error("Falha na requisição à API de detalhes Dados Abertos", [
                    'status' => $response->status(), 'url' => $detailUrl
                ]);
                 if ($response->status() == 404) {
                     return $this->formatResult(false, "Detalhes não encontrados na API (404): {$identifier}.", 0, null);
                 }
                return $this->formatResult(false, "Erro HTTP {$response->status()} ao acessar API de detalhes.", 0, null);
            }

            $data = $response->json();
            if (empty($data)) {
                Log::warning("Resposta vazia da API de detalhes Dados Abertos", ['url' => $detailUrl]);
                return $this->formatResult(false, 'Resposta vazia da API de detalhes.', 0, null);
            }

            // Reusa o formatador da API
            $formattedData = $this->formatApiItem($data, $sourceKey);
            if ($formattedData) {
                 return $this->formatResult(true, 'Detalhes da API buscados com sucesso.', 1, $formattedData);
            } else {
                 Log::error("Falha ao formatar dados detalhados da API Dados Abertos", ['identifier' => $identifier]);
                 return $this->formatResult(false, 'Falha ao formatar dados detalhados da API.', 0, null);
            }

        } catch (Exception $e) {
            Log::error("Erro ao buscar/processar detalhes da API Dados Abertos", ['error' => $e->getMessage(), 'identifier' => $identifier]);
            return $this->formatResult(false, 'Erro interno ao processar detalhes da API: '.$e->getMessage(), 0, null);
        }
    }

    /**
     * Busca detalhes da API PNCP.
     * @param string $identifier ID da licitação na API PNCP.
     * @return array Resultado.
     */
    protected function fetchDetailsFromPncp(string $identifier): array
    {
        $sourceKey = 'pncp';
        $sourceConfig = $this->sources[$sourceKey];
        $detailUrlPattern = $sourceConfig['detail_api_endpoint_pattern'] ?? null;

        if (!$detailUrlPattern) {
             return $this->formatResult(false, "Padrão de URL de detalhe não configurado para API PNCP.", 0, null);
        }

        // Monta a URL da API de detalhe
        if(filter_var($identifier, FILTER_VALIDATE_URL)) {
            $detailUrl = $identifier;
        } else {
            $detailUrl = sprintf(rtrim($sourceConfig['detail_api_endpoint_pattern'], '/'), $identifier);
        }

        try {
            Log::info("Buscando detalhes da API PNCP", ['url' => $detailUrl]);

            // Pode ser necessário token de autenticação dependendo da API
            $response = Http::apiClient()
                // ->withToken($token) // Adicionar se necessário
                ->get($detailUrl);

            if (!$response->successful()) {
                Log::error("Falha na requisição à API de detalhes PNCP", [
                    'status' => $response->status(), 'url' => $detailUrl
                ]);
                if ($response->status() == 404) {
                    return $this->formatResult(false, "Detalhes não encontrados na API PNCP (404): {$identifier}.", 0, null);
                }
                return $this->formatResult(false, "Erro HTTP {$response->status()} ao acessar API de detalhes PNCP.", 0, null);
            }

            $data = $response->json();
            if (empty($data)) {
                Log::warning("Resposta vazia da API de detalhes PNCP", ['url' => $detailUrl]);
                return $this->formatResult(false, 'Resposta vazia da API de detalhes PNCP.', 0, null);
            }

            // Formata o item usando o formatador específico do PNCP
            $formattedData = $this->formatPncpItem($data);
            if ($formattedData) {
                 return $this->formatResult(true, 'Detalhes da API PNCP buscados com sucesso.', 1, $formattedData);
            } else {
                 Log::error("Falha ao formatar dados detalhados da API PNCP", ['identifier' => $identifier]);
                 return $this->formatResult(false, 'Falha ao formatar dados detalhados da API PNCP.', 0, null);
            }

        } catch (Exception $e) {
            Log::error("Erro ao buscar/processar detalhes da API PNCP", ['error' => $e->getMessage(), 'identifier' => $identifier]);
            return $this->formatResult(false, 'Erro interno ao processar detalhes da API PNCP: '.$e->getMessage(), 0, null);
        }
    }

    /**
     * Tenta detectar a chave da fonte baseada em padrões na URL.
     * @param string|null $url A URL a ser analisada.
     * @return string|null A chave da fonte detectada ou null.
     */
    protected function detectSourceFromUrl(?string $url): ?string
    {
         if (empty($url)) return null;

         // Mapeia hosts/partes de URL para as chaves das fontes configuradas
         $patterns = [
             'dados-abertos-compras' => ['compras.dados.gov.br', 'gov.br/compras'], // API e Portal
             'pncp' => ['pncp.gov.br', 'api.pncp.gov.br'],
             'bec-sp-api' => ['bec.sp.gov.br', 'bec.fazenda.sp.gov.br'],
         ];

         foreach ($patterns as $key => $hostPatterns) {
            // Verifica se a fonte ainda está ativa/válida na configuração atual
             if (!isset($this->sources[$key]) || $this->sources[$key]['status'] === 'disabled') {
                 continue; // Pula fontes desabilitadas
             }
             foreach ($hostPatterns as $pattern) {
                 if (str_contains($url, $pattern)) {
                     return $key; // Retorna a primeira chave que der match
                 }
             }
         }

         Log::debug("Não foi possível detectar a fonte pela URL", ['url' => $url]);
         return null; // Nenhum padrão conhecido encontrado
    }
}
