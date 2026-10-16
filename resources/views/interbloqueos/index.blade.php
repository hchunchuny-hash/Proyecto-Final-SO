@extends('layouts.app')

@section('titulo', 'Interbloqueos')
@section('subtitulo', 'Simulador del algoritmo del banquero y detección de interbloqueos')

@push('estilos')
<style>
  .m4{display:grid;gap:16px;grid-template-columns:minmax(0,2fr) minmax(260px,1fr)}
  @media (max-width:900px){.m4{grid-template-columns:1fr}}
  .m4 .cab{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:12px}
  .m4 .cab h2{margin:0}
  .m4 .acciones{display:flex;flex-wrap:wrap;gap:8px}
  .m4 .tabla th,.m4 .tabla td{text-align:center;padding:6px 4px}
  .m4 .tabla th.grupo{border-left:1px solid var(--borde)}
  .m4 .tabla td.sep{border-left:1px solid var(--borde)}
  .m4 input.cel{width:52px;padding:5px 4px;text-align:center;background:var(--fondo);color:var(--texto);border:1px solid var(--borde);border-radius:var(--radio);font:13px var(--mono)}
  .m4 input.cel:focus-visible{outline:2px solid var(--acento);outline-offset:1px}
  .m4 .necesidad{font-family:var(--mono);color:var(--suave)}
  .m4 .necesidad.neg{color:var(--error)}
  .m4 .resultado{margin-top:16px;padding:12px 14px;border:1px solid var(--borde);border-radius:var(--radio)}
  .m4 .resultado.ok{border-color:var(--ok)}
  .m4 .resultado.mal{border-color:var(--error)}
  .m4 .secuencia{margin-top:8px;font:700 16px var(--mono)}
  .m4 .nota{color:var(--suave);font-size:12px;margin:6px 0 0}
  .deteccion{margin-top:16px}
</style>
@endpush

