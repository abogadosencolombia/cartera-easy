@extends('layouts.error')

@section('title', 'Página No Encontrada')

@section('content')
    <div class="flex flex-col items-center">
        <img src="{{ asset('favicon.png') }}" alt="Logo" class="h-20 w-auto mb-8 opacity-75">
        <div class="text-5xl font-bold text-yellow-500 mb-4">404</div>
        <h1 class="text-2xl font-bold tracking-tight sm:text-4xl">Página No Encontrada</h1>
        <p class="mt-4 text-base text-gray-400">Lo sentimos, la página que estás buscando no existe o ha sido movida.</p>
        <a href="{{ auth()->check() ? route('dashboard') : url('/') }}"
           class="mt-10 inline-block rounded-md bg-yellow-500 px-4 py-2.5 text-sm font-semibold text-gray-900 transition-transform duration-200 hover:bg-yellow-400 hover:scale-105">
            {{ auth()->check() ? 'Volver al Dashboard' : 'Volver al Inicio' }}
        </a>
    </div>
@endsection