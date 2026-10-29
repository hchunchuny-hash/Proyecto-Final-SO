<?php

namespace App\Services;

class LectorProcesos
{
    /**
     * Lee la carpeta /proc y construye un arreglo completo de procesos activos.
     */
    public static function obtenerProcesos(): array
    {
        $procesos = [];
        $usuarios = self::obtenerMapaUsuarios();
        $uptime = self::obtenerUptime();

        if (!is_dir('/proc')) {
            return [];
        }

        $elementos = scandir('/proc');
        if ($elementos === false) {
            return [];
        }

        foreach ($elementos as $elem) {
            // Unicamente procesar carpetas con identificadores numericos (PIDs)
            if (!ctype_digit($elem)) {
                continue;
            }

            $pid = (int)$elem;
            $dirProc = "/proc/{$pid}";

            if (!is_dir($dirProc)) {
                continue;
            }

            $infoStat = self::leerStat($dirProc);
            if (!$infoStat) {
                continue;
            }

            $infoStatus = self::leerStatus($dirProc);
            $cmd = self::leerCmdline($dirProc, $infoStat['comm']);

            $uid = $infoStatus['uid'] ?? 0;
            $usuario = $usuarios[$uid] ?? "UID {$uid}";

            // Calculo de uso de memoria en KB / MB
            $memoriaKb = $infoStatus['vm_rss'] ?? 0;
            $memoriaTexto = $memoriaKb >= 1024 
                ? number_format($memoriaKb / 1024, 1) . ' MB' 
                : $memoriaKb . ' KB';

            // Estimacion porcentual de CPU respecto al uptime del sistema
            $ticksTotales = $infoStat['utime'] + $infoStat['stime'];
            $segundosCpu = $ticksTotales / 100.0; // USER_HZ estandar de Linux = 100
            $porcentajeCpu = $uptime > 0 ? round(($segundosCpu / $uptime) * 100, 2) : 0.0;

            $procesos[] = [
                'pid' => $pid,
                'ppid' => $infoStat['ppid'],
                'comando' => $cmd,
                'estado' => $infoStat['estado'],
                'nice' => $infoStat['nice'],
                'usuario' => $usuario,
                'memoria_kb' => $memoriaKb,
                'memoria' => $memoriaTexto,
                'cpu' => $porcentajeCpu,
            ];
        }

        return $procesos;
    }

    /**
     * Agrupa y cuenta los procesos segun su estado (R, S, D, Z, T).
     */
    public static function obtenerResumenEstados(array $procesos): array
    {
        $resumen = [
            'R' => 0,
            'S' => 0,
            'D' => 0,
            'Z' => 0,
            'T' => 0,
            'OTROS' => 0,
            'TOTAL' => count($procesos),
        ];

        foreach ($procesos as $p) {
            $e = strtoupper($p['estado']);
            if (array_key_exists($e, $resumen)) {
                $resumen[$e]++;
            } else {
                $resumen['OTROS']++;
            }
        }

        return $resumen;
    }

    /**
     * Construye una estructura en arbol relacionando Padres e Hijos por PPID.
     */
    public static function construirArbol(array $procesos): array
    {
        $porPid = [];
        $hijos = [];

        foreach ($procesos as $p) {
            $porPid[$p['pid']] = $p;
            $ppid = $p['ppid'];
            if (!isset($hijos[$ppid])) {
                $hijos[$ppid] = [];
            }
            $hijos[$ppid][] = $p['pid'];
        }

        $raices = [];
        foreach ($procesos as $p) {
            if ($p['ppid'] === 0 || !isset($porPid[$p['ppid']])) {
                $raices[] = $p['pid'];
            }
        }

        return [
            'por_pid' => $porPid,
            'hijos' => $hijos,
            'raices' => array_values(array_unique($raices)),
        ];
    }

    private static function leerStat(string $dirProc): ?array
    {
        $archivo = "{$dirProc}/stat";
        if (!file_exists($archivo)) {
            return null;
        }

        $contenido = @file_get_contents($archivo);
        if (!$contenido) {
            return null;
        }

        $posInicio = strpos($contenido, '(');
        $posFin = strrpos($contenido, ')');

        if ($posInicio === false || $posFin === false || $posFin <= $posInicio) {
            return null;
        }

        $pid = (int)trim(substr($contenido, 0, $posInicio));
        $comm = substr($contenido, $posInicio + 1, $posFin - $posInicio - 1);
        $resto = trim(substr($contenido, $posFin + 1));
        $partes = preg_split('/\s+/', $resto);

        if (!$partes || count($partes) < 17) {
            return null;
        }

        return [
            'pid' => $pid,
            'comm' => $comm,
            'estado' => $partes[0] ?? 'S',      // Campo 3 (indice 0 tras parentesis)
            'ppid' => (int)($partes[1] ?? 0),   // Campo 4
            'utime' => (int)($partes[11] ?? 0), // Campo 14
            'stime' => (int)($partes[12] ?? 0), // Campo 15
            'nice' => (int)($partes[16] ?? 0),  // Campo 19
        ];
    }

    private static function leerStatus(string $dirProc): array
    {
        $archivo = "{$dirProc}/status";
        $resultado = ['uid' => 0, 'vm_rss' => 0];

        if (!file_exists($archivo)) {
            return $resultado;
        }

        $lineas = @file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lineas) {
            return $resultado;
        }

        foreach ($lineas as $linea) {
            if (str_starts_with($linea, 'Uid:')) {
                $partes = preg_split('/\s+/', trim($linea));
                if (isset($partes[1])) {
                    $resultado['uid'] = (int)$partes[1];
                }
            } elseif (str_starts_with($linea, 'VmRSS:')) {
                $partes = preg_split('/\s+/', trim($linea));
                if (isset($partes[1])) {
                    $resultado['vm_rss'] = (int)$partes[1];
                }
            }
        }

        return $resultado;
    }

    private static function leerCmdline(string $dirProc, string $defaultComm): string
    {
        $archivo = "{$dirProc}/cmdline";
        if (!file_exists($archivo)) {
            return "[{$defaultComm}]";
        }

        $contenido = @file_get_contents($archivo);
        if (empty($contenido)) {
            return "[{$defaultComm}]";
        }

        $cmd = trim(str_replace("\0", ' ', $contenido));
        return $cmd !== '' ? $cmd : "[{$defaultComm}]";
    }

    private static function obtenerMapaUsuarios(): array
    {
        $mapa = [];
        if (file_exists('/etc/passwd')) {
            $lineas = @file('/etc/passwd', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lineas) {
                foreach ($lineas as $linea) {
                    $partes = explode(':', $linea);
                    if (count($partes) >= 3) {
                        $mapa[(int)$partes[2]] = $partes[0];
                    }
                }
            }
        }
        return $mapa;
    }

    private static function obtenerUptime(): float
    {
        if (file_exists('/proc/uptime')) {
            $contenido = @file_get_contents('/proc/uptime');
            if ($contenido) {
                $partes = explode(' ', trim($contenido));
                return (float)($partes[0] ?? 0);
            }
        }
        return 0.0;
    }
}
