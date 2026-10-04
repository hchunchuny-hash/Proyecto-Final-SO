<?php

namespace App\Http\Controllers;

use App\Services\LectorProc;
use Illuminate\Http\JsonResponse;

class CpuMemoriaController extends Controller
{
    public function index()
    {
        return view('cpu-memoria');
    }

    public function datos(LectorProc $proc): JsonResponse
    {
        if (! $proc->disponible()) {
            return response()->json(['message' => '/proc no está disponible (se requiere Linux).'], 503);
        }

        return response()->json([
            'cpu' => $proc->cpu(),
            'memoria' => $proc->memoria(),
        ]);
    }
}
