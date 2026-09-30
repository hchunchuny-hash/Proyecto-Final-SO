<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar sesión · SysMonitor Web</title>
<style>
  body { margin:0; min-height:100vh; display:grid; place-items:center; background:#eef2fa; font:15px/1.5 system-ui,sans-serif; color:#14213d; }
  .tarjeta { background:#fff; border:1px solid #dde4f2; border-radius:12px; padding:32px; width:100%; max-width:360px; }
  h1 { margin:0; font-size:24px; color:#1d3fb8; }
  .sub { margin:2px 0 22px; color:#5b6785; font-size:13px; }
  label { display:block; margin:14px 0 4px; font-size:13px; color:#5b6785; }
  input { width:100%; padding:9px 10px; border:1px solid #dde4f2; border-radius:8px; font:inherit; box-sizing:border-box; }
  button { margin-top:20px; width:100%; padding:10px; border:0; border-radius:8px; background:#2456e6; color:#fff; font:inherit; font-weight:600; cursor:pointer; }
  .error { margin:10px 0 0; color:#b42318; font-size:13px; }
</style>
</head>
<body>
<main class="tarjeta">
  <h1>SysMonitor Web</h1>
  <p class="sub">Sistemas Operativos · UMG</p>
  <form method="POST" action="{{ route('login') }}">
    @csrf
    <label for="email">Correo</label>
    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
    <label for="password">Contraseña</label>
    <input id="password" type="password" name="password" required>
    @error('email')
      <p class="error">{{ $message }}</p>
    @enderror
    <button type="submit">Entrar</button>
  </form>
</main>
</body>
</html>
