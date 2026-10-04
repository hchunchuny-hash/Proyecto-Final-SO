@php
    $enlace = fn (string $ruta) => Route::has($ruta) ? route($ruta) : '#';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo') · SysMonitor</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Work+Sans:wght@400;500&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <nav class="nav">
        <span class="marca">SysMonitor</span>
        <span class="grupo">Grupo 4</span>
        <a href="{{ route('inicio') }}"><b>~</b>Inicio</a>
        <a href="#"><b>M1</b>Procesos</a>
        <a href="{{ $enlace('cpu-memoria') }}" @class(['on' => request()->routeIs('cpu-memoria')])><b>M2</b>CPU y Memoria</a>
        <a href="{{ $enlace('planificacion') }}" @class(['on' => request()->routeIs('planificacion')])><b>M3</b>Planificación</a>
        <a href="#"><b>M4</b>Interbloqueos</a>
        <a href="#"><b>M5</b>Almacenamiento</a>
        <a href="#"><b>M6</b>Bitácora</a>
        <span class="usuario">Administrador</span>
    </nav>

    <main class="contenedor">
        @yield('contenido')
    </main>

    @stack('scripts')
</body>
</html>
