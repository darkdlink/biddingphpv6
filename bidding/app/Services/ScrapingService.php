<?php
// app/Services/ScrapingService.php
namespace App\Services;

use App\Models\Bidding;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\DomCrawler\Crawler;

class ScrapingService
{
    /**
     * Tempo de cache padrão em minutos
     */
    const CACHE_DURATION = 60;

    /**
     * Fontes de licitações configuradas
     *
     * @var array
     */
    protected $sources = [
        'comprasnet' => [
            'name' => 'ComprasNet',
            'url' => 'https://comprasnet.gov.br/livre/pregao/lista_pregao_filtro.asp',
            'type' => 'governo-federal',
            'method' => 'GET',
            'selectors' => [
                'table' => 'table.lista_pregao tr:not(:first-child)',
                'number' => 'td:nth-child(1)',
                'title' => 'td:nth-child(2)',
                'date' => 'td:nth-child(3)',
                'modality' => 'td:nth-child(4)',
                'status' => 'td:nth-child(5)',
                'details_link' => 'td:nth-child(1) a'
            ],
            'details_selectors' => [
                'description' => '#btnSrchObjeto + table td.tex3',
                'opening_date' => 'table.tex3 tr:contains("Data de Realização:") td:last-child',
                'estimated_value' => 'table.tex3 tr:contains("Valor Estimado:") td:last-child'
            ]
        ],
        'licitacoes-e' => [
            'name' => 'Licitações-e (Banco do Brasil)',
            'url' => 'https://www.licitacoes-e.com.br/aop/consultar-detalhes-licitacao.aop',
            'type' => 'banco',
            'method' => 'POST',
            'selectors' => [
                'table' => 'table.lista_licitacao tr:not(:first-child)',
                'number' => 'td:nth-child(1)',
                'title' => 'td:nth-child(2)',
                'date' => 'td:nth-child(3)',
                'status' => 'td:nth-child(4)',
                'details_link' => 'td:nth-child(1) a'
            ]
        ],
        'compras-gov' => [
            'name' => 'Compras Gov',
            'url' => 'https://www.gov.br/compras/pt-br',
            'type' => 'governo-federal'
        ],
        'bec-sp' => [
            'name' => 'BEC-SP (Bolsa Eletrônica de Compras)',
            'url' => 'https://www.bec.sp.gov.br/becsp/aspx/HomePublico.aspx',
            'type' => 'governo-estadual',
            'method' => 'GET',
            'api_url' => 'https://www.bec.sp.gov.br/BECSP/Home/GetOCs'
        ]
    ];

    /**
     * Segmentos de negócio para classificação de licitações
     *
     * @var array
     */
    protected $segments = [
        'tecnologia' => [
            'name' => 'Tecnologia da Informação',
            'keywords' => ['tecnologia', 'software', 'hardware', 'computador', 'servidor', 'rede', 'ti', 'suporte', 'manutenção', 'sistema']
        ],
        'construcao' => [
            'name' => 'Construção Civil',
            'keywords' => ['construção', 'obra', 'engenharia', 'reforma', 'infraestrutura', 'pavimentação', 'edificação']
        ],
        'saude' => [
            'name' => 'Saúde',
            'keywords' => ['saúde', 'hospital', 'médico', 'medicamento', 'enfermagem', 'equipamento hospitalar', 'ambulância']
        ],
        'alimentacao' => [
            'name' => 'Alimentação',
            'keywords' => ['alimento', 'merenda', 'refeição', 'restaurante', 'comida', 'alimentação']
        ],
        'educacao' => [
            'name' => 'Educação',
            'keywords' => ['educação', 'escola', 'ensino', 'professor', 'didático', 'material escolar', 'livro']
        ],
        'servicos' => [
            'name' => 'Serviços Gerais',
            'keywords' => ['serviço', 'limpeza', 'vigilância', 'segurança', 'manutenção', 'conservação']
        ],
        'transporte' => [
            'name' => 'Transporte',
            'keywords' => ['transporte', 'veículo', 'automóvel', 'ônibus', 'combustível', 'frete', 'logística']
        ],
        'mobiliario' => [
            'name' => 'Mobiliário',
            'keywords' => ['mobiliário', 'móvel', 'cadeira', 'mesa', 'armário', 'estante', 'prateleira']
        ]
    ];

