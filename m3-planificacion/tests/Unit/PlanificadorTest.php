<?php

namespace Tests\Unit;

use App\Services\Planificador;
use PHPUnit\Framework\TestCase;

class PlanificadorTest extends TestCase
{
    private array $procesos = [
        ['pid' => 'P1', 'llegada' => 0, 'rafaga' => 8, 'prioridad' => 2],
        ['pid' => 'P2', 'llegada' => 1, 'rafaga' => 4, 'prioridad' => 1],
        ['pid' => 'P3', 'llegada' => 2, 'rafaga' => 9, 'prioridad' => 3],
        ['pid' => 'P4', 'llegada' => 3, 'rafaga' => 5, 'prioridad' => 2],
    ];

    public function test_fcfs(): void
    {
        $r = (new Planificador)->simular('FCFS', $this->procesos);

        $this->assertSame(8.75, $r['espera_promedio']);
        $this->assertSame(15.25, $r['retorno_promedio']);
        $this->assertSame(3, $r['cambios_contexto']);
        $this->assertSame(26, $r['tiempo_total']);
    }

    public function test_sjf(): void
    {
        $r = (new Planificador)->simular('SJF', $this->procesos);

        $this->assertSame(7.75, $r['espera_promedio']);
        $this->assertSame(['P1', 'P2', 'P4', 'P3'], array_column($r['segmentos'], 'pid'));
    }

    public function test_prioridad_menor_numero_primero(): void
    {
        $r = (new Planificador)->simular('PRIORIDAD', $this->procesos);

        $this->assertSame(['P1', 'P2', 'P4', 'P3'], array_column($r['segmentos'], 'pid'));
    }

    public function test_round_robin_quantum_4(): void
    {
        $r = (new Planificador)->simular('RR', $this->procesos, 4);

        $this->assertSame(['P1', 'P2', 'P3', 'P4', 'P1', 'P3', 'P4', 'P3'], array_column($r['segmentos'], 'pid'));
        $this->assertSame(11.75, $r['espera_promedio']);
        $this->assertSame(18.25, $r['retorno_promedio']);
        $this->assertSame(7, $r['cambios_contexto']);
        $this->assertSame(26, $r['tiempo_total']);
    }

    public function test_tiempo_ocioso_entre_procesos(): void
    {
        $r = (new Planificador)->simular('FCFS', [
            ['pid' => 'A', 'llegada' => 0, 'rafaga' => 2],
            ['pid' => 'B', 'llegada' => 5, 'rafaga' => 2],
        ]);

        $this->assertNull($r['segmentos'][1]['pid']);
        $this->assertSame(7, $r['tiempo_total']);
        $this->assertSame(0.0, $r['espera_promedio']);
    }
}
