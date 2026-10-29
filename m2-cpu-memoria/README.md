MÓDULO: M2 CPU y Memoria
Autor: [nombre]

ARCHIVOS NUEVOS:
- app/Services/MonitorCpuMemoria.php
- app/Http/Controllers/MemoriaController.php
- resources/views/memoria/index.blade.php

USE EN routes/web.php:
use App\Http\Controllers\MemoriaController;

BLOQUE DE RUTAS (dentro del grupo auth):
    // ===== M2 CPU y Memoria =====
    Route::get('/memoria', [MemoriaController::class, 'index'])->name('memoria.index');
    Route::get('/memoria/datos', [MemoriaController::class, 'datos'])
        ->middleware('throttle:120,1')
        ->name('memoria.datos');
    // ===== fin M2 =====

MIGRACIONES: ninguna
COMANDOS NUEVOS PARA EL COMMANDRUNNER: ninguno (lee /proc directamente con PHP)
LIBRERÍAS EXTERNAS: ninguna
