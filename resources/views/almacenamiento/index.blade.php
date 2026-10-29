@extends('layouts.app')

@section('titulo', 'Almacenamiento')
@section('subtitulo', 'Discos, particiones, sistemas de archivos y puntos de montaje')

@section('contenido')
    @include('almacenamiento._pestanas')

    @if (!$discos['ok'])
        <p class="error">{{ $discos['error'] }}</p>
    @endif

    <div class="rejilla">
        @forelse ($discos['discos'] as $d)
            <section class="tarjeta">
                <h2>/dev/{{ $d['nombre'] }}</h2>
                <p class="nota">{{ $d['modelo'] }} · lsblk</p>
                <div class="dato">{{ $d['tamano_txt'] }}</div>
                <p class="detalle">Tabla de particiones:
                    <span class="insignia {{ $d['tabla'] === 'Sin tabla de particiones' ? '' : 'ok' }}">{{ $d['tabla'] }}</span>
                </p>
            </section>
        @empty
            <section class="tarjeta"><p class="nota">No se encontraron discos.</p></section>
        @endforelse
    </div>

    <section class="tarjeta separado">
        <h2>Discos y particiones</h2>
        <p class="nota">lsblk · la sangría indica de qué dispositivo depende cada uno</p>
        <div class="caja-tabla">
            <table class="tabla">
                <thead>
                    <tr><th>Dispositivo</th><th>Tipo</th><th>Tamaño</th><th>Sistema de archivos</th><th>Punto de montaje</th></tr>
                </thead>
                <tbody>
                    @forelse ($discos['dispositivos'] as $d)
                        <tr>
                            <td class="mono" style="padding-left: {{ 10 + $d['nivel'] * 18 }}px">{{ $d['nivel'] > 0 ? '└ ' : '' }}{{ $d['nombre'] }}</td>
                            <td>
                                <span class="insignia">{{ $d['tipo'] }}</span>
                                @if ($d['solo_lectura'])
                                    <span class="insignia aviso">solo lectura</span>
                                @endif
                            </td>
                            <td class="num">{{ $d['tamano_txt'] }}</td>
                            <td>{{ $d['fs'] !== '' ? $d['fs'] : '—' }}</td>
                            <td>{{ $d['montaje'] !== '' ? $d['montaje'] : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="tarjeta separado">
        <h2>Uso de los sistemas de archivos</h2>
        <p class="nota">df -h · particiones y dispositivos reales (la barra se pone roja desde el 90 %)</p>
        @if (!$uso['ok'])
            <p class="error">{{ $uso['error'] }}</p>
        @else
            @include('almacenamiento._tabla_uso', ['filas' => $uso['reales']])

            @if (count($uso['virtuales']) > 0)
                <details class="separado">
                    <summary>Sistemas de archivos virtuales o en memoria ({{ count($uso['virtuales']) }})</summary>
                    @include('almacenamiento._tabla_uso', ['filas' => $uso['virtuales']])
                </details>
            @endif
        @endif
    </section>

    <div class="rejilla separado" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr))">
        <section class="tarjeta">
            <h2>Puntos de montaje activos</h2>
            <p class="nota">findmnt · lo que está montado ahora mismo</p>
            @if (!$montajes['ok'])
                <p class="error">{{ $montajes['error'] }}</p>
            @endif
            <div class="caja-tabla">
                <table class="tabla">
                    <thead><tr><th>Punto de montaje</th><th>Origen</th><th>Tipo</th><th>Opciones</th></tr></thead>
                    <tbody>
                        @forelse ($montajes['filas'] as $m)
                            <tr>
                                <td>{{ $m['destino'] }}</td>
                                <td>{{ $m['origen'] }}</td>
                                <td>{{ $m['tipo'] }}</td>
                                <td class="mono">{{ $m['opciones'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">Sin datos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="tarjeta">
            <h2>Montajes del arranque</h2>
            <p class="nota">/etc/fstab · lo que el sistema monta al iniciar</p>
            @if (!$fstab['ok'])
                <p class="error">{{ $fstab['error'] }}</p>
            @endif
            <div class="caja-tabla">
                <table class="tabla">
                    <thead><tr><th>Origen</th><th>Punto de montaje</th><th>Tipo</th><th>Opciones</th><th>Dump</th><th>Pass</th></tr></thead>
                    <tbody>
                        @forelse ($fstab['filas'] as $f)
                            <tr>
                                <td>{{ $f['origen'] }}</td>
                                <td>{{ $f['destino'] }}</td>
                                <td>{{ $f['tipo'] }}</td>
                                <td class="mono">{{ $f['opciones'] }}</td>
                                <td class="num">{{ $f['dump'] }}</td>
                                <td class="num">{{ $f['pass'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No hay entradas activas en /etc/fstab.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
