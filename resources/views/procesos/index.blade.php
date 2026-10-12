@extends('layouts.app')

@section('titulo', 'Módulo 1: Procesos')
@section('subtitulo', 'Monitoreo en tiempo real, tabla de procesos, jerarquía árbol y gestión con señales POSIX')

@section('contenido')

@if (session('exito'))
    <section class="tarjeta" style="border-top-color: var(--ok);">
        <p style="color: var(--ok); font-weight: bold; margin: 0;">{{ session('exito') }}</p>
    </section>
@endif

@if (session('error'))
    <section class="tarjeta" style="border-top-color: var(--error);">
        <p class="error" style="margin: 0;">{{ session('error') }}</p>
    </section>
@endif

@if ($errors->any())
    <section class="tarjeta" style="border-top-color: var(--error);">
        <div class="error">
            <ul style="margin: 0; padding-left: 20px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </section>
@endif

{{-- Tarjetas con resumen de estados --}}
<section class="rejilla">
    <div class="tarjeta">
        <h2>Total Procesos</h2>
        <p class="nota">Fuente: /proc</p>
        <p class="dato">{{ $resumen['TOTAL'] }}</p>
    </div>
    <div class="tarjeta">
        <h2>Ejecución (R)</h2>
        <p class="nota">Running / Runnable</p>
        <p class="dato" style="color: var(--ok);">{{ $resumen['R'] }}</p>
    </div>
    <div class="tarjeta">
        <h2>Durmiendo (S)</h2>
        <p class="nota">Interruptible Sleep</p>
        <p class="dato">{{ $resumen['S'] }}</p>
    </div>
    <div class="tarjeta">
        <h2>Espera E/S (D)</h2>
        <p class="nota">Disk Wait</p>
        <p class="dato" style="color: var(--acento);">{{ $resumen['D'] }}</p>
    </div>
    <div class="tarjeta">
        <h2>Zombis (Z)</h2>
        <p class="nota">Zombie Processes</p>
        <p class="dato" style="color: var(--error);">{{ $resumen['Z'] }}</p>
    </div>
    <div class="tarjeta">
        <h2>Detenidos (T)</h2>
        <p class="nota">Stopped by Signal</p>
        <p class="dato">{{ $resumen['T'] }}</p>
    </div>
</section>
