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

