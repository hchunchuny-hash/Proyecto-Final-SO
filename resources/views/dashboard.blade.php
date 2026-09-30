@extends('layouts.app')

@section('titulo', 'Inicio')
@section('subtitulo', 'Resumen general del sistema')

@section('contenido')
    <p>Bienvenido, <strong>{{ auth()->user()->name }}</strong>. Selecciona un módulo de la barra superior.</p>
@endsection
