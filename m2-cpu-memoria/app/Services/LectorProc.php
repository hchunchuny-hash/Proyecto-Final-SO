<?php

namespace App\Services;

/**
 * Lee métricas reales del sistema desde /proc (solo Linux).
 */
class LectorProc
{
    public function disponible(): bool
    {
        return is_readable('/proc/stat') && is_readable('/proc/meminfo');
    }

    /** Uso de CPU total y por núcleo, midiendo dos muestras de /proc/stat. */
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
        $cache = ($info['Buffers'] ?? 0) + ($info['Cached'] ?? 0) + ($info['SReclaimable'] ?? 0);
        $usada = max(0, $total - $libre - $cache);
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
            'cache_pct' => $pct($cache, $total),
            'libre_pct' => $pct($libre, $total),
            'swap_pct' => $pct($swapUsada, $swapTotal),
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

        return preg_match_all('/^processor\s*:/m', $txt);
    }
}
