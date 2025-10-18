@extends('layouts.error')

@section('title', 'Acceso No Autorizado')

@section('content')
    <div class="relative flex flex-col items-center">
        <div class="absolute -top-20 text-[180px] lg:text-[250px] font-black text-white/10 select-none">
            @yield('code', '403')
        </div>

        <div class="relative z-10 flex flex-col items-center">
            <a href="{{ url('/') }}">
                <img src="{{ asset('favicon.png') }}" alt="Logo" class="h-20 w-auto mb-8 opacity-90">
            </a>

            <h1 class="text-2xl font-bold tracking-tight text-white sm:text-4xl">
                @yield('message_title', 'Acceso No Autorizado')
            </h1>

            <p class="mt-4 text-base text-gray-400">
                @yield('message_detail', 'Lo sentimos, no tienes los permisos necesarios para acceder a esta página.')
            </p>

            <a href="{{ auth()->check() ? route('dashboard') : url('/') }}"
               class="mt-10 inline-flex items-center gap-x-2 rounded-md bg-yellow-500 px-5 py-3 text-sm font-semibold text-gray-900 transition-all duration-300 hover:bg-yellow-400 hover:scale-105 hover:shadow-lg hover:shadow-yellow-500/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400">
                <span>
                    {{ auth()->check() ? 'Volver al Dashboard' : 'Volver al Inicio' }}
                </span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                  <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" />
                </svg>
            </a>
        </div>
    </div>
@endsection