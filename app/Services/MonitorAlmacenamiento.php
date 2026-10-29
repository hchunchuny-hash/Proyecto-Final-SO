<?php

namespace App\Services;

class MonitorAlmacenamiento
{
    private const UID_MIN_DEFECTO = 1000;
    private const UID_MAX_DEFECTO = 60000;

    /** Convierte bytes en un texto legible (KiB, MiB, GiB...). */
    public static function formatoBytes(int|float $bytes): string
    {
        $unidades = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        $valor = (float) $bytes;
        $i = 0;

        while ($valor >= 1024 && $i < count($unidades) - 1) {
            $valor /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $valor : number_format($valor, 1)) . ' ' . $unidades[$i];
    }

    /** Convierte una salida con formato CLAVE="valor" (lsblk -P, findmnt -P) en un arreglo por línea. */
    private static function parearLineas(string $salida): array
    {
        $filas = [];

        foreach (preg_split('/\R/', trim($salida)) as $linea) {
            if (trim($linea) === '' || !preg_match_all('/([A-Z_]+)="([^"]*)"/', $linea, $pares, PREG_SET_ORDER)) {
                continue;
            }

            $fila = [];
            foreach ($pares as $par) {
                // Los caracteres especiales llegan escritos como \xNN
                $fila[$par[1]] = preg_replace_callback(
                    '/\\\\x([0-9a-fA-F]{2})/',
                    fn ($h) => chr(hexdec($h[1])),
                    $par[2]
                );
            }

            $filas[] = $fila;
        }

        return $filas;
    }

    private static function tipoTabla(string $pttype): string
    {
        return match (strtolower($pttype)) {
            'gpt'   => 'GPT',
            'dos'   => 'MBR',
            ''      => 'Sin tabla de particiones',
            default => strtoupper($pttype),
        };
    }

