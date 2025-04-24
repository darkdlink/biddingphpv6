<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ScraperService;

class ScrapeBids extends Command
{
    protected $signature = 'bids:scrape {source? : Nome da fonte para scraping}';
    protected $description = 'Executa scraping de licitações de fontes configuradas';

    public function handle()
    {
        $source = $this->argument('source');
        $scraper = new ScraperService();

        $this->info('Iniciando scraping de licitações...');

        if (!$source || $source === 'comprasnet') {
            $result = $scraper->scrapeComprasNet();
            $this->info('ComprasNet: ' . $result['message']);
        }

        // Adicionar mais fontes conforme necessário

        $this->info('Scraping concluído!');

        return Command::SUCCESS;
    }
}
