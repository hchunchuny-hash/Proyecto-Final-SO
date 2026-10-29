<?php

namespace App\Services;

class MonitorCpuMemoria
{
    private const STAT    = '/proc/stat';
    private const MEMINFO = '/proc/meminfo';
    private const LOADAVG = '/proc/loadavg';
    private const CPUINFO = '/proc/cpuinfo';
    private const UPTIME  = '/proc/uptime';

    /** Los contadores de CPU de /proc se cuentan en centésimas de segundo (USER_HZ = 100). */
    private const HZ = 100;

    private const CPU_VACIA = [
        'uso' => 0.0, 'usuario' => 0.0, 'sistema' => 0.0,
        'espera_es' => 0.0, 'robado' => 0.0, 'ocioso' => 100.0,
    ];

    /** Lee un archivo de /proc. Si no se puede leer, devuelve texto vacío. */
    private static function leer(string $ruta): string
    {
        $texto = @file_get_contents($ruta);

        return $texto === false ? '' : $texto;
    }

    /** Contadores de CPU (jiffies) del total y de cada núcleo, tomados de /proc/stat. */
    public static function muestraCpu(): array
    {
        $muestra = [];

        foreach (explode("\n", self::leer(self::STAT)) as $linea) {
            if (!preg_match('/^(cpu\d*)\s+(.+)$/', $linea, $m)) {
                continue;
            }

            // Orden de /proc/stat: user nice system idle iowait irq softirq steal ...
            $v = array_pad(array_map('intval', preg_split('/\s+/', trim($m[2]))), 8, 0);

            $muestra[$m[1]] = [
                'usuario'   => $v[0] + $v[1],
                'sistema'   => $v[2] + $v[5] + $v[6],
                'espera_es' => $v[4],
                'robado'    => $v[7],
                'ocioso'    => $v[3],
            ];
        }

        return $muestra;
    }

    /** Porcentajes de uso a partir de la diferencia entre dos muestras. */
    public static function usoEntre(array $antes, array $despues): array
    {
        $resultado = [];

        foreach ($despues as $nombre => $fin) {
            if (!isset($antes[$nombre])) {
                continue;
            }

            $d = [];
            foreach ($fin as $campo => $valor) {
                $d[$campo] = max(0, $valor - $antes[$nombre][$campo]);
            }

            $total = array_sum($d);

            if ($total <= 0) {
                $resultado[$nombre] = self::CPU_VACIA;
                continue;
            }

            $pct = fn (int $parte): float => round($parte * 100 / $total, 1);

            $resultado[$nombre] = [
                'uso'       => round(($total - $d['ocioso'] - $d['espera_es']) * 100 / $total, 1),
                'usuario'   => $pct($d['usuario']),
                'sistema'   => $pct($d['sistema']),
                'espera_es' => $pct($d['espera_es']),
                'robado'    => $pct($d['robado']),
                'ocioso'    => $pct($d['ocioso']),
            ];
        }

        return $resultado;
    }

    /** Arma el resultado de CPU (total y por núcleo) a partir de dos muestras. */
    public static function armarCpu(array $antes, array $despues): array
    {
        $uso = self::usoEntre($antes, $despues);

        $total = $uso['cpu'] ?? self::CPU_VACIA;
        unset($uso['cpu']);

        $nucleos = [];
        foreach ($uso as $nombre => $u) {
            $nucleos[] = ['nombre' => $nombre] + $u;
        }

        return [
            'disponible' => $antes !== [],
            'total'      => $total,
            'nucleos'    => $nucleos,
        ];
    }

    /** Uso de CPU tomando dos muestras separadas unos milisegundos (útil para pruebas). */
    public static function cpu(int $milisegundos = 500): array
    {
        $antes = self::muestraCpu();
        usleep(max(100, min($milisegundos, 1000)) * 1000);

        return self::armarCpu($antes, self::muestraCpu());
    }

