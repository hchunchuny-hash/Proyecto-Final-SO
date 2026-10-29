@extends('layouts.app')

@section('titulo', 'Almacenamiento')
@section('subtitulo', 'Explorador de solo lectura de directorios permitidos')

@section('contenido')
    @include('almacenamiento._pestanas')

    @php $r = $resultado; @endphp

    <section class="tarjeta">
        <h2>Explorador de archivos</h2>
        <p class="nota">Solo lectura: se muestran nombre, permisos, propietario, tamaño y fecha. No se abre ni se descarga el contenido de los archivos.</p>

        <form method="GET" action="{{ route('almacenamiento.explorador') }}" class="explorar">
            <label for="raiz">Directorio base
                <select id="raiz" name="raiz">
                    @foreach ($raices as $clave => $ruta)
                        <option value="{{ $clave }}" @selected($clave === $raiz)>{{ $ruta }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="btn sec">Ir</button>
        </form>

        @if (!$r['ok'])
            <p class="error">{{ $r['error'] }}</p>
        @else
            @php
                $segmentos = $r['sub'] === '' ? [] : explode('/', $r['sub']);
                $acumulado = '';
            @endphp

            <p class="migas">
                <a href="{{ route('almacenamiento.explorador', ['raiz' => $r['clave']]) }}">{{ $raices[$r['clave']] }}</a>
                @foreach ($segmentos as $seg)
                    @php $acumulado = $acumulado === '' ? $seg : $acumulado . '/' . $seg; @endphp
                    / <a href="{{ route('almacenamiento.explorador', ['raiz' => $r['clave'], 'ruta' => $acumulado]) }}">{{ $seg }}</a>
                @endforeach
            </p>

            @if ($r['sub'] !== '')
                @php $padre = str_contains($r['sub'], '/') ? dirname($r['sub']) : ''; @endphp
                <p><a class="btn sec" href="{{ route('almacenamiento.explorador', array_filter(['raiz' => $r['clave'], 'ruta' => $padre], fn ($v) => $v !== '')) }}">↑ Subir un nivel</a></p>
            @endif

            <div class="caja-tabla">
                <table class="tabla">
                    <thead>
                        <tr><th>Permisos</th><th>Propietario</th><th>Grupo</th><th>Tamaño</th><th>Modificado</th><th>Nombre</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($r['entradas'] as $e)
                            <tr>
                                <td class="mono">{{ $e['permisos'] }}</td>
                                <td>{{ $e['dueno'] }}</td>
                                <td>{{ $e['grupo'] }}</td>
                                <td class="num">{{ $e['tamano_txt'] }}</td>
                                <td>{{ $e['fecha'] }}</td>
                                <td>
                                    @if ($e['tipo'] === 'd')
                                        @php $hijo = $r['sub'] === '' ? $e['nombre'] : $r['sub'] . '/' . $e['nombre']; @endphp
                                        <a href="{{ route('almacenamiento.explorador', ['raiz' => $r['clave'], 'ruta' => $hijo]) }}">{{ $e['nombre'] }}/</a>
                                    @elseif ($e['tipo'] === 'l')
                                        {{ $e['nombre'] }} <span class="insignia aviso">enlace</span> → {{ $e['enlace'] }}
                                    @else
                                        {{ $e['nombre'] }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6">El directorio está vacío.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($r['truncado'])
                <p class="detalle">Se muestran solo las primeras {{ $r['maximo'] }} entradas de este directorio.</p>
            @endif
        @endif
    </section>
@endsection
