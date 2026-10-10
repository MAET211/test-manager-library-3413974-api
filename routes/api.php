<?php

declare(strict_types=1);

use App\Infrastructure\Http\Controllers\AuthController;
use App\Infrastructure\Http\Controllers\BookController;
use App\Infrastructure\Http\Controllers\CategoryController;
use App\Infrastructure\Http\Controllers\CoverController;
use App\Infrastructure\Http\Controllers\HealthController;
use App\Infrastructure\Http\Controllers\LoanController;
use App\Infrastructure\Http\Controllers\MemberController;
use App\Infrastructure\Http\Controllers\OverdueController;
use App\Infrastructure\Http\Controllers\ReportController;
use App\Infrastructure\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Manager Library (Base Prefix: /api/v1)
|--------------------------------------------------------------------------
*/

// Rutas Públicas (CA-01.3)
Route::get('/health', HealthController::class);
Route::post('/auth/login', [AuthController::class, 'login']);

// Rutas Autenticadas (Cualquier rol válido: admin o librarian)
Route::middleware(['auth.jwt'])->group(function () {
    // Categorías (3.3)
    Route::get('/categories', [CategoryController::class, 'index']);

    // Libros - Lectura y Portada (3.4)
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show']);
    Route::get('/books/{id}/cover', [CoverController::class, 'show']);

    // Rutas exclusivas para Administrador (role:admin)
    Route::middleware(['role:admin'])->group(function () {
        // Usuarios (3.2)
        Route::post('/users', [UserController::class, 'store']);

        // Libros - Mutación (3.4)
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update']);
        Route::delete('/books/{id}', [BookController::class, 'destroy']);
        Route::match(['post', 'put'], '/books/{id}/cover', [CoverController::class, 'upload']);
        Route::delete('/books/{id}/cover', [CoverController::class, 'destroy']);

        // Reportes (3.7)
        Route::get('/reports/most-borrowed', [ReportController::class, 'mostBorrowed']);
    });

    // Rutas operativas para Administrador y Bibliotecario (role:admin,librarian)
    Route::middleware(['role:admin,librarian'])->group(function () {
        // Socios (3.5)
        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/members/{id}', [MemberController::class, 'show']);
        Route::post('/members', [MemberController::class, 'store']);

        // Préstamos (3.6)
        Route::post('/loans', [LoanController::class, 'store']);
        Route::get('/loans', [LoanController::class, 'index']);
        Route::get('/loans/overdue', [OverdueController::class, 'index']);
        Route::get('/loans/{id}', [LoanController::class, 'show']);
        Route::post('/loans/{loan_id}/items/{item_id}/return', [LoanController::class, 'returnItem']);
    });
});
