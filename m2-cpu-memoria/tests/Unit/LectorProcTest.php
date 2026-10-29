<?php

namespace Tests\Unit;

use App\Services\LectorProc;
use PHPUnit\Framework\TestCase;

class LectorProcTest extends TestCase
{
    private string $salida = <<<'PS'
    1       0 root             Ss    0  0.1  0.3 systemd
  200       1 www-data         S     0 12.5  3.2 php-fpm: master
  300       1 dany             R     0 40.0  1.0 ps
  400       1 mysql            Sl    0  5.0 20.5 mysqld
PS;

    public function test_parsea_la_salida_de_ps(): void
    {
        $filas = LectorProc::parsearProcesos($this->salida);

        $this->assertCount(4, $filas);
        $this->assertSame(200, $filas[1]['pid']);
        $this->assertSame('www-data', $filas[1]['usuario']);
        $this->assertSame(12.5, $filas[1]['cpu']);
        $this->assertSame('php-fpm: master', $filas[1]['comando']); // el comando puede tener espacios
    }

    public function test_top_ordena_y_excluye_el_propio_ps(): void
    {
        $top = LectorProc::topDe(LectorProc::parsearProcesos($this->salida), 2);

        $this->assertSame([200, 400], array_column($top['cpu'], 'pid'));
        $this->assertSame([400, 200], array_column($top['memoria'], 'pid'));
    }

    public function test_ignora_lineas_vacias_o_invalidas(): void
    {
        $this->assertSame([], LectorProc::parsearProcesos(''));
        $this->assertSame([], LectorProc::parsearProcesos("texto sin formato\n"));
    }
}
