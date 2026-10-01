<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use Illuminate\Http\Request;

class BitacoraController extends Controller
{
    public function index(Request $request)
    {
        $consulta = Bitacora::query()->orderByDesc('id');

        $texto = trim((string) $request->query('q', ''));
        if ($texto !== '') {
            $consulta->where(function ($w) use ($texto) {
                $w->where('usuario', 'like', "%{$texto}%")
                  ->orWhere('accion', 'like', "%{$texto}%")
                  ->orWhere('objetivo', 'like', "%{$texto}%");
            });
        }

        $resultado = $request->query('resultado');
        if (in_array($resultado, ['exito', 'error', 'denegado'], true)) {
            $consulta->where('resultado', $resultado);
        }

        $registros = $consulta->simplePaginate(15)->withQueryString();

        return view('bitacora.index', compact('registros', 'texto', 'resultado'));
    }
}
