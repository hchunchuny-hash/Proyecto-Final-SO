@extends('layouts.app')

@section('titulo', 'Bitácora')
@section('subtitulo', 'Registro de acciones administrativas y eventos de sesión')

@push('estilos')
<style>
  .filtros{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:16px}
  .filtros .campo{margin-top:0;min-width:200px}
  .paginas{display:flex;justify-content:space-between;margin-top:14px}
</style>
@endpush

@section('contenido')
    <form method="GET" action="{{ route('bitacora.index') }}" class="filtros">
        <label class="campo" for="q">Buscar
            <input id="q" type="text" name="q" value="{{ $texto }}" placeholder="usuario, acción o proceso">
        </label>
        <label class="campo" for="resultado">Resultado
            <select id="resultado" name="resultado">
                <option value="">Todos</option>
                <option value="exito" @selected($resultado === 'exito')>Éxito</option>
                <option value="error" @selected($resultado === 'error')>Error</option>
                <option value="denegado" @selected($resultado === 'denegado')>Denegado</option>
            </select>
        </label>
        <button type="submit" class="btn">Filtrar</button>
        <a href="{{ route('bitacora.index') }}" class="btn sec">Limpiar</a>
    </form>

    <section class="tarjeta">
        <div class="caja-tabla">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Proceso afectado</th>
                        <th>Resultado</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($registros as $r)
                    <tr>
                        <td class="num">{{ $r->created_at->timezone('America/Guatemala')->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $r->usuario }}</td>
                        <td>{{ $r->accion }}</td>
                        <td>{{ $r->objetivo ?? '—' }}</td>
                        <td>
                            <span class="insignia {{ ['exito' => 'ok', 'error' => 'error', 'denegado' => 'aviso'][$r->resultado] ?? '' }}">{{ strtoupper($r->resultado) }}</span>
                        </td>
                        <td>{{ $r->ip ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No hay registros con ese filtro.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="paginas">
            @if ($registros->previousPageUrl())
                <a class="btn sec" href="{{ $registros->previousPageUrl() }}">← Más recientes</a>
            @else
                <span></span>
            @endif
            @if ($registros->nextPageUrl())
                <a class="btn sec" href="{{ $registros->nextPageUrl() }}">Más antiguos →</a>
            @endif
        </div>
    </section>
@endsection
