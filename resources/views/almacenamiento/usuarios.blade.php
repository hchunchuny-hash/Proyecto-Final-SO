@extends('layouts.app')

@section('titulo', 'Almacenamiento')
@section('subtitulo', 'Usuarios del sistema y sesiones activas')

@section('contenido')
    @include('almacenamiento._pestanas')

    <section class="tarjeta">
        <h2>Sesiones activas</h2>
        <p class="nota">who · usuarios con una sesión abierta en este momento</p>
        @if (!$sesiones['ok'])
            <p class="error">{{ $sesiones['error'] }}</p>
        @endif
        <div class="caja-tabla">
            <table class="tabla">
                <thead><tr><th>Usuario</th><th>Terminal</th><th>Inicio</th><th>Origen</th></tr></thead>
                <tbody>
                    @forelse ($sesiones['filas'] as $s)
                        <tr>
                            <td>{{ $s['usuario'] }}</td>
                            <td class="mono">{{ $s['terminal'] }}</td>
                            <td>{{ $s['inicio'] }}</td>
                            <td>{{ $s['origen'] !== '' ? $s['origen'] : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No hay sesiones abiertas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="tarjeta separado">
        <h2>Usuarios humanos</h2>
        @if ($usuarios['ok'])
            <p class="nota">/etc/passwd · UID entre {{ $usuarios['uid_min'] }} y {{ $usuarios['uid_max'] }} y con una shell que permite iniciar sesión</p>
        @else
            <p class="error">{{ $usuarios['error'] }}</p>
        @endif
        <div class="caja-tabla">
            <table class="tabla">
                <thead><tr><th>Usuario</th><th>UID</th><th>GID</th><th>Nombre</th><th>Directorio personal</th><th>Shell</th></tr></thead>
                <tbody>
                    @forelse ($usuarios['humanos'] as $u)
                        <tr>
                            <td>{{ $u['usuario'] }}</td>
                            <td class="num">{{ $u['uid'] }}</td>
                            <td class="num">{{ $u['gid'] }}</td>
                            <td>{{ $u['nombre'] !== '' ? $u['nombre'] : '—' }}</td>
                            <td class="mono">{{ $u['home'] }}</td>
                            <td class="mono">{{ $u['shell'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No hay usuarios humanos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="tarjeta separado">
        <h2>Usuarios del sistema</h2>
        <p class="nota">Cuentas de servicios y del propio sistema (root, www-data, etc.): UID fuera del rango humano o sin shell de inicio de sesión</p>
        <details>
            <summary>Mostrar los {{ count($usuarios['sistema']) }} usuarios del sistema</summary>
            <div class="caja-tabla">
                <table class="tabla">
                    <thead><tr><th>Usuario</th><th>UID</th><th>GID</th><th>Descripción</th><th>Directorio</th><th>Shell</th></tr></thead>
                    <tbody>
                        @forelse ($usuarios['sistema'] as $u)
                            <tr>
                                <td>{{ $u['usuario'] }}</td>
                                <td class="num">{{ $u['uid'] }}</td>
                                <td class="num">{{ $u['gid'] }}</td>
                                <td>{{ $u['nombre'] !== '' ? $u['nombre'] : '—' }}</td>
                                <td class="mono">{{ $u['home'] }}</td>
                                <td class="mono">{{ $u['shell'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">Sin datos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </details>
    </section>
@endsection
