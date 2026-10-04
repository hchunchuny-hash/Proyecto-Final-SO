<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Simulador de algoritmos de planificación de CPU.
 *
 * Algoritmos: FCFS, SJF (no expulsivo), PRIORIDAD (no expulsivo,
 * número menor = mayor prioridad) y RR (Round Robin).
 */
class Planificador
{
    public function simular(string $algoritmo, array $procesos, int $quantum = 4): array
    {
        $procs = [];
        foreach (array_values($procesos) as $i => $p) {
            $procs[] = [
                'pid' => (string) $p['pid'],
                'llegada' => max(0, (int) $p['llegada']),
                'rafaga' => max(1, (int) $p['rafaga']),
                'prioridad' => (int) ($p['prioridad'] ?? 0),
                'idx' => $i,
            ];
        }

        if ($procs === []) {
            throw new InvalidArgumentException('Se requiere al menos un proceso.');
        }

        $crudos = match (strtoupper($algoritmo)) {
            'RR' => $this->roundRobin($procs, max(1, $quantum)),
            'FCFS' => $this->noExpulsivo($procs, fn ($a, $b) => [$a['llegada'], $a['idx']] <=> [$b['llegada'], $b['idx']]),
            'SJF' => $this->noExpulsivo($procs, fn ($a, $b) => [$a['rafaga'], $a['llegada'], $a['idx']] <=> [$b['rafaga'], $b['llegada'], $b['idx']]),
            'PRIORIDAD' => $this->noExpulsivo($procs, fn ($a, $b) => [$a['prioridad'], $a['llegada'], $a['idx']] <=> [$b['prioridad'], $b['llegada'], $b['idx']]),
            default => throw new InvalidArgumentException("Algoritmo no soportado: {$algoritmo}"),
        };

        $segmentos = $this->unirContiguos($crudos);

        return $this->metricas($procs, $segmentos);
    }

    /** FCFS / SJF / Prioridad: elige entre los procesos listos con el comparador dado. */
    private function noExpulsivo(array $procs, callable $comparador): array
    {
        $pendientes = $procs;
        $t = 0;
        $segmentos = [];

        while ($pendientes) {
            $listos = array_values(array_filter($pendientes, fn ($p) => $p['llegada'] <= $t));

            if (! $listos) {
                $proximo = min(array_column($pendientes, 'llegada'));
                $segmentos[] = ['pid' => null, 'inicio' => $t, 'fin' => $proximo];
                $t = $proximo;
                continue;
            }

            usort($listos, $comparador);
            $p = $listos[0];

            $segmentos[] = ['pid' => $p['pid'], 'inicio' => $t, 'fin' => $t + $p['rafaga']];
            $t += $p['rafaga'];

            $pendientes = array_values(array_filter($pendientes, fn ($q) => $q['idx'] !== $p['idx']));
        }

        return $segmentos;
    }

    private function roundRobin(array $procs, int $quantum): array
    {
        $orden = $procs;
        usort($orden, fn ($a, $b) => [$a['llegada'], $a['idx']] <=> [$b['llegada'], $b['idx']]);

        $restante = [];
        foreach ($procs as $p) {
            $restante[$p['idx']] = $p['rafaga'];
        }

        $n = count($orden);
        $cola = [];
        $i = 0;
        $t = 0;
        $terminados = 0;
        $segmentos = [];

        $encolar = function () use (&$cola, &$i, &$t, $orden, $n) {
            while ($i < $n && $orden[$i]['llegada'] <= $t) {
                $cola[] = $orden[$i];
                $i++;
            }
        };

        while ($terminados < $n) {
            $encolar();

            if (! $cola) {
                $proximo = $orden[$i]['llegada'];
                $segmentos[] = ['pid' => null, 'inicio' => $t, 'fin' => $proximo];
                $t = $proximo;
                continue;
            }

            $p = array_shift($cola);
            $uso = min($quantum, $restante[$p['idx']]);

            $segmentos[] = ['pid' => $p['pid'], 'inicio' => $t, 'fin' => $t + $uso];
            $t += $uso;
            $restante[$p['idx']] -= $uso;

            // Los que llegan durante la ejecución entran antes que el proceso expulsado.
            $encolar();

            if ($restante[$p['idx']] > 0) {
                $cola[] = $p;
            } else {
                $terminados++;
            }
        }

        return $segmentos;
    }

    /** Une segmentos consecutivos del mismo proceso (o de ocio). */
    private function unirContiguos(array $segmentos): array
    {
        $res = [];
        foreach ($segmentos as $s) {
            $ult = count($res) - 1;
            if ($ult >= 0 && $res[$ult]['pid'] === $s['pid'] && $res[$ult]['fin'] === $s['inicio']) {
                $res[$ult]['fin'] = $s['fin'];
            } else {
                $res[] = $s;
            }
        }

        return $res;
    }

    private function metricas(array $procs, array $segmentos): array
    {
        $fin = [];
        foreach ($segmentos as $s) {
            if ($s['pid'] !== null) {
                $fin[$s['pid']] = $s['fin'];
            }
        }

        $filas = [];
        $sumaEspera = 0;
        $sumaRetorno = 0;
        foreach ($procs as $p) {
            $finalizacion = $fin[$p['pid']] ?? $p['llegada'] + $p['rafaga'];
            $retorno = $finalizacion - $p['llegada'];
            $espera = $retorno - $p['rafaga'];
            $sumaEspera += $espera;
            $sumaRetorno += $retorno;
            $filas[] = [
                'pid' => $p['pid'],
                'llegada' => $p['llegada'],
                'rafaga' => $p['rafaga'],
                'finalizacion' => $finalizacion,
                'retorno' => $retorno,
                'espera' => $espera,
            ];
        }

        $ejecutados = array_values(array_filter($segmentos, fn ($s) => $s['pid'] !== null));
        $cambios = 0;
        for ($k = 1; $k < count($ejecutados); $k++) {
            if ($ejecutados[$k]['pid'] !== $ejecutados[$k - 1]['pid']) {
                $cambios++;
            }
        }

        $n = count($procs);

        return [
            'segmentos' => $segmentos,
            'procesos' => $filas,
            'espera_promedio' => round($sumaEspera / $n, 2),
            'retorno_promedio' => round($sumaRetorno / $n, 2),
            'cambios_contexto' => $cambios,
            'tiempo_total' => $segmentos ? end($segmentos)['fin'] : 0,
        ];
    }
}
