<?php

namespace App\Http\Middleware;

use App\Services\RegistroBitacora;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RolMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();

        if (!$usuario || !in_array($usuario->role, $roles, true)) {
            RegistroBitacora::registrar('Acceso denegado', '/' . $request->path(), RegistroBitacora::DENEGADO);
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        return $next($request);
    }
}
