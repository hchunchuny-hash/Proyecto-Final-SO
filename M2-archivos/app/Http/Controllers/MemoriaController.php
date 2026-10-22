<?php

namespace App\Http\Controllers;

use App\Services\LectorProc;
use Illuminate\Http\JsonResponse;

class MemoriaController extends Controller
{
    public function index()
    {
        return view('memoria.index');
    }

    /** JSON que consulta la gráfica cada pocos segundos (fetch). */
    public function datos(LectorProc $proc): JsonResponse
    {
        if (! $proc->disponible()) {
            return response()->json(['message' => '/proc no está disponible (se requiere Linux).'], 503);
        }

        return response()->json([
            'cpu' => $proc->cpu(),
            'modelo' => $proc->modelo(),
            'uptime' => $proc->uptime(),
            'carga' => $proc->carga(),
            'memoria' => $proc->memoria(),
            'top' => $proc->top(5),
        ]);
    }
}