    /** Lee /proc/[pid]/stat de un proceso: nombre, tiempo de CPU usado y memoria residente. */
    private static function leerProceso(int $pid): ?array
    {
        $s = self::leer("/proc/{$pid}/stat");
        $abre = strpos($s, '(');
        $cierra = strrpos($s, ')');

        if ($s === '' || $abre === false || $cierra === false) {
            return null;
        }

        // El nombre va entre paréntesis y puede tener espacios; el resto empieza en el campo 3 (estado)
        $campos = explode(' ', trim(substr($s, $cierra + 2)));

        if (count($campos) < 22) {
            return null;
        }

        return [
            'pid'         => $pid,
            'comando'     => substr($s, $abre + 1, $cierra - $abre - 1),
            'jiffies'     => (int) $campos[11] + (int) $campos[12],  // campos 14 y 15: utime + stime
            'rss_paginas' => (int) $campos[21],                      // campo 24: páginas en RAM
        ];
    }

    /** Lectura de todos los procesos que existen en este momento. */
    public static function muestraProcesos(): array
    {
        $muestra = [];

        foreach (@scandir('/proc') ?: [] as $nombre) {
            if (!ctype_digit($nombre)) {
                continue;
            }

            $p = self::leerProceso((int) $nombre);

            if ($p !== null) {
                $muestra[$p['pid']] = $p;
            }
        }

        return $muestra;
    }

    /** Correspondencia UID → nombre de usuario, leída de /etc/passwd. */
    private static function usuarios(): array
    {
        $mapa = [];

        foreach (explode("\n", self::leer('/etc/passwd')) as $linea) {
            $c = explode(':', $linea);

            if (count($c) >= 3 && ctype_digit($c[2])) {
                $mapa[(int) $c[2]] = $c[0];
            }
        }

        return $mapa;
    }

