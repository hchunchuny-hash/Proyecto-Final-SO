<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('titulo', 'Inicio') · SysMonitor</title>
<link rel="stylesheet" href="{{ asset('css/sysmonitor.css') }}">
<link rel="stylesheet" href="{{ asset('css/componentes.css') }}">
@stack('estilos')
</head>
<body>
<header class="barra">
    <a class="marca" href="{{ route('dashboard') }}">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" aria-hidden="true"><rect x="3" y="3" width="18" height="18"/><path d="M6 13h3l2-5 3 9 2-4h2"/></svg>
        SysMonitor<em>Grupp 2</em>
    </a>
    @include('partials.menu')
    <div class="usuario">
        <span>{{ auth()->user()->name }}</span>
        <span class="rol {{ auth()->user()->role === 'admin' ? 'admin' : '' }}">{{ auth()->user()->role === 'admin' ? 'ADMIN' : 'OBSERVADOR' }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="salir">Salir</button>
        </form>
    </div>
</header>

<div class="titulo-pagina">
    <h1>@yield('titulo')</h1>
    <p>@yield('subtitulo')</p>
</div>

<main class="contenido">
    @yield('contenido')
</main>

<footer class="pie">kernel {{ php_uname('r') }} · UMG · Sistemas Operativos</footer>
@stack('scripts')
</body>
</html>
