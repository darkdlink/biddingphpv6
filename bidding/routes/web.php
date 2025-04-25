<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\BiddingController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Auth\LoginController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Aqui é onde você pode registrar as rotas da web para sua aplicação.
| Estas rotas são carregadas pelo RouteServiceProvider e todas elas
| serão atribuídas ao grupo de middleware "web".
|
*/

// Rotas de autenticação
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Middleware de autenticação
Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Empresas
    Route::resource('companies', CompanyController::class);

    // IMPORTANTE: A rota de busca deve vir ANTES da rota de resource de licitações
    // Busca de Licitações - esta rota precisa estar antes da definição do resource
    Route::get('/biddings/search', [BiddingController::class, 'showSearchForm'])->name('biddings.search');
    Route::post('/biddings/import', [BiddingController::class, 'import'])->name('biddings.import');

    // Licitações
    Route::resource('biddings', BiddingController::class);
    Route::get('/biddings/{bidding}/scrape', [BiddingController::class, 'scrape'])->name('biddings.scrape');

    // Propostas
    Route::resource('biddings.proposals', ProposalController::class);
    Route::get('/proposals/{proposal}/generate-pdf', [ProposalController::class, 'generatePdf'])->name('proposals.generate-pdf');

    // Documentos
    Route::post('/documents/upload', [DocumentController::class, 'upload'])->name('documents.upload');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // Notificações
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');

    // Análises Financeiras
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
});

// Rotas para testes
if (app()->environment('local')) {
    Route::get('/test', function () {
        return 'Teste de rota funcionando!';
    });
}
