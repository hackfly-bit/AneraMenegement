<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Api\{
    ClientController,
    ProjectController,
    InvoiceController,
    PaymentController,
    ReportController,
    DashboardController,
    ContentController
};

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

// Protected Routes with Authorization
Route::middleware(['auth:sanctum', 'api.user'])->group(function () {
    
    // Client Routes
    Route::prefix('clients')->group(function () {
        Route::get('/', [ClientController::class, 'index']);
        Route::post('/', [ClientController::class, 'store']);
        Route::get('/{client}', [ClientController::class, 'show']);
        Route::put('/{client}', [ClientController::class, 'update']);
        Route::delete('/{client}', [ClientController::class, 'destroy']);
        Route::get('/{client}/projects', [ClientController::class, 'projects']);
        Route::get('/{client}/invoices', [ClientController::class, 'invoices']);
        Route::get('/{client}/payments', [ClientController::class, 'payments']);
        Route::post('/{client}/send-email', [ClientController::class, 'sendEmail']);
        Route::post('/{client}/generate-pdf', [ClientController::class, 'generatePdf']);
    });

    // Project Routes
    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::post('/', [ProjectController::class, 'store']);
        Route::get('/{project}', [ProjectController::class, 'show']);
        Route::put('/{project}', [ProjectController::class, 'update']);
        Route::delete('/{project}', [ProjectController::class, 'destroy']);
        Route::get('/{project}/invoices', [ProjectController::class, 'invoices']);
        Route::get('/{project}/payments', [ProjectController::class, 'payments']);
        Route::post('/{project}/complete', [ProjectController::class, 'complete']);
        Route::post('/{project}/cancel', [ProjectController::class, 'cancel']);
    });

    // Invoice Routes
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::post('/', [InvoiceController::class, 'store']);
        Route::get('/{invoice}', [InvoiceController::class, 'show']);
        Route::put('/{invoice}', [InvoiceController::class, 'update']);
        Route::delete('/{invoice}', [InvoiceController::class, 'destroy']);
        Route::post('/{invoice}/send-email', [InvoiceController::class, 'sendEmail']);
        Route::post('/{invoice}/generate-pdf', [InvoiceController::class, 'generatePdf']);
        Route::post('/{invoice}/mark-paid', [InvoiceController::class, 'markPaid']);
        Route::post('/{invoice}/cancel', [InvoiceController::class, 'cancel']);
        Route::get('/{invoice}/payments', [InvoiceController::class, 'payments']);
        Route::get('/{invoice}/terms', [InvoiceController::class, 'terms']);
    });

    // Payment Routes
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::post('/', [PaymentController::class, 'store']);
        Route::get('/{payment}', [PaymentController::class, 'show']);
        Route::put('/{payment}', [PaymentController::class, 'update']);
        Route::delete('/{payment}', [PaymentController::class, 'destroy']);
        Route::get('/statistics', [PaymentController::class, 'statistics']);
        Route::get('/by-method', [PaymentController::class, 'byMethod']);
        Route::post('/{payment}/refund', [PaymentController::class, 'refund']);
    });

    // Report Routes
    Route::prefix('reports')->group(function () {
        Route::get('/monthly', [ReportController::class, 'monthly']);
        Route::get('/quarterly', [ReportController::class, 'quarterly']);
        Route::get('/yearly', [ReportController::class, 'yearly']);
        Route::get('/custom', [ReportController::class, 'custom']);
        Route::post('/generate-pdf', [ReportController::class, 'generatePdf']);
        Route::get('/types', [ReportController::class, 'types']);
    });

    // Dashboard Routes
    Route::prefix('dashboard')->group(function () {
        Route::get('/metrics', [DashboardController::class, 'metrics']);
        Route::get('/overview', [DashboardController::class, 'overview']);
        Route::get('/financial', [DashboardController::class, 'financial']);
        Route::get('/projects', [DashboardController::class, 'projects']);
        Route::get('/clients', [DashboardController::class, 'clients']);
        Route::get('/invoices', [DashboardController::class, 'invoices']);
        Route::get('/trends', [DashboardController::class, 'trends']);
        Route::get('/activity', [DashboardController::class, 'activity']);
    });

    // Content Management Routes (CMS)
    Route::prefix('content')->group(function () {
        Route::get('/', [ContentController::class, 'index']);
        Route::post('/', [ContentController::class, 'store']);
        Route::get('/{content}', [ContentController::class, 'show']);
        Route::put('/{content}', [ContentController::class, 'update']);
        Route::delete('/{content}', [ContentController::class, 'destroy']);
        
        // Media management
        Route::post('/media/upload', [ContentController::class, 'uploadMedia']);
        Route::get('/media/list', [ContentController::class, 'getMedia']);
        Route::delete('/media/{path}', [ContentController::class, 'deleteMedia']);
    });

});