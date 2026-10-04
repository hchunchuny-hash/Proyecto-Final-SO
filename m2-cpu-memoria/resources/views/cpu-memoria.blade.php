@extends('layouts.app')

@section('titulo', 'CPU y Memoria')

@section('contenido')
    <div>
        <h1 class="titulo">CPU y Memoria</h1>
        <div class="subtitulo">Uso del procesador por núcleo y distribución de la RAM</div>
    </div>

    <div class="grid-kpi">
        <div class="card"><div class="lbl">Uso de CPU</div><div class="src">Total · /proc/stat</div><div class="big" id="kpi-cpu">--</div></div>
        <div class="card"><div class="lbl">Núcleos</div><div class="src">Lógicos · /proc/cpuinfo</div><div class="big" id="kpi-nucleos">--</div></div>
        <div class="card"><div class="lbl">RAM usada</div><div class="src">/proc/meminfo</div><div class="big" id="kpi-ram">--</div></div>
        <div class="card"><div class="lbl">Swap</div><div class="src">En uso · /proc/meminfo</div><div class="big" id="kpi-swap">--</div></div>
    </div>

    <div class="error" id="error" role="alert"></div>

    <div class="grid-2">
        <div class="card col">
            <div class="lbl">Uso por núcleo</div>
            <div class="col" id="nucleos"></div>
            <div class="src" style="margin-top:8px">Historial (últimos 60 s)</div>
            <svg class="grafica" viewBox="0 0 400 90" preserveAspectRatio="none" role="img" aria-label="Gráfico de uso de CPU">
                <polyline id="linea" fill="none" stroke="#ffb000" stroke-width="2" points=""></polyline>
            </svg>
        </div>

        <div class="card col">
            <div class="lbl">Distribución de memoria</div>
            <div class="barra-mem">
                <div id="bm-usada" style="width:0;background:#ffb000"></div>
                <div id="bm-cache" style="width:0;background:#7a5a1c"></div>
                <div id="bm-libre" style="width:100%;background:#2e251b"></div>
            </div>
            <div class="row"><span class="sw" style="background:#ffb000"></span><span class="grow">Usada</span><span id="m-usada">-- GB</span></div>
            <div class="row"><span class="sw" style="background:#7a5a1c"></span><span class="grow">Caché y buffers</span><span id="m-cache">-- GB</span></div>
            <div class="row"><span class="sw" style="background:#2e251b;border:1px solid #6b5a42"></span><span class="grow">Libre</span><span id="m-libre">-- GB</span></div>
            <div class="row total"><span class="grow">Total</span><span id="m-total">-- GB</span></div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const URL_DATOS = "{{ route('datos.cpu-memoria') }}";
    const INTERVALO_MS = 2000;
    const MAX_PUNTOS = 30; // 30 muestras x 2 s = 60 s
    const historial = [];

    const $ = (id) => document.getElementById(id);
    const pct = (n) => Math.round(n) + '%';
    const gb = (n) => n.toFixed(2) + ' GB';

    function pintarNucleos(lista) {
        const cont = $('nucleos');
        if (cont.children.length !== lista.length) {
            cont.replaceChildren(...lista.map((n) => {
                const fila = document.createElement('div');
                fila.className = 'row';
                fila.innerHTML = '<span class="nombre"></span><div class="track"><div class="fill" style="width:0"></div></div><span class="pct"></span>';
                fila.querySelector('.nombre').textContent = n.nombre;
                return fila;
            }));
        }
        lista.forEach((n, i) => {
            const fila = cont.children[i];
            fila.querySelector('.fill').style.width = n.uso + '%';
            fila.querySelector('.pct').textContent = pct(n.uso);
        });
    }

    function pintarGrafica(valor) {
        historial.push(valor);
        if (historial.length > MAX_PUNTOS) historial.shift();
        const paso = 400 / (MAX_PUNTOS - 1);
        $('linea').setAttribute('points',
            historial.map((v, i) => `${(i * paso).toFixed(1)},${(85 - (v / 100) * 80).toFixed(1)}`).join(' '));
    }

    function pintar(d) {
        $('kpi-cpu').textContent = pct(d.cpu.total);
        $('kpi-nucleos').textContent = d.cpu.logicos;
        $('kpi-ram').textContent = pct(d.memoria.usada_pct);
        $('kpi-swap').textContent = pct(d.memoria.swap_pct);

        pintarNucleos(d.cpu.nucleos);
        pintarGrafica(d.cpu.total);

        $('bm-usada').style.width = d.memoria.usada_pct + '%';
        $('bm-cache').style.width = d.memoria.cache_pct + '%';
        $('bm-libre').style.width = d.memoria.libre_pct + '%';
        $('m-usada').textContent = gb(d.memoria.usada_gb);
        $('m-cache').textContent = gb(d.memoria.cache_gb);
        $('m-libre').textContent = gb(d.memoria.libre_gb);
        $('m-total').textContent = gb(d.memoria.total_gb);
    }

    async function actualizar() {
        try {
            const r = await fetch(URL_DATOS, { headers: { Accept: 'application/json' } });
            const d = await r.json();
            if (!r.ok) throw new Error(d.message || 'Error');
            $('error').textContent = '';
            pintar(d);
        } catch (e) {
            $('error').textContent = 'No se pudieron leer los datos del sistema: ' + e.message;
        }
    }

    actualizar();
    setInterval(actualizar, INTERVALO_MS);
</script>
@endpush
