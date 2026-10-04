@extends('layouts.app')

@section('titulo', 'Planificación')

@section('contenido')
    <div>
        <h1 class="titulo">Planificación</h1>
        <div class="subtitulo">Simulación de algoritmos de planificación de CPU</div>
    </div>

    <div class="grid-2">
        <div class="card col">
            <div class="lbl">Configuración</div>
            <div class="f">
                <label for="alg">Algoritmo</label>
                <select id="alg" class="in">
                    <option value="RR">Round Robin</option>
                    <option value="FCFS">FCFS</option>
                    <option value="SJF">SJF</option>
                    <option value="PRIORIDAD">Prioridad</option>
                </select>
            </div>
            <div class="f">
                <label for="q">Quantum (ms)</label>
                <input id="q" class="in" type="number" min="1" value="4">
            </div>
            <div class="src">En Prioridad, un número menor significa mayor prioridad. SJF y Prioridad son no expulsivos.</div>
            <div class="acciones">
                <button type="button" class="btn" id="btn-simular">Simular</button>
                <button type="button" class="btn ghost" id="btn-reiniciar">Reiniciar</button>
            </div>
            <div class="error" id="error" role="alert"></div>
        </div>

        <div class="card">
            <div class="lbl" style="margin-bottom:10px">Procesos</div>
            <div class="tabla-scroll">
                <table>
                    <thead><tr><th>PID</th><th>Llegada</th><th>Ráfaga</th><th>Prioridad</th><th></th></tr></thead>
                    <tbody id="procesos"></tbody>
                </table>
            </div>
            <button type="button" class="btn ghost" id="btn-agregar" style="margin-top:14px">+ Agregar proceso</button>
        </div>
    </div>

    <div class="card">
        <div class="lbl" style="margin-bottom:14px">Diagrama de Gantt</div>
        <div class="vacio" id="gantt-vacio">Pulsa «Simular» para ver el diagrama.</div>
        <div class="gantt" id="gantt" hidden></div>
        <div class="t" id="tiempos"></div>
    </div>

    <div class="grid-kpi">
        <div class="card"><div class="lbl">Espera promedio</div><div class="src">ms</div><div class="valor" id="k-espera">--</div></div>
        <div class="card"><div class="lbl">Retorno promedio</div><div class="src">ms</div><div class="valor" id="k-retorno">--</div></div>
        <div class="card"><div class="lbl">Cambios de contexto</div><div class="src">Total</div><div class="valor" id="k-cambios">--</div></div>
    </div>

    <div class="card">
        <div class="lbl" style="margin-bottom:10px">Resultados por proceso</div>
        <div class="tabla-scroll">
            <table>
                <thead><tr><th>PID</th><th>Llegada</th><th>Ráfaga</th><th>Finalización</th><th>Retorno</th><th>Espera</th></tr></thead>
                <tbody id="resultados"><tr><td colspan="6" class="vacio">Sin resultados todavía.</td></tr></tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const URL_SIMULAR = "{{ route('planificacion.simular') }}";
    const TOKEN = document.querySelector('meta[name="csrf-token"]').content;
    const COLORES = ['#ffb000', '#f5ede0', '#c98a00', '#ffd98a', '#e08a2c', '#b8a98f', '#8f6a1a', '#ffcf5e'];
    const BASE = [['P1', 0, 8, 2], ['P2', 1, 4, 1], ['P3', 2, 9, 3], ['P4', 3, 5, 2]];

    const $ = (id) => document.getElementById(id);
    const tbody = $('procesos');

    function celda(contenido) {
        const td = document.createElement('td');
        td.append(contenido);
        return td;
    }

    function agregarFila(datos) {
        const tr = document.createElement('tr');
        const tipos = ['text', 'number', 'number', 'number'];
        const nombres = ['PID', 'Llegada', 'Ráfaga', 'Prioridad'];
        tipos.forEach((tipo, i) => {
            const inp = document.createElement('input');
            inp.type = tipo;
            inp.className = 'in';
            inp.value = datos[i];
            inp.setAttribute('aria-label', nombres[i]);
            if (tipo === 'number') inp.min = i === 2 ? 1 : 0;
            if (tipo === 'text') inp.maxLength = 10;
            tr.append(celda(inp));
        });
        const quitar = document.createElement('button');
        quitar.type = 'button';
        quitar.className = 'btn ghost mini';
        quitar.textContent = '×';
        quitar.setAttribute('aria-label', 'Quitar proceso');
        quitar.onclick = () => tr.remove();
        tr.append(celda(quitar));
        tbody.append(tr);
    }

    function siguientePid() {
        const usados = new Set([...tbody.querySelectorAll('tr')].map((tr) => tr.querySelector('input').value.trim()));
        let k = 1;
        while (usados.has('P' + k)) k++;
        return 'P' + k;
    }

    function limpiarResultados() {
        $('error').textContent = '';
        $('gantt').hidden = true;
        $('gantt').replaceChildren();
        $('gantt-vacio').hidden = false;
        $('tiempos').replaceChildren();
        ['k-espera', 'k-retorno', 'k-cambios'].forEach((id) => ($(id).textContent = '--'));
        $('resultados').innerHTML = '<tr><td colspan="6" class="vacio">Sin resultados todavía.</td></tr>';
    }

    function reiniciar() {
        tbody.replaceChildren();
        BASE.forEach(agregarFila);
        $('alg').value = 'RR';
        $('q').value = 4;
        $('q').disabled = false;
        limpiarResultados();
    }

    function pintar(d) {
        const pids = [...new Set(d.procesos.map((p) => p.pid))];
        const gantt = $('gantt');
        const tiempos = $('tiempos');
        gantt.replaceChildren();
        tiempos.replaceChildren();

        d.segmentos.forEach((s) => {
            const dur = s.fin - s.inicio;
            const bloque = document.createElement('div');
            bloque.className = 'g';
            bloque.style.flex = dur;
            bloque.style.background = s.pid === null ? '#2e251b' : COLORES[pids.indexOf(s.pid) % COLORES.length];
            bloque.textContent = s.pid === null ? '—' : s.pid;
            bloque.title = (s.pid ?? 'Ocioso') + ': ' + s.inicio + ' → ' + s.fin;
            gantt.append(bloque);

            const marca = document.createElement('span');
            marca.style.flex = dur;
            marca.textContent = s.inicio;
            tiempos.append(marca);
        });
        const final = document.createElement('span');
        final.textContent = d.tiempo_total;
        tiempos.append(final);

        gantt.hidden = false;
        $('gantt-vacio').hidden = true;

        $('k-espera').textContent = d.espera_promedio;
        $('k-retorno').textContent = d.retorno_promedio;
        $('k-cambios').textContent = d.cambios_contexto;

        $('resultados').replaceChildren(...d.procesos.map((p) => {
            const tr = document.createElement('tr');
            [p.pid, p.llegada, p.rafaga, p.finalizacion, p.retorno, p.espera].forEach((v) => {
                const td = document.createElement('td');
                td.textContent = v;
                tr.append(td);
            });
            return tr;
        }));
    }

    async function simular() {
        $('error').textContent = '';
        const procesos = [...tbody.querySelectorAll('tr')].map((tr) => {
            const v = [...tr.querySelectorAll('input')].map((i) => i.value.trim());
            return { pid: v[0], llegada: v[1], rafaga: v[2], prioridad: v[3] };
        });

        try {
            const r = await fetch(URL_SIMULAR, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': TOKEN },
                body: JSON.stringify({ algoritmo: $('alg').value, quantum: $('q').value, procesos }),
            });
            const d = await r.json();
            if (!r.ok) {
                const detalle = d.errors ? Object.values(d.errors).flat()[0] : d.message;
                throw new Error(detalle || 'Error al simular');
            }
            pintar(d);
        } catch (e) {
            $('error').textContent = e.message;
        }
    }

    $('alg').addEventListener('change', () => ($('q').disabled = $('alg').value !== 'RR'));
    $('btn-agregar').addEventListener('click', () => agregarFila([siguientePid(), 0, 1, 1]));
    $('btn-simular').addEventListener('click', simular);
    $('btn-reiniciar').addEventListener('click', reiniciar);

    reiniciar();
</script>
@endpush
