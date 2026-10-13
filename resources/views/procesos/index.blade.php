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

{{-- Panel de Acciones y Gestión de Pruebas --}}
<section class="tarjeta" style="margin-top: 1.5rem;">
    <h2>Acciones sobre Procesos de Prueba</h2>
    <p class="nota">Herramientas administrativas limitadas a procesos de prueba iniciados por el sistema</p>

    <div class="rejilla" style="margin-top: 1rem;">
        {{-- Formulario 1: Lanzar prueba --}}
        <div>
            <h3>Lanzar Proceso</h3>
            <form action="{{ route('procesos.lanzar') }}" method="POST">
                @csrf
                <div class="campo">
                    <label for="segundos">Duración en segundos:</label>
                    <input type="number" id="segundos" name="segundos" value="300" min="10" max="3600" required>
                </div>
                <button type="submit" class="btn" style="margin-top: 0.5rem;">Lanzar Proceso</button>
            </form>
        </div>

        {{-- Formulario 2: Enviar Señal --}}
        <div>
            <h3>Enviar Señal</h3>
            <form action="{{ route('procesos.senal') }}" method="POST">
                @csrf
                <div class="campo">
                    <label for="pid_senal">PID del Proceso:</label>
                    <input type="number" id="pid_senal" name="pid" min="1" required placeholder="Ej: 1234">
                </div>
                <div class="campo" style="margin-top: 0.5rem;">
                    <label for="senal">Señal POSIX:</label>
                    <select id="senal" name="senal" required>
                        <option value="TERM">SIGTERM (Terminar limpio)</option>
                        <option value="KILL">SIGKILL (Terminado forzado)</option>
                        <option value="STOP">SIGSTOP (Pausar/Detener)</option>
                        <option value="CONT">SIGCONT (Reanudar)</option>
                    </select>
                </div>
                <button type="submit" class="btn sec" style="margin-top: 0.5rem;">Enviar Señal</button>
            </form>
        </div>
