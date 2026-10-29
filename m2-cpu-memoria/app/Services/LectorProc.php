<?php

namespace App\Services;

/**
 * M2 · Lee métricas reales del kernel desde /proc (solo lectura con
 * file_get_contents, sin ejecutar comandos). El Top 5 de procesos usa
 * CommandRunner::leer('procesos').
 */
class LectorProc
{
    public function disponible(): bool
    {
        return is_readable('/proc/stat') && is_readable('/proc/meminfo');
    }

    /** Uso de CPU total y por núcleo: dos lecturas de /proc/stat separadas por unos ms. */
    public function cpu(int $muestraMs = 200): array
    {
        $a = $this->leerStat();
        usleep($muestraMs * 1000);
        $b = $this->leerStat();

        $total = 0.0;
        $nucleos = [];

        foreach ($b as $clave => $v) {
            if (! isset($a[$clave])) {
                continue;
            }
            $dt = $v['total'] - $a[$clave]['total'];
            $di = $v['idle'] - $a[$clave]['idle'];
            $uso = $dt > 0 ? round((1 - $di / $dt) * 100, 1) : 0.0;

            if ($clave === 'cpu') {
                $total = $uso;
            } else {
                $nucleos[] = ['nombre' => strtoupper($clave), 'uso' => $uso];
            }
        }

        return [
            'total' => $total,
            'nucleos' => $nucleos,
            'logicos' => $this->contarNucleos() ?: count($nucleos),
        ];
    }

    /** Modelo del procesador desde /proc/cpuinfo. */
    public function modelo(): string
    {
        $txt = @file_get_contents('/proc/cpuinfo') ?: '';

        foreach (['model name', 'Model', 'Hardware'] as $campo) {
            if (preg_match('/^'.preg_quote($campo, '/').'\s*:\s*(.+)$/mi', $txt, $m)) {
                return trim($m[1]);
            }
        }

        return 'Desconocido';
    }

    /** Segundos encendido, desde /proc/uptime. */
    public function uptime(): int
    {
        $txt = trim((string) @file_get_contents('/proc/uptime'));

        return (int) (explode(' ', $txt)[0] ?? 0);
    }

    /** Carga promedio de 1, 5 y 15 minutos, desde /proc/loadavg. */
    public function carga(): array
    {
        $p = preg_split('/\s+/', trim((string) @file_get_contents('/proc/loadavg')));

        return [
            'm1' => (float) ($p[0] ?? 0),
            'm5' => (float) ($p[1] ?? 0),
            'm15' => (float) ($p[2] ?? 0),
        ];
    }

    /** Memoria y swap desde /proc/meminfo (valores en GB y porcentajes). */
    public function memoria(): array
    {
        $info = [];
        foreach (@file('/proc/meminfo', FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
            if (preg_match('/^(\w+):\s+(\d+)/', $linea, $m)) {
                $info[$m[1]] = (int) $m[2]; // kB
            }
        }

        $total = $info['MemTotal'] ?? 0;
        $libre = $info['MemFree'] ?? 0;
        $disponible = $info['MemAvailable'] ?? $libre;
        $usada = max(0, $total - $disponible);
        $cache = ($info['Buffers'] ?? 0) + ($info['Cached'] ?? 0) + ($info['SReclaimable'] ?? 0);
        $cache = max(0, min($cache, $total - $usada));
        $libre = max(0, $total - $usada - $cache);
        $swapTotal = $info['SwapTotal'] ?? 0;
        $swapUsada = max(0, $swapTotal - ($info['SwapFree'] ?? 0));

        $pct = fn (int $parte, int $todo) => $todo > 0 ? round($parte / $todo * 100, 1) : 0.0;
        $gb = fn (int $kb) => round($kb / 1048576, 2);

        return [
            'total_gb' => $gb($total),
            'usada_gb' => $gb($usada),
            'cache_gb' => $gb($cache),
            'libre_gb' => $gb($libre),
            'usada_pct' => $pct($usada, $total),
            'swap_total_gb' => $gb($swapTotal),
            'swap_usada_gb' => $gb($swapUsada),
            'swap_pct' => $pct($swapUsada, $swapTotal),
        ];
    }

    /** Top N de procesos por CPU y por memoria (usa la lista blanca del CommandRunner). */
    public function top(int $n = 5): array
    {
        $r = CommandRunner::leer('procesos');

        if (! $r['ok']) {
            return ['cpu' => [], 'memoria' => []];
        }

        return self::topDe(self::parsearProcesos($r['salida']), $n);
    }

    /** Convierte la salida de `ps -eo pid,ppid,user,stat,ni,pcpu,pmem,comm` en filas. */
    public static function parsearProcesos(string $salida): array
    {
        $filas = [];

        foreach (preg_split('/\r?\n/', trim($salida)) as $linea) {
            $p = preg_split('/\s+/', trim($linea), 8);
            if (count($p) < 8 || ! ctype_digit($p[0])) {
                continue;
            }

            [$pid, , $usuario, $estado, , $cpu, $mem, $comando] = $p;
            $filas[] = [
                'pid' => (int) $pid,
                'usuario' => $usuario,
                'estado' => $estado,
                'cpu' => (float) $cpu,
                'mem' => (float) $mem,
                'comando' => $comando,
            ];
        }

        return $filas;
    }

    /** Ordena y recorta: el propio `ps` que lanza la aplicación no cuenta. */
    public static function topDe(array $procesos, int $n = 5): array
    {
        $procesos = array_values(array_filter($procesos, fn ($p) => $p['comando'] !== 'ps'));

        $porCpu = $procesos;
        usort($porCpu, fn ($a, $b) => [$b['cpu'], $a['pid']] <=> [$a['cpu'], $b['pid']]);

        $porMem = $procesos;
        usort($porMem, fn ($a, $b) => [$b['mem'], $a['pid']] <=> [$a['mem'], $b['pid']]);

        return [
            'cpu' => array_slice($porCpu, 0, $n),
            'memoria' => array_slice($porMem, 0, $n),
        ];
    }

    private function leerStat(): array
    {
        $r = [];
        foreach (@file('/proc/stat', FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
            if (! str_starts_with($linea, 'cpu')) {
                continue;
            }
            $partes = preg_split('/\s+/', trim($linea));
            $nombre = array_shift($partes);
            $v = array_map('intval', array_slice($partes, 0, 8));
            $r[$nombre] = [
                'total' => array_sum($v),
                'idle' => ($v[3] ?? 0) + ($v[4] ?? 0), // idle + iowait
            ];
        }

        return $r;
    }

    private function contarNucleos(): int
    {
        $txt = @file_get_contents('/proc/cpuinfo') ?: '';

        return (int) preg_match_all('/^processor\s*:/m', $txt);
    }
}
