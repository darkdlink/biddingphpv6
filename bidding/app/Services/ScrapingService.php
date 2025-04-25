<?php
// app/Services/ScrapingService.php
namespace App\Services;

use App\Models\Bidding;
use Illuminate\Support\Facades\Http;
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
        'portal-transparencia' => [
            'name' => 'Portal da Transparência',
            'url' => 'https://portaldatransparencia.gov.br/licitacoes',
            'type' => 'governo-federal'
        ],
        'prefeitura-sp' => [
            'name' => 'Prefeitura de São Paulo',
            'url' => 'http://e-negocioscidadesp.prefeitura.sp.gov.br/BuscaLicitacao.aspx',
            'type' => 'governo-municipal'
        ],
        'prefeitura-rj' => [
            'name' => 'Prefeitura do Rio de Janeiro',
            'url' => 'http://ecomprasrio.rio.rj.gov.br/editais/banners',
            'type' => 'governo-municipal'
        ],
        'prefeitura-bh' => [
            'name' => 'Prefeitura de Belo Horizonte',
            'url' => 'https://prefeitura.pbh.gov.br/licitacoes',
            'type' => 'governo-municipal'
        ],
        'governo-mg' => [
            'name' => 'Governo de Minas Gerais',
            'url' => 'https://www.compras.mg.gov.br',
            'type' => 'governo-estadual'
        ],
        'governo-sp' => [
            'name' => 'Governo de São Paulo',
            'url' => 'https://www.bec.sp.gov.br',
            'type' => 'governo-estadual'
        ],
        'petrobras' => [
            'name' => 'Petrobras',
            'url' => 'https://petrobras-fornecedores.com.br',
            'type' => 'empresa-estatal'
        ],
        'bndes' => [
            'name' => 'BNDES',
            'url' => 'https://www.bndes.gov.br/wps/portal/site/home/transparencia/licitacoes-contratos',
            'type' => 'empresa-estatal'
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
        $sources = is_array($source) ? $source : [$source];

        foreach ($sources as $sourceKey) {
            if (!isset($this->sources[$sourceKey])) {
                continue;
            }

            $sourceResults = $this->searchInSource($sourceKey, $filters);
            if ($sourceResults['success']) {
                $results = array_merge($results, $sourceResults['data']);
            }
        }

        // Aplicar filtros por segmento se especificado
        if (!empty($filters['segment'])) {
            $results = $this->filterBySegment($results, $filters['segment']);
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
     * Busca licitações no ComprasNet
     *
     * @param array $filters Filtros de busca
     * @return array
     */
    protected function searchInComprasnet($filters = [])
    {
        try {
            $url = $this->sources['comprasnet']['url'];

            // Preparar parâmetros para o ComprasNet
            $params = [];
            if (!empty($filters['bidding_number'])) {
                $params['numprp'] = $filters['bidding_number'];
            }
            if (!empty($filters['start_date'])) {
                $startDate = is_string($filters['start_date']) ? $filters['start_date'] : $filters['start_date']->format('d/m/Y');
                $params['dt_publ_ini'] = $startDate;
            }
            if (!empty($filters['end_date'])) {
                $endDate = is_string($filters['end_date']) ? $filters['end_date'] : $filters['end_date']->format('d/m/Y');
                $params['dt_publ_fim'] = $endDate;
            }

            // Para fins de demonstração, vamos simular alguns resultados
            // Em um ambiente real, você faria uma solicitação HTTP real
            $results = [
                [
                    'bidding_number' => '10/2023',
                    'title' => 'Aquisição de equipamentos de informática para laboratório de TI',
                    'description' => 'Aquisição de computadores, servidores e equipamentos de rede para montagem de laboratório de TI',
                    'opening_date' => '2023-05-20 10:00:00',
                    'closing_date' => '2023-06-20 18:00:00',
                    'modality' => 'pregao_eletronico',
                    'status' => 'active',
                    'estimated_value' => 500000.00,
                    'url_source' => 'https://comprasnet.gov.br/exemplo/10-2023',
                    'source' => 'comprasnet',
                    'source_name' => 'ComprasNet'
                ],
                [
                    'bidding_number' => '15/2023',
                    'title' => 'Contratação de serviços de limpeza e conservação',
                    'description' => 'Contratação de empresa especializada em serviços de limpeza, asseio e conservação predial com fornecimento de mão de obra',
                    'opening_date' => '2023-05-25 14:00:00',
                    'closing_date' => '2023-06-25 18:00:00',
                    'modality' => 'concorrencia',
                    'status' => 'pending',
                    'estimated_value' => 1200000.00,
                    'url_source' => 'https://comprasnet.gov.br/exemplo/15-2023',
                    'source' => 'comprasnet',
                    'source_name' => 'ComprasNet'
                ],
                [
                    'bidding_number' => '22/2023',
                    'title' => 'Aquisição de medicamentos para hospital universitário',
                    'description' => 'Aquisição de medicamentos e insumos hospitalares para atender às necessidades do Hospital Universitário',
                    'opening_date' => '2023-06-05 09:00:00',
                    'closing_date' => '2023-07-05 18:00:00',
                    'modality' => 'pregao_eletronico',
                    'status' => 'active',
                    'estimated_value' => 800000.00,
                    'url_source' => 'https://comprasnet.gov.br/exemplo/22-2023',
                    'source' => 'comprasnet',
                    'source_name' => 'ComprasNet'
                ],
            ];

            return [
                'success' => true,
                'message' => 'Busca realizada com sucesso no ComprasNet.',
                'data' => $results
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao buscar licitações no ComprasNet: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Busca licitações no Licitações-e do Banco do Brasil
     *
     * @param array $filters Filtros de busca
     * @return array
     */
    protected function searchInLicitacoesE($filters = [])
    {
        try {
            // Simulação de resultados
            $results = [
                [
                    'bidding_number' => '987654',
                    'title' => 'Reforma de escola municipal',
                    'description' => 'Contratação de empresa especializada para reforma geral de escola municipal, incluindo pintura, estrutura elétrica e hidráulica',
                    'opening_date' => '2023-06-10 10:00:00',
                    'closing_date' => '2023-07-10 18:00:00',
                    'modality' => 'pregao_eletronico',
                    'status' => 'active',
                    'estimated_value' => 1500000.00,
                    'url_source' => 'https://licitacoes-e.com.br/exemplo/987654',
                    'source' => 'licitacoes-e',
                    'source_name' => 'Licitações-e (Banco do Brasil)'
                ],
                [
                    'bidding_number' => '987655',
                    'title' => 'Fornecimento de merenda escolar',
                    'description' => 'Aquisição de alimentos para merenda escolar das escolas municipais',
                    'opening_date' => '2023-06-15 09:00:00',
                    'closing_date' => '2023-07-15 18:00:00',
                    'modality' => 'pregao_eletronico',
                    'status' => 'active',
                    'estimated_value' => 900000.00,
                    'url_source' => 'https://licitacoes-e.com.br/exemplo/987655',
                    'source' => 'licitacoes-e',
                    'source_name' => 'Licitações-e (Banco do Brasil)'
                ]
            ];

            return [
                'success' => true,
                'message' => 'Busca realizada com sucesso no Licitações-e.',
                'data' => $results
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao buscar licitações no Licitações-e: ' . $e->getMessage(),
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
            $response = Http::get($bidding->url_source);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Falha ao acessar a URL da fonte. Código de status: ' . $response->status()
                ];
            }

            $html = $response->body();
            $crawler = new Crawler($html);

            // Tentar extrair informações genéricas
            $title = $this->extractText($crawler, 'h1, .title, .bidding-title');
            if ($title) {
                $bidding->title = $title;
            }

            $description = $this->extractText($crawler, '.description, .content, .bidding-description');
            if ($description) {
                $bidding->description = $description;
            }

            // Salvar alterações
            $bidding->save();

            return [
                'success' => true,
                'message' => 'Licitação atualizada com sucesso via scraping.'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao realizar scraping: ' . $e->getMessage()
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
            $node = $crawler->filter($selector)->first();
            if ($node->count() > 0) {
                return trim($node->text());
            }
        } catch (\Exception $e) {
            // Silenciar erros de seletor não encontrado
        }

        return null;
    }
}
