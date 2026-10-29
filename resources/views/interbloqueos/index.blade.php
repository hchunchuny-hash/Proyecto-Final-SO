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
  #sol-proceso{background:var(--fondo);color:var(--texto);border:1px solid var(--borde);border-radius:var(--radio);padding:5px 8px;font:13px var(--mono)}
  #grafo{display:block;max-width:100%;height:auto;margin:0 auto}
  .campos-sol{display:flex;flex-wrap:wrap;gap:8px;align-items:center;font:13px var(--mono);color:var(--suave)}
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
        <h2>Solicitud de recursos</h2>
        <p class="nota">Elige un proceso y cuánto pide de cada recurso. Se simula la asignación y se corre el banquero para decidir si se concede.</p>
        <div class="campos-sol">
            <label for="sol-proceso">Proceso</label>
            <select id="sol-proceso"></select>
            <span class="campos-sol" id="sol-campos"></span>
            <button type="button" class="btn sec" id="btn-solicitar">Evaluar solicitud</button>
        </div>
        <div class="error msg" id="error-sol" role="alert"></div>
        <div class="resultado" id="res-solicitud" hidden>
            Decisión: <span class="insignia" id="s-estado"></span>
            <p class="nota" id="s-motivo"></p>
            <div class="secuencia" id="s-secuencia"></div>
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

    <section class="tarjeta seccion">
        <div class="cab">
            <h2>Grafo de asignación de recursos</h2>
            <button type="button" class="btn sec" id="btn-grafo">Dibujar grafo</button>
        </div>
        <p class="nota">Círculos: procesos. Cuadrados: recursos. Línea continua R → P: instancias asignadas. Línea punteada P → R: el proceso espera ese recurso (necesita más de lo disponible).</p>
        <div class="error msg" id="error-grafo" role="alert"></div>
        <div class="caja-tabla" id="grafo-caja" hidden>
            <svg id="grafo" role="img" aria-label="Grafo de asignación de recursos"></svg>
        </div>
        <div id="grafo-res" hidden>
            Estado: <span class="insignia" id="g-estado"></span>
            <p class="nota" id="g-ciclo"></p>
            <div id="g-recuperacion"></div>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    const URL_BANQUERO = "{{ route('interbloqueos.banquero') }}";
    const URL_DETECTAR = "{{ route('interbloqueos.detectar') }}";
    const URL_SOLICITUD = "{{ route('interbloqueos.solicitud') }}";
    const URL_GRAFO = "{{ route('interbloqueos.grafo') }}";
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
        $('res-solicitud').hidden = true;
        $('grafo-caja').hidden = true;
        $('grafo-res').hidden = true;
    }

    function dibujar(asignacion, maximo, disponible) {
        recursos = disponible.length;
        encabezado();
        filas.replaceChildren();
        asignacion.forEach((a, i) => agregarFila(a, maximo[i]));
        $('disponibles').replaceChildren();
        disponible.forEach((v, j) => filaDisponible(j, v));
        recalcular();
        actualizarSolicitud();
        limpiarResultados();
    }

    function actualizarSolicitud() {
        const lista = $('sol-proceso');
        lista.replaceChildren();
        for (let i = 0; i < filas.children.length; i++) lista.append(new Option('P' + (i + 1), i));
        const campos = $('sol-campos');
        campos.replaceChildren();
        for (let j = 0; j < recursos; j++) campos.append(crear('span', 'R' + (j + 1)), entrada(0));
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

    $('btn-solicitar').addEventListener('click', async () => {
        const datos = leerDatos();
        datos.proceso = parseInt($('sol-proceso').value, 10);
        datos.solicitud = Array.from($('sol-campos').querySelectorAll('input'), numero);
        if (datos.solicitud.some(Number.isNaN)) {
            mostrarError('Completa la solicitud con números enteros.', 'error-sol');
            return;
        }
        const r = await enviar(URL_SOLICITUD, datos, 'error-sol');
        if (!r) return;
        const textos = {
            concedida: ['SOLICITUD CONCEDIDA', 'ok', 'ok'],
            negada: ['SOLICITUD NEGADA', 'error', 'mal'],
            espera: ['EL PROCESO DEBE ESPERAR', 'aviso', ''],
            error: ['SOLICITUD INVÁLIDA', 'error', 'mal'],
        };
        const [texto, clase, borde] = textos[r.decision];
        const caja = $('res-solicitud');
        caja.hidden = false;
        caja.className = 'resultado ' + borde;
        ponerEstado($('s-estado'), texto, clase);
        $('s-motivo').textContent = r.motivo;
        $('s-secuencia').textContent = r.secuencia.length ? 'Secuencia segura: ' + nombres(r.secuencia) : '';
    });

    const SVG = 'http://www.w3.org/2000/svg';

    function svg(etiqueta, atributos, texto) {
        const e = document.createElementNS(SVG, etiqueta);
        Object.entries(atributos).forEach(([k, v]) => e.setAttribute(k, v));
        if (texto !== undefined) e.textContent = texto;
        return e;
    }

    function nombreNodo(id, procesos) {
        return id < procesos ? 'P' + (id + 1) : 'R' + (id - procesos + 1);
    }

    function flecha(id, color) {
        const m = svg('marker', { id, viewBox: '0 0 10 10', refX: '9', refY: '5', markerWidth: '7', markerHeight: '7', orient: 'auto-start-reverse' });
        m.append(svg('path', { d: 'M 0 0 L 10 5 L 0 10 z', style: 'fill:' + color }));
        return m;
    }

    function dibujarGrafo(g, disponible) {
        const lienzo = $('grafo');
        lienzo.replaceChildren();
        const ancho = Math.max(360, Math.max(g.procesos, g.recursos) * 110 + 60);
        const alto = 280;
        lienzo.setAttribute('viewBox', '0 0 ' + ancho + ' ' + alto);
        lienzo.setAttribute('width', ancho);

        const defs = svg('defs', {});
        defs.append(flecha('f-asig', 'var(--ok)'), flecha('f-sol', 'var(--acento)'), flecha('f-ciclo', 'var(--error)'));
        lienzo.append(defs);

        const posicion = (id) => (id < g.procesos
            ? { x: ancho * (id + 1) / (g.procesos + 1), y: 50 }
            : { x: ancho * (id - g.procesos + 1) / (g.recursos + 1), y: alto - 60 });

        const enCiclo = new Set(g.ciclo.map((de, k) => de + '>' + g.ciclo[(k + 1) % g.ciclo.length]));

        g.aristas.forEach((a) => {
            const desde = posicion(a.de);
            const hasta = posicion(a.a);
            const dx = hasta.x - desde.x;
            const dy = hasta.y - desde.y;
            const largo = Math.hypot(dx, dy) || 1;
            const ux = dx / largo;
            const uy = dy / largo;
            const lado = a.tipo === 'asignacion' ? 5 : -5;
            const ox = -uy * lado;
            const oy = ux * lado;
            const ciclo = enCiclo.has(a.de + '>' + a.a);
            const color = ciclo ? 'var(--error)' : (a.tipo === 'asignacion' ? 'var(--ok)' : 'var(--acento)');
            const marca = ciclo ? 'f-ciclo' : (a.tipo === 'asignacion' ? 'f-asig' : 'f-sol');
            const x1 = desde.x + ux * 24 + ox;
            const y1 = desde.y + uy * 24 + oy;
            const x2 = hasta.x - ux * 28 + ox;
            const y2 = hasta.y - uy * 28 + oy;
            lienzo.append(svg('line', {
                x1, y1, x2, y2,
                'marker-end': 'url(#' + marca + ')',
                'stroke-dasharray': a.tipo === 'solicitud' ? '6 4' : '0',
                style: 'stroke:' + color + ';stroke-width:' + (ciclo ? 3 : 1.5),
            }));
            lienzo.append(svg('text', {
                x: (x1 + x2) / 2 + ox * 2, y: (y1 + y2) / 2 + oy * 2,
                style: 'fill:var(--suave);font:11px var(--mono);text-anchor:middle',
            }, String(a.cantidad)));
        });

        const nodosCiclo = new Set(g.ciclo);
        for (let id = 0; id < g.procesos + g.recursos; id++) {
            const { x, y } = posicion(id);
            const borde = nodosCiclo.has(id) ? 'var(--error)' : 'var(--acento)';
            const estilo = 'fill:var(--panel2);stroke:' + borde + ';stroke-width:2';
            if (id < g.procesos) {
                lienzo.append(svg('circle', { cx: x, cy: y, r: 22, style: estilo }));
            } else {
                lienzo.append(svg('rect', { x: x - 20, y: y - 20, width: 40, height: 40, style: estilo }));
                lienzo.append(svg('text', {
                    x, y: y + 36, style: 'fill:var(--suave);font:11px var(--mono);text-anchor:middle',
                }, 'disp. ' + disponible[id - g.procesos]));
            }
            lienzo.append(svg('text', {
                x, y, style: 'fill:var(--texto);font:700 13px var(--mono);text-anchor:middle;dominant-baseline:central',
            }, nombreNodo(id, g.procesos)));
        }
    }

    function mostrarRecuperacion(g) {
        const caja = $('g-recuperacion');
        caja.replaceChildren();
        if (!g.sugerencia) return;
        const { terminar, apropiar } = g.sugerencia;
        caja.append(crear('p', 'Opción 1 · Terminar P' + (terminar.proceso + 1)
            + ': libera ' + vector(terminar.liberaria) + ' y rompe el ciclo.'));
        if (apropiar) {
            caja.append(crear('p', 'Opción 2 · Apropiar: quitarle ' + apropiar.cantidad + ' instancia(s) de R'
                + (apropiar.recurso + 1) + ' a P' + (apropiar.proceso + 1) + ' para que otro proceso del ciclo pueda avanzar.'));
        }
    }

    $('btn-grafo').addEventListener('click', async () => {
        const datos = leerDatos();
        const g = await enviar(URL_GRAFO, datos, 'error-grafo');
        if (!g) return;
        dibujarGrafo(g, datos.disponible);
        $('grafo-caja').hidden = false;
        $('grafo-res').hidden = false;
        ponerEstado($('g-estado'), g.hayCiclo ? 'CICLO DETECTADO' : 'NO HAY CICLOS', g.hayCiclo ? 'error' : 'ok');
        $('g-ciclo').textContent = g.hayCiclo
            ? 'Ciclo: ' + g.ciclo.concat(g.ciclo[0]).map((id) => nombreNodo(id, g.procesos)).join(' → ')
              + '. Con varias instancias por recurso un ciclo no siempre es interbloqueo: confírmalo con el banquero.'
            : 'El grafo no tiene ciclos: ningún grupo de procesos se espera en círculo.';
        mostrarRecuperacion(g);
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
