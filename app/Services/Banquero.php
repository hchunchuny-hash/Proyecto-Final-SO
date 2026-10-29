<?php

namespace App\Services;

/**
 * M4 · Interbloqueos.
 * Algoritmo del banquero (estado seguro) y detección de interbloqueos.
 * Solo hace cálculos con matrices: no ejecuta comandos del sistema.
 */
class Banquero
{
    /** Necesidad = Máximo - Asignación. */
    public function necesidad(array $maximo, array $asignacion): array
    {
        $necesidad = [];
        foreach ($maximo as $i => $fila) {
            foreach ($fila as $j => $valor) {
                $necesidad[$i][$j] = $valor - $asignacion[$i][$j];
            }
        }

        return $necesidad;
    }

    /**
     * Algoritmo del banquero: busca una secuencia segura.
     *
     * @return array{seguro: bool, secuencia: array, pasos: array, necesidad: array}
     */
    public function ejecutar(array $asignacion, array $maximo, array $disponible): array
    {
        $necesidad = $this->necesidad($maximo, $asignacion);
        $resultado = $this->buscarSecuencia($asignacion, $necesidad, $disponible);

        return [
            'seguro'    => $resultado['terminaron'] === count($asignacion),
            'secuencia' => $resultado['secuencia'],
            'pasos'     => $resultado['pasos'],
            'necesidad' => $necesidad,
        ];
    }

    /**
     * Detección de interbloqueos: los procesos que no pueden terminar
     * con los recursos disponibles quedan bloqueados entre sí.
     * Aquí la solicitud pendiente de cada proceso es su necesidad.
     *
     * @return array{interbloqueo: bool, bloqueados: array, pasos: array}
     */
    public function detectar(array $asignacion, array $maximo, array $disponible): array
    {
        $necesidad = $this->necesidad($maximo, $asignacion);
        $resultado = $this->buscarSecuencia($asignacion, $necesidad, $disponible);

        $bloqueados = [];
        foreach (array_keys($asignacion) as $i) {
            if (! in_array($i, $resultado['secuencia'], true)) {
                $bloqueados[] = $i;
            }
        }

        return [
            'interbloqueo' => count($bloqueados) > 0,
            'bloqueados'   => $bloqueados,
            'pasos'        => $resultado['pasos'],
        ];
    }

    /**
     * Evalúa si se puede conceder una solicitud de recursos de un proceso.
     * 1) Si pide más de su necesidad: error.
     * 2) Si pide más de lo disponible: el proceso espera.
     * 3) Si no, se simula la asignación y se corre el banquero:
     *    estado seguro => se concede; estado inseguro => se niega.
     *
     * @return array{decision: string, motivo: string, secuencia: array, pasos: array}
     */
    public function solicitar(array $asignacion, array $maximo, array $disponible, int $proceso, array $solicitud): array
    {
        $necesidad = $this->necesidad($maximo, $asignacion);
        $nombre = 'P' . ($proceso + 1);

        foreach ($solicitud as $j => $valor) {
            if ($valor > $necesidad[$proceso][$j]) {
                return $this->decision('error', "$nombre pide más de su necesidad en R" . ($j + 1) . '.');
            }
        }

        foreach ($solicitud as $j => $valor) {
            if ($valor > $disponible[$j]) {
                return $this->decision('espera', "No hay suficiente R" . ($j + 1) . " disponible: $nombre debe esperar.");
            }
        }

        // Simulación: Disponible -= Solicitud, Asignación += Solicitud (la Necesidad baja sola).
        foreach ($solicitud as $j => $valor) {
            $disponible[$j] -= $valor;
            $asignacion[$proceso][$j] += $valor;
        }

        $resultado = $this->ejecutar($asignacion, $maximo, $disponible);

        if ($resultado['seguro']) {
            return $this->decision('concedida', 'El estado resultante es seguro: se concede la solicitud.', $resultado);
        }

        return $this->decision('negada', 'El estado resultante sería inseguro: se niega la solicitud.', $resultado);
    }

    private function decision(string $decision, string $motivo, array $resultado = []): array
    {
        return [
            'decision'  => $decision,
            'motivo'    => $motivo,
            'secuencia' => $resultado['secuencia'] ?? [],
            'pasos'     => $resultado['pasos'] ?? [],
        ];
    }

