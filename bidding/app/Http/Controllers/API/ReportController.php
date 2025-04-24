<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BidsExport;
use App\Exports\ProposalsExport;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function bidReport(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $category = $request->input('category_id');
        $status = $request->input('status');

        $query = Bid::with('category');

        if ($startDate) {
            $query->where('opening_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('closing_date', '<=', $endDate);
        }

        if ($category) {
            $query->where('bid_category_id', $category);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $bids = $query->get();

        // Estatísticas
        $totalBids = $bids->count();
        $totalValue = $bids->sum('estimated_value');
        $byCategory = $bids->groupBy('bid_category_id')
            ->map(function($items, $key) {
                return [
                    'category_id' => $key,
                    'category_name' => $items->first()->category->name,
                    'count' => $items->count(),
                    'total_value' => $items->sum('estimated_value')
                ];
            })->values();

        return response()->json([
            'bids' => $bids,
            'statistics' => [
                'total_bids' => $totalBids,
                'total_value' => $totalValue,
                'by_category' => $byCategory,
            ]
        ]);
    }

    public function proposalReport(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = Proposal::with('bid');

        if ($startDate) {
            $query->whereHas('bid', function($q) use ($startDate) {
                $q->where('opening_date', '>=', $startDate);
            });
        }

        if ($endDate) {
            $query->whereHas('bid', function($q) use ($endDate) {
                $q->where('closing_date', '<=', $endDate);
            });
        }

        $proposals = $query->get();

        // Estatísticas
        $totalProposals = $proposals->count();
        $totalValue = $proposals->sum('proposal_value');
        $avgProfitMargin = $proposals->avg('profit_margin');

        return response()->json([
            'proposals' => $proposals,
            'statistics' => [
                'total_proposals' => $totalProposals,
                'total_value' => $totalValue,
                'avg_profit_margin' => $avgProfitMargin,
            ]
        ]);
    }

    public function exportReport($type, Request $request)
    {
        switch ($type) {
            case 'bids-excel':
                return Excel::download(new BidsExport($request->all()), 'licitacoes.xlsx');

            case 'bids-pdf':
                $bids = $this->getBidsForReport($request);
                $pdf = PDF::loadView('reports.bids', [
                    'bids' => $bids,
                    'filters' => $request->all()
                ]);
                return $pdf->download('licitacoes.pdf');

            case 'proposals-excel':
                return Excel::download(new ProposalsExport($request->all()), 'propostas.xlsx');

            case 'proposals-pdf':
                $proposals = $this->getProposalsForReport($request);
                $pdf = PDF::loadView('reports.proposals', [
                    'proposals' => $proposals,
                    'filters' => $request->all()
                ]);
                return $pdf->download('propostas.pdf');

            default:
                return response()->json(['error' => 'Tipo de relatório inválido'], 400);
        }
    }

    private function getBidsForReport(Request $request)
    {
        // Lógica para obter licitações filtradas para relatório
        // Similar ao método bidReport mas formatado para relatório
    }

    private function getProposalsForReport(Request $request)
    {
        // Lógica para obter propostas filtradas para relatório
        // Similar ao método proposalReport mas formatado para relatório
    }
}
