@extends('layouts.app')

@section('titulo', 'Interbloqueos')
@section('subtitulo', 'Simulador del algoritmo del banquero y detección de interbloqueos')

@push('estilos')
<style>
  .m4{display:grid;gap:16px;grid-template-columns:minmax(0,2fr) minmax(260px,1fr)}
  @media (max-width:900px){.m4{grid-template-columns:1fr}}
  .cab{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:12px}
  .cab h2{margin:0}
  .acciones{display:flex;flex-wrap:wrap;gap:8px}
  .tarjeta .tabla th,.tarjeta .tabla td{text-align:center;padding:6px 4px}
  .tabla th.grupo{border-left:1px solid var(--borde)}
  .tabla td.sep{border-left:1px solid var(--borde)}
  input.cel{width:52px;padding:5px 4px;text-align:center;background:var(--fondo);color:var(--texto);border:1px solid var(--borde);border-radius:var(--radio);font:13px var(--mono)}
  input.cel:focus-visible{outline:2px solid var(--acento);outline-offset:1px}
  .necesidad{font-family:var(--mono);color:var(--suave)}
  .necesidad.neg{color:var(--error)}
  .resultado{margin-top:16px;padding:12px 14px;border:1px solid var(--borde);border-radius:var(--radio)}
  .resultado.ok{border-color:var(--ok)}
  .resultado.mal{border-color:var(--error)}
  .secuencia{margin-top:8px;font:700 16px var(--mono)}
  .nota{color:var(--suave);font-size:12px;margin:6px 0 0}
  .seccion{margin-top:16px}
</style>
@endpush

