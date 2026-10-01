<?php

namespace App\Services;

use App\Models\Bitacora;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class RegistroBitacora
{
    public const EXITO = 'exito';
    public const ERROR = 'error';
    public const DENEGADO = 'denegado';

    public static function registrar(
        string $accion,
        ?string $objetivo = null,
        string $resultado = self::EXITO,
        ?string $detalle = null
    ): void {
        try {
            $usuario = Auth::user();

            Bitacora::create([
                'user_id'   => $usuario?->id,
                'usuario'   => $usuario?->email ?? 'anónimo',
                'accion'    => Str::limit($accion, 150, ''),
                'objetivo'  => $objetivo !== null ? Str::limit($objetivo, 150, '') : null,
                'resultado' => $resultado,
                'detalle'   => $detalle !== null ? Str::limit($detalle, 500, '') : null,
                'ip'        => request()->ip(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
