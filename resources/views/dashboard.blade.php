<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard · SysMonitor Web</title>
<style>
  body { margin:0; padding:32px; background:#eef2fa; font:15px/1.5 system-ui,sans-serif; color:#14213d; }
  .tarjeta { background:#fff; border:1px solid #dde4f2; border-radius:12px; padding:24px; max-width:520px; }
  h1 { margin:0 0 6px; font-size:22px; color:#1d3fb8; }
  button { margin-top:16px; padding:8px 16px; border:0; border-radius:8px; background:#2456e6; color:#fff; font:inherit; font-weight:600; cursor:pointer; }
</style>
</head>
<body>
<div class="tarjeta">
  <h1>Dashboard</h1>
  <p>Sesión iniciada como <strong>{{ auth()->user()->name }}</strong>
     ({{ auth()->user()->role === 'admin' ? 'Administrador' : 'Observador' }}).</p>
  <form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit">Cerrar sesión</button>
  </form>
</div>
</body>
</html>
