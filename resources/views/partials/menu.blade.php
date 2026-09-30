@php
    $esAdmin = auth()->user()->role === 'admin';
    $modulos = [
        ['M1', 'Procesos',         'procesos'],
        ['M2', 'CPU y Memoria',    'memoria'],
        ['M3', 'Planificación',    'planificacion'],
        ['M4', 'Interbloqueos',    'interbloqueos'],
        ['M5', 'Almacenamiento',   'almacenamiento'],
        ['M6', 'Bitácora',         'bitacora'],
    ];
@endphp
<nav class="menu" aria-label="Módulos">
    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'activo' : '' }}"><b>~</b>Inicio</a>
    @foreach ($modulos as [$codigo, $nombre, $ruta])
        @continue($ruta === 'bitacora' && ! $esAdmin)
        <a href="{{ Route::has($ruta . '.index') ? route($ruta . '.index') : '#' }}"
           class="{{ request()->routeIs($ruta . '.*') ? 'activo' : '' }}"><b>{{ $codigo }}</b>{{ $nombre }}</a>
    @endforeach
</nav>

