<?php

namespace App\Http\Controllers;

use App\Services\RegistroBitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function mostrarLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($datos)) {
            $request->session()->regenerate();
            RegistroBitacora::registrar('Inicio de sesión');
            return redirect()->intended(route('dashboard'));
        }

        RegistroBitacora::registrar('Intento de inicio de sesión fallido', $datos['email'], RegistroBitacora::ERROR);

        return back()
            ->withErrors(['email' => 'Correo o contraseña incorrectos.'])
            ->onlyInput('email');
    }

    public function logout(Request $request)
    {
        RegistroBitacora::registrar('Cierre de sesión');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
