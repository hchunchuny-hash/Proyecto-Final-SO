<?php

namespace App\Http\Controllers;

use App\Services\Banquero;
use App\Services\RegistroBitacora;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterbloqueosController extends Controller
{
    public function index()
    {
        return view('interbloqueos.index');
    }

    public function banquero(Request $request, Banquero $banquero): JsonResponse
    {
        [$asignacion, $maximo, $disponible] = $this->datos($request);

        $resultado = $banquero->ejecutar($asignacion, $maximo, $disponible);

        RegistroBitacora::registrar(
            'Simulación del algoritmo del banquero',
            count($asignacion) . ' procesos',
            RegistroBitacora::EXITO,
            $resultado['seguro'] ? 'Estado seguro' : 'Estado inseguro'
        );

        return response()->json($resultado);
    }

    public function detectar(Request $request, Banquero $banquero): JsonResponse
    {
        [$asignacion, $maximo, $disponible] = $this->datos($request);

        $resultado = $banquero->detectar($asignacion, $maximo, $disponible);

        RegistroBitacora::registrar(
            'Detección de interbloqueos',
            count($asignacion) . ' procesos',
            RegistroBitacora::EXITO,
            $resultado['interbloqueo'] ? 'Interbloqueo detectado' : 'Sin interbloqueos'
        );

        return response()->json($resultado);
    }

    /** Valida las matrices que escribe el usuario y las devuelve como enteros. */
    private function datos(Request $request): array
    {
        $datos = $request->validate([
            'asignacion'     => ['required', 'array', 'min:1', 'max:10'],
            'asignacion.*'   => ['required', 'array', 'min:1', 'max:6'],
            'asignacion.*.*' => ['required', 'integer', 'min:0', 'max:99'],
            'maximo'         => ['required', 'array', 'min:1', 'max:10'],
            'maximo.*'       => ['required', 'array', 'min:1', 'max:6'],
            'maximo.*.*'     => ['required', 'integer', 'min:0', 'max:99'],
            'disponible'     => ['required', 'array', 'min:1', 'max:6'],
            'disponible.*'   => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $asignacion = array_map(fn ($f) => array_map('intval', array_values($f)), array_values($datos['asignacion']));
        $maximo = array_map(fn ($f) => array_map('intval', array_values($f)), array_values($datos['maximo']));
        $disponible = array_map('intval', array_values($datos['disponible']));

        $recursos = count($disponible);
        if (count($asignacion) !== count($maximo)) {
            abort(422, 'Asignación y máximo deben tener los mismos procesos.');
        }
        foreach ($asignacion as $i => $fila) {
            if (count($fila) !== $recursos || count($maximo[$i]) !== $recursos) {
                abort(422, 'Todas las filas deben tener el mismo número de recursos.');
            }
            foreach ($fila as $j => $valor) {
                if ($valor > $maximo[$i][$j]) {
                    abort(422, 'La asignación no puede superar el máximo (proceso P' . ($i + 1) . ').');
                }
            }
        }

        return [$asignacion, $maximo, $disponible];
    }
}
