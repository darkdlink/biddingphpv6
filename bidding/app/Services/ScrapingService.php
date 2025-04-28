<?php
// app/Services/ScrapingService.php
namespace App\Services;

use App\Models\Bidding;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class ScrapingService
{
    // Lista de fontes de licitações disponíveis
    protected $sources = [
        'comprasnet' => [
            'name' => 'ComprasNet',
            'url' => 'https://comprasnet.gov.br/livre/pregao/lista_pregao_filtro.asp',
            'type' => 'governo-federal'
        ],
        'licitacoes-e' => [
            'name' => 'Licitações-e (Banco do Brasil)',
            'url' => 'https://www.licitacoes-e.com.br/aop/consultar-detalhes-licitacao.aop',
            'type' => 'banco'
        ],
        'compras-gov' => [
            'name' => 'Compras Gov',
            'url' => 'https://www.gov.br/compras/pt-br',
            'type' => 'governo-federal'
        ],
        'bec-sp' => [
            'name' => 'BEC-SP (Bolsa Eletrônica de Compras)',
            'url' => 'https://www.bec.sp.gov.br/becsp/aspx/HomePublico.aspx',
            'type' => 'governo-estadual'
        ]
    ];

    // Lista de segmentos de negócio
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
     * Retorna a lista de fontes disponíveis
     *
     * @return array
     */
    public function getSources()
    {
        return $this->sources;
    }

    /**
     * Retorna a lista de segmentos disponíveis
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
     * @return array
     */
    public function searchBiddings($source, $filters = [])
    {
        $results = [];

        // Se "all" for selecionado, usar todas as fontes
        if ($source === 'all') {
            $sources = array_keys($this->sources);
        } else {
            $sources = is_array($source) ? $source : [$source];
        }

        Log::info('Iniciando busca de licitações', [
            'sources' => $sources,
            'filters' => $filters
        ]);

        foreach ($sources as $sourceKey) {
            if (!isset($this->sources[$sourceKey])) {
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
                    Log::warning("Falha na busca em {$sourceKey}", [
                        'message' => $sourceResults['message']
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Erro ao buscar em {$sourceKey}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // Aplicar filtros por segmento se especificado
        if (!empty($filters['segment'])) {
            $results = $this->filterBySegment($results, $filters['segment']);
        }

        // Limitar resultados (opcional)
        $limit = $filters['limit'] ?? 50;
        if (count($results) > $limit) {
            $results = array_slice($results, 0, $limit);
        }

        // Ordenar resultados pela data de abertura (mais recentes primeiro)
        usort($results, function($a, $b) {
            if (empty($a['opening_date']) || empty($b['opening_date'])) {
                return 0;
            }
            return strtotime($b['opening_date']) - strtotime($a['opening_date']);
        });

        return [
            'success' => true,
            'message' => 'Busca realizada com sucesso.',
            'count' => count($results),
            'data' => $results
        ];
    }

    /**
     * Filtra resultados por segmento de negócio
     *
     * @param array $results Resultados de busca
     * @param string $segment Código do segmento
     * @return array
     */
    protected function filterBySegment($results, $segment)
    {
        if (!isset($this->segments[$segment])) {
            return $results;
        }

        $keywords = $this->segments[$segment]['keywords'];

        return array_filter($results, function($result) use ($keywords) {
            $text = strtolower($result['title'] . ' ' . ($result['description'] ?? ''));

            foreach ($keywords as $keyword) {
                if (strpos($text, strtolower($keyword)) !== false) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Busca licitações em uma fonte específica
     *
     * @param string $source Fonte para busca
     * @param array $filters Filtros de busca
     * @return array
     */
    protected function searchInSource($source, $filters = [])
    {
        $method = 'searchIn' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $source)));

        if (method_exists($this, $method)) {
            return $this->$method($filters);
        }

        // Método genérico para fontes não implementadas especificamente
        return $this->searchGeneric($source, $filters);
    }

    /**
     * Busca licitações no ComprasNet (implementação real)
     *
     * @param array $filters Filtros de busca
     * @return array
     */
    protected function searchInComprasnet($filters = [])
    {
        try {
            $baseUrl = $this->sources['comprasnet']['url'];

            // Preparar parâmetros para o ComprasNet
            $params = [];
            if (!empty($filters['bidding_number'])) {
                $params['numprp'] = $filters['bidding_number'];
            }

            // Datas no formato dd/mm/aaaa
            $startDate = null;
            if (!empty($filters['start_date'])) {
                $startDate = date('d/m/Y', strtotime($filters['start_date']));
                $params['dt_publ_ini'] = $startDate;
            } else {
                // Padrão: 30 dias atrás
                $startDate = date('d/m/Y', strtotime('-30 days'));
                $params['dt_publ_ini'] = $startDate;
            }

            $endDate = null;
            if (!empty($filters['end_date'])) {
                $endDate = date('d/m/Y', strtotime($filters['end_date']));
                $params['dt_publ_fim'] = $endDate;
            } else {
                // Padrão: data atual
                $endDate = date('d/m/Y');
                $params['dt_publ_fim'] = $endDate;
            }

            // Construir URL com parâmetros
            $url = $baseUrl;
            if (!empty($params)) {
                $queryString = http_build_query($params);
                $url .= '?' . $queryString;
            }

            Log::info("Buscando no ComprasNet", [
                'url' => $url,
                'params' => $params
            ]);

            // Fazer a requisição HTTP
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7'
            ])
            ->timeout(30)
            ->get($url);

            if (!$response->successful()) {
                Log::warning("Resposta não bem-sucedida do ComprasNet", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return [
                    'success' => false,
                    'message' => 'Falha ao acessar o ComprasNet. Código de status: ' . $response->status(),
                    'data' => []
                ];
            }

            $html = $response->body();
            $crawler = new Crawler($html);

            // Extrair dados da tabela de licitações
            $results = [];

            // No ComprasNet, a tabela de resultados geralmente tem uma classe específica
            // Ajuste o seletor conforme necessário após inspecionar o HTML real
            $crawler->filter('table.lista_pregao tr:not(:first-child)')->each(function(Crawler $row) use (&$results) {
                try {
                    // Extrair campos de cada linha
                    $cells = $row->filter('td');

                    if ($cells->count() >= 5) {
                        $biddingNumber = trim($cells->eq(0)->text());
                        $title = trim($cells->eq(1)->text());

                        // Data no formato dd/mm/aaaa hh:mm
                        $openingDateText = trim($cells->eq(2)->text());
                        $openingDate = null;

                        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})/', $openingDateText, $matches)) {
                            $openingDate = "{$matches[3]}-{$matches[2]}-{$matches[1]} {$matches[4]}:{$matches[5]}:00";
                        }

                        $modality = trim($cells->eq(3)->text());
                        $status = trim($cells->eq(4)->text());

                        // Extrair link para detalhes
                        $detailsUrl = null;
                        $linkNode = $cells->eq(0)->filter('a');
                        if ($linkNode->count() > 0) {
                            $href = $linkNode->attr('href');
                            // Construir URL completa
                            if (strpos($href, 'http') !== 0) {
                                $detailsUrl = 'https://comprasnet.gov.br/livre/pregao/' . $href;
                            } else {
                                $detailsUrl = $href;
                            }
                        }

                        // Normalizar valores
                        $normalizedModality = $this->normalizeModality($modality);
                        $normalizedStatus = $this->normalizeStatus($status);

                        $results[] = [
                            'bidding_number' => $biddingNumber,
                            'title' => $title,
                            'opening_date' => $openingDate,
                            'modality' => $normalizedModality,
                            'status' => $normalizedStatus,
                            'url_source' => $detailsUrl,
                            'source' => 'comprasnet',
                            'source_name' => 'ComprasNet'
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning("Erro ao processar linha da tabela no ComprasNet", [
                        'error' => $e->getMessage()
                    ]);
                }
            });

            Log::info("Extração de dados do ComprasNet concluída", [
                'count' => count($results)
            ]);

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

            return [
                'success' => false,
                'message' => 'Erro ao buscar licitações no ComprasNet: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Busca licitações no Licitações-e do Banco do Brasil (implementação real)
     *
     * @param array $filters Filtros de busca
     * @return array
     */
    protected function searchInLicitacoesE($filters = [])
    {
        try {
            $baseUrl = 'https://www.licitacoes-e.com.br/aop/pesquisar-licitacao.aop';

            // Preparar parâmetros para o Licitações-e
            $params = [
                'opcao' => 'numeroLicitacao'
            ];

            if (!empty($filters['bidding_number'])) {
                $params['numeroLicitacao'] = $filters['bidding_number'];
            }

            // Outros filtros específicos do Licitações-e
            if (!empty($filters['status'])) {
                $statusMap = [
                    'abertas' => 'A',
                    'encerradas' => 'E',
                    'todas' => 'T'
                ];

                if (isset($statusMap[$filters['status']])) {
                    $params['pesquisarPor'] = $statusMap[$filters['status']];
                }
            }

            Log::info("Buscando no Licitações-e", [
                'url' => $baseUrl,
                'params' => $params
            ]);

            // O Licitações-e utiliza formulário POST em vez de parâmetros GET
            $response = Http::asForm()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
                    'Referer' => 'https://www.licitacoes-e.com.br/'
                ])
                ->timeout(30)
                ->post($baseUrl, $params);

            if (!$response->successful()) {
                Log::warning("Resposta não bem-sucedida do Licitações-e", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return [
                    'success' => false,
                    'message' => 'Falha ao acessar o Licitações-e. Código de status: ' . $response->status(),
                    'data' => []
                ];
            }

            $html = $response->body();
            $crawler = new Crawler($html);

            // Extrair dados da tabela de licitações
            $results = [];

            // No Licitações-e, a tabela geralmente tem um ID ou classe específica
            $crawler->filter('table.lista_licitacao tr:not(:first-child)')->each(function(Crawler $row) use (&$results) {
                try {
                    $cells = $row->filter('td');

                    if ($cells->count() >= 4) {
                        $biddingNumber = trim($cells->eq(0)->text());
                        $title = trim($cells->eq(1)->text());

                        // Data no formato dd/mm/aaaa
                        $openingDateText = trim($cells->eq(2)->text());
                        $openingDate = null;

                        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $openingDateText, $matches)) {
                            $openingDate = "{$matches[3]}-{$matches[2]}-{$matches[1]} 00:00:00";
                        }

                        $status = trim($cells->eq(3)->text());

                        // Extrair link para detalhes
                        $detailsUrl = null;
                        $linkNode = $cells->eq(0)->filter('a');
                        if ($linkNode->count() > 0) {
                            $href = $linkNode->attr('href');
                            // Construir URL completa
                            if (strpos($href, 'http') !== 0) {
                                $detailsUrl = 'https://www.licitacoes-e.com.br/aop/' . $href;
                            } else {
                                $detailsUrl = $href;
                            }
                        }

                        // Normalizar valores
                        $normalizedStatus = $this->normalizeStatus($status);

                        $results[] = [
                            'bidding_number' => $biddingNumber,
                            'title' => $title,
                            'opening_date' => $openingDate,
                            'modality' => 'pregao_eletronico', // O Licitações-e geralmente usa pregão eletrônico
                            'status' => $normalizedStatus,
                            'url_source' => $detailsUrl,
                            'source' => 'licitacoes-e',
                            'source_name' => 'Licitações-e (Banco do Brasil)'
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning("Erro ao processar linha da tabela no Licitações-e", [
                        'error' => $e->getMessage()
                    ]);
                }
            });

            Log::info("Extração de dados do Licitações-e concluída", [
                'count' => count($results)
            ]);

            return [
                'success' => true,
                'message' => 'Busca realizada com sucesso no Licitações-e.',
                'data' => $results
            ];

        } catch (\Exception $e) {
            Log::error("Erro ao buscar no Licitações-e", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao buscar licitações no Licitações-e: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Busca licitações na BEC-SP (implementação real)
     *
     * @param array $filters Filtros de busca
     * @return array
     */
    protected function searchInBecSp($filters = [])
    {
        try {
            $baseUrl = 'https://www.bec.sp.gov.br/BECSP/Home/GetOCs';

            // Preparar parâmetros para a BEC-SP
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
                $startDate = date('d/m/Y', strtotime($filters['start_date']));
                $params['dtPublicacaoInicio'] = $startDate;
            }

            if (!empty($filters['end_date'])) {
                $endDate = date('d/m/Y', strtotime($filters['end_date']));
                $params['dtPublicacaoFim'] = $endDate;
            }

            Log::info("Buscando na BEC-SP", [
                'url' => $baseUrl,
                'params' => $params
            ]);

            // A BEC-SP geralmente retorna dados JSON em vez de HTML
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => 'https://www.bec.sp.gov.br/BECSP/Home/Home.aspx'
            ])
            ->timeout(30)
            ->get($baseUrl, $params);

            if (!$response->successful()) {
                Log::warning("Resposta não bem-sucedida da BEC-SP", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return [
                    'success' => false,
                    'message' => 'Falha ao acessar a BEC-SP. Código de status: ' . $response->status(),
                    'data' => []
                ];
            }

            // Processar resposta JSON
            $data = $response->json();
            $results = [];

            if (isset($data['registros']) && is_array($data['registros'])) {
                foreach ($data['registros'] as $item) {
                    try {
                        $biddingNumber = $item['codigo'] ?? null;
                        $title = $item['descricao'] ?? null;

                        // Data no formato ISO ou similar
                        $openingDate = null;
                        if (isset($item['dataAbertura'])) {
                            $openingDate = date('Y-m-d H:i:s', strtotime($item['dataAbertura']));
                        }

                        $status = $item['situacao'] ?? null;

                        // Construir URL para detalhes
                        $detailsUrl = "https://www.bec.sp.gov.br/BECSP/Pregao/DetalheOC.aspx?chave={$biddingNumber}";

                        // Normalizar valores
                        $normalizedStatus = $this->normalizeStatus($status);

                        $results[] = [
                            'bidding_number' => $biddingNumber,
                            'title' => $title,
                            'opening_date' => $openingDate,
                            'modality' => 'pregao_eletronico', // A BEC-SP geralmente usa pregão eletrônico
                            'status' => $normalizedStatus,
                            'url_source' => $detailsUrl,
                            'estimated_value' => $item['valorEstimado'] ?? null,
                            'source' => 'bec-sp',
                            'source_name' => 'BEC-SP (Bolsa Eletrônica de Compras)'
                        ];
                    } catch (\Exception $e) {
                        Log::warning("Erro ao processar item JSON da BEC-SP", [
                            'error' => $e->getMessage(),
                            'item' => $item
                        ]);
                    }
                }
            }

            Log::info("Extração de dados da BEC-SP concluída", [
                'count' => count($results)
            ]);

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

            return [
                'success' => false,
                'message' => 'Erro ao buscar licitações na BEC-SP: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Método genérico para fontes não implementadas especificamente
     *
     * @param string $source Fonte para busca
     * @param array $filters Filtros de busca
     * @return array
     */
    protected function searchGeneric($source, $filters = [])
    {
        Log::info("Usando método genérico para fonte não implementada", [
            'source' => $source
        ]);

        // Para fontes não implementadas, retornamos uma lista vazia
        return [
            'success' => true,
            'message' => 'Fonte não implementada completamente: ' . $source,
            'data' => []
        ];
    }

    /**
     * Atualiza as informações de uma licitação a partir de sua fonte original
     *
     * @param Bidding $bidding Objeto da licitação
     * @return array Resultado da operação
     */
    public function updateBiddingFromSource(Bidding $bidding)
    {
        if (!$bidding->url_source) {
            return [
                'success' => false,
                'message' => 'URL da fonte não fornecida para esta licitação.'
            ];
        }

        // Identificar a fonte pelo URL
        $source = null;
        foreach ($this->sources as $key => $sourceData) {
            if (strpos($bidding->url_source, $sourceData['url']) !== false) {
                $source = $key;
                break;
            }
        }

        if (!$source) {
            $source = 'generic'; // Usar método genérico se não identificar a fonte
        }

        $method = 'update' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $source))) . 'Bidding';

        if (method_exists($this, $method)) {
            return $this->$method($bidding);
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

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7'
            ])
            ->timeout(30)
            ->get($bidding->url_source);

            if (!$response->successful()) {
                Log::warning("Resposta não bem-sucedida ao atualizar licitação", [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 200) // Primeiros 200 caracteres apenas
                ]);

                return [
                    'success' => false,
                    'message' => 'Falha ao acessar a URL da fonte. Código de status: ' . $response->status()
                ];
            }

            $html = $response->body();
            $crawler = new Crawler($html);

            // Tentar extrair informações genéricas
            $updated = false;

            // Tentar extrair o título
            $title = $this->extractText($crawler, 'h1, .title, .bidding-title, .licitacao-titulo');
            if ($title && trim($title) !== '') {
                $bidding->title = $title;
                $updated = true;
                Log::info("Título atualizado", ['title' => $title]);
            }

            // Tentar extrair a descrição
            $description = $this->extractText($crawler, '.description, .content, .bidding-description, .objeto, .objeto-licitacao');
            if ($description && trim($description) !== '') {
                $bidding->description = $description;
                $updated = true;
                Log::info("Descrição atualizada", ['length' => strlen($description)]);
            }

            // Tentar extrair a data de abertura
            $openingDateText = $this->extractText($crawler, '.opening-date, .date-info, .data-abertura');
            if ($openingDateText) {
                // Tentar vários formatos de data
                $formats = [
                    'd/m/Y H:i', // 01/01/2023 10:00
                    'd/m/Y', // 01/01/2023
                    'Y-m-d H:i:s', // 2023-01-01 10:00:00
                    'Y-m-d H:i', // 2023-01-01 10:00
                    'Y-m-d', // 2023-01-01
                ];

                foreach ($formats as $format) {
                    $date = \DateTime::createFromFormat($format, trim($openingDateText));
                    if ($date !== false) {
                        $bidding->opening_date = $date->format('Y-m-d H:i:s');
                        $updated = true;
                        Log::info("Data de abertura atualizada", ['date' => $bidding->opening_date]);
                        break;
                    }
                }
            }

            // Tentar extrair o valor estimado
            $estimatedValueText = $this->extractText($crawler, '.estimated-value, .value-info, .valor-estimado, .valor-referencia');
            if ($estimatedValueText) {
                // Remove caracteres não numéricos, exceto vírgula e ponto
                $estimatedValue = preg_replace('/[^\d,.]/', '', $estimatedValueText);
                // Substitui vírgula por ponto para formato numérico
                $estimatedValue = str_replace(',', '.', $estimatedValue);

                if (is_numeric($estimatedValue)) {
                    $bidding->estimated_value = (float) $estimatedValue;
                    $updated = true;
                    Log::info("Valor estimado atualizado", ['value' => $bidding->estimated_value]);
                }
            }

            if ($updated) {
                // Salvar alterações
                $bidding->save();
                Log::info("Licitação atualizada com sucesso", ['bidding_id' => $bidding->id]);

                return [
                    'success' => true,
                    'message' => 'Licitação atualizada com sucesso via scraping.'
                ];
            } else {
                Log::warning("Nenhuma informação útil encontrada para atualizar", ['bidding_id' => $bidding->id]);

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
                'message' => 'Erro ao realizar scraping: ' . $e->getMessage()
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
                'bidding_number' => $bidding->bidding_number,
                'url' => $bidding->url_source
            ]);

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7'
            ])
            ->timeout(30)
            ->get($bidding->url_source);

            if (!$response->successful()) {
                Log::warning("Resposta não bem-sucedida ao atualizar licitação do ComprasNet", [
                    'status' => $response->status()
                ]);

                return [
                    'success' => false,
                    'message' => 'Falha ao acessar a URL da fonte. Código de status: ' . $response->status()
                ];
            }

            $html = $response->body();
            $crawler = new Crawler($html);

            $updated = false;

            // No ComprasNet, o objeto geralmente está em um campo específico
            $description = $this->extractText($crawler, '#btnSrchObjeto + table td.tex3');
            if ($description && trim($description) !== '') {
                $bidding->description = $description;
                $updated = true;
                Log::info("Descrição atualizada do ComprasNet", ['length' => strlen($description)]);
            }

            // Data de abertura geralmente está em uma tabela específica
            $openingDateText = $this->extractText($crawler, 'table.tex3 tr:contains("Data de Realização:") td:last-child');
            if ($openingDateText) {
                // O formato geralmente é dd/mm/aaaa hh:mm
                if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})/', $openingDateText, $matches)) {
                    $openingDate = "{$matches[3]}-{$matches[2]}-{$matches[1]} {$matches[4]}:{$matches[5]}:00";
                    $bidding->opening_date = $openingDate;
                    $updated = true;
                    Log::info("Data de abertura atualizada do ComprasNet", ['date' => $openingDate]);
                }
            }

            // Valor estimado geralmente está em uma tabela específica
            $estimatedValueText = $this->extractText($crawler, 'table.tex3 tr:contains("Valor Estimado:") td:last-child');
            if ($estimatedValueText) {
                // Remove R$ e outros caracteres, mantém apenas números, vírgula e ponto
                $estimatedValue = preg_replace('/[^\d,.]/', '', $estimatedValueText);
                // Substitui vírgula por ponto para formato numérico
                $estimatedValue = str_replace(',', '.', $estimatedValue);

                if (is_numeric($estimatedValue)) {
                    $bidding->estimated_value = (float) $estimatedValue;
                    $updated = true;
                    Log::info("Valor estimado atualizado do ComprasNet", ['value' => $bidding->estimated_value]);
                }
            }

            if ($updated) {
                // Salvar alterações
                $bidding->save();
                Log::info("Licitação do ComprasNet atualizada com sucesso", ['bidding_id' => $bidding->id]);

                return [
                    'success' => true,
                    'message' => 'Licitação atualizada com sucesso via scraping do ComprasNet.'
                ];
            } else {
                Log::warning("Nenhuma informação útil encontrada para atualizar do ComprasNet", ['bidding_id' => $bidding->id]);

                return [
                    'success' => false,
                    'message' => 'Nenhuma informação útil encontrada para atualizar a licitação do ComprasNet.'
                ];
            }
        } catch (\Exception $e) {
            Log::error("Erro ao atualizar licitação do ComprasNet via scraping", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao realizar scraping do ComprasNet: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extrair texto de um elemento usando um seletor CSS
     *
     * @param Crawler $crawler
     * @param string $selector
     * @return string|null
     */
    protected function extractText(Crawler $crawler, $selector)
    {
        try {
            $node = $crawler->filter($selector);
            if ($node->count() > 0) {
                return trim($node->text());
            }
        } catch (\Exception $e) {
            // Silenciar erros de seletor não encontrado
            Log::debug("Seletor não encontrado: {$selector}", [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Normaliza o tipo de modalidade de licitação
     *
     * @param string $modalityText
     * @return string
     */
    protected function normalizeModality($modalityText)
    {
        $modalityText = strtolower($modalityText);

        if (strpos($modalityText, 'pregão eletrônico') !== false || strpos($modalityText, 'pregao eletronico') !== false) {
            return 'pregao_eletronico';
        } else if (strpos($modalityText, 'pregão presencial') !== false || strpos($modalityText, 'pregao presencial') !== false) {
            return 'pregao_presencial';
        } else if (strpos($modalityText, 'concorrência') !== false || strpos($modalityText, 'concorrencia') !== false) {
            return 'concorrencia';
        } else if (strpos($modalityText, 'tomada de preço') !== false || strpos($modalityText, 'tomada de preco') !== false) {
            return 'tomada_precos';
        } else if (strpos($modalityText, 'convite') !== false) {
            return 'convite';
        } else if (strpos($modalityText, 'leilão') !== false || strpos($modalityText, 'leilao') !== false) {
            return 'leilao';
        } else if (strpos($modalityText, 'concurso') !== false) {
            return 'concurso';
        }

        return 'outros';
    }

    /**
     * Normaliza o status da licitação
     *
     * @param string $statusText
     * @return string
     */
    protected function normalizeStatus($statusText)
    {
        $statusText = strtolower($statusText);

        if (strpos($statusText, 'aberto') !== false ||
            strpos($statusText, 'abertura') !== false ||
            strpos($statusText, 'em andamento') !== false ||
            strpos($statusText, 'vigente') !== false) {
            return 'active';
        } else if (strpos($statusText, 'encerrado') !== false ||
                  strpos($statusText, 'finalizado') !== false ||
                  strpos($statusText, 'homologado') !== false ||
                  strpos($statusText, 'adjudicado') !== false) {
            return 'finished';
        } else if (strpos($statusText, 'suspenso') !== false ||
                  strpos($statusText, 'cancelado') !== false ||
                  strpos($statusText, 'revogado') !== false ||
                  strpos($statusText, 'anulado') !== false) {
            return 'canceled';
        } else if (strpos($statusText, 'aguardando') !== false ||
                  strpos($statusText, 'futuro') !== false ||
                  strpos($statusText, 'agendado') !== false ||
                  strpos($statusText, 'publicado') !== false) {
            return 'pending';
        }

        return 'pending'; // Status padrão se não for identificado
    }
}
