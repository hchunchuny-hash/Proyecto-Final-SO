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

                foreach ($filaAsignada as $j => $valor) {
                    $trabajo[$j] += $valor;
                }
                $termino[$i] = true;
                $secuencia[] = $i;
                $pasos[] = ['proceso' => $i, 'trabajo' => $trabajo];
                $avanzo = true;
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

