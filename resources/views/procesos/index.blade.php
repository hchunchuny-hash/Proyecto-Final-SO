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

	{{-- Formulario 3: Cambiar Prioridad --}}
        <div>
            <h3>Cambiar Prioridad (Renice)</h3>
            <form action="{{ route('procesos.prioridad') }}" method="POST">
                @csrf
                <div class="campo">
                    <label for="pid_nice">PID del Proceso:</label>
                    <input type="number" id="pid_nice" name="pid" min="1" required placeholder="Ej: 1234">
                </div>
                <div class="campo" style="margin-top: 0.5rem;">
                    <label for="prioridad">Nuevo Valor Nice (-20 a 19):</label>
                    <input type="number" id="prioridad" name="prioridad" value="10" min="-20" max="19" required>
                </div>
                <button type="submit" class="btn sec" style="margin-top: 0.5rem;">Aplicar Renice</button>
            </form>
        </div>
    </div>
 </section>

{{-- Buscador y Tabla de Procesos --}}
<section class="tarjeta" style="margin-top: 1.5rem;">
    <h2>Listado General de Procesos</h2>
    <p class="nota">Datos capturados dinámicamente desde /proc/[pid]/stat y /proc/[pid]/status</p>

    <form method="GET" action="{{ route('procesos.index') }}" style="margin-bottom: 1rem; display: flex; gap: 0.5rem;">
        <div class="campo" style="flex: 1;">
            <input type="text" name="buscar" value="{{ $buscar }}" placeholder="Buscar por PID, PPID, usuario, estado o comando...">
        </div>
        <button type="submit" class="btn">Buscar</button>
        @if ($buscar)
            <a href="{{ route('procesos.index') }}" class="btn sec" style="text-decoration: none; text-align: center;">Limpiar</a>
        @endif
    </form>

    <div class="caja-tabla">
        <table class="tabla">
            <thead>
                <tr>
                    @php
                        function urlOrden($col, $ordenActual, $dirActual) {
                            $nuevaDir = ($ordenActual === $col && $dirActual === 'asc') ? 'desc' : 'asc';
                            return route('procesos.index', array_merge(request()->query(), ['orden' => $col, 'direccion' => $nuevaDir]));
                        }
                    @endphp
                    <th><a href="{{ urlOrden('pid', $orden, $direccion) }}">PID</a></th>
                    <th><a href="{{ urlOrden('ppid', $orden, $direccion) }}">PPID</a></th>
                    <th><a href="{{ urlOrden('usuario', $orden, $direccion) }}">Usuario</a></th>
                    <th><a href="{{ urlOrden('estado', $orden, $direccion) }}">Estado</a></th>
                    <th class="num"><a href="{{ urlOrden('nice', $orden, $direccion) }}">Nice</a></th>
                    <th class="num"><a href="{{ urlOrden('cpu', $orden, $direccion) }}">% CPU</a></th>
                    <th class="num"><a href="{{ urlOrden('memoria_kb', $orden, $direccion) }}">RAM</a></th>
                    <th><a href="{{ urlOrden('comando', $orden, $direccion) }}">Comando</a></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($procesos as $p)
                    <tr>
                        <td class="num"><strong>{{ $p['pid'] }}</strong></td>
                        <td class="num">{{ $p['ppid'] }}</td>
                        <td>{{ $p['usuario'] }}</td>
                        <td>
                            @if ($p['estado'] === 'R')
                                <span class="insignia ok">R (Running)</span>
                            @elseif ($p['estado'] === 'Z')
                                <span class="insignia error">Z (Zombie)</span>
                            @elseif ($p['estado'] === 'D' || $p['estado'] === 'T')
                                <span class="insignia aviso">{{ $p['estado'] }}</span>
                            @else
                                <span class="insignia">{{ $p['estado'] }} (Sleep)</span>
                            @endif
                        </td>
                        <td class="num">{{ $p['nice'] }}</td>
                        <td class="num">{{ number_format($p['cpu'], 2) }}%</td>
                        <td class="num">{{ $p['memoria'] }}</td>
                        <td style="font-family: var(--mono); font-size: 0.85rem; word-break: break-all;">
                            {{ $p['comando'] }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 1.5rem;">
                            No se encontraron procesos que coincidan con la búsqueda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
