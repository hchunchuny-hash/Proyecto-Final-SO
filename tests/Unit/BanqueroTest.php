<?php

namespace Tests\Unit;

use App\Services\Banquero;
use PHPUnit\Framework\TestCase;

class BanqueroTest extends TestCase
{
    public function test_estado_seguro_con_ejemplo_clasico(): void
    {
        $r = (new Banquero())->ejecutar(
            [[0, 1, 0], [2, 0, 0], [3, 0, 2], [2, 1, 1], [0, 0, 2]],
            [[7, 5, 3], [3, 2, 2], [9, 0, 2], [2, 2, 2], [4, 3, 3]],
            [3, 3, 2]
        );

        $this->assertTrue($r['seguro']);
        $this->assertSame([1, 3, 0, 2, 4], $r['secuencia']);
    }

    public function test_reinicia_desde_p1_y_detalla_los_pasos(): void
    {
        $r = (new Banquero())->ejecutar(
            [[1, 0, 1], [2, 1, 0], [1, 1, 0]],
            [[3, 2, 2], [3, 1, 1], [2, 2, 1]],
            [2, 1, 1]
        );

        $this->assertSame([1, 0, 2], $r['secuencia']);
        $this->assertSame([2, 1, 1], $r['pasos'][0]['trabajo_antes']);
        $this->assertSame([4, 2, 1], $r['pasos'][0]['trabajo']);
        $this->assertSame([1, 0, 1], $r['pasos'][0]['necesidad']);
    }

    public function test_calcula_la_necesidad(): void
    {
        $n = (new Banquero())->necesidad([[3, 2, 2]], [[1, 0, 1]]);

        $this->assertSame([[2, 2, 1]], $n);
    }

    public function test_detecta_interbloqueo(): void
    {
        $r = (new Banquero())->detectar([[1, 0], [0, 1]], [[1, 1], [1, 1]], [0, 0]);

        $this->assertTrue($r['interbloqueo']);
        $this->assertSame([0, 1], $r['bloqueados']);
    }

    public function test_sin_interbloqueo_si_alcanzan_los_recursos(): void
    {
        $r = (new Banquero())->detectar([[1, 0, 1], [2, 1, 0]], [[3, 2, 2], [3, 1, 1]], [2, 1, 1]);

        $this->assertFalse($r['interbloqueo']);
    }

    public function test_solicitud_que_supera_la_necesidad_es_error(): void
    {
        $r = (new Banquero())->solicitar(
            [[0, 1, 0], [2, 0, 0]], [[7, 5, 3], [3, 2, 2]], [3, 3, 2], 1, [2, 0, 0]
        );

        $this->assertSame('error', $r['decision']);
    }

    public function test_solicitud_mayor_que_lo_disponible_espera(): void
    {
        $r = (new Banquero())->solicitar(
            [[0, 1, 0], [2, 0, 0]], [[7, 5, 3], [3, 2, 2]], [1, 0, 0], 0, [2, 0, 0]
        );

        $this->assertSame('espera', $r['decision']);
    }

    public function test_solicitud_se_concede_si_el_estado_queda_seguro(): void
    {
        // Ejemplo clásico: P1 pide (1,0,2) con disponible (3,3,2).
        $r = (new Banquero())->solicitar(
            [[0, 1, 0], [2, 0, 0], [3, 0, 2], [2, 1, 1], [0, 0, 2]],
            [[7, 5, 3], [3, 2, 2], [9, 0, 2], [2, 2, 2], [4, 3, 3]],
            [3, 3, 2], 1, [1, 0, 2]
        );

        $this->assertSame('concedida', $r['decision']);
    }

    public function test_solicitud_se_niega_si_el_estado_queda_inseguro(): void
    {
        // Ejemplo clásico: P0 pide (0,2,0) con disponible (2,3,0).
        $r = (new Banquero())->solicitar(
            [[0, 1, 0], [3, 0, 2], [3, 0, 2], [2, 1, 1], [0, 0, 2]],
            [[7, 5, 3], [3, 2, 2], [9, 0, 2], [2, 2, 2], [4, 3, 3]],
            [2, 3, 0], 0, [0, 2, 0]
        );

        $this->assertSame('negada', $r['decision']);
    }

    public function test_grafo_detecta_un_ciclo(): void
    {
        $g = (new Banquero())->grafo([[1, 0], [0, 1]], [[1, 1], [1, 1]], [0, 0]);

        $this->assertTrue($g['hayCiclo']);
        $this->assertSame([0, 3, 1, 2], $g['ciclo']);
        $this->assertSame(4, count($g['aristas']));
    }

    public function test_grafo_sin_ciclos(): void
    {
        $g = (new Banquero())->grafo(
            [[1, 0, 1], [2, 1, 0], [1, 1, 0]],
            [[3, 2, 2], [3, 1, 1], [2, 2, 1]],
            [2, 1, 1]
        );

        $this->assertFalse($g['hayCiclo']);
        $this->assertNull($g['sugerencia']);
    }

    public function test_grafo_sugiere_como_recuperarse(): void
    {
        $g = (new Banquero())->grafo([[1, 0], [0, 1]], [[1, 1], [1, 1]], [0, 0]);

        $this->assertSame(0, $g['sugerencia']['terminar']['proceso']);
        $this->assertSame(['proceso' => 1, 'recurso' => 1, 'cantidad' => 1], $g['sugerencia']['apropiar']);
    }
}
