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
        $this->assertSame([1, 3, 4, 0, 2], $r['secuencia']);
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
}
