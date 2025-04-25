<?php
// app/Console/Commands/FetchBiddings.php
namespace App\Console\Commands;

use App\Models\Bidding;
use App\Models\Company;
use App\Services\ScrapingService;
use Illuminate\Console\Command;

class FetchBiddings extends Command
{
    protected $signature = 'biddings:fetch {source=comprasnet} {--days=7}';
    protected $description = 'Busca novas licitações de fontes externas';

    public function handle()
    {
        $source = $this->argument('source');
        $days = $this->option('days');

        $this->info("Buscando licitações de {$source} dos últimos {$days} dias...");

        $filters = [];

        // Definir filtros conforme a fonte
        if ($source === 'comprasnet') {
            $startDate = now()->subDays($days)->format('d/m/Y');
            $endDate = now()->format('d/m/Y');

            $filters = [
                'dt_publ_ini' => $startDate,
                'dt_publ_fim' => $endDate
            ];
        }

        $scrapingService = new ScrapingService();
        $result = $scrapingService->searchNewBiddings($source, $filters);

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

        foreach ($result['data'] as $biddingData) {
            try {
                // Verificar se já existe
                $exists = Bidding::where('bidding_number', $biddingData['bidding_number'])->exists();

                if (!$exists) {
                    Bidding::create([
                        'bidding_number' => $biddingData['bidding_number'],
                        'title' => $biddingData['title'],
                        'opening_date' => $biddingData['opening_date'],
                        'modality' => $biddingData['modality'],
                        'status' => $biddingData['status'],
                        'url_source' => $biddingData['url_source'],
                        'company_id' => $defaultCompany->id
                    ]);

                    $imported++;
                }
            } catch (\Exception $e) {
                $this->error("Erro ao importar licitação {$biddingData['bidding_number']}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->info("Importação concluída: {$imported} licitações importadas, {$errors} erros.");

        return 0;
    }
}
