@extends('layouts.app')

@section('titulo', 'CPU y Memoria')
@section('subtitulo', 'Uso real de CPU, RAM, swap, carga y procesos, leído de /proc')

@push('estilos')
<style>
  .separado{margin-top:16px}
  .controles{display:flex;flex-wrap:wrap;gap:18px;align-items:center}
  .controles label{display:flex;align-items:center;gap:6px;color:var(--suave);font-size:13px}
  .controles select{padding:5px 8px;background:var(--fondo);color:var(--texto);border:1px solid var(--borde);border-radius:var(--radio);font:inherit}
  .estado{margin-left:auto;color:var(--suave);font:12px var(--mono)}
  .uso span.alto{background:var(--error)}
  .detalle{margin:8px 0 0;color:var(--suave);font-size:12px}
  .grafica{width:100%;height:auto;display:block;margin-top:6px}
  .grafica text{fill:var(--suave);font:10px var(--mono)}
  .desglose{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px}
  .desglose div{border:1px solid var(--borde);border-radius:var(--radio);padding:8px 10px;background:var(--panel2)}
  .desglose small{display:block;color:var(--suave);font-size:12px}
  .desglose strong{font:700 18px var(--mono)}
</style>
@endpush

@section('contenido')
    <section class="tarjeta">
        <h2>Actualización en vivo</h2>
        <p class="nota">Cada actualización pide una lectura nueva de /proc al servidor (AJAX con fetch).</p>
        <div class="controles">
            <label for="auto"><input type="checkbox" id="auto" checked> Actualizar automáticamente</label>
            <label for="intervalo">Cada
                <select id="intervalo">
                    <option value="2000" selected>2 segundos</option>
                    <option value="3000">3 segundos</option>
                    <option value="5000">5 segundos</option>
                </select>
            </label>
            <button type="button" class="btn sec" id="ahora">Actualizar ahora</button>
            <span class="estado" id="estado" role="status">Esperando datos…</span>
        </div>
    </section>

    <div class="rejilla separado">
        <section class="tarjeta">
            <h2>Uso de CPU</h2>
            <p class="nota">Todos los núcleos · /proc/stat</p>
            <div class="dato"><span id="cpu-uso">—</span><small>%</small></div>
            <div class="uso" role="img" aria-label="Barra de uso de CPU"><span id="cpu-barra" style="width:0%"></span></div>
        </section>

        <section class="tarjeta">
            <h2>Memoria RAM</h2>
            <p class="nota">Usada = total − disponible · /proc/meminfo</p>
            <div class="dato"><span id="ram-uso">—</span><small>%</small></div>
            <div class="uso" role="img" aria-label="Barra de uso de memoria RAM"><span id="ram-barra" style="width:0%"></span></div>
            <p class="detalle" id="ram-detalle">—</p>
        </section>

        <section class="tarjeta">
            <h2>Swap</h2>
            <p class="nota">Memoria en disco · /proc/meminfo</p>
            <div class="dato"><span id="swap-uso">—</span><small>%</small></div>
            <div class="uso" role="img" aria-label="Barra de uso de swap"><span id="swap-barra" style="width:0%"></span></div>
            <p class="detalle" id="swap-detalle">—</p>
        </section>

        <section class="tarjeta">
            <h2>Carga del sistema</h2>
            <p class="nota">Promedio de 1 minuto · /proc/loadavg</p>
            <div class="dato"><span id="carga-1">—</span></div>
            <p class="detalle" id="carga-detalle">5 min: — · 15 min: —</p>
            <p class="detalle" id="carga-procesos">—</p>
        </section>

        <section class="tarjeta">
            <h2>Tiempo encendido</h2>
            <p class="nota">Desde el arranque · /proc/uptime</p>
            <div class="dato" style="font-size:22px"><span id="encendido">—</span></div>
        </section>
    </div>

    <div class="rejilla separado" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr))">
        <section class="tarjeta">
            <h2>Gráfica de uso de CPU</h2>
            <p class="nota">Últimas 60 lecturas. Se reinicia al recargar la página.</p>
            <svg id="grafica-cpu" class="grafica" viewBox="0 0 600 160" role="img" aria-label="Gráfica del uso de CPU"></svg>
        </section>

        <section class="tarjeta">
            <h2>Gráfica de uso de memoria RAM</h2>
            <p class="nota">Últimas 60 lecturas. Se reinicia al recargar la página.</p>
            <svg id="grafica-ram" class="grafica" viewBox="0 0 600 160" role="img" aria-label="Gráfica del uso de memoria RAM"></svg>
        </section>
    </div>

    <div class="rejilla separado" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr))">
        <section class="tarjeta">
            <h2>En qué gasta el tiempo la CPU</h2>
            <p class="nota">Reparto del tiempo entre dos lecturas · /proc/stat</p>
            <div class="desglose">
                <div><small>Usuario</small><strong id="d-usuario">—</strong></div>
                <div><small>Sistema (kernel)</small><strong id="d-sistema">—</strong></div>
                <div><small>Espera de E/S</small><strong id="d-es">—</strong></div>
                <div><small>Robado</small><strong id="d-robado">—</strong></div>
                <div><small>Ocioso</small><strong id="d-ocioso">—</strong></div>
            </div>
        </section>

        <section class="tarjeta">
            <h2>Uso por núcleo</h2>
            <p class="nota">Cada procesador lógico que ve el kernel</p>
            <div class="caja-tabla">
                <table class="tabla">
                    <thead><tr><th>Núcleo</th><th>Uso</th><th>Barra</th></tr></thead>
                    <tbody id="nucleos-cuerpo">
                        <tr><td colspan="3">Esperando datos…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="rejilla separado" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr))">
        <section class="tarjeta">
            <h2>Top 5 procesos por CPU</h2>
            <p class="nota">Porcentaje de un núcleo · /proc/[pid]/stat</p>
            <div class="caja-tabla">
                <table class="tabla">
                    <thead><tr><th>PID</th><th>Comando</th><th>Usuario</th><th>CPU</th></tr></thead>
                    <tbody id="top-cpu-cuerpo">
                        <tr><td colspan="4">Esperando datos…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="tarjeta">
            <h2>Top 5 procesos por memoria</h2>
            <p class="nota">Memoria residente (RSS) · /proc/[pid]/stat y status</p>
            <div class="caja-tabla">
                <table class="tabla">
                    <thead><tr><th>PID</th><th>Comando</th><th>Usuario</th><th>Memoria</th><th>% RAM</th></tr></thead>
                    <tbody id="top-mem-cuerpo">
                        <tr><td colspan="5">Esperando datos…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="rejilla separado" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr))">
        <section class="tarjeta">
            <h2>Detalle de memoria</h2>
            <p class="nota">Valores de /proc/meminfo</p>
            <div class="caja-tabla">
                <table class="tabla">
                    <tbody>
                        <tr><th>RAM total</th><td class="num" id="m-total">—</td></tr>
                        <tr><th>RAM usada</th><td class="num" id="m-usada">—</td></tr>
                        <tr><th>RAM libre</th><td class="num" id="m-libre">—</td></tr>
                        <tr><th>En caché (búferes y archivos)</th><td class="num" id="m-cache">—</td></tr>
                        <tr><th>RAM disponible</th><td class="num" id="m-disponible">—</td></tr>
                        <tr><th>Swap total</th><td class="num" id="m-swap-total">—</td></tr>
                        <tr><th>Swap usada</th><td class="num" id="m-swap-usada">—</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="tarjeta">
            <h2>Información del procesador</h2>
            <p class="nota">/proc/cpuinfo y datos del kernel</p>
            <div class="caja-tabla">
                <table class="tabla">
                    <tbody>
                        <tr><th>Modelo</th><td>{{ $info['modelo'] }}</td></tr>
                        <tr><th>Núcleos lógicos</th><td class="num">{{ $info['nucleos'] }}</td></tr>
                        <tr><th>Arquitectura</th><td>{{ $info['arquitectura'] }}</td></tr>
                        <tr><th>Kernel</th><td>{{ $info['kernel'] }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const URL_DATOS = @json(route('memoria.datos', [], false));
    const MAX = 60;
    const NS = 'http://www.w3.org/2000/svg';
    const histCpu = [];
    const histRam = [];
    let temporizador = null;
    let cargando = false;

    const $ = function (id) { return document.getElementById(id); };
    const n1 = function (v) { return Number(v).toFixed(1); };

    function fmt(kb) {
        const mib = kb / 1024;
        return mib >= 1024 ? (mib / 1024).toFixed(2) + ' GiB' : mib.toFixed(0) + ' MiB';
    }

    function encendido(s) {
        const d = Math.floor(s / 86400);
        const h = Math.floor(s % 86400 / 3600);
        const m = Math.floor(s % 3600 / 60);
        return (d > 0 ? d + 'd ' : '') + h + 'h ' + m + 'm ' + (s % 60) + 's';
    }

    function texto(id, valor) { $(id).textContent = valor; }

    function barra(id, valor) {
        const b = $(id);
        b.style.width = Math.min(100, Math.max(0, valor)) + '%';
        b.className = valor >= 90 ? 'alto' : '';
    }

    function estado(mensaje) { texto('estado', mensaje); }

    function guardar(serie, valor) {
        serie.push(Number(valor));
        while (serie.length > MAX) { serie.shift(); }
    }

    function dibujar(idSvg, serie, color) {
        const svg = $(idSvg);
        const L = 34, R = 8, T = 8, B = 8;
        const W = 600 - L - R, H = 160 - T - B;

        while (svg.firstChild) { svg.removeChild(svg.firstChild); }

        [0, 25, 50, 75, 100].forEach(function (v) {
            const y = T + (1 - v / 100) * H;

            const g = document.createElementNS(NS, 'line');
            g.setAttribute('x1', L);
            g.setAttribute('x2', L + W);
            g.setAttribute('y1', y);
            g.setAttribute('y2', y);
            g.setAttribute('stroke-width', '1');
            g.style.stroke = 'var(--borde)';
            svg.appendChild(g);

            const t = document.createElementNS(NS, 'text');
            t.setAttribute('x', 2);
            t.setAttribute('y', y + 3);
            t.textContent = v + '%';
            svg.appendChild(t);
        });

        if (serie.length < 2) { return; }

        const puntos = serie.map(function (v, i) {
            const x = L + (MAX - serie.length + i) / (MAX - 1) * W;
            const y = T + (1 - Math.min(100, Math.max(0, v)) / 100) * H;
            return x.toFixed(1) + ',' + y.toFixed(1);
        }).join(' ');

        const l = document.createElementNS(NS, 'polyline');
        l.setAttribute('points', puntos);
        l.setAttribute('fill', 'none');
        l.setAttribute('stroke-width', '2');
        l.style.stroke = color;
        svg.appendChild(l);
    }

    function celda(tr, valor, numerica) {
        const td = document.createElement('td');
        if (numerica) { td.className = 'num'; }
        td.textContent = valor;
        tr.appendChild(td);
    }

    function llenarTabla(idCuerpo, filas, armar) {
        const cuerpo = $(idCuerpo);
        while (cuerpo.firstChild) { cuerpo.removeChild(cuerpo.firstChild); }
        filas.forEach(function (f) {
            const tr = document.createElement('tr');
            armar(tr, f);
            cuerpo.appendChild(tr);
        });
    }

    function filaNucleo(tr, n) {
        celda(tr, n.nombre.replace('cpu', 'CPU '), false);
        celda(tr, n1(n.uso) + '%', true);

        const td = document.createElement('td');
        const caja = document.createElement('div');
        caja.className = 'uso';
        const relleno = document.createElement('span');
        relleno.style.width = Math.min(100, Math.max(0, n.uso)) + '%';
        if (n.uso >= 90) { relleno.className = 'alto'; }
        caja.appendChild(relleno);
        td.appendChild(caja);
        tr.appendChild(td);
    }

    function pintar(d) {
        const c = d.cpu.total;
        const m = d.memoria;
        const k = d.carga;

        texto('cpu-uso', n1(c.uso));
        barra('cpu-barra', c.uso);

        texto('ram-uso', n1(m.pct_usada));
        barra('ram-barra', m.pct_usada);
        texto('ram-detalle', fmt(m.usada_kb) + ' usados de ' + fmt(m.total_kb));

        texto('swap-uso', n1(m.pct_swap));
        barra('swap-barra', m.pct_swap);
        texto('swap-detalle', m.swap_total_kb > 0
            ? fmt(m.swap_usada_kb) + ' usados de ' + fmt(m.swap_total_kb)
            : 'Este sistema no tiene swap configurado');

        texto('carga-1', Number(k.m1).toFixed(2));
        texto('carga-detalle', '5 min: ' + Number(k.m5).toFixed(2) + ' · 15 min: ' + Number(k.m15).toFixed(2));
        texto('carga-procesos', k.ejecutando + ' en ejecución de ' + k.procesos + ' procesos');

        texto('encendido', encendido(d.uptime_s));

        texto('d-usuario', n1(c.usuario) + '%');
        texto('d-sistema', n1(c.sistema) + '%');
        texto('d-es', n1(c.espera_es) + '%');
        texto('d-robado', n1(c.robado) + '%');
        texto('d-ocioso', n1(c.ocioso) + '%');

        llenarTabla('nucleos-cuerpo', d.cpu.nucleos, filaNucleo);

        llenarTabla('top-cpu-cuerpo', d.top.cpu, function (tr, p) {
            celda(tr, p.pid, true);
            celda(tr, p.comando, false);
            celda(tr, p.usuario, false);
            celda(tr, n1(p.cpu) + '%', true);
        });

        llenarTabla('top-mem-cuerpo', d.top.memoria, function (tr, p) {
            celda(tr, p.pid, true);
            celda(tr, p.comando, false);
            celda(tr, p.usuario, false);
            celda(tr, fmt(p.rss_kb), true);
            celda(tr, n1(p.pct) + '%', true);
        });

        texto('m-total', fmt(m.total_kb));
        texto('m-usada', fmt(m.usada_kb));
        texto('m-libre', fmt(m.libre_kb));
        texto('m-cache', fmt(m.cache_kb));
        texto('m-disponible', fmt(m.disponible_kb));
        texto('m-swap-total', fmt(m.swap_total_kb));
        texto('m-swap-usada', fmt(m.swap_usada_kb));

        guardar(histCpu, c.uso);
        guardar(histRam, m.pct_usada);
        dibujar('grafica-cpu', histCpu, 'var(--acento)');
        dibujar('grafica-ram', histRam, 'var(--ok)');
    }

    async function actualizar() {
        if (cargando) { return; }
        cargando = true;

        try {
            const r = await fetch(URL_DATOS, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (r.status === 401 || r.redirected) {
                estado('La sesión venció. Vuelve a iniciar sesión.');
                detener();
                return;
            }
            if (!r.ok) { throw new Error('HTTP ' + r.status); }

            const d = await r.json();
            pintar(d);
            estado('Última actualización: ' + d.hora);
        } catch (e) {
            estado('No se pudo actualizar (' + e.message + ')');
        } finally {
            cargando = false;
        }
    }

    function detener() {
        if (temporizador) { clearInterval(temporizador); temporizador = null; }
    }

    function iniciar() {
        detener();
        if ($('auto').checked) {
            temporizador = setInterval(function () {
                if (!document.hidden) { actualizar(); }
            }, Number($('intervalo').value));
        }
    }

    $('auto').addEventListener('change', iniciar);
    $('intervalo').addEventListener('change', iniciar);
    $('ahora').addEventListener('click', actualizar);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { actualizar(); }
    });

    dibujar('grafica-cpu', histCpu, 'var(--acento)');
    dibujar('grafica-ram', histRam, 'var(--ok)');
    actualizar();
    iniciar();
})();
</script>
@endpush