    /** Memoria residente exacta (KiB) de un proceso, desde /proc/[pid]/status. */
    private static function rssKb(int $pid): int
    {
        if (preg_match('/^VmRSS:\s+(\d+)\s+kB/m', self::leer("/proc/{$pid}/status"), $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /** Los procesos que más CPU y más memoria usan. */
    public static function topProcesos(array $antes, array $despues, float $segundos, int $memTotalKb, int $limite = 5): array
    {
        $segundos = max(0.05, $segundos);
        $usuarios = self::usuarios();

        $duenio = function (int $pid) use ($usuarios): string {
            $uid = @fileowner("/proc/{$pid}");

            return $uid === false ? '?' : ($usuarios[$uid] ?? (string) $uid);
        };

        // CPU: jiffies usados entre las dos lecturas, entre el tiempo transcurrido (porcentaje de un núcleo)
        $porCpu = [];
        foreach ($despues as $pid => $p) {
            if (!isset($antes[$pid])) {
                continue;
            }

            $dj = max(0, $p['jiffies'] - $antes[$pid]['jiffies']);

            $porCpu[] = [
                'pid'     => $pid,
                'comando' => $p['comando'],
                'cpu'     => round($dj / self::HZ / $segundos * 100, 1),
            ];
        }

        usort($porCpu, fn ($a, $b) => [$b['cpu'], $a['pid']] <=> [$a['cpu'], $b['pid']]);

        $topCpu = array_map(
            fn ($f) => $f + ['usuario' => $duenio($f['pid'])],
            array_slice($porCpu, 0, $limite)
        );

        // Memoria: se ordena por las páginas residentes y se lee el valor exacto de los 5 primeros
        $porMem = array_values($despues);
        usort($porMem, fn ($a, $b) => [$b['rss_paginas'], $a['pid']] <=> [$a['rss_paginas'], $b['pid']]);

        $topMem = [];
        foreach (array_slice($porMem, 0, $limite) as $p) {
            $kb = self::rssKb($p['pid']);

            $topMem[] = [
                'pid'     => $p['pid'],
                'comando' => $p['comando'],
                'usuario' => $duenio($p['pid']),
                'rss_kb'  => $kb,
                'pct'     => $memTotalKb > 0 ? round($kb * 100 / $memTotalKb, 1) : 0.0,
            ];
        }

        return ['cpu' => $topCpu, 'memoria' => $topMem];
    }

    /** Memoria RAM y swap, en KiB, a partir de /proc/meminfo. */
    public static function memoria(): array
    {
        $kb = [];

        foreach (explode("\n", self::leer(self::MEMINFO)) as $linea) {
            if (preg_match('/^(\w+):\s+(\d+)/', $linea, $m)) {
                $kb[$m[1]] = (int) $m[2];
            }
        }

        $total      = $kb['MemTotal'] ?? 0;
        $libre      = $kb['MemFree'] ?? 0;
        $disponible = $kb['MemAvailable'] ?? $libre;
        $usada      = max(0, $total - $disponible);
        $swapTotal  = $kb['SwapTotal'] ?? 0;
        $swapUsada  = max(0, $swapTotal - ($kb['SwapFree'] ?? 0));

        $pct = fn (int $parte, int $todo): float => $todo > 0 ? round($parte * 100 / $todo, 1) : 0.0;

        return [
            'total_kb'      => $total,
            'usada_kb'      => $usada,
            'libre_kb'      => $libre,
            'disponible_kb' => $disponible,
            'cache_kb'      => ($kb['Buffers'] ?? 0) + ($kb['Cached'] ?? 0) + ($kb['SReclaimable'] ?? 0),
            'pct_usada'     => $pct($usada, $total),
            'swap_total_kb' => $swapTotal,
            'swap_usada_kb' => $swapUsada,
            'pct_swap'      => $pct($swapUsada, $swapTotal),
        ];
    }

    /** Carga del sistema y procesos, a partir de /proc/loadavg. */
    public static function carga(): array
    {
        $p = preg_split('/\s+/', trim(self::leer(self::LOADAVG)));
        [$ejecutando, $procesos] = array_pad(explode('/', $p[3] ?? '0/0'), 2, 0);

        return [
            'm1'         => (float) ($p[0] ?? 0),
            'm5'         => (float) ($p[1] ?? 0),
            'm15'        => (float) ($p[2] ?? 0),
            'ejecutando' => (int) $ejecutando,
            'procesos'   => (int) $procesos,
        ];
    }

    /** Segundos desde el arranque, desde /proc/uptime. */
    public static function uptime(): int
    {
        $p = explode(' ', trim(self::leer(self::UPTIME)));

        return (int) ($p[0] ?? 0);
    }

    /** Datos fijos del procesador: modelo, núcleos lógicos, arquitectura y kernel. */
    public static function info(): array
    {
        $texto = self::leer(self::CPUINFO);

        $modelo = 'No disponible';
        if (preg_match('/^model name\s*:\s*(.+)$/m', $texto, $m)
            || preg_match('/^(?:Model|Hardware)\s*:\s*(.+)$/m', $texto, $m)) {
            $modelo = trim($m[1]);
        }

        return [
            'modelo'       => $modelo,
            'nucleos'      => max(1, (int) preg_match_all('/^processor\s*:/m', $texto)),
            'arquitectura' => php_uname('m'),
            'kernel'       => php_uname('r'),
        ];
    }

    /** Todo junto, para la actualización automática. Usa una sola espera para CPU y procesos. */
    public static function instantanea(int $milisegundos = 500): array
    {
        $cpuAntes  = self::muestraCpu();
        $procAntes = self::muestraProcesos();
        $inicio    = microtime(true);

        usleep(max(100, min($milisegundos, 1000)) * 1000);

        $cpuDespues  = self::muestraCpu();
        $procDespues = self::muestraProcesos();
        $segundos    = microtime(true) - $inicio;

        $memoria = self::memoria();

        return [
            'hora'     => now()->timezone('America/Guatemala')->format('H:i:s'),
            'uptime_s' => self::uptime(),
            'cpu'      => self::armarCpu($cpuAntes, $cpuDespues),
            'memoria'  => $memoria,
            'carga'    => self::carga(),
            'top'      => self::topProcesos($procAntes, $procDespues, $segundos, $memoria['total_kb']),
        ];
    }
}
