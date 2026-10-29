<div class="caja-tabla">
    <table class="tabla">
        <thead>
            <tr><th>Sistema de archivos</th><th>Tamaño</th><th>Usado</th><th>Disponible</th><th>Ocupación</th><th>Montado en</th></tr>
        </thead>
        <tbody>
            @forelse ($filas as $f)
                <tr>
                    <td>{{ $f['fs'] }}</td>
                    <td class="num">{{ $f['tamano'] }}</td>
                    <td class="num">{{ $f['usado'] }}</td>
                    <td class="num">{{ $f['disponible'] }}</td>
                    <td style="min-width:160px">
                        <div class="ocupacion">
                            <div class="uso" role="img" aria-label="Ocupación {{ $f['pct'] }} por ciento"><span class="{{ $f['pct'] >= 90 ? 'alto' : '' }}" style="width: {{ $f['pct'] }}%"></span></div>
                            <b>{{ $f['pct'] }}%</b>
                        </div>
                    </td>
                    <td>{{ $f['montaje'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
