<?php

namespace App\Services;

use Exception;

class ProcessService
{
    // Lista blanca de señales permitidas
    private const ALLOWED_SIGNALS = [
        'SIGTERM' => 15,
        'SIGKILL' => 9,
        'SIGSTOP' => 19,
        'SIGCONT' => 18,
    ];

    /**
     * Obtener el listado estructurado de procesos del sistema
     */
    public function getProcesses(): array
    {
        // Usamos ps formateado de forma segura y portable
        $command = "ps -eo pid,ppid,user,stat,ni,%cpu,%mem,args --no-headers";
        $output = shell_exec($command);

        if (!$output) {
            return [];
        }

        $lines = explode("\n", trim($output));
        $processes = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            // Extraer columnas usando expresiones regulares
            $parts = preg_split('/\s+/', trim($line), 8);

            if (count($parts) >= 8) {
                $rawState = $parts[3];
                // El primer carácter indica el estado principal (R, S, D, Z, T)
                $mainState = strtoupper(substr($rawState, 0, 1));

                $processes[] = [
                    'pid'     => (int) $parts[0],
                    'ppid'    => (int) $parts[1],
                    'user'    => $parts[2],
                    'state'   => $mainState,
                    'raw_state' => $rawState,
                    'nice'    => (int) $parts[4],
                    'cpu'     => (float) $parts[5],
                    'memory'  => (float) $parts[6],
                    'command' => $parts[7],
                ];
            }
        }

        return $processes;
    }

    /**
     * Calcular contadores por estado (R, S, D, Z, T)
     */
    public function getStateSummary(array $processes): array
    {
        $summary = ['R' => 0, 'S' => 0, 'D' => 0, 'Z' => 0, 'T' => 0, 'Otros' => 0];

        foreach ($processes as $proc) {
            $state = $proc['state'];
            if (array_key_exists($state, $summary)) {
                $summary[$state]++;
            } else {
                $summary['Otros']++;
            }
        }

        return $summary;
    }

    /**
     * Construir estructura jerárquica (árbol de procesos)
     */
    public function getProcessTree(array $processes): array
    {
        $indexed = [];
        $tree = [];

        foreach ($processes as $proc) {
            $proc['children'] = [];
            $indexed[$proc['pid']] = $proc;
        }

        foreach ($indexed as $pid => &$proc) {
            $ppid = $proc['ppid'];
            if ($ppid > 0 && isset($indexed[$ppid])) {
                $indexed[$ppid]['children'][] = &$proc;
            } else {
                $tree[] = &$proc;
            }
        }

        return $tree;
    }

    /**
     * Lanzar un proceso de prueba (ejemplo: sleep en segundo plano)
     */
    public function launchTestProcess(int $seconds = 300): ?int
    {
        // Validar que la cantidad de segundos sea segura
        $seconds = max(10, min($seconds, 3600));

        // Ejecutar sleep en segundo plano e imprimir su PID
        $cmd = "sleep " . escapeshellarg($seconds) . " > /dev/null 2>&1 & echo $!";
        $pidOutput = shell_exec($cmd);

        $pid = (int) trim($pidOutput);
        return $pid > 0 ? $pid : null;
    }

    /**
     * Enviar una señal a un PID validado
     */
    public function sendSignal(int $pid, string $signalName): bool
    {
        if (!array_key_exists($signalName, self::ALLOWED_SIGNALS)) {
            throw new Exception("Señal no permitida: {$signalName}");
        }

        $safePid = escapeshellarg($pid);
        $safeSignal = escapeshellarg($signalName);

        // Se usa kill con el nombre o número de la señal validada
        $output = shell_exec("kill -s {$safeSignal} {$safePid} 2>&1");

        return empty($output);
    }

    /**
     * Cambiar la prioridad (nice) de un proceso de prueba
     */
    public function changePriority(int $pid, int $niceValue): bool
    {
        // nice típicamente va de -20 a 19 (www-data generalmente solo puede asignar valores > 0)
        $niceValue = max(0, min($niceValue, 19));

        $safePid = escapeshellarg($pid);
        $safeNice = escapeshellarg($niceValue);

        $output = shell_exec("renice -n {$safeNice} -p {$safePid} 2>&1");

        return !str_contains(strtolower($output ?? ''), 'error');
    }
}