    /**
     * Mapeamento de modalidades de licitação
     */
    protected $modalityMap = [
        'pregão eletrônico' => 'pregao_eletronico',
        'pregao eletronico' => 'pregao_eletronico',
        'pregão presencial' => 'pregao_presencial',
        'pregao presencial' => 'pregao_presencial',
        'concorrência' => 'concorrencia',
        'concorrencia' => 'concorrencia',
        'tomada de preço' => 'tomada_precos',
        'tomada de preco' => 'tomada_precos',
        'convite' => 'convite',
        'leilão' => 'leilao',
        'leilao' => 'leilao',
        'concurso' => 'concurso'
    ];

    /**
     * Mapeamento de status de licitação
     */
    protected $statusMap = [
        'aberto' => 'active',
        'abertura' => 'active',
        'em andamento' => 'active',
        'vigente' => 'active',
        'encerrado' => 'finished',
        'finalizado' => 'finished',
        'homologado' => 'finished',
        'adjudicado' => 'finished',
        'suspenso' => 'canceled',
        'cancelado' => 'canceled',
        'revogado' => 'canceled',
        'anulado' => 'canceled',
        'aguardando' => 'pending',
        'futuro' => 'pending',
        'agendado' => 'pending',
        'publicado' => 'pending'
    ];

