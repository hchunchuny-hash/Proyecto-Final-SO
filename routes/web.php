<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BitacoraController;
use App\Http\Middleware\RolMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'mostrarLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    // Ruta de prueba: solo el rol Administrador puede entrar (se puede borrar al final)
    Route::get('/prueba-admin', fn () => 'Acceso de administrador correcto')
        ->middleware(RolMiddleware::class . ':admin')
        ->name('prueba.admin');

    // Bitácora: solo Administrador
    Route::get('/bitacora', [BitacoraController::class, 'index'])
        ->middleware(RolMiddleware::class . ':admin')
        ->name('bitacora.index');
});
