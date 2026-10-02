<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Throwable;

class CommandRunner
{
    /**
     * Comandos de solo lectura permitidos. Los argumentos son fijos:
     * nunca se mezclan con datos del usuario.
     */
    private const LECTURA = [
        'procesos'   => ['/usr/bin/ps', '-eo', 'pid,ppid,user:20,stat,ni,pcpu,pmem,comm', '--no-headers'],
        'discos'     => ['/usr/bin/lsblk', '-o', 'NAME,SIZE,TYPE,FSTYPE,MOUNTPOINT'],
        'uso_discos' => ['/usr/bin/df', '-h'],
        'montajes'   => ['/usr/bin/findmnt'],
        'sesiones'   => ['/usr/bin/who'],
        'puertos'    => ['/usr/bin/ss', '-tuln'],
        'servicios'  => ['/usr/bin/systemctl', 'list-units', '--type=service', '--no-pager', '--no-legend'],
        'interfaces' => ['/usr/bin/ip', '-br', 'addr'],
    ];

    private const SENALES = ['TERM', 'KILL', 'STOP', 'CONT'];

    /** Nombres de proceso que la aplicación puede lanzar y controlar. */
    private const PRUEBAS_PERMITIDAS = ['sleep'];

    /** Ejecuta un comando de solo lectura de la lista blanca. */
    public static function leer(string $clave): array
    {
        if (!array_key_exists($clave, self::LECTURA)) {
            RegistroBitacora::registrar('Comando rechazado (fuera de la lista blanca)', $clave, RegistroBitacora::DENEGADO);
            return self::resultado(false, '', 'Comando no permitido.');
        }

        return self::ejecutar(self::LECTURA[$clave]);
    }

    /** Lanza un proceso de prueba (sleep) y lo registra como proceso de la aplicación. */
    public static function lanzarPrueba(int $segundos = 600): array
    {
        $segundos = max(1, min($segundos, 3600));

        // Único uso de shell: texto fijo, y el número entra como parámetro posicional ($1),
        // nunca concatenado. El proceso queda desligado de la petición web.
        $r = self::ejecutar([
            '/bin/sh', '-c',
            '/usr/bin/sleep "$1" >/dev/null 2>&1 </dev/null & echo $!',
            'sh', (string) $segundos,
        ]);

        $pid = (int) trim($r['salida']);

        if (!$r['ok'] || $pid <= 1) {
            RegistroBitacora::registrar('Lanzar proceso de prueba', "sleep {$segundos}", RegistroBitacora::ERROR, $r['error']);
            return self::resultado(false, '', 'No se pudo lanzar el proceso de prueba.');
        }

        $lista = Cache::get('procesos_prueba', []);
        $lista[$pid] = time();
        Cache::put('procesos_prueba', $lista, now()->addDay());

        RegistroBitacora::registrar('Lanzar proceso de prueba', "PID {$pid} (sleep {$segundos})", RegistroBitacora::EXITO);

        return self::resultado(true, (string) $pid);
    }

    /** Envía una señal permitida a un proceso de prueba de la aplicación. */
    public static function enviarSenal(mixed $pid, string $senal): array
    {
        $senal = strtoupper($senal);
        $objetivo = 'PID ' . (is_scalar($pid) ? (string) $pid : '?');

        if (!in_array($senal, self::SENALES, true)) {
            RegistroBitacora::registrar('Señal rechazada', $objetivo, RegistroBitacora::DENEGADO, 'Señal no permitida');
            return self::resultado(false, '', 'Señal no permitida.');
        }

        $pid = self::validarProcesoDePrueba($pid);
        if ($pid === null) {
            RegistroBitacora::registrar("Señal SIG{$senal} rechazada", $objetivo, RegistroBitacora::DENEGADO, 'No es un proceso de prueba de la aplicación');
            return self::resultado(false, '', 'Solo se pueden controlar procesos de prueba de la aplicación.');
        }

        $r = self::ejecutar(['/usr/bin/kill', '-' . $senal, (string) $pid]);

        RegistroBitacora::registrar(
            "Enviar señal SIG{$senal}",
            "PID {$pid}",
            $r['ok'] ? RegistroBitacora::EXITO : RegistroBitacora::ERROR,
            $r['ok'] ? null : $r['error']
        );

        return $r;
    }

    /** Cambia la prioridad (nice 0 a 19) de un proceso de prueba de la aplicación. */
    public static function cambiarPrioridad(mixed $pid, mixed $nice): array
    {
        $objetivo = 'PID ' . (is_scalar($pid) ? (string) $pid : '?');
        $valor = filter_var($nice, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 19]]);

        if ($valor === false) {
            RegistroBitacora::registrar('renice rechazado', $objetivo, RegistroBitacora::DENEGADO, 'Valor de nice fuera de 0 a 19');
            return self::resultado(false, '', 'El valor de nice debe estar entre 0 y 19.');
        }

        $pid = self::validarProcesoDePrueba($pid);
        if ($pid === null) {
            RegistroBitacora::registrar('renice rechazado', $objetivo, RegistroBitacora::DENEGADO, 'No es un proceso de prueba de la aplicación');
            return self::resultado(false, '', 'Solo se pueden controlar procesos de prueba de la aplicación.');
        }

        $r = self::ejecutar(['/usr/bin/renice', '-n', (string) $valor, '-p', (string) $pid]);

        RegistroBitacora::registrar(
            "Cambiar prioridad (renice {$valor})",
            "PID {$pid}",
            $r['ok'] ? RegistroBitacora::EXITO : RegistroBitacora::ERROR,
            $r['ok'] ? null : $r['error']
        );

        return $r;
    }

    /** Devuelve el PID solo si es numérico, fue lanzado por la aplicación y sigue siendo de prueba. */
    private static function validarProcesoDePrueba(mixed $pid): ?int
    {
        if (!is_int($pid) && !(is_string($pid) && ctype_digit($pid))) {
            return null;
        }

        $pid = (int) $pid;
        if ($pid <= 1) {
            return null;
        }

        $lista = Cache::get('procesos_prueba', []);
        if (!isset($lista[$pid])) {
            return null;
        }

        $nombre = @file_get_contents("/proc/{$pid}/comm");
        if ($nombre === false || !in_array(trim($nombre), self::PRUEBAS_PERMITIDAS, true)) {
            unset($lista[$pid]);
            Cache::put('procesos_prueba', $lista, now()->addDay());
            return null;
        }

        return $pid;
    }

    /** Ejecuta un comando como arreglo (sin interpretar por shell) con límite de tiempo. */
    private static function ejecutar(array $argumentos): array
    {
        try {
            $r = Process::timeout(5)->run($argumentos);

            return self::resultado($r->successful(), $r->output(), $r->errorOutput(), $r->exitCode());
        } catch (Throwable $e) {
            report($e);
            return self::resultado(false, '', 'No se pudo ejecutar el comando.');
        }
    }

    private static function resultado(bool $ok, string $salida, string $error = '', ?int $codigo = null): array
    {
        return ['ok' => $ok, 'salida' => $salida, 'error' => $error, 'codigo' => $codigo];
    }
}
