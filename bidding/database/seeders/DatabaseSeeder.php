<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Empresas
        DB::table('companies')->insert([
            [
                'name' => 'Prefeitura Municipal de Joinville',
                'cnpj' => '83.169.623/0001-10',
                'address' => 'Av. Hermann August Lepper, 10',
                'city' => 'Joinville',
                'state' => 'SC',
                'zip_code' => '89201-000',
                'phone' => '(47) 3431-3233',
                'email' => 'licitacoes@joinville.sc.gov.br',
                'description' => 'Prefeitura do município de Joinville, maior cidade de Santa Catarina.',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Governo do Estado de Santa Catarina',
                'cnpj' => '82.951.229/0001-76',
                'address' => 'Rod. SC 401, Km 5, 4600',
                'city' => 'Florianópolis',
                'state' => 'SC',
                'zip_code' => '88032-000',
                'phone' => '(48) 3665-1000',
                'email' => 'licitacoes@sc.gov.br',
                'description' => 'Governo do Estado de Santa Catarina.',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'UFSC - Universidade Federal de Santa Catarina',
                'cnpj' => '83.899.526/0001-82',
                'address' => 'Campus Universitário Reitor João David Ferreira Lima',
                'city' => 'Florianópolis',
                'state' => 'SC',
                'zip_code' => '88040-900',
                'phone' => '(48) 3721-9000',
                'email' => 'licitacoes@ufsc.br',
                'description' => 'Universidade Federal de Santa Catarina, uma das principais instituições de ensino superior do Brasil.',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Prefeitura Municipal de Florianópolis',
                'cnpj' => '82.892.282/0001-43',
                'address' => 'Rua Tenente Silveira, 60',
                'city' => 'Florianópolis',
                'state' => 'SC',
                'zip_code' => '88010-300',
                'phone' => '(48) 3251-6000',
                'email' => 'licitacoes@pmf.sc.gov.br',
                'description' => 'Prefeitura da capital do estado de Santa Catarina.',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Prefeitura Municipal de Blumenau',
                'cnpj' => '83.108.357/0001-15',
                'address' => 'Praça Victor Konder, 2',
                'city' => 'Blumenau',
                'state' => 'SC',
                'zip_code' => '89010-000',
                'phone' => '(47) 3381-7000',
                'email' => 'licitacoes@blumenau.sc.gov.br',
                'description' => 'Prefeitura da cidade de Blumenau, conhecida por sua forte herança alemã e pela Oktoberfest.',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);

        // Licitações
        DB::table('biddings')->insert([
            [
                'title' => 'Aquisição de material de escritório para Secretaria de Educação',
                'bidding_number' => 'PMJ-001/2025',
                'description' => 'Aquisição de materiais de escritório diversos incluindo papel A4, canetas, grampeadores, etc.',
                'company_id' => 1,
                'modality' => 'pregao_eletronico',
                'status' => 'active',
                'estimated_value' => 50000.00,
                'publication_date' => '2025-04-15 00:00:00',
                'opening_date' => '2025-05-15 10:00:00',
                'closing_date' => '2025-05-15 16:00:00',
                'url_source' => 'https://www.joinville.sc.gov.br/licitacoes/pmj-001-2025',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => 'Contratação de serviços de limpeza para unidades escolares',
                'bidding_number' => 'PMJ-002/2025',
                'description' => 'Contratação de empresa especializada em serviços de limpeza para atender 20 unidades escolares do município.',
                'company_id' => 1,
                'modality' => 'pregao_eletronico',
                'status' => 'pending',
                'estimated_value' => 750000.00,
                'publication_date' => '2025-04-20 00:00:00',
                'opening_date' => '2025-05-20 09:00:00',
                'closing_date' => null,
                'url_source' => 'https://www.joinville.sc.gov.br/licitacoes/pmj-002-2025',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => 'Aquisição de ambulâncias tipo A para Secretaria de Saúde',
                'bidding_number' => 'PE-025/2025-SC',
                'description' => 'Aquisição de 10 ambulâncias tipo A para renovação da frota da Secretaria de Saúde.',
                'company_id' => 2,
                'modality' => 'pregao_eletronico',
                'status' => 'pending',
                'estimated_value' => 1200000.00,
                'publication_date' => '2025-04-25 00:00:00',
                'opening_date' => '2025-05-25 14:00:00',
                'closing_date' => null,
                'url_source' => 'https://www.portaldecompras.sc.gov.br/pe-025-2025',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => 'Contratação de serviços de manutenção predial',
                'bidding_number' => 'UFSC-PE-010/2025',
                'description' => 'Contratação de empresa para prestação de serviços de manutenção predial preventiva e corretiva nos edifícios do campus Trindade.',
                'company_id' => 3,
                'modality' => 'concorrencia',
                'status' => 'active',
                'estimated_value' => 2500000.00,
                'publication_date' => '2025-04-10 00:00:00',
                'opening_date' => '2025-05-10 10:00:00',
                'closing_date' => '2025-07-10 17:00:00',
                'url_source' => 'https://licitacoes.ufsc.br/ufsc-pe-010-2025',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => 'Fornecimento de merenda escolar',
                'bidding_number' => 'PMF-PE-005/2025',
                'description' => 'Contratação de empresa para fornecimento de merenda escolar para rede municipal de ensino.',
                'company_id' => 4,
                'modality' => 'pregao_eletronico',
                'status' => 'active',
                'estimated_value' => 3000000.00,
                'publication_date' => '2025-04-05 00:00:00',
                'opening_date' => '2025-05-05 09:00:00',
                'closing_date' => null,
                'url_source' => 'https://www.pmf.sc.gov.br/licitacoes/pmf-pe-005-2025',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'title' => 'Construção de ponte no bairro Fortaleza',
                'bidding_number' => 'BLU-CC-002/2025',
                'description' => 'Contratação de empresa para construção de ponte de concreto armado no bairro Fortaleza, com extensão de 120 metros.',
                'company_id' => 5,
                'modality' => 'concorrencia',
                'status' => 'active',
                'estimated_value' => 5000000.00,
                'publication_date' => '2025-04-01 00:00:00',
                'opening_date' => '2025-06-01 10:00:00',
                'closing_date' => null,
                'url_source' => 'https://www.blumenau.sc.gov.br/licitacoes/blu-cc-002-2025',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);

        // Documentos
        DB::table('documents')->insert([
            [
                'name' => 'Edital PMJ-001/2025',
                'file_path' => 'documents/edital-pmj-001-2025.pdf',
                'file_type' => 'application/pdf',
                'documentable_id' => 1,
                'documentable_type' => 'App\\Models\\Bidding',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Anexo I - Termo de Referência',
                'file_path' => 'documents/anexo1-pmj-001-2025.pdf',
                'file_type' => 'application/pdf',
                'documentable_id' => 1,
                'documentable_type' => 'App\\Models\\Bidding',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Edital PMJ-002/2025',
                'file_path' => 'documents/edital-pmj-002-2025.pdf',
                'file_type' => 'application/pdf',
                'documentable_id' => 2,
                'documentable_type' => 'App\\Models\\Bidding',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Edital PE-025/2025-SC',
                'file_path' => 'documents/edital-pe-025-2025-sc.pdf',
                'file_type' => 'application/pdf',
                'documentable_id' => 3,
                'documentable_type' => 'App\\Models\\Bidding',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Especificações Técnicas',
                'file_path' => 'documents/espec-tecnicas-pe-025-2025-sc.pdf',
                'file_type' => 'application/pdf',
                'documentable_id' => 3,
                'documentable_type' => 'App\\Models\\Bidding',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);

        // Propostas
        DB::table('proposals')->insert([
            [
                'bidding_id' => 1,
                'value' => 48500.00,
                'description' => 'Proposta para fornecimento de materiais de escritório conforme edital PMJ-001/2025',
                'status' => 'submitted',
                'profit_margin' => 15.00,
                'total_cost' => 42173.91,
                'submission_date' => '2025-05-15 11:30:00',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'bidding_id' => 1,
                'value' => 47800.00,
                'description' => 'Proposta alternativa com valores menores em alguns itens',
                'status' => 'submitted',
                'profit_margin' => 12.50,
                'total_cost' => 42488.89,
                'submission_date' => '2025-05-15 12:15:00',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'bidding_id' => 3,
                'value' => 1180000.00,
                'description' => 'Proposta para fornecimento de ambulâncias conforme especificações técnicas',
                'status' => 'draft',
                'profit_margin' => 8.75,
                'total_cost' => 1084965.89,
                'submission_date' => null,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'bidding_id' => 4,
                'value' => 2950000.00,
                'description' => 'Proposta para serviços de manutenção predial conforme edital UFSC-PE-010/2025',
                'status' => 'submitted',
                'profit_margin' => 9.50,
                'total_cost' => 2694063.93,
                'submission_date' => '2025-05-05 10:30:00',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'bidding_id' => 4,
                'value' => 2920000.00,
                'description' => 'Proposta vencedora para serviços de manutenção predial',
                'status' => 'won',
                'profit_margin' => 10.25,
                'total_cost' => 2647800.45,
                'submission_date' => '2025-05-05 11:45:00',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'bidding_id' => 6,
                'value' => 4950000.00,
                'description' => 'Proposta para construção de ponte conforme edital BLU-CC-002/2025',
                'status' => 'submitted',
                'profit_margin' => 7.80,
                'total_cost' => 4591837.66,
                'submission_date' => '2025-06-01 11:00:00',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
