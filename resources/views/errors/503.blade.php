@extends('layouts.error')

@section('title', 'Sitio en Mantenimiento')

@section('content')
    <div class="flex flex-col items-center">
        <img src="{{ asset('favicon.png') }}" alt="Logo" class="h-20 w-auto mb-8 opacity-75">
        <div class="text-5xl font-bold text-blue-500 mb-4">503</div>
        <h1 class="text-2xl font-bold tracking-tight sm:text-4xl">Volvemos Pronto</h1>
        <p class="mt-4 text-base text-gray-400">Actualmente estamos realizando tareas de mantenimiento programado para mejorar tu experiencia. Por favor, intenta de nuevo en unos minutos.</p>
    </div>
@endsection