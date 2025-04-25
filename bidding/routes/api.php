<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BiddingApiController;

// Rotas protegidas por API token
Route::middleware('auth:sanctum')->group(function () {
    // Rotas para licitações
    Route::get('/biddings', [BiddingApiController::class, 'index']);
    Route::get('/biddings/{id}', [BiddingApiController::class, 'show']);
    Route::post('/biddings', [BiddingApiController::class, 'store']);
    Route::put('/biddings/{id}', [BiddingApiController::class, 'update']);
    Route::delete('/biddings/{id}', [BiddingApiController::class, 'destroy']);

    // Rotas para propostas
    Route::get('/biddings/{id}/proposals', [BiddingApiController::class, 'proposals']);
    Route::post('/biddings/{id}/proposals', [BiddingApiController::class, 'storeProposal']);
});
