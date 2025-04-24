<?php

namespace App\Services;

use App\Models\Bid;
use App\Models\BidCategory;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

class ScraperService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false,
        ]);
    }

    public function scrapeComprasNet()
    {
        try {
            $response = $this->client->get('https://comprasnet.gov.br/ConsultaLicitacoes/ConsLicitacaoPorUf.asp');
            $html = (string) $response->getBody();

            // Aqui implementamos a lógica para extrair dados da página
            // Usando DOM ou regex para extrair licitações

            $extractedBids = $this->extractBidsFromHtml($html);

            foreach ($extractedBids as $bidData) {
                // Verificar se a licitação já existe pelo número
                $existingBid = Bid::where('bid_number', $bidData['bid_number'])->first();

                if (!$existingBid) {
                    // Obter ou criar categoria
                    $category = BidCategory::firstOrCreate(
                        ['name' => $bidData['category']],
                        ['description' => 'Categoria importada automaticamente']
                    );

                    // Criar nova licitação
                    Bid::create([
                        'title' => $bidData['title'],
                        'description' => $bidData['description'],
                        'bid_number' => $bidData['bid_number'],
                        'source_url' => $bidData['url'],
                        'bid_category_id' => $category->id,
                        'estimated_value' => $bidData['estimated_value'] ?? null,
                        'opening_date' => $bidData['opening_date'],
                        'closing_date' => $bidData['closing_date'],
                        'status' => 'Novo',
                        'requirements' => $bidData['requirements'] ?? null,
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => 'Scraped ' . count($extractedBids) . ' bids, ' . count($existingBid ? [] : $extractedBids) . ' new',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error scraping bids: ' . $e->getMessage(),
            ];
        }
    }

    private function extractBidsFromHtml($html)
    {
        // Implementar lógica real de extração com DOM ou regex
        // Esta é uma função de exemplo que simula a extração
        return [
            [
                'title' => 'Aquisição de equipamentos de TI',
                'description' => 'Licitação para aquisição de computadores e periféricos',
                'bid_number' => 'PREGÃO-' . Str::random(8),
                'url' => 'https://comprasnet.gov.br/pregao/123456',
                'category' => 'Tecnologia',
                'estimated_value' => 150000.00,
                'opening_date' => now()->addDays(5),
                'closing_date' => now()->addDays(15),
                'requirements' => 'Empresa com experiência em fornecimento de equipamentos de TI',
            ],
            // Mais licitações extraídas...
        ];
    }
}
