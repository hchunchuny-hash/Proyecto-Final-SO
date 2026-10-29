@extends('layouts.app')

@section('titulo', 'Planificación')
@section('subtitulo', 'Simulador de algoritmos de planificación de CPU con diagrama de Gantt')

@php
    $esAdmin = auth()->user()->role === 'admin';
    $nombres = \App\Services\Planificador::NOMBRES;
@endphp

@push('estilos')
<style>
  .dos-col{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));margin-bottom:16px}
  .tabla input{width:100%;min-width:64px;padding:6px 8px;background:var(--fondo);color:var(--texto);border:1px solid var(--borde);border-radius:var(--radio);font:inherit}
  .tabla input:focus-visible{outline:2px solid var(--acento);outline-offset:1px}
  .acciones{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}
  .btn.mini{padding:4px 10px}
  .sep{margin-bottom:16px}
  .caja-gantt{overflow-x:auto;padding-bottom:6px}
  .gantt{display:flex;border:1px solid var(--borde);min-width:100%}
  .gantt .g{height:44px;display:flex;align-items:center;justify-content:center;font:700 12px var(--mono);color:#13100d;border-right:1px solid #13100d;overflow:hidden}
  .gantt-t{display:flex;margin-top:4px;font:11px var(--mono);color:var(--suave)}
  .gantt-t span{overflow:visible;white-space:nowrap}
  .vacio{color:var(--suave);font-size:13px}
  .nota-rol{margin:12px 0 0;color:var(--suave);font-size:12px}
</style>
@endpush

@section('contenido')
    <div class="dos-col">
        <section class="tarjeta">
            <h2>Configuración</h2>
            <p class="nota">Prioridades: un número menor significa mayor prioridad</p>

            <label class="campo" for="alg">Algoritmo
                <select id="alg">
                    @foreach ($nombres as $codigo => $nombre)
                        <option value="{{ $codigo }}">{{ $nombre }}</option>
                    @endforeach
                </select>
            </label>
            <label class="campo" for="q">Quantum (solo Round Robin)
                <input id="q" type="number" min="1" max="100" value="4">
            </label>

            <div class="acciones">
                <button type="button" class="btn" id="btn-simular">Simular</button>
                <button type="button" class="btn sec" id="btn-reiniciar">Reiniciar</button>
            </div>
            <p class="error" id="error" role="alert" hidden></p>
            <p class="nota-rol">
                @if ($esAdmin)
                    Cada simulación se guarda automáticamente y queda registrada en la bitácora.
                @else
                    Como Observador puedes simular y consultar las simulaciones guardadas, pero solo el Administrador las guarda.
                @endif
            </p>
        </section>

        <section class="tarjeta">
            <h2>Procesos</h2>
            <p class="nota">Ingreso manual o aleatorio (máximo 20 procesos)</p>
            <div class="caja-tabla">
                <table class="tabla">
                    <thead><tr><th>PID</th><th>Llegada</th><th>Ráfaga</th><th>Prioridad</th><th></th></tr></thead>
                    <tbody id="procesos"></tbody>
                </table>
            </div>
            <div class="acciones">
                <button type="button" class="btn sec" id="btn-agregar">+ Agregar proceso</button>
                <button type="button" class="btn sec" id="btn-aleatorio">Generar aleatorio</button>
            </div>
        </section>
    </div>

    <section class="tarjeta sep">
        <h2>Diagrama de Gantt</h2>
        <p class="nota" id="gantt-titulo">Pulsa «Simular» para ver el diagrama.</p>
        <div class="caja-gantt">
            <div class="gantt" id="gantt" hidden></div>
            <div class="gantt-t" id="tiempos"></div>
        </div>
    </section>

    <div class="rejilla sep">
        <section class="tarjeta"><h2>Espera promedio</h2><p class="nota">Unidades de tiempo</p><div class="dato" id="k-espera">--</div></section>
        <section class="tarjeta"><h2>Retorno promedio</h2><p class="nota">Unidades de tiempo</p><div class="dato" id="k-retorno">--</div></section>
        <section class="tarjeta"><h2>Cambios de contexto</h2><p class="nota">Total</p><div class="dato" id="k-cambios">--</div></section>
        <section class="tarjeta"><h2>Tiempo total</h2><p class="nota">Hasta terminar todo</p><div class="dato" id="k-total">--</div></section>
    </div>

    <section class="tarjeta sep">
        <h2>Resultados por proceso</h2>
        <p class="nota">Retorno = finalización − llegada · Espera = retorno − ráfaga</p>
        <div class="caja-tabla">
            <table class="tabla">
                <thead><tr><th>PID</th><th class="num">Llegada</th><th class="num">Ráfaga</th><th class="num">Finalización</th><th class="num">Retorno</th><th class="num">Espera</th></tr></thead>
                <tbody id="resultados"><tr><td colspan="6" class="vacio">Sin resultados todavía.</td></tr></tbody>
            </table>
        </div>
    </section>

    <section class="tarjeta sep">
        <h2>Tabla comparativa</h2>
        <p class="nota">Los cinco algoritmos con los mismos procesos · en verde, la menor espera promedio</p>
        <div class="caja-tabla">
            <table class="tabla">
                <thead><tr><th>Algoritmo</th><th class="num">Espera prom.</th><th class="num">Retorno prom.</th><th class="num">Cambios de contexto</th><th class="num">Tiempo total</th></tr></thead>
                <tbody id="comparativa"><tr><td colspan="5" class="vacio">Sin resultados todavía.</td></tr></tbody>
            </table>
        </div>
    </section>

    <section class="tarjeta">
        <h2>Simulaciones guardadas</h2>
        <p class="nota">Las 10 más recientes · pulsa «Ver» para cargarla</p>
        <div class="caja-tabla">
            <table class="tabla">
                <thead><tr><th class="num">#</th><th>Fecha</th><th>Algoritmo</th><th class="num">Procesos</th><th class="num">Espera prom.</th><th class="num">Retorno prom.</th><th></th></tr></thead>
                <tbody id="guardadas">
                @forelse ($guardadas as $s)
                    <tr>
                        <td class="num">{{ $s->id }}</td>
                        <td>{{ $s->created_at->timezone('America/Guatemala')->format('d/m/Y H:i') }}</td>
                        <td>{{ $nombres[$s->algoritmo] ?? $s->algoritmo }}</td>
                        <td class="num">{{ count($s->procesos) }}</td>
                        <td class="num">{{ $s->espera_promedio }}</td>
                        <td class="num">{{ $s->retorno_promedio }}</td>
                        <td><button type="button" class="btn sec mini" data-ver="{{ $s->id }}">Ver</button></td>
                    </tr>
                @empty
                    <tr id="sin-guardadas"><td colspan="7" class="vacio">Aún no hay simulaciones guardadas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
<script>
(function () {
    const URL_SIMULAR = "{{ route('planificacion.simular') }}";
    const URL_VER = "{{ url('/planificacion/simulaciones') }}";
    const TOKEN = document.querySelector('meta[name="csrf-token"]').content;
    const COLORES = ['#ffb000', '#efe8dc', '#c98a00', '#ffd98a', '#e08a2c', '#b8a98f', '#8f6a1a', '#ffcf5e'];
    const BASE = [['P1', 0, 8, 2], ['P2', 1, 4, 1], ['P3', 2, 9, 3], ['P4', 3, 5, 2]];
    const PX = 12; // ancho base de una unidad de tiempo en el Gantt

    const $ = (id) => document.getElementById(id);
    const tbody = $('procesos');

    function celda(contenido) {
        const td = document.createElement('td');
        td.append(contenido);
        return td;
    }

    function agregarFila(datos) {
        const tr = document.createElement('tr');
        const nombres = ['PID', 'Llegada', 'Ráfaga', 'Prioridad'];
        ['text', 'number', 'number', 'number'].forEach((tipo, i) => {
            const inp = document.createElement('input');
            inp.type = tipo;
            inp.value = datos[i];
            inp.setAttribute('aria-label', nombres[i]);
            if (tipo === 'number') { inp.min = i === 2 ? 1 : 0; inp.step = 1; }
            if (tipo === 'text') inp.maxLength = 10;
            tr.append(celda(inp));
        });
        const quitar = document.createElement('button');
        quitar.type = 'button';
        quitar.className = 'btn sec mini';
        quitar.textContent = '×';
        quitar.setAttribute('aria-label', 'Quitar proceso');
        quitar.onclick = () => tr.remove();
        tr.append(celda(quitar));
        tbody.append(tr);
    }

    function cargarProcesos(lista) {
        tbody.replaceChildren();
        lista.forEach((p) => agregarFila([p.pid, p.llegada, p.rafaga, p.prioridad ?? 0]));
    }

    function siguientePid() {
        const usados = new Set([...tbody.querySelectorAll('tr')].map((tr) => tr.querySelector('input').value.trim()));
        let k = 1;
        while (usados.has('P' + k)) k++;
        return 'P' + k;
    }

    const azar = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min;

    function aleatorio() {
        const n = azar(4, 6);
        cargarProcesos(Array.from({ length: n }, (_, i) => ({
            pid: 'P' + (i + 1), llegada: azar(0, 10), rafaga: azar(1, 12), prioridad: azar(1, 5),
        })));
        limpiar();
    }

    function mostrarError(msg) {
        const e = $('error');
        e.textContent = msg;
        e.hidden = !msg;
    }

    function limpiar() {
        mostrarError('');
        $('gantt').hidden = true;
        $('gantt').replaceChildren();
        $('tiempos').replaceChildren();
        $('gantt-titulo').textContent = 'Pulsa «Simular» para ver el diagrama.';
        ['k-espera', 'k-retorno', 'k-cambios', 'k-total'].forEach((id) => ($(id).textContent = '--'));
        $('resultados').innerHTML = '<tr><td colspan="6" class="vacio">Sin resultados todavía.</td></tr>';
        $('comparativa').innerHTML = '<tr><td colspan="5" class="vacio">Sin resultados todavía.</td></tr>';
    }

    function reiniciar() {
        cargarProcesos(BASE.map((f) => ({ pid: f[0], llegada: f[1], rafaga: f[2], prioridad: f[3] })));
        $('alg').value = 'RR';
        $('q').value = 4;
        $('q').disabled = false;
        limpiar();
    }

    function fila(valores, clasesNum) {
        const tr = document.createElement('tr');
        valores.forEach((v, i) => {
            const td = document.createElement('td');
            if (clasesNum.includes(i)) td.className = 'num';
            td.textContent = v;
            tr.append(td);
        });
        return tr;
    }

    function pintar(d, algoritmo) {
        const pids = [...new Set(d.procesos.map((p) => p.pid))];
        const gantt = $('gantt');
        const tiempos = $('tiempos');
        gantt.replaceChildren();
        tiempos.replaceChildren();

        d.segmentos.forEach((s) => {
            const dur = s.fin - s.inicio;
            const flex = dur + ' 0 ' + (dur * PX) + 'px';

            const bloque = document.createElement('div');
            bloque.className = 'g';
            bloque.style.flex = flex;
            bloque.style.background = s.pid === null ? 'var(--panel2)' : COLORES[pids.indexOf(s.pid) % COLORES.length];
            bloque.style.color = s.pid === null ? 'var(--suave)' : '#13100d';
            bloque.textContent = s.pid === null ? '—' : s.pid;
            bloque.title = (s.pid ?? 'Ocioso') + ': ' + s.inicio + ' → ' + s.fin;
            gantt.append(bloque);

            const marca = document.createElement('span');
            marca.style.flex = flex;
            marca.textContent = s.inicio;
            tiempos.append(marca);
        });
        const final = document.createElement('span');
        final.textContent = d.tiempo_total;
        tiempos.append(final);
        gantt.hidden = false;
        $('gantt-titulo').textContent = 'Algoritmo: ' + (d.comparativa.find((c) => c.algoritmo === algoritmo)?.nombre ?? algoritmo);

        $('k-espera').textContent = d.espera_promedio;
        $('k-retorno').textContent = d.retorno_promedio;
        $('k-cambios').textContent = d.cambios_contexto;
        $('k-total').textContent = d.tiempo_total;

        $('resultados').replaceChildren(...d.procesos.map((p) =>
            fila([p.pid, p.llegada, p.rafaga, p.finalizacion, p.retorno, p.espera], [1, 2, 3, 4, 5])));

        const mejor = Math.min(...d.comparativa.map((c) => c.espera_promedio));
        $('comparativa').replaceChildren(...d.comparativa.map((c) => {
            const tr = fila([c.nombre, c.espera_promedio, c.retorno_promedio, c.cambios_contexto, c.tiempo_total], [1, 2, 3, 4]);
            if (c.espera_promedio === mejor) {
                const badge = document.createElement('span');
                badge.className = 'insignia ok';
                badge.textContent = 'MEJOR';
                badge.style.marginLeft = '8px';
                tr.firstChild.append(badge);
            }
            return tr;
        }));
    }

    function agregarGuardada(g) {
        const vacio = $('sin-guardadas');
        if (vacio) vacio.remove();
        const tr = fila([g.id, g.fecha, g.nombre, g.cantidad, g.espera, g.retorno], [0, 3, 4, 5]);
        const td = document.createElement('td');
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn sec mini';
        b.textContent = 'Ver';
        b.dataset.ver = g.id;
        td.append(b);
        tr.append(td);
        $('guardadas').prepend(tr);
    }

    function leerProcesos() {
        return [...tbody.querySelectorAll('tr')].map((tr) => {
            const v = [...tr.querySelectorAll('input')].map((i) => i.value.trim());
            return { pid: v[0], llegada: v[1], rafaga: v[2], prioridad: v[3] === '' ? 0 : v[3] };
        });
    }

    async function simular() {
        mostrarError('');
        const algoritmo = $('alg').value;
        try {
            const r = await fetch(URL_SIMULAR, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': TOKEN },
                body: JSON.stringify({ algoritmo, quantum: $('q').value || 4, procesos: leerProcesos() }),
            });
            const d = await r.json();
            if (!r.ok) {
                const detalle = d.errors ? Object.values(d.errors).flat()[0] : d.message;
                throw new Error(detalle || 'Error al simular');
            }
            pintar(d, algoritmo);
            if (d.guardada) agregarGuardada(d.guardada);
        } catch (e) {
            mostrarError(e.message);
        }
    }

    async function ver(id) {
        mostrarError('');
        try {
            const r = await fetch(URL_VER + '/' + encodeURIComponent(id), { headers: { Accept: 'application/json' } });
            const d = await r.json();
            if (!r.ok) throw new Error(d.message || 'No se pudo cargar la simulación');
            cargarProcesos(d.procesos);
            $('alg').value = d.algoritmo;
            $('q').value = d.quantum ?? 4;
            $('q').disabled = d.algoritmo !== 'RR';
            pintar(d.resultado, d.algoritmo);
        } catch (e) {
            mostrarError(e.message);
        }
    }

    $('alg').addEventListener('change', () => ($('q').disabled = $('alg').value !== 'RR'));
    $('btn-agregar').addEventListener('click', () => agregarFila([siguientePid(), 0, 1, 1]));
    $('btn-aleatorio').addEventListener('click', aleatorio);
    $('btn-simular').addEventListener('click', simular);
    $('btn-reiniciar').addEventListener('click', reiniciar);
    $('guardadas').addEventListener('click', (ev) => {
        const b = ev.target.closest('[data-ver]');
        if (b) ver(b.dataset.ver);
    });

    reiniciar();
})();
</script>
@endpush
