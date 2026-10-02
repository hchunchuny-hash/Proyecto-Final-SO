@extends('layouts.app')

@section('titulo', 'Inicio')
@section('subtitulo', 'Resumen general del sistema')

@php
    $carga = sys_getloadavg() ?: [0, 0, 0];

    $segundos = (int) explode(' ', trim((string) @file_get_contents('/proc/uptime')))[0];
    $dias = intdiv($segundos, 86400);
    $horas = intdiv($segundos % 86400, 3600);
    $minutos = intdiv($segundos % 3600, 60);

    $mem = [];
    foreach (@file('/proc/meminfo') ?: [] as $linea) {
        if (preg_match('/^(\w+):\s+(\d+)/', $linea, $m)) {
            $mem[$m[1]] = (int) $m[2];
        }
    }
    $total = $mem['MemTotal'] ?? 0;
    $usada = $total - ($mem['MemAvailable'] ?? 0);
    $pct = $total ? round($usada * 100 / $total) : 0;
@endphp

@section('contenido')
    <div class="rejilla">
        <section class="tarjeta">
            <h2>Carga del sistema</h2>
            <p class="nota">Promedio de 1 minuto · /proc/loadavg</p>
            <div class="dato">{{ number_format($carga[0], 2) }}</div>
        </section>
        <section class="tarjeta">
            <h2>Tiempo encendido</h2>
            <p class="nota">Desde el último arranque · /proc/uptime</p>
            <div class="dato">{{ $dias }}<small>d</small> {{ $horas }}<small>h</small> {{ $minutos }}<small>m</small></div>
        </section>
        <section class="tarjeta">
            <h2>Memoria en uso</h2>
            <p class="nota">RAM usada · /proc/meminfo</p>
            <div class="dato">{{ $pct }}<small>%</small></div>
            <div class="uso" role="img" aria-label="Memoria usada {{ $pct }} por ciento"><span style="width: {{ $pct }}%"></span></div>
        </section>
    </div>
@endsection
