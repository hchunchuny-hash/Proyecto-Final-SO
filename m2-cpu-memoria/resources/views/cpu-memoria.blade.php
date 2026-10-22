@extends('layouts.app')

@section('titulo', 'CPU y Memoria')
@section('subtitulo', 'Uso del procesador por núcleo, carga del sistema y distribución de la RAM')

@push('estilos')
<style>
  .grafica{position:relative;height:220px}
  .nucleo{display:flex;align-items:center;gap:10px;margin-bottom:8px;font:12px var(--mono)}
  .nucleo .nombre{width:48px;color:var(--suave)}
  .nucleo .uso{flex:1}
  .nucleo .pct{width:48px;text-align:right}
  .leyenda{list-style:none;margin:12px 0 0;padding:0;font:12px var(--mono)}
  .leyenda li{display:flex;align-items:center;gap:8px;margin-bottom:4px}
  .leyenda li span:nth-child(2){flex:1;color:var(--suave)}
  .cubo{width:10px;height:10px;display:inline-block}
  .dos-col{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));margin-top:16px}
  .sub{display:block;margin-top:4px;color:var(--suave);font-size:12px;overflow-wrap:anywhere}
</style>
@endpush

@section('contenido')
    <p class="error" id="error" role="alert" hidden></p>

    <div class="rejilla">
        <section class="tarjeta">
            <h2>Uso de CPU</h2>
            <p class="nota">Total · dos lecturas de /proc/stat</p>
            <div class="dato" id="kpi-cpu">--<small>%</small></div>
        </section>
        <section class="tarjeta">
            <h2>Carga promedio</h2>
            <p class="nota">1 · 5 · 15 min · /proc/loadavg</p>
            <div class="dato" id="kpi-carga">--</div>
        </section>
        <section class="tarjeta">
            <h2>Núcleos</h2>
            <p class="nota">Lógicos · /proc/cpuinfo</p>
            <div class="dato" id="kpi-nucleos">--</div>
            <span class="sub" id="kpi-modelo"></span>
        </section>
        <section class="tarjeta">
            <h2>Tiempo encendido</h2>
            <p class="nota">Desde el arranque · /proc/uptime</p>
            <div class="dato" id="kpi-uptime">--</div>
        </section>
    </div>

    <div class="dos-col">
        <section class="tarjeta">
            <h2>Uso por núcleo</h2>
            <p class="nota">Se actualiza cada 3 segundos</p>
            <div id="nucleos"></div>
        </section>
        <section class="tarjeta">
            <h2>Historial de CPU</h2>
            <p class="nota">Últimos 60 segundos</p>
            <div class="grafica"><canvas id="graf-cpu" aria-label="Historial de uso de CPU" role="img"></canvas></div>
        </section>
    </div>

    <div class="dos-col">
        <section class="tarjeta">
            <h2>Memoria RAM</h2>
            <p class="nota">Distribución · /proc/meminfo</p>
            <div class="grafica"><canvas id="graf-mem" aria-label="Distribución de la memoria" role="img"></canvas></div>
            <ul class="leyenda">
                <li><i class="cubo" style="background:var(--acento)"></i><span>Usada</span><span class="num" id="m-usada">--</span></li>
                <li><i class="cubo" style="background:#7a5a1c"></i><span>Caché y buffers</span><span class="num" id="m-cache">--</span></li>
                <li><i class="cubo" style="background:var(--borde)"></i><span>Libre</span><span class="num" id="m-libre">--</span></li>
                <li><i class="cubo" style="background:transparent"></i><span>Total</span><span class="num" id="m-total">--</span></li>
            </ul>
        </section>
        <section class="tarjeta">
            <h2>Memoria de intercambio (swap)</h2>
            <p class="nota">En uso · /proc/meminfo</p>
            <div class="dato" id="swap-pct">--<small>%</small></div>
            <div class="uso" role="img" aria-label="Swap en uso"><span id="swap-barra" style="width:0%"></span></div>
            <span class="sub" id="swap-detalle"></span>
        </section>
    </div>

    <div class="dos-col">
        <section class="tarjeta">
            <h2>Top 5 por CPU</h2>
            <p class="nota">Procesos con más uso de CPU · ps</p>
            <div class="caja-tabla">
                <table class="tabla">
                    <thead><tr><th>PID</th><th>Usuario</th><th>Comando</th><th class="num">% CPU</th></tr></thead>
                    <tbody id="top-cpu"><tr><td colspan="4">Cargando…</td></tr></tbody>
                </table>
            </div>
        </section>
        <section class="tarjeta">
            <h2>Top 5 por memoria</h2>
            <p class="nota">Procesos con más uso de RAM · ps</p>
            <div class="caja-tabla">
                <table class="tabla">
                    <thead><tr><th>PID</th><th>Usuario</th><th>Comando</th><th class="num">% MEM</th></tr></thead>
                    <tbody id="top-mem"><tr><td colspan="4">Cargando…</td></tr></tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    const URL_DATOS = "{{ route('memoria.datos') }}";
    const INTERVALO_MS = 3000;
    const MAX_PUNTOS = 20; // 20 muestras x 3 s = 60 s

    const $ = (id) => document.getElementById(id);
    const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
    const fmt = (n) => Number(n).toFixed(2);

    let grafCpu = null;
    let grafMem = null;
    const historial = [];

    function crearGraficas() {
        if (typeof Chart === 'undefined') {
            mostrarError('No se pudo cargar Chart.js (se necesita internet para cdnjs.cloudflare.com). Las gráficas no se mostrarán.');
            return;
        }
        Chart.defaults.color = css('--suave');
        Chart.defaults.borderColor = css('--borde');

        grafCpu = new Chart($('graf-cpu'), {
            type: 'line',
            data: { labels: [], datasets: [{ data: [], borderColor: css('--acento'), backgroundColor: 'rgba(255,176,0,.15)', fill: true, tension: .25, pointRadius: 0 }] },
            options: {
                maintainAspectRatio: false, animation: false,
                plugins: { legend: { display: false } },
                scales: { y: { min: 0, max: 100, ticks: { callback: (v) => v + '%' } }, x: { ticks: { maxTicksLimit: 6 } } },
            },
        });

        grafMem = new Chart($('graf-mem'), {
            type: 'doughnut',
            data: { labels: ['Usada', 'Caché y buffers', 'Libre'], datasets: [{ data: [0, 0, 0], backgroundColor: [css('--acento'), '#7a5a1c', css('--borde')], borderColor: css('--panel') }] },
            options: { maintainAspectRatio: false, animation: false, plugins: { legend: { display: false } } },
        });
    }

    function mostrarError(msg) {
        const e = $('error');
        e.textContent = msg;
        e.hidden = !msg;
    }

    function formatoUptime(s) {
        const d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60);
        return d + 'd ' + h + 'h ' + m + 'm';
    }

    function pintarNucleos(lista) {
        const cont = $('nucleos');
        if (cont.children.length !== lista.length) {
            cont.replaceChildren(...lista.map(() => {
                const fila = document.createElement('div');
                fila.className = 'nucleo';
                fila.innerHTML = '<span class="nombre"></span><div class="uso"><span style="width:0%"></span></div><span class="pct num"></span>';
                return fila;
            }));
        }
        lista.forEach((n, i) => {
            const fila = cont.children[i];
            fila.querySelector('.nombre').textContent = n.nombre;
            fila.querySelector('.uso span').style.width = n.uso + '%';
            fila.querySelector('.pct').textContent = Math.round(n.uso) + '%';
        });
    }

    function pintarTop(idCuerpo, filas, campo) {
        const cuerpo = $(idCuerpo);
        if (!filas.length) {
            cuerpo.innerHTML = '<tr><td colspan="4">Sin datos.</td></tr>';
            return;
        }
        cuerpo.replaceChildren(...filas.map((p) => {
            const tr = document.createElement('tr');
            [p.pid, p.usuario, p.comando, p[campo].toFixed(1)].forEach((v, i) => {
                const td = document.createElement('td');
                if (i === 3 || i === 0) td.className = 'num';
                td.textContent = v;
                tr.append(td);
            });
            return tr;
        }));
    }

    function pintar(d) {
        $('kpi-cpu').firstChild.textContent = Math.round(d.cpu.total);
        $('kpi-carga').textContent = fmt(d.carga.m1) + ' · ' + fmt(d.carga.m5) + ' · ' + fmt(d.carga.m15);
        $('kpi-nucleos').textContent = d.cpu.logicos;
        $('kpi-modelo').textContent = d.modelo;
        $('kpi-uptime').textContent = formatoUptime(d.uptime);

        pintarNucleos(d.cpu.nucleos);

        const m = d.memoria;
        $('m-usada').textContent = fmt(m.usada_gb) + ' GB';
        $('m-cache').textContent = fmt(m.cache_gb) + ' GB';
        $('m-libre').textContent = fmt(m.libre_gb) + ' GB';
        $('m-total').textContent = fmt(m.total_gb) + ' GB';
        $('swap-pct').firstChild.textContent = Math.round(m.swap_pct);
        $('swap-barra').style.width = m.swap_pct + '%';
        $('swap-detalle').textContent = m.swap_total_gb > 0
            ? fmt(m.swap_usada_gb) + ' GB de ' + fmt(m.swap_total_gb) + ' GB'
            : 'Este sistema no tiene swap configurado.';

        pintarTop('top-cpu', d.top.cpu, 'cpu');
        pintarTop('top-mem', d.top.memoria, 'mem');

        if (grafCpu) {
            historial.push(d.cpu.total);
            if (historial.length > MAX_PUNTOS) historial.shift();
            grafCpu.data.labels = historial.map((_, i) => '-' + ((historial.length - 1 - i) * INTERVALO_MS / 1000) + 's');
            grafCpu.data.datasets[0].data = historial.slice();
            grafCpu.update('none');
        }
        if (grafMem) {
            grafMem.data.datasets[0].data = [m.usada_gb, m.cache_gb, m.libre_gb];
            grafMem.update('none');
        }
    }

    async function actualizar() {
        try {
            const r = await fetch(URL_DATOS, { headers: { Accept: 'application/json' } });
            const d = await r.json();
            if (!r.ok) throw new Error(d.message || 'Error ' + r.status);
            if (typeof Chart !== 'undefined') mostrarError('');
            pintar(d);
        } catch (e) {
            mostrarError('No se pudieron leer los datos del sistema: ' + e.message);
        }
    }

    crearGraficas();
    actualizar();
    setInterval(actualizar, INTERVALO_MS);
})();
</script>
@endpush
