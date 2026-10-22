<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LectorProcesos;
use App\Services\CommandRunner;

class ProcesosController extends Controller
{
    public function index(Request $request)
    {
        $procesos = LectorProcesos::obtenerProcesos();
        $resumen = LectorProcesos::obtenerResumenEstados($procesos);

        // Búsqueda por término
        $buscar = strtolower(trim((string)$request->input('buscar', '')));
        if ($buscar !== '') {
            $procesos = array_filter($procesos, function ($p) use ($buscar) {
                return str_contains((string)$p['pid'], $buscar)
                    || str_contains((string)$p['ppid'], $buscar)
                    || str_contains(strtolower($p['usuario']), $buscar)
                    || str_contains(strtolower($p['estado']), $buscar)
                    || str_contains(strtolower($p['comando']), $buscar);
            });
        }

        // Ordenamiento
        $columna = $request->input('orden', 'pid');
        $direccion = strtolower($request->input('direccion', 'asc')) === 'desc' ? 'desc' : 'asc';

        $columnasPermitidas = ['pid', 'ppid', 'usuario', 'estado', 'nice', 'cpu', 'memoria_kb', 'comando'];
        if (!in_array($columna, $columnasPermitidas)) {
            $columna = 'pid';
        }

        usort($procesos, function ($a, $b) use ($columna, $direccion) {
            $valA = $a[$columna];
            $valB = $b[$columna];

            if (is_string($valA)) {
                $res = strnatcasecmp($valA, (string)$valB);
            } else {
                $res = $valA <=> $valB;
            }

            return $direccion === 'desc' ? -$res : $res;
        });

        // Construcción de jerarquía de árbol
        $arbol = LectorProcesos::construirArbol($procesos);

        return view('procesos.index', [
            'procesos' => $procesos,
            'resumen' => $resumen,
            'arbol' => $arbol,
            'buscar' => $buscar,
            'orden' => $columna,
            'direccion' => $direccion,
        ]);
    }

    public function lanzar(Request $request)
    {
        $request->validate([
            'segundos' => 'required|integer|min:10|max:3600',
        ]);

        $resultado = CommandRunner::lanzarPrueba((int)$request->input('segundos'));

        if ($resultado['ok']) {
            return back()->with('exito', 'Proceso de prueba lanzado correctamente. PID: ' . $resultado['salida']);
        }

        return back()->with('error', 'Error al lanzar el proceso: ' . $resultado['error']);
    }

    public function senal(Request $request)
    {
        $request->validate([
            'pid' => 'required|integer|min:1',
            'senal' => 'required|string|in:TERM,KILL,STOP,CONT',
        ]);

        $resultado = CommandRunner::enviarSenal(
            (int)$request->input('pid'),
            $request->input('senal')
        );

        if ($resultado['ok']) {
            return back()->with('exito', "Señables enviadas con éxito (SIG{$request->input('senal')}) al PID {$request->input('pid')}.");
        }

        return back()->with('error', 'Error al enviar señal: ' . $resultado['error']);
    }

    public function prioridad(Request $request)
    {
        $request->validate([
            'pid' => 'required|integer|min:1',
            'prioridad' => 'required|integer|between:-20,19',
        ]);

        $resultado = CommandRunner::cambiarPrioridad(
            (int)$request->input('pid'),
            (int)$request->input('prioridad')
        );

        if ($resultado['ok']) {
            return back()->with('exito', "Prioridad del PID {$request->input('pid')} cambiada a {$request->input('prioridad')}.");
        }

        return back()->with('error', 'Error al cambiar prioridad: ' . $resultado['error']);
    }
}
