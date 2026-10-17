<?php

namespace App\Http\Controllers;

use App\Services\MonitorCpuMemoria;

class MemoriaController extends Controller
{
    /** Pantalla del módulo: los datos que cambian se piden después por JSON. */
    public function index()
    {
        return view('memoria.index', ['info' => MonitorCpuMemoria::info()]);
    }

    /** Datos actuales de CPU, memoria, carga y procesos, en JSON. */
    public function datos()
    {
        return response()
            ->json(MonitorCpuMemoria::instantanea())
            ->header('Cache-Control', 'no-store');
    }
}