@section('contenido')
    <div class="m4">
        <section class="tarjeta">
            <div class="cab">
                <h2>Recursos y procesos</h2>
                <div class="acciones">
                    <button type="button" class="btn sec" id="btn-agregar">+ Proceso</button>
                    <button type="button" class="btn sec" id="btn-quitar">− Proceso</button>
                    <button type="button" class="btn sec" id="btn-mas-recurso">+ Recurso</button>
                    <button type="button" class="btn sec" id="btn-menos-recurso">− Recurso</button>
                    <button type="button" class="btn sec" id="btn-ej-seguro">Ejemplo seguro</button>
                    <button type="button" class="btn sec" id="btn-ej-bloqueo">Ejemplo con interbloqueo</button>
                </div>
            </div>
            <div class="caja-tabla">
                <table class="tabla">
                    <thead>
                        <tr id="cab-grupos"></tr>
                        <tr id="cab-recursos"></tr>
                    </thead>
                    <tbody id="filas"></tbody>
                </table>
            </div>
            <p class="nota">Necesidad = Máximo − Asignación (se calcula sola). Hasta 10 procesos y 6 recursos.</p>
            <div class="error msg" id="error" role="alert"></div>
        </section>

        <section class="tarjeta">
            <h2>Recursos disponibles</h2>
            <p class="nota">Instancias libres de cada recurso.</p>
            <div class="caja-tabla">
                <table class="tabla">
                    <thead><tr><th>Recurso</th><th>Cantidad</th></tr></thead>
                    <tbody id="disponibles"></tbody>
                </table>
            </div>
            <button type="button" class="btn bloque" id="btn-banquero">▶ Ejecutar algoritmo del banquero</button>

            <div class="resultado" id="res-banquero" hidden>
                <strong>Resultado</strong>
                <div style="margin-top:8px">Estado: <span class="insignia" id="b-estado"></span></div>
                <div class="secuencia" id="b-secuencia"></div>
            </div>
        </section>
    </div>

    <section class="tarjeta seccion" id="sec-pasos" hidden>
        <h2>Paso a paso del algoritmo</h2>
        <p class="nota" id="pasos-nota"></p>
        <div class="caja-tabla">
            <table class="tabla">
                <thead><tr><th>Paso</th><th>Proceso</th><th>Necesidad ≤ Trabajo</th><th>Trabajo al terminar</th></tr></thead>
                <tbody id="pasos"></tbody>
            </table>
        </div>
    </section>

    <section class="tarjeta seccion">
        <div class="cab">
            <h2>Detección de interbloqueos</h2>
            <button type="button" class="btn sec" id="btn-detectar">⚠ Detectar interbloqueo</button>
        </div>
        <div class="error msg" id="error-det" role="alert"></div>
        <div class="vacio" id="d-vacio">Pulsa «Detectar interbloqueo» para analizar el estado actual.</div>
        <div id="res-detectar" hidden>
            Estado: <span class="insignia" id="d-estado"></span>
            <p class="nota" id="d-detalle"></p>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    const URL_BANQUERO = "{{ route('interbloqueos.banquero') }}";
    const URL_DETECTAR = "{{ route('interbloqueos.detectar') }}";
    const TOKEN = document.querySelector('meta[name="csrf-token"]').content;
    const MAX_PROCESOS = 10;
    const MAX_RECURSOS = 6;
    const EJEMPLOS = {
        seguro: {
            disponible: [2, 1, 1],
            procesos: [[[1, 0, 1], [3, 2, 2]], [[2, 1, 0], [3, 1, 1]], [[1, 1, 0], [2, 2, 1]]],
        },
        interbloqueo: {
            disponible: [0, 0, 0],
            procesos: [[[1, 0, 0], [1, 1, 0]], [[0, 1, 0], [1, 1, 0]], [[0, 0, 1], [0, 0, 1]]],
        },
    };

    const $ = (id) => document.getElementById(id);
    const filas = $('filas');
    let recursos = 0;

    function crear(etiqueta, texto, clase) {
        const e = document.createElement(etiqueta);
        if (texto !== undefined) e.textContent = texto;
        if (clase) e.className = clase;
        return e;
    }

    function entrada(valor) {
        const i = crear('input', undefined, 'cel');
        i.type = 'number'; i.min = 0; i.max = 99;
        i.value = Number.isNaN(valor) ? '' : valor;
        i.setAttribute('aria-label', 'cantidad');
        i.addEventListener('input', recalcular);
        return i;
    }

    function celda(contenido, clase) {
        const td = crear('td', undefined, clase);
        td.append(contenido);
        return td;
    }

    function numero(i) {
        return i.value === '' ? NaN : parseInt(i.value, 10);
    }

    function encabezado() {
        const grupos = $('cab-grupos');
        const nombres = $('cab-recursos');
        grupos.replaceChildren();
        nombres.replaceChildren();
        const proceso = crear('th', 'Proceso');
        proceso.rowSpan = 2;
        grupos.append(proceso);
        ['Asignación', 'Máximo', 'Necesidad'].forEach((titulo) => {
            const th = crear('th', titulo, 'grupo');
            th.colSpan = recursos;
            grupos.append(th);
            for (let j = 0; j < recursos; j++) nombres.append(crear('th', 'R' + (j + 1), j === 0 ? 'grupo' : ''));
        });
    }

    function agregarFila(asignacion, maximo) {
        const tr = crear('tr');
        tr.append(celda('P' + (filas.children.length + 1)));
        for (let j = 0; j < recursos; j++) tr.append(celda(entrada(asignacion[j]), j === 0 ? 'sep' : ''));
        for (let j = 0; j < recursos; j++) tr.append(celda(entrada(maximo[j]), j === 0 ? 'sep' : ''));
        for (let j = 0; j < recursos; j++) tr.append(celda('0', 'necesidad' + (j === 0 ? ' sep' : '')));
        filas.append(tr);
    }

    function filaDisponible(j, valor) {
        const tr = crear('tr');
        tr.append(celda('R' + (j + 1)), celda(entrada(valor)));
        $('disponibles').append(tr);
    }

    function mostrarError(texto, id = 'error') {
        $(id).textContent = texto || '';
    }

    function limpiarResultados() {
        document.querySelectorAll('.msg').forEach((e) => { e.textContent = ''; });
        $('res-banquero').hidden = true;
        $('res-detectar').hidden = true;
        $('d-vacio').hidden = false;
        $('sec-pasos').hidden = true;
    }

    function dibujar(asignacion, maximo, disponible) {
        recursos = disponible.length;
        encabezado();
        filas.replaceChildren();
        asignacion.forEach((a, i) => agregarFila(a, maximo[i]));
        $('disponibles').replaceChildren();
        disponible.forEach((v, j) => filaDisponible(j, v));
        recalcular();
        limpiarResultados();
    }

    function leerFila(tr) {
        const valores = Array.from(tr.querySelectorAll('input'), numero);
        return { asignacion: valores.slice(0, recursos), maximo: valores.slice(recursos) };
    }

    function recalcular() {
        filas.querySelectorAll('tr').forEach((tr) => {
            const { asignacion, maximo } = leerFila(tr);
            tr.querySelectorAll('td.necesidad').forEach((td, j) => {
                const n = maximo[j] - asignacion[j];
                td.textContent = Number.isNaN(n) ? '–' : n;
                td.classList.toggle('neg', n < 0);
            });
        });
    }

    function leerDatos() {
        const asignacion = [];
        const maximo = [];
        filas.querySelectorAll('tr').forEach((tr) => {
            const f = leerFila(tr);
            asignacion.push(f.asignacion);
            maximo.push(f.maximo);
        });
        const disponible = Array.from($('disponibles').querySelectorAll('input'), numero);
        return { asignacion, maximo, disponible };
    }

    function vector(v) {
        return '(' + v.join(', ') + ')';
    }

    function mostrarPasos(pasos, disponible, seguro) {
        const cuerpo = $('pasos');
        cuerpo.replaceChildren();
        const inicio = crear('tr');
        inicio.append(celda('0'), celda('—'), celda('Trabajo = Disponible'), celda(vector(disponible)));
        cuerpo.append(inicio);
        pasos.forEach((p, k) => {
            const tr = crear('tr');
            tr.append(
                celda(String(k + 1)),
                celda('P' + (p.proceso + 1)),
                celda(vector(p.necesidad) + ' ≤ ' + vector(p.trabajo_antes)),
                celda(vector(p.trabajo))
            );
            cuerpo.append(tr);
        });
        $('pasos-nota').textContent = 'Se toma el primer proceso (desde P1) cuya Necesidad cabe en Trabajo; al terminar devuelve su Asignación.'
            + (seguro ? '' : ' El algoritmo se detiene: ningún proceso restante cabe en Trabajo.');
        $('sec-pasos').hidden = false;
    }

    function nombres(indices) {
        return indices.map((i) => 'P' + (i + 1)).join(' → ');
    }

    async function enviar(url, datos, idError = 'error') {
        mostrarError('', idError);
        const hayVacios = [...datos.asignacion.flat(), ...datos.maximo.flat(), ...datos.disponible].some(Number.isNaN);
        if (hayVacios) {
            mostrarError('Completa todas las celdas con números enteros.', idError);
            return null;
        }
        try {
            const r = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': TOKEN },
                body: JSON.stringify(datos),
            });
            const json = await r.json();
            if (!r.ok) {
                const primero = json.errors ? Object.values(json.errors)[0][0] : null;
                mostrarError(primero || json.message || 'No se pudo procesar la solicitud.', idError);
                return null;
            }
            return json;
        } catch (e) {
            mostrarError('Error de conexión con el servidor.', idError);
            return null;
        }
    }

    function ponerEstado(span, texto, clase) {
        span.textContent = texto;
        span.className = 'insignia ' + clase;
    }

    $('btn-banquero').addEventListener('click', async () => {
        const datos = leerDatos();
        const r = await enviar(URL_BANQUERO, datos);
        if (!r) return;
        const caja = $('res-banquero');
        caja.hidden = false;
        caja.className = 'resultado ' + (r.seguro ? 'ok' : 'mal');
        ponerEstado($('b-estado'), r.seguro ? 'SISTEMA SEGURO' : 'SISTEMA INSEGURO', r.seguro ? 'ok' : 'error');
        $('b-secuencia').textContent = r.seguro
            ? 'Secuencia segura: ' + nombres(r.secuencia)
            : 'No existe una secuencia segura.';
        mostrarPasos(r.pasos, datos.disponible, r.seguro);
    });

    $('btn-detectar').addEventListener('click', async () => {
        const r = await enviar(URL_DETECTAR, leerDatos(), 'error-det');
        if (!r) return;
        $('d-vacio').hidden = true;
        $('res-detectar').hidden = false;
        ponerEstado($('d-estado'), r.interbloqueo ? 'INTERBLOQUEO DETECTADO' : 'NO HAY INTERBLOQUEOS', r.interbloqueo ? 'error' : 'ok');
        $('d-detalle').textContent = r.interbloqueo
            ? 'Procesos bloqueados: ' + r.bloqueados.map((i) => 'P' + (i + 1)).join(', ') + '.'
            : 'El sistema no presenta interbloqueos en este momento.';
    });

    $('btn-agregar').addEventListener('click', () => {
        if (filas.children.length >= MAX_PROCESOS) { mostrarError('El máximo es de ' + MAX_PROCESOS + ' procesos.'); return; }
        const d = leerDatos();
        d.asignacion.push(new Array(recursos).fill(0));
        d.maximo.push(new Array(recursos).fill(0));
        dibujar(d.asignacion, d.maximo, d.disponible);
    });

    $('btn-quitar').addEventListener('click', () => {
        if (filas.children.length <= 1) { mostrarError('Debe quedar al menos un proceso.'); return; }
        const d = leerDatos();
        d.asignacion.pop();
        d.maximo.pop();
        dibujar(d.asignacion, d.maximo, d.disponible);
    });

    $('btn-mas-recurso').addEventListener('click', () => {
        if (recursos >= MAX_RECURSOS) { mostrarError('El máximo es de ' + MAX_RECURSOS + ' recursos.'); return; }
        const d = leerDatos();
        d.asignacion.forEach((f) => f.push(0));
        d.maximo.forEach((f) => f.push(0));
        d.disponible.push(0);
        dibujar(d.asignacion, d.maximo, d.disponible);
    });

    $('btn-menos-recurso').addEventListener('click', () => {
        if (recursos <= 1) { mostrarError('Debe quedar al menos un recurso.'); return; }
        const d = leerDatos();
        d.asignacion.forEach((f) => f.pop());
        d.maximo.forEach((f) => f.pop());
        d.disponible.pop();
        dibujar(d.asignacion, d.maximo, d.disponible);
    });

    function cargarEjemplo(nombre) {
        const e = EJEMPLOS[nombre];
        dibujar(e.procesos.map((p) => p[0]), e.procesos.map((p) => p[1]), e.disponible);
    }

    $('btn-ej-seguro').addEventListener('click', () => cargarEjemplo('seguro'));
    $('btn-ej-bloqueo').addEventListener('click', () => cargarEjemplo('interbloqueo'));

    cargarEjemplo('seguro');
</script>
@endpush
