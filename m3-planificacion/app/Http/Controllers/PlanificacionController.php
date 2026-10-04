<?php

namespace App\Http\Controllers;

use App\Services\Planificador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanificacionController extends Controller
{
    public function index()
    {
        return view('planificacion');
    }

    public function simular(Request $request, Planificador $planificador): JsonResponse
    {
        $datos = $request->validate([
            'algoritmo' => ['required', 'in:RR,FCFS,SJF,PRIORIDAD'],
            'quantum' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'procesos' => ['required', 'array', 'min:1', 'max:30'],
            'procesos.*.pid' => ['required', 'string', 'max:10', 'distinct'],
            'procesos.*.llegada' => ['required', 'integer', 'min:0', 'max:10000'],
            'procesos.*.rafaga' => ['required', 'integer', 'min:1', 'max:10000'],
            'procesos.*.prioridad' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        return response()->json(
            $planificador->simular($datos['algoritmo'], $datos['procesos'], (int) ($datos['quantum'] ?? 4))
        );
    }
}
