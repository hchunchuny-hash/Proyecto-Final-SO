<?php

namespace App\Http\Controllers;

use App\Services\ExploradorSeguro;
use App\Services\MonitorAlmacenamiento;
use Illuminate\Http\Request;

class AlmacenamientoController extends Controller
{
    /** Discos, particiones, uso de sistemas de archivos y puntos de montaje. */
    public function index()
    {
        return view('almacenamiento.index', [
            'discos'   => MonitorAlmacenamiento::discos(),
            'uso'      => MonitorAlmacenamiento::usoDiscos(),
            'montajes' => MonitorAlmacenamiento::montajes(),
            'fstab'    => MonitorAlmacenamiento::fstab(),
        ]);
    }

    /** Usuarios del sistema (de /etc/passwd) y sesiones activas (who). */
    public function usuarios()
    {
        return view('almacenamiento.usuarios', [
            'usuarios' => MonitorAlmacenamiento::usuarios(),
            'sesiones' => MonitorAlmacenamiento::sesiones(),
        ]);
    }

    /** Explorador de solo lectura de un directorio permitido. */
    public function explorador(Request $request)
    {
        $raiz = $request->query('raiz');
        $ruta = $request->query('ruta');

        // Si llega algo que no es texto (por ejemplo ?raiz[]=x), se usan los valores por defecto
        $raiz = is_string($raiz) ? $raiz : 'etc';
        $ruta = is_string($ruta) ? $ruta : '';

        return view('almacenamiento.explorador', [
            'resultado' => ExploradorSeguro::listar($raiz, $ruta),
            'raices'    => ExploradorSeguro::RAICES,
            'raiz'      => $raiz,
        ]);
    }
}
