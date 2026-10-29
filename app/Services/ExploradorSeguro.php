<?php

namespace App\Services;

use Carbon\Carbon;

class ExploradorSeguro
{
    /**
     * Directorios base que se pueden consultar (clave => ruta).
     * Solo se listan nombres y metadatos: nunca se abre ni se descarga el contenido de un archivo.
     */
    public const RAICES = [
        'etc'  => '/etc',
        'home' => '/home',
        'log'  => '/var/log',
        'tmp'  => '/tmp',
    ];

    private const MAX_ENTRADAS = 500;
    private const MAX_RUTA     = 400;

    /** Lista un directorio permitido. $sub es la ruta relativa dentro del directorio base. */
    public static function listar(string $clave, string $sub = ''): array
    {
        if (!array_key_exists($clave, self::RAICES)) {
            return self::rechazo('Directorio base no permitido.', 'base ' . substr($clave, 0, 60));
        }

        $base = realpath(self::RAICES[$clave]);

        if ($base === false || !is_dir($base)) {
            return self::fallo('El directorio base no existe en este sistema.');
        }

        if (strlen($sub) > self::MAX_RUTA || str_contains($sub, "\0")) {
            return self::rechazo('Ruta no válida.', $clave . ':' . substr(str_replace("\0", '', $sub), 0, 100));
        }

        $sub     = trim($sub, '/');
        $destino = $sub === '' ? $base : realpath($base . '/' . $sub);

        if ($destino === false) {
            return self::fallo('La ruta no existe o no se puede leer.');
        }

        // realpath() ya resolvió "..", "." y los enlaces simbólicos: si el resultado
        // no queda dentro del directorio base, se rechaza y se registra en la bitácora.
        if ($destino !== $base && !str_starts_with($destino, rtrim($base, '/') . '/')) {
            return self::rechazo('La ruta está fuera del directorio permitido.', $clave . ':' . $sub);
        }

        if (!is_dir($destino)) {
            return self::fallo('La ruta no es un directorio.');
        }

        $nombres = @scandir($destino);

        if ($nombres === false) {
            return self::fallo('Sin permiso para listar este directorio.');
        }

        $usuarios = self::mapa('/etc/passwd');
        $grupos   = self::mapa('/etc/group');
        $entradas = [];
        $truncado = false;

        foreach ($nombres as $nombre) {
            if ($nombre === '.' || $nombre === '..') {
                continue;
            }

            if (count($entradas) >= self::MAX_ENTRADAS) {
                $truncado = true;
                break;
            }

            $ruta = $destino . '/' . $nombre;
            $st   = @lstat($ruta); // lstat no sigue enlaces simbólicos

            if ($st === false) {
                continue;
            }

            $tipo = self::tipo($st['mode']);

            $entradas[] = [
                'nombre'     => $nombre,
                'tipo'       => $tipo,
                'permisos'   => self::permisos($st['mode']),
                'dueno'      => $usuarios[$st['uid']] ?? (string) $st['uid'],
                'grupo'      => $grupos[$st['gid']] ?? (string) $st['gid'],
                'tamano_txt' => $tipo === 'd' ? '—' : MonitorAlmacenamiento::formatoBytes($st['size']),
                'fecha'      => Carbon::createFromTimestamp($st['mtime'], 'America/Guatemala')->format('Y-m-d H:i'),
                'enlace'     => $tipo === 'l' ? (string) @readlink($ruta) : '',
            ];
        }

        // Primero los directorios, luego los demás, cada grupo por nombre
        usort($entradas, fn ($a, $b) => [$a['tipo'] !== 'd', strtolower($a['nombre'])] <=> [$b['tipo'] !== 'd', strtolower($b['nombre'])]);

        return [
            'ok'       => true,
            'error'    => '',
            'clave'    => $clave,
            'ruta'     => $destino,
            'sub'      => ltrim(substr($destino, strlen($base)), '/'),
            'entradas' => $entradas,
            'truncado' => $truncado,
            'maximo'   => self::MAX_ENTRADAS,
        ];
    }

    private static function fallo(string $mensaje): array
    {
        return ['ok' => false, 'error' => $mensaje, 'entradas' => [], 'truncado' => false];
    }

    /** Rechazo por seguridad: se registra como DENEGADO en la bitácora. */
    private static function rechazo(string $mensaje, string $objetivo): array
    {
        RegistroBitacora::registrar('Explorador: ruta rechazada', $objetivo, RegistroBitacora::DENEGADO, $mensaje);

        return self::fallo($mensaje);
    }

    /** Correspondencia id → nombre, para /etc/passwd (UID) y /etc/group (GID). */
    private static function mapa(string $ruta): array
    {
        $mapa = [];

        foreach (preg_split('/\R/', (string) @file_get_contents($ruta)) as $linea) {
            $c = explode(':', $linea);

            if (count($c) >= 3 && ctype_digit($c[2])) {
                $mapa[(int) $c[2]] = $c[0];
            }
        }

        return $mapa;
    }

    /** Letra del tipo de archivo, como en ls -l. */
    private static function tipo(int $modo): string
    {
        return match ($modo & 0170000) {
            0040000 => 'd',
            0100000 => '-',
            0120000 => 'l',
            0060000 => 'b',
            0020000 => 'c',
            0010000 => 'p',
            0140000 => 's',
            default => '?',
        };
    }

    /** Permisos en formato rwx, como en ls -l (por ejemplo drwxr-xr-x). */
    private static function permisos(int $modo): string
    {
        $r = self::tipo($modo);

        $r .= ($modo & 0400) ? 'r' : '-';
        $r .= ($modo & 0200) ? 'w' : '-';
        $r .= ($modo & 04000) ? (($modo & 0100) ? 's' : 'S') : (($modo & 0100) ? 'x' : '-');

        $r .= ($modo & 0040) ? 'r' : '-';
        $r .= ($modo & 0020) ? 'w' : '-';
        $r .= ($modo & 02000) ? (($modo & 0010) ? 's' : 'S') : (($modo & 0010) ? 'x' : '-');

        $r .= ($modo & 0004) ? 'r' : '-';
        $r .= ($modo & 0002) ? 'w' : '-';
        $r .= ($modo & 01000) ? (($modo & 0001) ? 't' : 'T') : (($modo & 0001) ? 'x' : '-');

        return $r;
    }
}