    /**
     * Headers HTTP padrão para requisições
     */
    protected $defaultHeaders = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7'
    ];

    /**
     * Obtém a lista de fontes disponíveis
     *
     * @return array
     */
    public function getSources()
    {
        return $this->sources;
    }

    /**
     * Obtém a lista de segmentos disponíveis
     *
     * @return array
     */
    public function getSegments()
    {
        return $this->segments;
    }

    /**
     * Busca licitações em uma ou mais fontes
     *
     * @param string|array $source Fonte(s) para busca
     * @param array $filters Filtros de busca
     * @return array Resultado da busca
     */
    public function searchBiddings($source, array $filters = [])
    {
        // Determinar as fontes a serem consultadas
        $sources = $this->getSourcesFromParam($source);

        Log::info('Iniciando busca de licitações', [
            'sources' => $sources,
            'filters' => $filters
        ]);

        // Verificar se pode usar cache
        $cacheKey = $this->generateCacheKey($sources, $filters);
        if (!isset($filters['skip_cache']) && Cache::has($cacheKey)) {
            Log::info('Utilizando dados em cache', ['key' => $cacheKey]);
            return Cache::get($cacheKey);
        }

        // Buscar em cada fonte
        $results = [];
        $errors = [];

        foreach ($sources as $sourceKey) {
            if (!isset($this->sources[$sourceKey])) {
                $errors[] = "Fonte não encontrada: {$sourceKey}";
                continue;
            }

            try {
                $sourceResults = $this->searchInSource($sourceKey, $filters);

                if ($sourceResults['success']) {
                    Log::info("Busca em {$sourceKey} concluída com sucesso", [
                        'count' => count($sourceResults['data'])
                    ]);
                    $results = array_merge($results, $sourceResults['data']);
                } else {
                    $errors[] = $sourceResults['message'];
                    Log::warning("Falha na busca em {$sourceKey}", [
                        'message' => $sourceResults['message']
                    ]);
                }
            } catch (\Exception $e) {
                $errors[] = "Erro ao buscar em {$sourceKey}: " . $e->getMessage();
                Log::error("Exceção ao buscar em {$sourceKey}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // Processar resultados
        $processedResults = $this->processSearchResults($results, $filters);

        // Armazenar em cache
        $response = [
            'success' => !empty($processedResults),
            'message' => empty($processedResults)
                ? 'Nenhuma licitação encontrada. ' . implode(' ', $errors)
                : 'Busca realizada com sucesso.',
            'count' => count($processedResults),
            'data' => $processedResults
        ];

        if (!empty($processedResults)) {
            Cache::put($cacheKey, $response, now()->addMinutes(self::CACHE_DURATION));
        }

        return $response;
    }

    /**
     * Processa e filtra os resultados da busca
     *
     * @param array $results Resultados brutos
     * @param array $filters Filtros a aplicar
     * @return array Resultados processados
     */
    protected function processSearchResults(array $results, array $filters)
    {
        // Aplicar filtro por segmento
        if (!empty($filters['segment'])) {
            $results = $this->filterBySegment($results, $filters['segment']);
        }

        // Aplicar outros filtros específicos
        if (!empty($filters['bidding_number'])) {
            $term = strtolower($filters['bidding_number']);
            $results = array_filter($results, function($item) use ($term) {
                return stripos($item['bidding_number'], $term) !== false;
            });
        }

        if (!empty($filters['start_date'])) {
            $startDate = strtotime($filters['start_date']);
            $results = array_filter($results, function($item) use ($startDate) {
                return !empty($item['opening_date']) && strtotime($item['opening_date']) >= $startDate;
            });
        }

        if (!empty($filters['end_date'])) {
            $endDate = strtotime($filters['end_date'] . ' 23:59:59');
            $results = array_filter($results, function($item) use ($endDate) {
                return !empty($item['opening_date']) && strtotime($item['opening_date']) <= $endDate;
            });
        }

        // Limitar quantidade de resultados
        $limit = $filters['limit'] ?? 50;
        if (count($results) > $limit) {
            $results = array_slice($results, 0, $limit);
        }

        // Ordenar resultados (mais recentes primeiro)
        usort($results, function($a, $b) {
            if (empty($a['opening_date']) && empty($b['opening_date'])) return 0;
            if (empty($a['opening_date'])) return 1;
            if (empty($b['opening_date'])) return -1;
            return strtotime($b['opening_date']) - strtotime($a['opening_date']);
        });

        return array_values($results); // Reindexar array
    }

    /**
     * Filtra resultados por segmento de negócio
     *
     * @param array $results Resultados de busca
     * @param string $segment Código do segmento
     * @return array Resultados filtrados
     */
    protected function filterBySegment(array $results, string $segment)
    {
        if (!isset($this->segments[$segment])) {
            return $results;
        }

        $keywords = $this->segments[$segment]['keywords'];

        return array_filter($results, function($result) use ($keywords) {
            $text = strtolower($result['title'] . ' ' . ($result['description'] ?? ''));

            foreach ($keywords as $keyword) {
                if (stripos($text, $keyword) !== false) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Busca licitações em uma fonte específica
     *
     * @param string $source Código da fonte
     * @param array $filters Filtros a aplicar
     * @return array Resultado da busca
     */
    protected function searchInSource(string $source, array $filters = [])
    {
        $methodName = 'searchIn' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $source)));

        if (method_exists($this, $methodName)) {
            return $this->$methodName($filters);
        }

        // Se não houver método específico, tenta método genérico
        return $this->searchGeneric($source, $filters);
    }

    /**
     * Método genérico para fontes não implementadas
     *
     * @param string $source Código da fonte
     * @param array $filters Filtros a aplicar
     * @return array Resultado da busca
     */
    protected function searchGeneric(string $source, array $filters = [])
    {
        Log::info("Utilizando método genérico para fonte", ['source' => $source]);

        return [
            'success' => true,
            'message' => "Fonte '{$source}' não implementada completamente.",
            'data' => []
        ];
    }

    /**
     * Busca licitações no ComprasNet
     *
     * @param array $filters Filtros de busca
     * @return array Resultado da busca
     */
    protected function searchInComprasnet(array $filters = [])
    {
        try {
            $sourceConfig = $this->sources['comprasnet'];
            $params = $this->prepareComprasnetParams($filters);

            Log::info("Buscando no ComprasNet", [
                'url' => $sourceConfig['url'],
                'params' => $params
            ]);

            // Fazer requisição HTTP
            $response = Http::withHeaders($this->defaultHeaders)
                ->timeout(30)
                ->get($sourceConfig['url'], $params);

            if (!$response->successful()) {
                return $this->createErrorResponse(
                    "Falha ao acessar o ComprasNet. Código: {$response->status()}"
                );
            }

            // Processar HTML
            $html = $response->body();
            $crawler = new Crawler($html);
            $results = [];

            // Seletores definidos na configuração
            $selectors = $sourceConfig['selectors'];

            $crawler->filter($selectors['table'])->each(function(Crawler $row) use (&$results, $selectors, $sourceConfig) {
                try {
                    // Extrair dados da linha
                    $biddingNumber = $this->extractText($row, $selectors['number']);
                    $title = $this->extractText($row, $selectors['title']);
                    $dateText = $this->extractText($row, $selectors['date']);
                    $modality = $this->extractText($row, $selectors['modality']);
                    $status = $this->extractText($row, $selectors['status']);

                    // Processar data
                    $openingDate = $this->parseDate($dateText);

                    // Extrair URL de detalhes
                    $detailsUrl = $this->extractLink($row, $selectors['details_link'], $sourceConfig['url']);

                    // Normalizar valores
                    $normalizedModality = $this->normalizeModality($modality);
                    $normalizedStatus = $this->normalizeStatus($status);

                    // Adicionar ao resultado
                    $results[] = [
                        'bidding_number' => $biddingNumber,
                        'title' => $title,
                        'opening_date' => $openingDate,
                        'modality' => $normalizedModality,
                        'status' => $normalizedStatus,
                        'url_source' => $detailsUrl,
                        'source' => 'comprasnet',
                        'source_name' => $sourceConfig['name']
                    ];
                } catch (\Exception $e) {
                    Log::warning("Erro ao processar linha do ComprasNet", [
                        'error' => $e->getMessage()
                    ]);
                }
            });

            return [
                'success' => true,
                'message' => 'Busca realizada com sucesso no ComprasNet.',
                'data' => $results
            ];
        } catch (\Exception $e) {
            Log::error("Erro ao buscar no ComprasNet", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->createErrorResponse(
                "Erro ao buscar licitações no ComprasNet: {$e->getMessage()}"
            );
        }
    }

    /**
     * Busca licitações na BEC-SP
     *
     * @param array $filters Filtros de busca
     * @return array Resultado da busca
     */
    protected function searchInBecSp(array $filters = [])
    {
        try {
            $sourceConfig = $this->sources['bec-sp'];
            $params = $this->prepareBecSpParams($filters);

            Log::info("Buscando na BEC-SP", [
                'url' => $sourceConfig['api_url'],
                'params' => $params
            ]);

            // A BEC-SP usa uma API JSON
            $response = Http::withHeaders(array_merge(
                $this->defaultHeaders,
                ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']
            ))
            ->timeout(30)
            ->get($sourceConfig['api_url'], $params);

            if (!$response->successful()) {
                return $this->createErrorResponse(
                    "Falha ao acessar a BEC-SP. Código: {$response->status()}"
                );
            }

            // Processar resposta JSON
            $data = $response->json();
            $results = [];

            if (isset($data['registros']) && is_array($data['registros'])) {
                foreach ($data['registros'] as $item) {
                    try {
                        $biddingNumber = $item['codigo'] ?? null;
                        $title = $item['descricao'] ?? null;
                        $openingDate = null;

                        if (isset($item['dataAbertura'])) {
                            $openingDate = date('Y-m-d H:i:s', strtotime($item['dataAbertura']));
                        }

                        $status = $item['situacao'] ?? null;

                        // URL para detalhes
                        $detailsUrl = "https://www.bec.sp.gov.br/BECSP/Pregao/DetalheOC.aspx?chave={$biddingNumber}";

                        // Normalizar status
                        $normalizedStatus = $this->normalizeStatus($status);

                        $results[] = [
                            'bidding_number' => $biddingNumber,
                            'title' => $title,
                            'opening_date' => $openingDate,
                            'modality' => 'pregao_eletronico', // Padrão para BEC-SP
                            'status' => $normalizedStatus,
                            'url_source' => $detailsUrl,
                            'estimated_value' => $item['valorEstimado'] ?? null,
                            'source' => 'bec-sp',
                            'source_name' => $sourceConfig['name']
                        ];
                    } catch (\Exception $e) {
                        Log::warning("Erro ao processar item JSON da BEC-SP", [
                            'error' => $e->getMessage(),
                            'item' => $item
                        ]);
                    }
                }
            }

            return [
                'success' => true,
                'message' => 'Busca realizada com sucesso na BEC-SP.',
                'data' => $results
            ];
        } catch (\Exception $e) {
            Log::error("Erro ao buscar na BEC-SP", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->createErrorResponse(
                "Erro ao buscar licitações na BEC-SP: {$e->getMessage()}"
            );
        }
    }

    /**
     * Atualiza uma licitação a partir de sua fonte original
     *
     * @param Bidding $bidding Objeto da licitação
     * @return array Resultado da operação
     */
    public function updateBiddingFromSource(Bidding $bidding)
    {
        if (empty($bidding->url_source)) {
            return [
                'success' => false,
                'message' => 'URL da fonte não fornecida para esta licitação.'
            ];
        }

        // Identificar a fonte pelo URL
        $source = $this->identifySourceFromUrl($bidding->url_source);

        // Método específico ou genérico
        $methodName = 'update' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $source))) . 'Bidding';

        if (method_exists($this, $methodName)) {
            return $this->$methodName($bidding);
        }

        // Método genérico
        return $this->updateGenericBidding($bidding);
    }

    /**
     * Método genérico para atualizar licitação
     *
     * @param Bidding $bidding Objeto da licitação
     * @return array Resultado da operação
     */
    protected function updateGenericBidding(Bidding $bidding)
    {
        try {
            Log::info("Atualizando licitação via método genérico", [
                'bidding_id' => $bidding->id,
                'bidding_number' => $bidding->bidding_number,
                'url' => $bidding->url_source
            ]);

            $response = Http::withHeaders($this->defaultHeaders)
                ->timeout(30)
                ->get($bidding->url_source);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => "Falha ao acessar a URL da fonte. Código: {$response->status()}"
                ];
            }

            $html = $response->body();
            $crawler = new Crawler($html);
            $updated = false;

            // Campos comuns a extrair
            $fields = [
                'title' => ['h1', '.title', '.bidding-title', '.licitacao-titulo'],
                'description' => ['.description', '.content', '.bidding-description', '.objeto', '.objeto-licitacao'],
                'openingDate' => ['.opening-date', '.date-info', '.data-abertura'],
                'estimatedValue' => ['.estimated-value', '.value-info', '.valor-estimado', '.valor-referencia']
            ];

            // Tentar extrair cada campo
            foreach ($fields as $field => $selectors) {
                $selector = implode(', ', $selectors);
                $value = $this->extractText($crawler, $selector);

                if (!empty($value)) {
                    switch ($field) {
                        case 'title':
                            $bidding->title = $value;
                            $updated = true;
                            break;

                        case 'description':
                            $bidding->description = $value;
                            $updated = true;
                            break;

                        case 'openingDate':
                            $date = $this->parseDate($value);
                            if ($date) {
                                $bidding->opening_date = $date;
                                $updated = true;
                            }
                            break;

                        case 'estimatedValue':
                            $numericValue = $this->extractNumericValue($value);
                            if ($numericValue !== null) {
                                $bidding->estimated_value = $numericValue;
                                $updated = true;
                            }
                            break;
                    }
                }
            }

            if ($updated) {
                $bidding->save();
                return [
                    'success' => true,
                    'message' => 'Licitação atualizada com sucesso via scraping.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Nenhuma informação útil encontrada para atualizar a licitação.'
                ];
            }
        } catch (\Exception $e) {
            Log::error("Erro ao atualizar licitação via scraping", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => "Erro ao realizar scraping: {$e->getMessage()}"
            ];
        }
    }

    /**
     * Método específico para atualizar licitação do ComprasNet
     *
     * @param Bidding $bidding Objeto da licitação
     * @return array Resultado da operação
     */
    protected function updateComprasnetBidding(Bidding $bidding)
    {
        try {
            Log::info("Atualizando licitação do ComprasNet", [
                'bidding_id' => $bidding->id,
                'url' => $bidding->url_source
            ]);

            $response = Http::withHeaders($this->defaultHeaders)
                ->timeout(30)
                ->get($bidding->url_source);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => "Falha ao acessar a URL da fonte. Código: {$response->status()}"
                ];
            }

            $html = $response->body();
            $crawler = new Crawler($html);
            $updated = false;
            $sourceConfig = $this->sources['comprasnet'];
            $selectors = $sourceConfig['details_selectors'];

            // Extrair descrição
            $description = $this->extractText($crawler, $selectors['description']);
            if (!empty($description)) {
                $bidding->description = $description;
                $updated = true;
                Log::info("Descrição atualizada", ['bidding_id' => $bidding->id]);
            }

            // Extrair data de abertura
            $openingDateText = $this->extractText($crawler, $selectors['opening_date']);
            if (!empty($openingDateText)) {
                $openingDate = $this->parseDate($openingDateText);
                if ($openingDate) {
                    $bidding->opening_date = $openingDate;
                    $updated = true;
                    Log::info("Data de abertura atualizada", ['bidding_id' => $bidding->id]);
                }
            }

            // Extrair valor estimado
            $estimatedValueText = $this->extractText($crawler, $selectors['estimated_value']);
            if (!empty($estimatedValueText)) {
                $estimatedValue = $this->extractNumericValue($estimatedValueText);
                if ($estimatedValue !== null) {
                    $bidding->estimated_value = $estimatedValue;
                    $updated = true;
                    Log::info("Valor estimado atualizado", ['bidding_id' => $bidding->id]);
                }
            }

            if ($updated) {
                $bidding->save();
                return [
                    'success' => true,
                    'message' => 'Licitação atualizada com sucesso do ComprasNet.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Nenhuma informação útil encontrada para atualizar.'
                ];
            }
        } catch (\Exception $e) {
            Log::error("Erro ao atualizar licitação do ComprasNet", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => "Erro ao atualizar do ComprasNet: {$e->getMessage()}"
            ];
        }
    }

    /*
     * MÉTODOS AUXILIARES
     */

