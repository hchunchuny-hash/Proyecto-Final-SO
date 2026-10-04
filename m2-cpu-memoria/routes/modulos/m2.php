<?php

use App\Http\Controllers\CpuMemoriaController;
use Illuminate\Support\Facades\Route;

// M2 para CPU y Memoria
Route::get('/cpu-memoria', [CpuMemoriaController::class, 'index'])->name('cpu-memoria');
Route::get('/datos/cpu-memoria', [CpuMemoriaController::class, 'datos'])->name('datos.cpu-memoria');
