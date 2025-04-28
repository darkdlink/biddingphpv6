<?php

namespace App\Services;

use App\Models\Bidding; // Certifique-se de que o caminho para seu Model Bidding está correto
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
// use Illuminate\Http\Client\Response; // Geralmente não precisa importar diretamente
use Symfony\Component\DomCrawler\Crawler; // Necessário se mantiver algum método de scraping
use Exception;
use Illuminate\Http\Client\RequestException;

/**
 * Serviço Robusto para Busca e Atualização de Licitações.
 * Prioriza APIs oficiais e marca métodos de scraping como frágeis ou inviáveis.
 */
class RobustScrapingService
{
    /**
     * Fontes de licitação configuradas.
     * Status: 'active', 'fragile', 'experimental', 'requires_captcha', 'disabled'
     * Type: 'api', 'scraping'
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $sources = [
        'dados-abertos-compras' => [
            'name' => 'API Dados Abertos Compras Gov (Oficial)', // Prioridade Máxima
            'url' => 'http://compras.dados.gov.br',
            'api_endpoint' => 'http://compras.dados.gov.br/licitacoes/v1/licitacoes.json',
            'detail_api_endpoint_pattern' => 'http://compras.dados.gov.br/licitacoes/doc/licitacao/%s.json', // %s = identificador da licitação na API
            'type' => 'api',
            'status' => 'active', // Essencial que funcione
        ],
        'pncp' => [
            'name' => 'PNCP (Portal Nacional - API Experimental)',
            'url' => 'https://pncp.gov.br/',
            // 'api_endpoint' => 'https://api.pncp.gov.br/v1/...', // Necessita confirmação da API real
            'type' => 'api',
            'status' => 'experimental', // Marcar como não totalmente implementado/verificado
        ],
        // --- ComprasNet Scraping (HTML Legado) - DESABILITADO ---
        'comprasnet-scraping-legacy' => [
            'name' => 'ComprasNet (Scraping HTML - Desabilitado)',
            'search_url' => 'https://cnetmobile.estaleiro.serpro.gov.br/comprasnet-web/public/compras', // URL antiga
            'portal_url' => 'https://www.gov.br/compras/pt-br',
            'type' => 'scraping',
            'status' => 'requires_captcha', // Motivo: Interface web provavelmente usa API interna com captcha
            'notes' => 'Scraping direto inviável devido à provável proteção por Captcha na interface web/API interna.'
        ],
        // --- Outras Fontes de Scraping (Manter com cautela) ---
        'licitacoes-e' => [
            'name' => 'Licitações-e (BB - Scraping)',
            'url' => 'https://www.licitacoes-e.com.br',
            'search_url' => 'https://www.licitacoes-e.com.br/aop/pesquisar-licitacao.aop', // Verificar endpoint
            'type' => 'scraping',
            'status' => 'fragile', // Pode quebrar facilmente, pode ter captcha próprio
        ],
        'bec-sp' => [
            'name' => 'BEC-SP (Scraping)',
            'url' => 'https://www.bec.sp.gov.br',
            'search_url' => 'https://www.bec.fazenda.sp.gov.br/BECSP/Home.aspx', // Verificar entrada/busca
            'type' => 'scraping',
            'status' => 'very_fragile', // Scraping de ASP.NET é complexo e quebradiço
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
        // Adicionar mais segmentos conforme necessário
    ];

    /**
     * Tempo de cache em segundos (padrão: 1 hora). Configurável via .env.
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
        'timeout' => 45, // Tempo total da requisição
        'connect_timeout' => 15, // Tempo para estabelecer conexão
        'verify' => false, // Mudar para true em produção com CAs configurados!
        'headers' => [
            // User agent razoavelmente moderno
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding' => 'gzip, deflate, br',
            'DNT' => '1', // Do Not Track
            'Upgrade-Insecure-Requests' => '1',
        ],
    ];

    /**
     * Construtor
     */
    public function __construct()
    {
        // Permite sobrescrever o tempo de cache via variável de ambiente
        $this->cacheTime = (int) env('SCRAPING_CACHE_TIME', 3600);

        // Configura macro global para cliente HTTP se não existir
        if (!Http::hasMacro('scraperClient')) {
            Http::macro('scraperClient', function () {
                // Garante opções frescas e adiciona retry simples
                return Http::withOptions($this->httpOptions)->retry(2, 1000); // Tenta 3 vezes (1 + 2 retries) com 1s de espera
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
        $allowFragile = env('ALLOW_FRAGILE_SCRAPING', true); // Permite tentar fontes frágeis?
        foreach ($targetSourcesInput as $key) {
            if (!isset($this->sources[$key])) {
                Log::warning("Chave de fonte inválida fornecida: {$key}");
                $messages[] = "Fonte inválida: {$key}";
                continue;
            }

            $sourceConfig = $this->sources[$key];
            $status = $sourceConfig['status'] ?? 'active';

            // Pular fontes explicitamente desabilitadas ou que requerem captcha
            if ($status === 'disabled' || $status === 'requires_captcha') {
                $reason = ($status === 'requires_captcha') ? 'requer captcha' : 'desabilitada';
                Log::info("Pulando fonte '{$key}' ({$sourceConfig['name']}) - Status: {$reason}");
                $skippedSources[] = "{$sourceConfig['name']} (Motivo: {$reason})";
                continue;
            }

            // Pular frágeis/experimentais se configurado para não permitir
            if (($status === 'fragile' || $status === 'very_fragile' || $status === 'experimental') && !$allowFragile) {
                Log::info("Pulando fonte frágil/experimental '{$key}' devido à configuração.");
                $skippedSources[] = "{$sourceConfig['name']} (Motivo: frágil/experimental não permitido)";
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

        Log::info('Iniciando busca de licitações', ['sources_to_try' => $validSourcesToTry, 'filters' => $filters]);

        // Lógica de Cache
        $cacheKey = $this->generateCacheKey($validSourcesToTry, $filters);
        if ($this->cacheTime > 0) {
            $cachedResults = Cache::get($cacheKey);
            // Verifica se o cache existe e se tem a estrutura mínima esperada (array, com ou sem itens)
            if ($cachedResults !== null && is_array($cachedResults) && (!empty($cachedResults) && isset($cachedResults[0]['bidding_number']) || empty($cachedResults)) ) {
                 Log::info('Resultados encontrados no cache', ['cache_key' => $cacheKey, 'count' => count($cachedResults)]);
                 // Adiciona mensagens de fontes puladas mesmo se vier do cache
                 if (!empty($skippedSources)) {
                    $messages = array_merge($messages, ['Fontes puladas (cache): ' . implode(', ', $skippedSources)]);
                 }
                 return $this->formatResult(true, 'Busca concluída com sucesso (do cache).', count($cachedResults), $cachedResults, $messages);
             } elseif ($cachedResults !== null) {
                 // Se o cache existe mas é inválido, remove
                 Log::warning("Dados inválidos encontrados no cache, ignorando e removendo.", ['cache_key' => $cacheKey]);
                 Cache::forget($cacheKey);
             }
        }


        $successfulSourcesCount = 0;
        // Loop pelas fontes válidas *para tentar*
        foreach ($validSourcesToTry as $sourceKey) {
            try {
                Log::info("Buscando na fonte: {$sourceKey}", ['filters' => $filters]);
                $sourceResult = $this->searchInSource($sourceKey, $filters); // Chama o método específico

                if ($sourceResult['success']) {
                    $count = count($sourceResult['data']);
                    Log::info("Busca em {$sourceKey} completada", ['count' => $count]);
                    $allResults = array_merge($allResults, $sourceResult['data']);
                    $successfulSourcesCount++;
                    // Adiciona mensagens de sucesso (exceto as genéricas)
                    if (!empty($sourceResult['message']) && !in_array($sourceResult['message'], ['Query successful.', 'Nenhuma licitação encontrada.'])) {
                        $messages[] = "{$this->sources[$sourceKey]['name']}: {$sourceResult['message']}";
                    }
                } else {
                    // Adiciona mensagens de falha
                    Log::warning("Falha na busca na fonte: {$sourceKey}", ['message' => $sourceResult['message']]);
                    $messages[] = "Falha em {$this->sources[$sourceKey]['name']}: {$sourceResult['message']}";
                }
            } catch (Exception $e) {
                // Adiciona mensagens de erro interno
                Log::error("Erro ao buscar fonte: {$sourceKey}", ['error' => $e->getMessage(), 'trace_snippet' => substr($e->getTraceAsString(), 0, 500)]);
                $messages[] = "Erro interno ao buscar em {$this->sources[$sourceKey]['name']}.";
            }
        }

        // Pós-processamento: deduplicar, filtrar, ordenar, limitar
        $processedResults = $this->postProcessResults($allResults, $filters);
        $finalCount = count($processedResults);

        // Determina mensagem final baseada nos resultados e falhas
        $success = $finalCount > 0 || $successfulSourcesCount > 0; // Sucesso se encontrou algo ou se alguma fonte respondeu sem erro fatal
        $message = $success ? 'Busca concluída.' : 'Nenhum resultado encontrado ou falha em todas as fontes tentadas.';
        if ($successfulSourcesCount > 0 && $successfulSourcesCount < count($validSourcesToTry)) {
            $message = 'Busca parcialmente concluída (algumas fontes falharam ou não retornaram dados).';
        } elseif ($successfulSourcesCount == 0 && count($validSourcesToTry) > 0) {
            $message = 'Falha ao buscar em todas as fontes selecionadas e habilitadas.'; // A mensagem de erro original
        }
         // Adiciona informação sobre fontes puladas à mensagem final
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
            'details' => $details, // Para mensagens de erro específicas de fontes, etc.
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
            if ($number === null) continue; // Pula resultados sem número
            $key = $source . '_' . $number;
            if (!isset($seenKeys[$key])) {
                $uniqueResults[] = $result;
                $seenKeys[$key] = true;
            }
        }
        $results = $uniqueResults;
        if (count($results) < count($results)) { // Log apenas se houve dedup
             Log::info("Resultados deduplicados", ['before' => count($results), 'after' => count($results)]);
        }

        // 2. Filtragem por Segmento (se aplicado)
        if (!empty($filters['segment'])) {
            $initialCount = count($results);
            $results = $this->filterBySegment($results, $filters['segment']);
            Log::info("Filtro por segmento aplicado", ['segment' => $filters['segment'], 'before' => $initialCount, 'after' => count($results)]);
        }

        // 3. Ordenação (por data de abertura, decrescente)
        usort($results, function ($a, $b) {
            $dateA = !empty($a['opening_date']) ? strtotime((string) $a['opening_date']) : 0;
            $dateB = !empty($b['opening_date']) ? strtotime((string) $b['opening_date']) : 0;
            return $dateB <=> $dateA; // Mais recentes primeiro
        });

        // 4. Limite de Resultados
        $limit = isset($filters['limit']) && is_numeric($filters['limit']) ? max(1, (int) $filters['limit']) : 100; // Padrão 100, mínimo 1
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
        sort($sources); // Garante ordem consistente
        ksort($filters); // Garante ordem consistente
        $version = 'v1.2'; // Incrementar se a lógica de busca/formatação mudar
        $keyData = ['v' => $version, 'sources' => $sources, 'filters' => $filters];
        return 'biddings_search_' . md5(json_encode($keyData));
    }


    /**
     * Despacha a busca para o método específico da fonte, verificando o status.
     *
     * @param string $sourceKey Chave da fonte.
     * @param array $filters Filtros.
     * @return array Resultado da busca da fonte específica.
     */
    protected function searchInSource(string $sourceKey, array $filters = []): array
    {
        $methodName = 'searchIn' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $sourceKey)));

        // Verifica se o método existe E se a fonte está habilitada para busca
        $sourceConfig = $this->sources[$sourceKey];
        $status = $sourceConfig['status'] ?? 'active';

        if (method_exists($this, $methodName) && $status !== 'disabled' && $status !== 'requires_captcha') {
            return $this->$methodName($filters);
        }

        // Log e mensagem de erro se o método não existe ou a fonte está desabilitada/requer captcha
        if ($status === 'disabled' || $status === 'requires_captcha') {
             $reason = ($status === 'requires_captcha') ? 'requer captcha' : 'desabilitada';
             $msg = "Busca para '{$sourceConfig['name']}' {$reason} e não pode ser executada.";
             Log::warning("Tentativa de chamar método de busca para fonte '{$sourceKey}' que está {$reason}.");
             return $this->formatResult(false, $msg, 0, []);
        } else {
            $msg = "Funcionalidade de busca para '{$sourceConfig['name']}' não implementada ({$methodName}).";
            Log::warning("Método de busca não implementado para fonte: {$sourceKey} ({$methodName})");
            return $this->formatResult(false, $msg, 0, []);
        }
    }

    // ========================================================================
    // == MÉTODOS DE BUSCA ESPECÍFICOS POR FONTE ==
    // ========================================================================

    /**
     * Busca na API Dados Abertos Compras Gov. (IMPLEMENTAÇÃO PRINCIPAL)
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
            $apiMaxLimit = 500; // Limite documentado da API

            // Mapeia filtros para parâmetros da API
             if (!empty($filters['bidding_number'])) {
                 $queryParams['numero_licitacao'] = $filters['bidding_number'];
                 Log::info("Filtrando API Dados Abertos por número específico", ['number' => $filters['bidding_number']]);
             } else {
                 // Filtros gerais apenas se não for busca por número específico
                 if (!empty($filters['start_date'])) $queryParams['data_publicacao_min'] = $this->parseDateForApi($filters['start_date']);
                 if (!empty($filters['end_date'])) $queryParams['data_publicacao_max'] = $this->parseDateForApi($filters['end_date']);
                 // TODO: Adicionar mapeamento para outros filtros da API se necessário (ex: modalidade, uasg)
             }

            // Paginação
            $queryParams['offset'] = $filters['offset'] ?? 0;
            $queryParams['limit'] = min($filters['limit'] ?? 100, $apiMaxLimit);

            Log::info("Consultando API Dados Abertos", ['url' => $apiUrl, 'params' => $queryParams]);
            $response = Http::scraperClient()->get($apiUrl, $queryParams);

            if (!$response->successful()) {
                Log::error("Falha na requisição à API Dados Abertos", [
                    'status' => $response->status(), 'url' => $apiUrl, 'params' => $queryParams, 'body' => substr($response->body(), 0, 500)
                ]);
                return $this->formatResult(false, "Erro HTTP {$response->status()} ao acessar API Dados Abertos.", 0, []);
            }

            $responseData = $response->json();

            // Validação cuidadosa da estrutura da resposta
            if (empty($responseData) || !isset($responseData['_embedded']['licitacoes']) || !is_array($responseData['_embedded']['licitacoes'])) {
                // Verifica se é um resultado vazio legítimo
                if (isset($responseData['count']) && $responseData['count'] === 0) {
                    Log::info("Nenhum resultado encontrado na API Dados Abertos para os filtros.");
                    return $this->formatResult(true, 'Nenhuma licitação encontrada.', 0, []); // Sucesso, mas sem dados
                }
                // Se não for vazio, mas a estrutura estiver errada
                 Log::warning("Formato de resposta inesperado da API Dados Abertos", [
                    'url' => $apiUrl, 'params' => $queryParams, 'keys' => array_keys($responseData ?? [])
                 ]);
                 return $this->formatResult(false, 'Formato de resposta inesperado da API Dados Abertos.', 0, []);
            }

            // Formata os itens encontrados
            $licitacoes = $responseData['_embedded']['licitacoes'];
            $results = [];
            foreach ($licitacoes as $item) {
                $formattedItem = $this->formatApiItem($item, $sourceKey); // Usa o formatador de API
                if ($formattedItem) {
                    $results[] = $formattedItem;
                }
            }

            Log::info("Resultados da API Dados Abertos processados", ['count' => count($results)]);
            return $this->formatResult(true, 'Query successful.', count($results), $results);

        } catch (RequestException $e) { // Erro específico de rede/HTTP
             Log::error("Erro de Conexão/HTTP ao consultar API Dados Abertos", [
                'error' => $e->getMessage(), 'url' => $apiUrl, 'trace_snippet' => substr($e->getTraceAsString(), 0, 500)
             ]);
             // Tentar extrair o status code se possível
             $statusCode = ($e->response instanceof \Illuminate\Http\Client\Response) ? $e->response->status() : 'N/A';
             return $this->formatResult(false, "Erro de conexão ({$statusCode}) ao acessar API Dados Abertos.", 0, []);
        } catch (Exception $e) { // Outros erros (ex: JSON inválido, erro de lógica)
            Log::error("Erro ao consultar API Dados Abertos", [
                'error' => $e->getMessage(), 'filters' => $filters, 'trace_snippet' => substr($e->getTraceAsString(), 0, 500)
            ]);
            return $this->formatResult(false, 'Erro interno ao processar busca na API Dados Abertos: '.$e->getMessage(), 0, []);
        }
    }

    /**
     * Busca no PNCP. (REQUER IMPLEMENTAÇÃO)
     * @param array $filters Filtros.
     * @return array Resultado.
     */
    protected function searchInPncp(array $filters = []): array
    {
        $sourceKey = 'pncp';
        Log::warning("Busca no PNCP ({$sourceKey}) ainda não implementada.", ['filters' => $filters]);
        // TODO: Implementar usando a API oficial do PNCP, se e quando for descoberta/documentada.
        return $this->formatResult(false, "Busca no PNCP não implementada.", 0, []);
    }

    /**
     * Busca no ComprasNet (Scraping HTML Legado) - Método existe mas retorna erro.
     * @param array $filters Filtros.
     * @return array Resultado indicando falha.
     */
    protected function searchInComprasnetScrapingLegacy(array $filters = []): array
    {
        $sourceKey = 'comprasnet-scraping-legacy';
        $msg = "Scraping direto do ComprasNet ({$this->sources[$sourceKey]['name']}) foi desabilitado devido à provável proteção por Captcha.";
        Log::warning($msg, ['filters' => $filters]);
        return $this->formatResult(false, $msg, 0, []);
    }

    /**
     * Busca no Licitações-e (Scraping). (Frágil)
     * @param array $filters Filtros.
     * @return array Resultado.
     */
    protected function searchInLicitacoesE(array $filters = []): array
    {
        $sourceKey = 'licitacoes-e';
        $sourceConfig = $this->sources[$sourceKey];
        $searchUrl = $sourceConfig['search_url'];
        Log::warning("Executando busca frágil por scraping em Licitações-e ({$sourceKey}) - Requer POST e pode ter Captcha");
         try {
             // === IMPLEMENTAÇÃO DE SCRAPING (Exemplo - PRECISA SER COMPLETADA/VERIFICADA) ===
             // 1. Mapear $filters para os campos do formulário POST do Licitações-e
             $formData = [
                 'numeroLicitacao' => $filters['bidding_number'] ?? '',
                 //'dataPublicacaoInicio' => isset($filters['start_date']) ? Carbon::parse($filters['start_date'])->format('d/m/Y') : '',
                 //'dataPublicacaoFim' => isset($filters['end_date']) ? Carbon::parse($filters['end_date'])->format('d/m/Y') : '',
                 // ... outros campos do formulário ...
                 // ATENÇÃO: Pode precisar de CSRF token, cookies de sessão, etc.
             ];

             // 2. Fazer a requisição POST (pode precisar de GET inicial para cookies/tokens)
             Log::debug("Enviando POST para Licitações-e", ['url' => $searchUrl, 'form_data_keys' => array_keys($formData)]);
             $response = Http::scraperClient()
                 // ->withCookies(...) // Se necessário
                 ->asForm()
                 ->post($searchUrl, array_filter($formData));

             if (!$response->successful()) {
                 Log::error("Falha na requisição ao Licitações-e", ['status' => $response->status(), 'url' => $searchUrl]);
                 return $this->formatResult(false, "Erro HTTP {$response->status()} ao acessar Licitações-e.", 0, []);
             }

             // 3. Parsear o HTML da resposta
             $html = $response->body();
             $crawler = new Crawler($html);

             // 4. Encontrar a tabela/linhas de resultado (ATUALIZAR SELETOR!)
             $rows = $crawler->filter('table#resultado > tbody > tr'); // Exemplo! VERIFICAR NO SITE!
             if ($rows->count() === 0) {
                 if (str_contains(strtolower($html), 'nenhuma licitação encontrada')) {
                     return $this->formatResult(true, 'Nenhuma licitação encontrada.', 0, []);
                 }
                 Log::warning("Seletor de resultados não encontrado no Licitações-e.", ['url' => $searchUrl, 'selector' => 'table#resultado > tbody > tr']);
                 return $this->formatResult(false, 'Falha ao encontrar tabela de resultados (layout mudou?).', 0, []);
             }

             // 5. Iterar e formatar os resultados
             $results = [];
             $rows->each( function (Crawler $row, $i) use (&$results, $sourceKey, $sourceConfig) {
                  try {
                      $cols = $row->filter('td');
                      // Verificar contagem de colunas esperada
                      if ($cols->count() < 5) { // Ajustar conforme a tabela real
                          Log::debug("Linha pulada no Licitações-e: contagem de colunas inesperada.", ['index' => $i, 'count' => $cols->count()]);
                          return;
                      }
                      // Extrair dados brutos das colunas (VERIFICAR ÍNDICES!)
                      $itemData = [
                         'bidding_number_raw' => trim($cols->eq(0)->text('')),
                         'agency_text' => trim($cols->eq(1)->text('')),
                         'title' => trim($cols->eq(2)->text('')),
                         'date_text' => trim($cols->eq(3)->text('')),
                         'situation_text' => trim($cols->eq(4)->text('')),
                         'detail_link_node' => $cols->eq(0)->filter('a')->first(), // Link no número?
                      ];
                      // Formatar usando o helper de scraping
                      $formatted = $this->formatScrapedItem($itemData, $sourceKey);
                      if ($formatted) $results[] = $formatted;
                  } catch (Exception $e) {
                     Log::error("Erro ao processar linha do Licitações-e", ['index' => $i, 'error' => $e->getMessage(), 'html_snippet' => substr($row->html(), 0, 200)]);
                  }
             });

             return $this->formatResult(true, 'Query successful.', count($results), $results);
             // === FIM DA IMPLEMENTAÇÃO DE EXEMPLO ===

         } catch (RequestException $e) {
             Log::error("Erro de Conexão/HTTP ao consultar Licitações-e", ['error' => $e->getMessage(), 'url' => $searchUrl]);
             $statusCode = ($e->response instanceof \Illuminate\Http\Client\Response) ? $e->response->status() : 'N/A';
             return $this->formatResult(false, "Erro de conexão ({$statusCode}) ao acessar Licitações-e.", 0, []);
         } catch (Exception $e) {
             Log::error("Erro ao fazer scraping no Licitações-e", ['error' => $e->getMessage(), 'filters' => $filters]);
             return $this->formatResult(false, 'Erro interno ao processar busca no Licitações-e: '.$e->getMessage(), 0, []);
         }
    }

     /**
     * Busca na BEC-SP (Scraping). (Muito Frágil)
     * @param array $filters Filtros.
     * @return array Resultado indicando falha/não implementado.
     */
    protected function searchInBecSp(array $filters = []): array
    {
        $sourceKey = 'bec-sp';
        $msg = "Busca na BEC-SP ({$this->sources[$sourceKey]['name']}) requer scraping complexo (ASP.NET) e não está implementada de forma robusta.";
        Log::warning($msg, ['filters' => $filters]);
        // TODO: Implementar lógica complexa de scraping ASP.NET (GET inicial, extrair viewstate, POST com campos corretos) se for absolutamente necessário.
        return $this->formatResult(false, $msg, 0, []);
    }


    // ========================================================================
    // == MÉTODOS HELPER (Formatação, Parse, Mapeamento) ==
    // ========================================================================

    /**
     * Formata um item de licitação vindo de uma API.
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
             if (!empty($item['descricao_objeto'])) $desc .= "\n" . $item['descricao_objeto']; // Outro campo comum
            $formatted['description'] = trim($desc);

            // Datas (usar helper robusto)
            $formatted['opening_date'] = $this->parseDateTime($item['data_abertura_proposta'] ?? $item['data_publicacao'] ?? $item['data_entrega_proposta'] ?? null);
            $formatted['closing_date'] = $this->parseDateTime($item['data_encerramento'] ?? null);
            $formatted['publication_date'] = $this->parseDateTime($item['data_publicacao'] ?? null);

            // Modalidade e Status (usar helpers de mapeamento)
            $formatted['modality'] = $this->mapModalityFromText($item['modalidade'] ?? $item['modalidade_licitacao'] ?? null, $sourceKey);
            $formatted['status'] = $this->mapStatusFromText($item['situacao_aviso'] ?? $item['situacao_licitacao'] ?? null);

            // Valor Estimado
            $formatted['estimated_value'] = isset($item['valor_estimado']) && is_numeric($item['valor_estimado'])
                ? (float) $item['valor_estimado'] : null;

            // URL da Fonte (detalhe)
            $formatted['url_source'] = null;
            if ($identifier && !empty($sourceConfig['detail_api_endpoint_pattern'])) {
                 $formatted['url_source'] = sprintf(rtrim($sourceConfig['detail_api_endpoint_pattern'], '/'), $identifier);
                 // Tentar link para versão HTML se existir um padrão conhecido
                 // Ex: $formatted['url_source'] = "http://compras.dados.gov.br/licitacoes/doc/licitacao/{$identifier}.html";
             } elseif (!empty($item['_links']['self']['href'])) {
                 $formatted['url_source'] = $item['_links']['self']['href']; // Link direto da API, se houver
             }

            // Informações da Fonte
            $formatted['source'] = $sourceKey;
            $formatted['source_name'] = $sourceConfig['name'];

            // Incluir dados brutos opcionalmente para depuração
            // $formatted['_raw_api_data'] = $item;

            return $formatted;

        } catch (Exception $e) {
            Log::error("Erro ao formatar item da API", ['error' => $e->getMessage(), 'source' => $sourceKey, 'item_snippet' => json_encode(array_slice($item, 0, 3))]);
            return null;
        }
    }

     /**
     * Formata um item de licitação extraído via scraping de HTML.
      * @param array $itemData Dados brutos extraídos (ex: textos das colunas, nós de link).
      * @param string $sourceKey Chave da fonte.
      * @return array|null Item formatado ou null se inválido.
     */
    protected function formatScrapedItem(array $itemData, string $sourceKey): ?array
    {
        try {
            $sourceConfig = $this->sources[$sourceKey];
            $formatted = [];

            // Número (requer limpeza específica da fonte)
            $biddingNumberRaw = $itemData['bidding_number_raw'] ?? '';
            $formatted['bidding_number'] = $this->cleanBiddingNumber($biddingNumberRaw, $sourceKey);
            if (empty($formatted['bidding_number'])) {
                 Log::debug("Número da licitação ausente ou não parseável do scraping", ['source' => $sourceKey, 'raw' => $biddingNumberRaw]);
                 return null; // Essencial ter o número
            }

            // Título e Descrição (construir com dados disponíveis)
            $formatted['title'] = $itemData['title'] ?? 'Título não extraído';
            $descParts = [];
            if (!empty($itemData['agency_text'])) $descParts[] = "Órgão: " . trim($itemData['agency_text']);
            if (!empty($itemData['uasg'])) $descParts[] = "UASG: " . trim($itemData['uasg']);
            // Adicionar o objeto/título à descrição também
            if(!empty($formatted['title'])) $descParts[] = "Objeto: " . trim($formatted['title']);
            $formatted['description'] = implode(". ", $descParts);


            // Datas (requer parse específico da fonte)
             $formatted['opening_date'] = $this->parseDateTime($itemData['date_text'] ?? null, $sourceKey); // Passa sourceKey para lógica customizada
            $formatted['closing_date'] = null; // Raramente na lista
            $formatted['publication_date'] = null; // Raramente na lista

            // Modalidade e Status
            $formatted['modality'] = $this->mapModalityFromText($itemData['modality_text'] ?? null, $sourceKey); // Pode precisar de texto específico da fonte
            $formatted['status'] = $this->mapStatusFromText($itemData['situation_text'] ?? null);

            // Valor Estimado (Raramente na lista)
            $formatted['estimated_value'] = $this->parseCurrency($itemData['value_text'] ?? null); // Tenta parsear se houver

            // URL da Fonte (requer tratamento de URL relativa/absoluta)
            $formatted['url_source'] = null;
            $linkNode = $itemData['detail_link_node'] ?? null; // Espera um nó Crawler ou null
             if ($linkNode instanceof Crawler && $linkNode->count() > 0) {
                $href = $linkNode->attr('href');
                if ($href) {
                     // Determina URL base (pode ser a URL de busca ou a URL principal do portal)
                     $baseUrl = rtrim($sourceConfig['url'] ?? $sourceConfig['search_url'] ?? '', '/');
                     // Torna a URL absoluta
                     if (!preg_match('/^https?:\/\//i', $href)) { // Verifica se não começa com http:// ou https://
                        // Resolve URL relativa (cuidado com caminhos como ../)
                         if (str_starts_with($href, '/')) {
                            $urlParts = parse_url($baseUrl);
                            $baseUrl = ($urlParts['scheme'] ?? 'https') . '://' . ($urlParts['host'] ?? '');
                            if (!empty($urlParts['port'])) $baseUrl .= ':' . $urlParts['port'];
                         }
                         $formatted['url_source'] = $baseUrl . '/' . ltrim($href, '/');
                         // Simplificação: pode precisar de lógica mais robusta para resolver caminhos relativos complexos.
                     } else {
                         $formatted['url_source'] = $href; // Já é absoluta
                     }
                }
             }

            // Informações da Fonte
            $formatted['source'] = $sourceKey;
            $formatted['source_name'] = $sourceConfig['name'];

            // Incluir dados brutos opcionalmente para depuração
            // $formatted['_raw_scraped_data'] = $itemData;

            return $formatted;

        } catch (Exception $e) {
            Log::error("Erro ao formatar item de scraping", ['error' => $e->getMessage(), 'source' => $sourceKey, 'item_data_keys' => array_keys($itemData)]);
            return null;
        }
    }

    /**
     * Limpa um número de licitação bruto extraído via scraping.
     * @param string|null $rawNumber Número bruto.
     * @param string $sourceKey Chave da fonte para regras específicas.
     * @return string|null Número limpo ou null.
     */
    protected function cleanBiddingNumber(?string $rawNumber, string $sourceKey): ?string
    {
        if (empty($rawNumber)) return null;
        $cleaned = trim($rawNumber);

        // Exemplo de regras específicas (ADICIONAR MAIS SE NECESSÁRIO)
        if ($sourceKey === 'comprasnet-scraping-legacy') { // Embora desabilitado, exemplo permanece
             // Remove UASG inicial: "123456 - 9999/2023" -> "9999/2023"
             $cleaned = preg_replace('/^\d+\s*-\s*/', '', $cleaned);
             // Remove texto em parênteses: "9999/2023 (Processo ...)" -> "9999/2023"
             $cleaned = preg_replace('/\s*\(.*\)\s*$/', '', $cleaned);
        }
        if ($sourceKey === 'licitacoes-e') {
            // Exemplo: Pode ter formato "Pregão Eletrônico Nº 999999"
            $cleaned = preg_replace('/^\D+/i', '', $cleaned); // Remove texto não numérico do início
        }

        // Remove espaços múltiplos
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        return empty($cleaned) ? null : trim($cleaned);
    }

    /**
     * Tenta parsear uma string de data/hora em vários formatos comuns.
     * @param string|null $dateString String da data.
     * @param string|null $sourceKey Chave da fonte para lógicas específicas (opcional).
     * @return string|null Data formatada (Y-m-d H:i:s) ou null.
     */
    protected function parseDateTime(?string $dateString, ?string $sourceKey = null): ?string
    {
        if (empty($dateString)) return null;

        $dateString = trim($dateString);
        // Formatos comuns a tentar (priorizar formatos mais completos ou ISO)
        $formats = [
            'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP', 'Y-m-d H:i:s', // ISO e DB
            'd/m/Y H:i:s', // Brasil com segundos
            'd/m/Y H:i',   // Brasil sem segundos
            'Y-m-d',       // Apenas data
            'd/m/Y',       // Brasil apenas data
        ];

        // Lógica específica da fonte (exemplo)
        if ($sourceKey === 'comprasnet-scraping-legacy' || $sourceKey === 'licitacoes-e') {
            // Tratar "dd/mm/yyyy às hh:mm:ss" ou "dd/mm/yyyy às hh:mm"
             $dateString = str_ireplace([' às ', ' as '], ' ', $dateString);
        }
        // Adicionar mais regras de limpeza/formato específicas aqui se necessário

        foreach ($formats as $format) {
            try {
                // Tenta criar a data a partir do formato
                return Carbon::createFromFormat($format, $dateString)->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Ignora e tenta o próximo formato
                continue;
            }
        }

        // Última tentativa: Deixar o Carbon tentar adivinhar (menos confiável)
        try {
             return Carbon::parse($dateString)->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            Log::debug("Falha ao parsear string de data/hora", ['date_string' => $dateString, 'source' => $sourceKey, 'error' => $e->getMessage()]);
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
         // Mais específicos primeiro
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
     * @param string|null $sourceKey Chave da fonte para lógica específica (opcional).
     * @return string Código padronizado (ex: 'pregao_eletronico', 'concorrencia', 'unknown').
     */
    protected function mapModalityFromText(?string $modalityText, ?string $sourceKey = null): string
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

        // Exemplo: Mapeamento por código numérico da API (se aplicável)
        // if ($sourceKey === 'dados-abertos-compras' && is_numeric($modalityText)) {
        //     $codeMap = [ '5' => 'pregao_eletronico', '6' => 'pregao_presencial', /* ... outros códigos ... */ ];
        //     if (isset($codeMap[$modalityText])) return $codeMap[$modalityText];
        // }

        Log::debug("Texto de modalidade não mapeado encontrado", ['modality_text' => $modalityText, 'source' => $sourceKey]);
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
                 // Usar word boundary (\b) para matches mais precisos (opcional)
                 // if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/u', $searchText)) {
                 //     return true; // Encontrou match de palavra inteira
                 // }
                 if (str_contains($searchText, $keyword)) {
                     return true; // Encontrou match (pode ser substring)
                 }
             }

             return false; // Nenhuma palavra-chave encontrada
         });
    }

     /**
     * Tenta parsear uma string de moeda (ex: "R$ 1.234,56") para float.
      * @param string|null $currencyString String da moeda.
      * @return float|null Valor float ou null se não puder parsear.
     */
    protected function parseCurrency(?string $currencyString): ?float
    {
        if (empty($currencyString)) return null;
        // 1. Remove tudo que NÃO for dígito ou vírgula/ponto decimal
        $cleaned = preg_replace('/[^\d,\.]/', '', $currencyString);
        // 2. Trata casos com ponto de milhar e vírgula decimal (padrão BR)
        if (str_contains($cleaned, ',') && str_contains($cleaned, '.')) {
            $cleaned = str_replace('.', '', $cleaned); // Remove ponto de milhar
            $cleaned = str_replace(',', '.', $cleaned); // Troca vírgula decimal por ponto
        } elseif (str_contains($cleaned, ',')) {
            // Assume que vírgula é decimal se for a única pontuação não numérica
            $cleaned = str_replace(',', '.', $cleaned);
        }
        // 3. Remove qualquer coisa após o segundo ponto decimal (caso raro de erro)
        // $parts = explode('.', $cleaned);
        // if (count($parts) > 2) {
        //     $cleaned = $parts[0] . '.' . $parts[1];
        // }

        // 4. Converte para float se for numérico
        return is_numeric($cleaned) ? (float) $cleaned : null;
    }


    // ========================================================================
    // == DETALHES E ATUALIZAÇÃO DE LICITAÇÃO ==
    // ========================================================================

    /**
     * Atualiza os dados de uma licitação buscando informações frescas da fonte original.
     *
     * @param Bidding $bidding Instância do Model Bidding a ser atualizada.
     * @return array Resultado da operação de atualização.
     */
    public function updateBiddingFromSource(Bidding $bidding): array
    {
        // Detecta a fonte (pelo campo 'source' do Bidding ou pela URL)
        $sourceKey = $bidding->source ?? $this->detectSourceFromUrl($bidding->url_source);

        // Validação da fonte (existe, não está desabilitada, não requer captcha)
        $sourceConfig = $this->sources[$sourceKey] ?? null;
        $status = $sourceConfig['status'] ?? 'invalid';
        if (!$sourceConfig || $status === 'disabled' || $status === 'requires_captcha') {
             $reason = 'inválida/não detectada';
             if($sourceConfig) {
                $reason = ($status === 'requires_captcha') ? 'requer captcha' : 'desabilitada';
             }
             $msg = "Não é possível atualizar licitação: fonte {$reason}.";
             Log::warning($msg, ['bidding_id' => $bidding->id, 'source' => $sourceKey]);
             return $this->formatResult(false, $msg, 0, null);
         }

        // Precisa de um identificador (URL ou ID específico da fonte)
        $identifier = $bidding->source_identifier ?? $bidding->url_source; // Idealmente ter um ID da fonte
        if (empty($identifier)) {
            Log::warning("Não é possível atualizar licitação: URL ou identificador da fonte ausente.", ['bidding_id' => $bidding->id]);
            return $this->formatResult(false, 'URL ou identificador da fonte ausente para atualização.', 0, null);
        }

        Log::info("Tentando atualizar licitação da fonte", [
            'bidding_id' => $bidding->id, 'source' => $sourceKey, 'identifier' => $identifier
        ]);

        // Busca os detalhes mais recentes
        $detailsResult = $this->getBiddingDetails($sourceKey, $identifier);

        if (!$detailsResult['success'] || empty($detailsResult['data'])) {
             Log::warning("Falha ao buscar detalhes para atualização", [
                'bidding_id' => $bidding->id, 'source' => $sourceKey, 'message' => $detailsResult['message'] ?? 'Erro desconhecido'
             ]);
             // Atualiza 'last_checked_at' mesmo em caso de falha para evitar retentativas imediatas? Opcional.
             // $bidding->forceFill(['last_checked_at' => now()])->saveQuietly();
             return $this->formatResult(false, 'Falha ao buscar detalhes da fonte para atualização: ' . ($detailsResult['message'] ?? 'Erro desconhecido'), 0, null);
        }

        // Compara dados novos com os existentes no Model
        $newData = $detailsResult['data'];
        $updateData = []; // Campos que realmente mudaram

        foreach ($newData as $field => $newValue) {
             // Pula campos que não pertencem diretamente ao Bidding ou são gerenciados de outra forma
             if (in_array($field, ['source', 'source_name', '_raw_api_data', '_raw_scraped_data'])) continue;

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

                         // Compara se são diferentes (ou um é null e outro não)
                         if (($currentCarbon === null && $newCarbon !== null) ||
                             ($currentCarbon !== null && $newCarbon === null) ||
                             ($currentCarbon !== null && $newCarbon !== null && !$currentCarbon->eq($newCarbon)))
                         {
                            $updateData[$field] = $newValue; // Usa o valor original novo para update
                         }
                         continue; // Pula a comparação genérica abaixo para datas

                    } catch (Exception $e) {
                         Log::debug("Erro ao comparar datas para atualização", ['field' => $field, 'current' => $currentValue, 'new' => $newValue]);
                         // Se não conseguir parsear, talvez não atualize ou force a atualização? Decidir estratégia.
                         if ($currentValue != $newValue) { // Fallback: compara como string se parse falhar
                             $updateData[$field] = $newValue;
                         }
                         continue;
                    }
                } elseif (is_string($currentValue) && is_string($newValue)) {
                    // Compara strings após trim
                     if (trim((string)$currentValue) !== trim((string)$newValue)) {
                         $updateData[$field] = $newValue;
                     }
                     continue; // Pula a comparação genérica
                }

                // Comparação genérica para outros tipos (int, bool, etc.)
                 // Usar != para permitir comparação entre tipos diferentes (ex: null vs string vazia) se necessário,
                 // mas == é geralmente mais seguro se os tipos deveriam ser consistentes.
                if ($currentValue != $newValue) {
                     Log::debug("Mudança detectada no campo", ['bidding_id' => $bidding->id, 'field' => $field, 'old' => $currentValue, 'new' => $newValue]);
                     $updateData[$field] = $newValue;
                }
             } else {
                 Log::debug("Campo da fonte não encontrado no Model Bidding", ['field' => $field, 'source' => $sourceKey]);
             }
        }

        // Atualiza o Model se houver mudanças
        if (!empty($updateData)) {
             $updateData['last_checked_at'] = now(); // Sempre atualiza o timestamp de verificação
             try {
                 $bidding->update($updateData);
                 Log::info("Licitação atualizada com sucesso da fonte", [
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
                // Usar forceFill + saveQuietly para não disparar eventos de update desnecessários
                $bidding->forceFill(['last_checked_at' => now()])->saveQuietly();
                Log::info("Nenhuma atualização necessária para a licitação baseado nos dados da fonte.", ['bidding_id' => $bidding->id]);
                return $this->formatResult(true, 'Nenhuma alteração encontrada nos dados.', 0, null);
             } catch (Exception $e) {
                  Log::error("Erro ao atualizar last_checked_at da licitação", [
                    'bidding_id' => $bidding->id, 'error' => $e->getMessage()
                 ]);
                  // Não retorna erro fatal, mas loga
                 return $this->formatResult(true, 'Nenhuma alteração encontrada (erro ao atualizar timestamp).', 0, null);
             }
        }
    }

    /**
     * Busca informações detalhadas de uma licitação específica de uma fonte.
     *
     * @param string $sourceKey Chave da fonte.
     * @param string $identifier Identificador da licitação (URL ou ID específico da fonte).
     * @return array Resultado da busca de detalhes.
     */
    public function getBiddingDetails(string $sourceKey, string $identifier): array
    {
         // Validação da fonte (existe e está habilitada para busca de detalhes)
         $sourceConfig = $this->sources[$sourceKey] ?? null;
         $status = $sourceConfig['status'] ?? 'invalid';
         if (!$sourceConfig || $status === 'disabled' || $status === 'requires_captcha') {
             $reason = 'inválida/não detectada';
             if($sourceConfig) {
                $reason = ($status === 'requires_captcha') ? 'requer captcha' : 'desabilitada';
             }
             $msg = "Não é possível buscar detalhes: fonte {$reason}.";
             return $this->formatResult(false, $msg, 0, null);
         }

         // Determina o nome do método para buscar detalhes
         $methodName = 'fetchDetailsFrom' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $sourceKey)));

         // Verifica se o método existe
         if (method_exists($this, $methodName)) {
             try {
                 Log::info("Buscando detalhes da licitação", ['source' => $sourceKey, 'identifier' => $identifier]);
                 // Chama o método específico da fonte, passando o identificador
                 return $this->$methodName($identifier);
             } catch (Exception $e) {
                 Log::error("Erro ao buscar detalhes", [
                    'source' => $sourceKey, 'identifier' => $identifier, 'error' => $e->getMessage(), 'trace_snippet' => substr($e->getTraceAsString(), 0, 500)
                 ]);
                 return $this->formatResult(false, 'Erro interno ao buscar detalhes: ' . $e->getMessage(), 0, null);
             }
         }

         // Se o método não existe
         $msg = "Busca de detalhes para '{$sourceConfig['name']}' não implementada.";
         Log::warning($msg, ['source' => $sourceKey]);
         return $this->formatResult(false, $msg, 0, null);
    }

    // ========================================================================
    // == MÉTODOS PARA BUSCAR DETALHES ESPECÍFICOS POR FONTE ==
    // ========================================================================

    /**
     * Busca detalhes da API Dados Abertos usando o identificador numérico.
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
        // Verifica se o identificador já é uma URL completa (pouco provável para API)
        if(filter_var($identifier, FILTER_VALIDATE_URL)) {
            $detailUrl = $identifier; // Usa a URL diretamente se for o caso
        } else {
             // Assume que é o ID e monta a URL
            $detailUrl = sprintf(rtrim($sourceConfig['detail_api_endpoint_pattern'], '/'), $identifier);
        }


        try {
            Log::info("Buscando detalhes da API Dados Abertos", ['url' => $detailUrl]);
            $response = Http::scraperClient()->get($detailUrl);

            if (!$response->successful()) {
                Log::error("Falha na requisição à API de detalhes Dados Abertos", [
                    'status' => $response->status(), 'url' => $detailUrl, 'body' => substr($response->body(), 0, 300)
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
                 // TODO: Adicionar extração de campos extras disponíveis apenas na API de detalhe, se houver (ex: itens da licitação)
                 // Ex: $formattedData['items'] = $this->extractItemsFromApiDetail($data['itens'] ?? []);
                 return $this->formatResult(true, 'Detalhes da API buscados com sucesso.', 1, $formattedData);
            } else {
                 Log::error("Falha ao formatar dados detalhados da API Dados Abertos", ['identifier' => $identifier]);
                 return $this->formatResult(false, 'Falha ao formatar dados detalhados da API.', 0, null);
            }

        } catch (RequestException $e) {
             Log::error("Erro de Conexão/HTTP ao buscar detalhes da API Dados Abertos", ['error' => $e->getMessage(), 'url' => $detailUrl]);
             $statusCode = ($e->response instanceof \Illuminate\Http\Client\Response) ? $e->response->status() : 'N/A';
             return $this->formatResult(false, "Erro de conexão ({$statusCode}) ao buscar detalhes da API.", 0, null);
        } catch (Exception $e) {
            Log::error("Erro ao buscar/processar detalhes da API Dados Abertos", ['error' => $e->getMessage(), 'identifier' => $identifier]);
            return $this->formatResult(false, 'Erro interno ao processar detalhes da API: '.$e->getMessage(), 0, null);
        }
    }

    // --- Métodos de busca de detalhes para outras fontes (Placeholders) ---

    // O método para 'comprasnet-scraping-legacy' foi efetivamente desabilitado acima
    // ao impedir que getBiddingDetails o chame.

    /**
     * Busca detalhes do Licitações-e (Scraping). (REQUER IMPLEMENTAÇÃO)
     * @param string $url URL da página de detalhes da licitação.
     * @return array Resultado.
     */
    protected function fetchDetailsFromLicitacoesE(string $url): array
    {
        $sourceKey = 'licitacoes-e';
        Log::warning("Busca de detalhes para Licitações-e ({$sourceKey}) via scraping não implementada.", ['url' => $url]);
        // TODO: Implementar scraping da página de detalhes. Atenção a:
        // 1. Possível necessidade de login/sessão/cookies.
        // 2. Estrutura HTML complexa e variável.
        // 3. Possíveis captchas na página de detalhes.
        // 4. Extrair campos detalhados (valor, descrição completa, documentos, etc.).
        // 5. Usar formatScrapedItem ou lógica similar para padronizar.
        return $this->formatResult(false, "Busca de detalhes no Licitações-e (scraping) não implementada.", 0, null);
    }

    /**
     * Busca detalhes da BEC-SP (Scraping). (REQUER IMPLEMENTAÇÃO)
     * @param string $url URL da página de detalhes da OC (Oferta de Compra).
     * @return array Resultado.
     */
    protected function fetchDetailsFromBecSp(string $url): array
    {
        $sourceKey = 'bec-sp';
        Log::warning("Busca de detalhes para BEC-SP ({$sourceKey}) via scraping não implementada.", ['url' => $url]);
        // TODO: Implementar scraping da página de detalhes. MUITO COMPLEXO devido a ASP.NET.
        // 1. Requer gerenciamento de ViewState e EventValidation.
        // 2. Estrutura baseada em controles ASP.NET (GridViews, etc.).
        // 3. Extrair detalhes específicos da BEC.
        return $this->formatResult(false, "Busca de detalhes na BEC-SP (scraping) não implementada.", 0, null);
    }

     /**
     * Busca detalhes do PNCP. (REQUER IMPLEMENTAÇÃO)
     * @param string $identifier ID ou URL da contratação no PNCP.
     * @return array Resultado.
     */
    protected function fetchDetailsFromPncp(string $identifier): array
    {
        $sourceKey = 'pncp';
        Log::warning("Busca de detalhes para PNCP ({$sourceKey}) não implementada.", ['identifier' => $identifier]);
        // TODO: Implementar usando a API oficial do PNCP ou scraping da página de detalhes.
        return $this->formatResult(false, "Busca de detalhes no PNCP não implementada.", 0, null);
    }

    // ========================================================================
    // == DETECÇÃO DE FONTE PELA URL ==
    // ========================================================================

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
             'pncp' => ['pncp.gov.br'],
             'licitacoes-e' => ['licitacoes-e.com.br'],
             'bec-sp' => ['bec.sp.gov.br', 'bec.fazenda.sp.gov.br'],
             // A fonte 'comprasnet-scraping-legacy' não deve ser detectada aqui para update,
             // mas o padrão antigo seria 'comprasnet.gov.br', 'comprasgovernamentais.gov.br', 'cnetmobile.estaleiro.serpro.gov.br/comprasnet-web'
         ];

         foreach ($patterns as $key => $hostPatterns) {
            // Verifica se a fonte ainda está ativa/válida na configuração atual
             if (!isset($this->sources[$key]) || $this->sources[$key]['status'] === 'disabled' || $this->sources[$key]['status'] === 'requires_captcha') {
                 continue; // Pula fontes desabilitadas ou que requerem captcha
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