/**
     * Prepara parâmetros para busca no ComprasNet
     */
    protected function prepareComprasnetParams(array $filters)
    {
        $params = [];

        // Código da licitação
        if (!empty($filters['bidding_number'])) {
            $params['numprp'] = $filters['bidding_number'];
        }

        // Datas no formato dd/mm/aaaa
        if (!empty($filters['start_date'])) {
            $params['dt_publ_ini'] = date('d/m/Y', strtotime($filters['start_date']));
        } else {
            $params['dt_publ_ini'] = date('d/m/Y', strtotime('-30 days'));
        }

        if (!empty($filters['end_date'])) {
            $params['dt_publ_fim'] = date('d/m/Y', strtotime($filters['end_date']));
        } else {
            $params['dt_publ_fim'] = date('d/m/Y');
        }

        return $params;
    }

    /**
     * Prepara parâmetros para busca na BEC-SP
     */
    protected function prepareBecSpParams(array $filters)
    {
        $params = [
            'chave' => '',
            'paginaAtual' => '1',
            'tamanhoPagina' => '20',
            'orientacao' => 'descendente',
            'colunaOrdenacao' => 'DataPublicacao'
        ];

        if (!empty($filters['bidding_number'])) {
            $params['chave'] = $filters['bidding_number'];
        }

        if (!empty($filters['start_date'])) {
            $params['dtPublicacaoInicio'] = date('d/m/Y', strtotime($filters['start_date']));
        }

        if (!empty($filters['end_date'])) {
            $params['dtPublicacaoFim'] = date('d/m/Y', strtotime($filters['end_date']));
        }

        return $params;
    }

    /**
     * Identifica a fonte a partir de uma URL
     */
    protected function identifySourceFromUrl(string $url)
    {
        foreach ($this->sources as $key => $sourceData) {
            if (strpos($url, parse_url($sourceData['url'], PHP_URL_HOST)) !== false) {
                return $key;
            }
        }

        return 'generic';
    }

    /**
     * Determina as fontes a partir do parâmetro fornecido
     */
    protected function getSourcesFromParam($source)
    {
        if ($source === 'all') {
            return array_keys($this->sources);
        }

        return is_array($source) ? $source : [$source];
    }

    /**
     * Gera uma chave de cache baseada nos parâmetros de busca
     */
    protected function generateCacheKey($sources, $filters)
    {
        $sourceString = is_array($sources) ? implode('-', $sources) : $sources;
        $filterString = md5(json_encode($filters));

        return "bidding_search_{$sourceString}_{$filterString}";
    }

    /**
     * Cria uma resposta de erro padronizada
     */
    protected function createErrorResponse($message)
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => []
        ];
    }

    /**
     * Extrai texto de um elemento usando um seletor CSS
     */
    protected function extractText(Crawler $crawler, $selector)
    {
        try {
            $node = $crawler->filter($selector);
            if ($node->count() > 0) {
                return trim($node->text());
            }
        } catch (\Exception $e) {
            // Silencia erros de seletor não encontrado
        }

        return null;
    }

    /**
     * Extrai um link de um elemento usando um seletor CSS
     */
    protected function extractLink(Crawler $crawler, $selector, $baseUrl = '')
    {
        try {
            $node = $crawler->filter($selector);
            if ($node->count() > 0) {
                $href = $node->attr('href');

                // Adiciona URL base se for um link relativo
                if (!empty($href) && strpos($href, 'http') !== 0) {
                    $baseUrlParts = parse_url($baseUrl);
                    $baseUrlHost = $baseUrlParts['scheme'] . '://' . $baseUrlParts['host'];

                    if (strpos($href, '/') === 0) {
                        return $baseUrlHost . $href;
                    } else {
                        $basePath = isset($baseUrlParts['path']) ? dirname($baseUrlParts['path']) : '';
                        return $baseUrlHost . $basePath . '/' . $href;
                    }
                }

                return $href;
            }
        } catch (\Exception $e) {
            // Silencia erros de seletor não encontrado
        }

        return null;
    }

    /**
     * Analisa uma string de data e retorna no formato YYYY-MM-DD HH:MM:SS
     */
    protected function parseDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        $formats = [
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d',
            'd.m.Y H:i:s',
            'd.m.Y H:i',
            'd.m.Y'
        ];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, trim($dateString));
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        // Tenta extrair datas de strings mais complexas
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})/', $dateString, $matches)) {
            return "{$matches[3]}-{$matches[2]}-{$matches[1]} {$matches[4]}:{$matches[5]}:00";
        }

        return null;
    }

    /**
     * Extrai um valor numérico (float) de uma string
     */
    protected function extractNumericValue($string)
    {
        if (empty($string)) {
            return null;
        }

        // Remove caracteres não numéricos, exceto vírgula e ponto
        $value = preg_replace('/[^\d,.]/', '', $string);

        // Substitui vírgula por ponto para formato numérico
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Normaliza o tipo de modalidade de licitação
     */
    protected function normalizeModality($modalityText)
    {
        if (empty($modalityText)) {
            return 'outros';
        }

        $modalityText = strtolower(trim($modalityText));

        foreach ($this->modalityMap as $key => $value) {
            if (strpos($modalityText, $key) !== false) {
                return $value;
            }
        }

        return 'outros';
    }

    /**
     * Normaliza o status da licitação
     */
    protected function normalizeStatus($statusText)
    {
        if (empty($statusText)) {
            return 'pending';
        }

        $statusText = strtolower(trim($statusText));

        foreach ($this->statusMap as $key => $value) {
            if (strpos($statusText, $key) !== false) {
                return $value;
            }
        }

        return 'pending';
    }
}