    /** Discos, particiones y dispositivos derivados (lsblk), con el tipo de tabla de particiones. */
    public static function discos(): array
    {
        $r = CommandRunner::leer('discos_detalle');

        if (!$r['ok']) {
            return ['ok' => false, 'error' => $r['error'] ?: 'No se pudo leer lsblk.', 'discos' => [], 'dispositivos' => []];
        }

        $profundidad  = [];
        $vistos       = [];
        $discos       = [];
        $dispositivos = [];

        foreach (self::parearLineas($r['salida']) as $f) {
            $nombre = $f['NAME'] ?? '';
            $padre  = $f['PKNAME'] ?? '';
            $clave  = $nombre . '<' . $padre;

            if ($nombre === '' || isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;

            $nivel = ($padre !== '' && isset($profundidad[$padre])) ? $profundidad[$padre] + 1 : 0;
            $profundidad[$nombre] = $nivel;

            $tipo   = $f['TYPE'] ?? '';
            $tamano = (int) ($f['SIZE'] ?? 0);

            $dispositivos[] = [
                'nombre'       => $nombre,
                'nivel'        => $nivel,
                'tipo'         => $tipo,
                'tamano_txt'   => self::formatoBytes($tamano),
                'fs'           => $f['FSTYPE'] ?? '',
                'montaje'      => $f['MOUNTPOINT'] ?? '',
                'solo_lectura' => ($f['RO'] ?? '0') === '1',
            ];

            if ($tipo === 'disk' && $tamano > 0) {
                $discos[] = [
                    'nombre'     => $nombre,
                    'modelo'     => trim($f['MODEL'] ?? '') ?: 'Modelo no informado',
                    'tamano_txt' => self::formatoBytes($tamano),
                    'tabla'      => self::tipoTabla($f['PTTYPE'] ?? ''),
                ];
            }
        }

        return ['ok' => true, 'error' => '', 'discos' => $discos, 'dispositivos' => $dispositivos];
    }

    /** Uso de los sistemas de archivos montados (df -h), separados en reales y virtuales. */
    public static function usoDiscos(): array
    {
        $r = CommandRunner::leer('uso_discos');

        if (!$r['ok']) {
            return ['ok' => false, 'error' => $r['error'] ?: 'No se pudo leer df.', 'reales' => [], 'virtuales' => []];
        }

        $reales    = [];
        $virtuales = [];

        foreach (preg_split('/\R/', trim($r['salida'])) as $i => $linea) {
            if ($i === 0 || trim($linea) === '') {
                continue; // encabezado o línea vacía
            }

            $c = preg_split('/\s+/', trim($linea), 6);

            if (count($c) < 6) {
                continue;
            }

            [$fs, $tamano, $usado, $disponible, $pct, $montaje] = $c;

            $fila = [
                'fs'         => $fs,
                'tamano'     => $tamano,
                'usado'      => $usado,
                'disponible' => $disponible,
                'pct'        => min(100, max(0, (int) rtrim($pct, '%'))),
                'montaje'    => $montaje,
            ];

            if (str_starts_with($fs, '/dev/')) {
                $reales[] = $fila;
            } else {
                $virtuales[] = $fila;
            }
        }

        return ['ok' => true, 'error' => '', 'reales' => $reales, 'virtuales' => $virtuales];
    }

    /** Sistemas de archivos reales montados ahora mismo (findmnt). */
    public static function montajes(): array
    {
        $r = CommandRunner::leer('montajes_reales');

        if (!$r['ok']) {
            return ['ok' => false, 'error' => $r['error'] ?: 'No se pudo leer findmnt.', 'filas' => []];
        }

        $filas = array_map(fn ($f) => [
            'destino'  => $f['TARGET'] ?? '',
            'origen'   => $f['SOURCE'] ?? '',
            'tipo'     => $f['FSTYPE'] ?? '',
            'opciones' => $f['OPTIONS'] ?? '',
        ], self::parearLineas($r['salida']));

        return ['ok' => true, 'error' => '', 'filas' => $filas];
    }

    /** Montajes configurados para el arranque, leídos de /etc/fstab. */
    public static function fstab(): array
    {
        $texto = @file_get_contents('/etc/fstab');

        if ($texto === false) {
            return ['ok' => false, 'error' => 'No se pudo leer /etc/fstab.', 'filas' => []];
        }

        $filas = [];

        foreach (preg_split('/\R/', $texto) as $linea) {
            $linea = trim($linea);

            if ($linea === '' || $linea[0] === '#') {
                continue;
            }

            $c = preg_split('/\s+/', $linea);

            if (count($c) < 4) {
                continue;
            }

            $filas[] = [
                'origen'   => $c[0],
                'destino'  => $c[1],
                'tipo'     => $c[2],
                'opciones' => $c[3],
                'dump'     => $c[4] ?? '0',
                'pass'     => $c[5] ?? '0',
            ];
        }

        return ['ok' => true, 'error' => '', 'filas' => $filas];
    }

    /** Rango de UID de usuarios normales, según /etc/login.defs. */
    private static function rangoUid(): array
    {
        $texto = (string) @file_get_contents('/etc/login.defs');

        $min = preg_match('/^\s*UID_MIN\s+(\d+)/m', $texto, $a) ? (int) $a[1] : self::UID_MIN_DEFECTO;
        $max = preg_match('/^\s*UID_MAX\s+(\d+)/m', $texto, $b) ? (int) $b[1] : self::UID_MAX_DEFECTO;

        return [$min, $max];
    }

    /** Usuarios de /etc/passwd, separados en humanos y del sistema. Nunca se lee /etc/shadow. */
    public static function usuarios(): array
    {
        $texto = @file_get_contents('/etc/passwd');

        if ($texto === false) {
            return ['ok' => false, 'error' => 'No se pudo leer /etc/passwd.', 'humanos' => [], 'sistema' => [], 'uid_min' => 0, 'uid_max' => 0];
        }

        [$uidMin, $uidMax] = self::rangoUid();

        $humanos = [];
        $sistema = [];

        foreach (preg_split('/\R/', $texto) as $linea) {
            $c = explode(':', $linea);

            if (count($c) < 7 || !ctype_digit($c[2]) || !ctype_digit($c[3])) {
                continue;
            }

            $uid      = (int) $c[2];
            $shell    = $c[6];
            $sinShell = in_array(basename($shell), ['nologin', 'false'], true);

            $fila = [
                'usuario' => $c[0],
                'uid'     => $uid,
                'gid'     => (int) $c[3],
                'nombre'  => trim(explode(',', $c[4])[0]),
                'home'    => $c[5],
                'shell'   => $shell,
            ];

            if ($uid >= $uidMin && $uid <= $uidMax && !$sinShell) {
                $humanos[] = $fila;
            } else {
                $sistema[] = $fila;
            }
        }

        usort($humanos, fn ($a, $b) => $a['uid'] <=> $b['uid']);
        usort($sistema, fn ($a, $b) => $a['uid'] <=> $b['uid']);

        return ['ok' => true, 'error' => '', 'humanos' => $humanos, 'sistema' => $sistema, 'uid_min' => $uidMin, 'uid_max' => $uidMax];
    }

    /** Sesiones activas (comando who). */
    public static function sesiones(): array
    {
        $r = CommandRunner::leer('sesiones');

        if (!$r['ok']) {
            return ['ok' => false, 'error' => $r['error'] ?: 'No se pudo leer who.', 'filas' => []];
        }

        $filas = [];

        foreach (preg_split('/\R/', trim($r['salida'])) as $linea) {
            if (!preg_match('/^(\S+)\s+(\S+)\s+(.+?)\s*(?:\(([^)]*)\))?$/', trim($linea), $m)) {
                continue;
            }

            $filas[] = [
                'usuario'  => $m[1],
                'terminal' => $m[2],
                'inicio'   => $m[3],
                'origen'   => $m[4] ?? '',
            ];
        }

        return ['ok' => true, 'error' => '', 'filas' => $filas];
    }
}