@section('contenido')
    <div class="m4">
        <section class="tarjeta">
            <div class="cab">
                <h2>Recursos y procesos</h2>
                <div class="acciones">
                    <button type="button" class="btn sec" id="btn-agregar">+ Agregar proceso</button>
                    <button type="button" class="btn sec" id="btn-quitar">− Quitar proceso</button>
                </div>
            </div>
            <div class="caja-tabla">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th rowspan="2">Proceso</th>
                            <th colspan="3" class="grupo">Asignación</th>
                            <th colspan="3" class="grupo">Máximo</th>
                            <th colspan="3" class="grupo">Necesidad</th>
                        </tr>
                        <tr>
                            <th class="grupo">R1</th><th>R2</th><th>R3</th>
                            <th class="grupo">R1</th><th>R2</th><th>R3</th>
                            <th class="grupo">R1</th><th>R2</th><th>R3</th>
                        </tr>
                    </thead>
                    <tbody id="filas"></tbody>
                </table>
            </div>
            <p class="nota">Necesidad = Máximo − Asignación (se calcula sola).</p>
            <div class="error" id="error" role="alert"></div>
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

    <section class="tarjeta deteccion">
        <div class="cab">
            <h2>Detección de interbloqueos</h2>
            <button type="button" class="btn sec" id="btn-detectar">⚠ Detectar interbloqueo</button>
        </div>
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
    const RECURSOS = 3;
    const BASE = [[[1, 0, 1], [3, 2, 2]], [[2, 1, 0], [3, 1, 1]], [[1, 1, 0], [2, 2, 1]]];
    const DISPONIBLE = [2, 1, 1];

    const $ = (id) => document.getElementById(id);
    const filas = $('filas');

    function entrada(valor) {
        const i = document.createElement('input');
        i.type = 'number'; i.min = 0; i.max = 99; i.value = valor; i.className = 'cel';
        i.setAttribute('aria-label', 'cantidad');
        i.addEventListener('input', recalcular);
        return i;
    }

    function celda(contenido, clase) {
        const td = document.createElement('td');
        if (clase) td.className = clase;
        td.append(contenido);
        return td;
    }

    function agregarFila(asignacion, maximo) {
        const tr = document.createElement('tr');
        tr.append(celda('P' + (filas.children.length + 1)));
        for (let j = 0; j < RECURSOS; j++) tr.append(celda(entrada(asignacion[j]), j === 0 ? 'sep' : ''));
        for (let j = 0; j < RECURSOS; j++) tr.append(celda(entrada(maximo[j]), j === 0 ? 'sep' : ''));
        for (let j = 0; j < RECURSOS; j++) tr.append(celda('0', 'necesidad ' + (j === 0 ? 'sep' : '')));
        filas.append(tr);
        recalcular();
    }

    function leerFila(tr) {
        const entradas = tr.querySelectorAll('input');
        const valores = Array.from(entradas, (i) => (i.value === '' ? NaN : parseInt(i.value, 10)));
        return { asignacion: valores.slice(0, RECURSOS), maximo: valores.slice(RECURSOS) };
    }

    function recalcular() {
        filas.querySelectorAll('tr').forEach((tr) => {
            const { asignacion, maximo } = leerFila(tr);
            const celdas = tr.querySelectorAll('td.necesidad');
            celdas.forEach((td, j) => {
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
        const disponible = Array.from($('disponibles').querySelectorAll('input'), (i) => (i.value === '' ? NaN : parseInt(i.value, 10)));
        return { asignacion, maximo, disponible };
    }

    function nombres(indices) {
        return indices.map((i) => 'P' + (i + 1)).join(' → ');
    }

    function mostrarError(texto) {
        $('error').textContent = texto || '';
    }

    async function enviar(url, datos) {
        mostrarError('');
        const hayVacios = [...datos.asignacion.flat(), ...datos.maximo.flat(), ...datos.disponible].some(Number.isNaN);
        if (hayVacios) {
            mostrarError('Completa todas las celdas con números enteros.');
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
                mostrarError(primero || json.message || 'No se pudo procesar la solicitud.');
                return null;
            }
            return json;
        } catch (e) {
            mostrarError('Error de conexión con el servidor.');
            return null;
        }
    }

    function ponerEstado(span, texto, clase) {
        span.textContent = texto;
        span.className = 'insignia ' + clase;
    }

    $('btn-banquero').addEventListener('click', async () => {
        const r = await enviar(URL_BANQUERO, leerDatos());
        if (!r) return;
        const caja = $('res-banquero');
        caja.hidden = false;
        caja.className = 'resultado ' + (r.seguro ? 'ok' : 'mal');
        ponerEstado($('b-estado'), r.seguro ? 'SISTEMA SEGURO' : 'SISTEMA INSEGURO', r.seguro ? 'ok' : 'error');
        $('b-secuencia').textContent = r.seguro
            ? 'Secuencia segura: ' + nombres(r.secuencia)
            : 'No existe una secuencia segura.';
    });

    $('btn-detectar').addEventListener('click', async () => {
        const r = await enviar(URL_DETECTAR, leerDatos());
        if (!r) return;
        $('d-vacio').hidden = true;
        $('res-detectar').hidden = false;
        ponerEstado($('d-estado'), r.interbloqueo ? 'INTERBLOQUEO DETECTADO' : 'NO HAY INTERBLOQUEOS', r.interbloqueo ? 'error' : 'ok');
        $('d-detalle').textContent = r.interbloqueo
            ? 'Procesos bloqueados: ' + r.bloqueados.map((i) => 'P' + (i + 1)).join(', ') + '.'
            : 'El sistema no presenta interbloqueos en este momento.';
    });

    $('btn-agregar').addEventListener('click', () => {
        if (filas.children.length >= 10) { mostrarError('El máximo es de 10 procesos.'); return; }
        mostrarError('');
        agregarFila([0, 0, 0], [0, 0, 0]);
    });

    $('btn-quitar').addEventListener('click', () => {
        if (filas.children.length <= 1) { mostrarError('Debe quedar al menos un proceso.'); return; }
        mostrarError('');
        filas.lastElementChild.remove();
    });

    DISPONIBLE.forEach((valor, j) => {
        const tr = document.createElement('tr');
        tr.append(celda('R' + (j + 1)), celda(entrada(valor)));
        $('disponibles').append(tr);
    });
    BASE.forEach(([a, m]) => agregarFila(a, m));
</script>
@endpush

