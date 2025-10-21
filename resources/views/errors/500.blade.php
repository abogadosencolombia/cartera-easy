@extends('layouts.error')

@section('title', 'Error del Servidor')

@section('content')
    <div class="flex flex-col items-center">
        <img src="{{ asset('favicon.png') }}" alt="Logo" class="h-20 w-auto mb-8 opacity-75">
        <div class="text-5xl font-bold text-red-500 mb-4">500</div>
        <h1 class="text-2xl font-bold tracking-tight sm:text-4xl">Algo Salió Mal</h1>
        <p class="mt-4 text-base text-gray-400">Estamos experimentando un problema técnico inesperado. Nuestro equipo ya ha sido notificado para solucionarlo.</p>
        <a href="{{ auth()->check() ? route('dashboard') : url('/') }}"
           class="mt-10 inline-block rounded-md bg-yellow-500 px-4 py-2.5 text-sm font-semibold text-gray-900 transition-transform duration-200 hover:bg-yellow-400 hover:scale-105">
            {{ auth()->check() ? 'Volver al Dashboard' : 'Volver al Inicio' }}
        </a>
    </div>
@endsection