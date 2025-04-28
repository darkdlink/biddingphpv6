<?php
// app/Console/Commands/FetchBiddings.php
namespace App\Console\Commands;

use App\Models\Bidding;
use App\Models\Company;
use App\Services\ScrapingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchBiddings extends Command
{
    protected $signature = 'biddings:fetch
                            {--source=* : Fontes para busca (padrão: todas)}
                            {--segment= : Segmento para filtrar (opcional)}
                            {--days=7 : Período em dias para busca}';

    protected $description = 'Busca novas licitações de fontes externas';

    public function handle()
    {
        $sources = $this->option('source');
        $segment = $this->option('segment');
        $days = $this->option('days');

        if (empty($sources)) {
            $sources = 'all'; // Buscar em todas as fontes
        }

        $this->info("Buscando licitações dos últimos {$days} dias...");

        // Inicializar serviço de scraping
        $scrapingService = new ScrapingService();

        // Definir datas para filtro
        $startDate = now()->subDays($days)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'segment' => $segment
        ];

        $this->info("Filtros: " . json_encode($filters));

        // Realizar busca
        $result = $scrapingService->searchBiddings($sources, $filters);

        if (!$result['success']) {
            $this->error($result['message']);
            return 1;
        }

        $this->info("Encontradas " . count($result['data']) . " licitações.");

        // Obter empresa padrão para importação
        $defaultCompany = Company::first();

        if (!$defaultCompany) {
            $this->error("Nenhuma empresa cadastrada para associar às licitações.");
            return 1;
        }

        $imported = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($result['data'] as $biddingData) {
            try {
                // Verificar se já existe
                $exists = Bidding::where('bidding_number', $biddingData['bidding_number'])->exists();

                if (!$exists) {
                    // Criar nova licitação
                    Bidding::create([
                        'bidding_number' => $biddingData['bidding_number'],
                        'title' => $biddingData['title'],
                        'opening_date' => $biddingData['opening_date'] ?? now(),
                        'closing_date' => $biddingData['closing_date'] ?? null,
                        'modality' => $biddingData['modality'],
                        'status' => $biddingData['status'],
                        'url_source' => $biddingData['url_source'] ?? null,
                        'estimated_value' => $biddingData['estimated_value'] ?? null,
                        'description' => $biddingData['description'] ?? null,
                        'company_id' => $defaultCompany->id
                    ]);

                    $imported++;
                    $this->info("Importada: {$biddingData['bidding_number']} - {$biddingData['title']}");
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $this->error("Erro ao importar licitação {$biddingData['bidding_number']}: " . $e->getMessage());
                Log::error("Erro ao importar licitação", [
                    'bidding_number' => $biddingData['bidding_number'],
                    'error' => $e->getMessage()
                ]);
                $errors++;
            }
        }

        $this->info("Importação concluída: {$imported} licitações importadas, {$skipped} ignoradas, {$errors} erros.");

        return 0;
    }
}
