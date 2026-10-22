<?php

namespace App\Http\Controllers;

use App\Models\Simulacion;
use App\Services\Planificador;
use App\Services\RegistroBitacora;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanificacionController extends Controller
{
    public function index()
    {
        $guardadas = Simulacion::query()->latest('id')->limit(10)->get();

        return view('planificacion.index', compact('guardadas'));
    }

    public function simular(Request $request, Planificador $planificador): JsonResponse
    {
        $datos = $request->validate([
            'algoritmo' => ['required', 'in:'.implode(',', array_keys(Planificador::NOMBRES))],
            'quantum' => ['nullable', 'integer', 'min:1', 'max:100'],
            'procesos' => ['required', 'array', 'min:1', 'max:20'],
            'procesos.*.pid' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9_-]+$/', 'distinct'],
            'procesos.*.llegada' => ['required', 'integer', 'min:0', 'max:1000'],
            'procesos.*.rafaga' => ['required', 'integer', 'min:1', 'max:100'],
            'procesos.*.prioridad' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $quantum = (int) ($datos['quantum'] ?? 4);

        $resultado = $planificador->simular($datos['algoritmo'], $datos['procesos'], $quantum);
        $resultado['comparativa'] = $planificador->comparar($datos['procesos'], $quantum);

        // El Observador es de solo lectura: simula y consulta, pero solo el Administrador guarda.
        $guardada = null;
        if ($request->user()->role === 'admin') {
            $simulacion = Simulacion::create([
                'user_id' => $request->user()->id,
                'algoritmo' => $datos['algoritmo'],
                'quantum' => $datos['algoritmo'] === 'RR' ? $quantum : null,
                'procesos' => $datos['procesos'],
                'espera_promedio' => $resultado['espera_promedio'],
                'retorno_promedio' => $resultado['retorno_promedio'],
                'cambios_contexto' => $resultado['cambios_contexto'],
                'resultado' => $resultado,
            ]);

            RegistroBitacora::registrar('Guardar simulación de planificación', "Simulación #{$simulacion->id} ({$datos['algoritmo']})");

            $guardada = $this->fila($simulacion);
        }

        return response()->json($resultado + ['guardada' => $guardada]);
    }

    public function ver(Simulacion $simulacion): JsonResponse
    {
        return response()->json([
            'algoritmo' => $simulacion->algoritmo,
            'quantum' => $simulacion->quantum,
            'procesos' => $simulacion->procesos,
            'resultado' => $simulacion->resultado,
        ]);
    }

    private function fila(Simulacion $s): array
    {
        return [
            'id' => $s->id,
            'fecha' => $s->created_at->timezone('America/Guatemala')->format('d/m/Y H:i'),
            'algoritmo' => $s->algoritmo,
            'nombre' => Planificador::NOMBRES[$s->algoritmo] ?? $s->algoritmo,
            'cantidad' => count($s->procesos),
            'espera' => $s->espera_promedio,
            'retorno' => $s->retorno_promedio,
        ];
    }
}
