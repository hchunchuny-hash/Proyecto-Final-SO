<?php

use Illuminate\Support\Facades\Route;

// Página de inicio: lleva al primer módulo instalado.
Route::get('/', function () {
    foreach (['cpu-memoria', 'planificacion'] as $nombre) {
        if (Route::has($nombre)) {
            return redirect()->route($nombre);
        }
    }

    return view('welcome');
})->name('inicio');

// Cada módulo (M2, M3...) registra sus rutas en routes/modulos/*.php
foreach (glob(__DIR__.'/modulos/*.php') as $archivo) {
    require $archivo;
}
