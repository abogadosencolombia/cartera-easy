<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name', 'Laravel') }}</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased font-sans">
    <div class="relative min-h-screen w-full flex items-center justify-center bg-gray-900 overflow-hidden">
        
        <div class="absolute top-0 left-0 -translate-x-1/3 -translate-y-1/3">
            <div class="w-[600px] h-[600px] rounded-full bg-gradient-to-tr from-yellow-500/30 to-blue-800/30 blur-3xl animate-pulse"></div>
        </div>
        <div class="absolute bottom-0 right-0 translate-x-1/3 translate-y-1/3">
             <div class="w-[500px] h-[500px] rounded-full bg-gradient-to-tl from-yellow-500/20 to-indigo-800/20 blur-3xl animate-pulse animation-delay-3000"></div>
        </div>
        <div class="relative z-10 w-full max-w-xl mx-auto p-6 text-center">
            
            @yield('content')

        </div>
    </div>
</body>
</html>