    /**
     * Grafo de asignación de recursos (procesos = círculos, recursos = cuadrados).
     * - Arista de asignación R -> P: el recurso tiene instancias asignadas al proceso.
     * - Arista de solicitud P -> R: el proceso necesita más de lo que hay disponible, así que espera.
     * Los ciclos se buscan con DFS. Los nodos 0..n-1 son procesos y n..n+m-1 son recursos.
     *
     * @return array{procesos: int, recursos: int, aristas: array, hayCiclo: bool, ciclo: array, sugerencia: ?array}
     */
    public function grafo(array $asignacion, array $maximo, array $disponible): array
    {
        $necesidad = $this->necesidad($maximo, $asignacion);
        $n = count($asignacion);
        $m = count($disponible);

        $aristas = [];
        $siguientes = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $m; $j++) {
                if ($asignacion[$i][$j] > 0) {
                    $aristas[] = ['tipo' => 'asignacion', 'de' => $n + $j, 'a' => $i, 'cantidad' => $asignacion[$i][$j]];
                    $siguientes[$n + $j][] = $i;
                }
                if ($necesidad[$i][$j] > $disponible[$j]) {
                    $aristas[] = ['tipo' => 'solicitud', 'de' => $i, 'a' => $n + $j, 'cantidad' => $necesidad[$i][$j]];
                    $siguientes[$i][] = $n + $j;
                }
            }
        }

        $ciclo = $this->buscarCiclo($siguientes, $n + $m);

        return [
            'procesos'   => $n,
            'recursos'   => $m,
            'aristas'    => $aristas,
            'hayCiclo'   => $ciclo !== [],
            'ciclo'      => $ciclo,
            'sugerencia' => $ciclo !== [] ? $this->sugerencia($ciclo, $asignacion, $n) : null,
        ];
    }

    /** Búsqueda en profundidad (DFS): devuelve los nodos de un ciclo, o [] si no hay. */
    private function buscarCiclo(array $siguientes, int $total): array
    {
        $estado = array_fill(0, $total, 0); // 0 sin visitar, 1 en el camino, 2 terminado
        $camino = [];

        $visitar = function (int $nodo) use (&$visitar, &$estado, &$camino, $siguientes): array {
            $estado[$nodo] = 1;
            $camino[] = $nodo;

            foreach ($siguientes[$nodo] ?? [] as $vecino) {
                if ($estado[$vecino] === 1) {
                    return array_slice($camino, array_search($vecino, $camino, true));
                }
                if ($estado[$vecino] === 0) {
                    $ciclo = $visitar($vecino);
                    if ($ciclo !== []) {
                        return $ciclo;
                    }
                }
            }

            array_pop($camino);
            $estado[$nodo] = 2;

            return [];
        };

        for ($nodo = 0; $nodo < $total; $nodo++) {
            if ($estado[$nodo] === 0) {
                $ciclo = $visitar($nodo);
                if ($ciclo !== []) {
                    return $ciclo;
                }
            }
        }

        return [];
    }

    /**
     * Recuperación: terminar el proceso del ciclo con menos recursos asignados
     * o apropiarle (quitarle) las instancias de un recurso que otro proceso del ciclo espera.
     */
    private function sugerencia(array $ciclo, array $asignacion, int $n): array
    {
        $procesos = array_values(array_filter($ciclo, fn ($nodo) => $nodo < $n));

        $victima = $procesos[0];
        foreach ($procesos as $p) {
            if (array_sum($asignacion[$p]) < array_sum($asignacion[$victima])) {
                $victima = $p;
            }
        }

        $apropiar = null;
        $total = count($ciclo);
        for ($k = 0; $k < $total; $k++) {
            $de = $ciclo[$k];
            $a = $ciclo[($k + 1) % $total];
            if ($de >= $n && $a < $n) {
                $cantidad = $asignacion[$a][$de - $n];
                if ($apropiar === null || $cantidad < $apropiar['cantidad']) {
                    $apropiar = ['proceso' => $a, 'recurso' => $de - $n, 'cantidad' => $cantidad];
                }
            }
        }

        return [
            'terminar' => ['proceso' => $victima, 'liberaria' => $asignacion[$victima]],
            'apropiar' => $apropiar,
        ];
    }

    /** Recorre los procesos buscando uno cuya necesidad quepa en lo disponible. */
    private function buscarSecuencia(array $asignacion, array $necesidad, array $disponible): array
    {
        $trabajo = $disponible;
        $termino = array_fill(0, count($asignacion), false);
        $secuencia = [];
        $pasos = [];

        do {
            $avanzo = false;
            foreach ($asignacion as $i => $filaAsignada) {
                if ($termino[$i] || ! $this->cabe($necesidad[$i], $trabajo)) {
                    continue;
                }

                $antes = $trabajo;
                foreach ($filaAsignada as $j => $valor) {
                    $trabajo[$j] += $valor;
                }
                $termino[$i] = true;
                $secuencia[] = $i;
                $pasos[] = [
                    'proceso'       => $i,
                    'necesidad'     => $necesidad[$i],
                    'trabajo_antes' => $antes,
                    'trabajo'       => $trabajo,
                ];
                $avanzo = true;
                break; // al terminar un proceso se vuelve a empezar desde P1
            }
        } while ($avanzo);

        return [
            'secuencia'  => $secuencia,
            'pasos'      => $pasos,
            'terminaron' => count($secuencia),
        ];
    }

    private function cabe(array $necesidad, array $trabajo): bool
    {
        foreach ($necesidad as $j => $valor) {
            if ($valor > $trabajo[$j]) {
                return false;
            }
        }

        return true;
    }
}

