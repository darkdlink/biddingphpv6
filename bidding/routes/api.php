<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\BidController;
use App\Http\Controllers\API\SupplierController;
use App\Http\Controllers\API\ProposalController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\ScraperController;

// Rotas protegidas por autenticação
Route::middleware('auth:sanctum')->group(function () {
    // Rotas para licitações
    Route::apiResource('bids', BidController::class);

    // Rotas para fornecedores
    Route::apiResource('suppliers', SupplierController::class);

    // Rotas para propostas
    Route::apiResource('proposals', ProposalController::class);
    Route::post('proposals/calculate', [ProposalController::class, 'calculateProposal']);

    // Rotas para o web scraper
    Route::post('scraper/comprasnet', [ScraperController::class, 'scrapeComprasNet']);
    Route::post('scraper/custom', [ScraperController::class, 'scrapeCustomSource']);

    // Rotas para relatórios
    Route::get('reports/bids', [ReportController::class, 'bidReport']);
    Route::get('reports/proposals', [ReportController::class, 'proposalReport']);
    Route::get('reports/performance', [ReportController::class, 'performanceReport']);
    Route::get('reports/export/{type}', [ReportController::class, 'exportReport']);
});
