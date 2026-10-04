<?php

use App\Http\Controllers\PlanificacionController;
use Illuminate\Support\Facades\Route;

// M3 · Planificación
Route::get('/planificacion', [PlanificacionController::class, 'index'])->name('planificacion');
Route::post('/planificacion/simular', [PlanificacionController::class, 'simular'])->name('planificacion.simular');
