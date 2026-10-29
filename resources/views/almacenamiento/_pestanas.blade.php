@push('estilos')
<style>
  .pestanas{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
  .pestanas a{padding:7px 14px;border:1px solid var(--borde);border-radius:var(--radio);color:var(--suave);text-decoration:none;font:600 13px var(--mono)}
  .pestanas a:hover{color:var(--texto);background:var(--panel2)}
  .pestanas a.activa{background:var(--acento);color:#13100d;border-color:var(--acento)}
  .separado{margin-top:16px}
  .detalle{margin:8px 0 0;color:var(--suave);font-size:12px}
  .uso span.alto{background:var(--error)}
  .ocupacion{display:flex;align-items:center;gap:8px}
  .ocupacion .uso{flex:1}
  .ocupacion b{font:600 12px var(--mono);min-width:38px;text-align:right}
  .tarjeta details summary{cursor:pointer;color:var(--acento);font:600 13px var(--mono);margin-bottom:10px}
  .mono{font-family:var(--mono)}
  .migas{margin:0 0 12px;font:13px var(--mono);color:var(--suave)}
  .migas a{color:var(--acento);text-decoration:none}
  .migas a:hover{text-decoration:underline}
  .explorar{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:14px}
  .explorar label{color:var(--suave);font-size:13px}
  .explorar select{display:block;margin-top:4px;padding:8px 10px;background:var(--fondo);color:var(--texto);border:1px solid var(--borde);border-radius:var(--radio);font:inherit}
  .tabla td a{color:var(--acento);text-decoration:none}
  .tabla td a:hover{text-decoration:underline}
</style>
@endpush

<nav class="pestanas" aria-label="Secciones de almacenamiento">
    <a href="{{ route('almacenamiento.index') }}" class="{{ request()->routeIs('almacenamiento.index') ? 'activa' : '' }}">Discos y sistemas de archivos</a>
    <a href="{{ route('almacenamiento.usuarios') }}" class="{{ request()->routeIs('almacenamiento.usuarios') ? 'activa' : '' }}">Usuarios y sesiones</a>
    <a href="{{ route('almacenamiento.explorador') }}" class="{{ request()->routeIs('almacenamiento.explorador') ? 'activa' : '' }}">Explorador de archivos</a>
</nav>
