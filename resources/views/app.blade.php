<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts y Estilos con Vite -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead

        @auth
        <!-- Chatwoot SDK Integration -->
        <script>
          (function(d,t) {
            var BASE_URL="https://chatwoot.servilutioncrm.cloud";
            var g=d.createElement(t),s=d.getElementsByTagName(t)[0];
            g.src=BASE_URL+"/packs/js/sdk.js";
            g.defer = true;
            g.async = true;
            s.parentNode.insertBefore(g,s);
            g.onload=function(){
              window.chatwootSDK.run({
                websiteToken: 'TU_WEBSITE_TOKEN_AQUI', // <--- REEMPLAZA ESTO CON TU TOKEN
                baseUrl: BASE_URL
              })
            }
          })(document,"script");

          // Opcional: Identificar al usuario en Chatwoot automáticamente
          window.addEventListener('chatwoot:ready', function () {
            window.$chatwoot.setUser('{{ Auth::user()->id }}', {
              email: '{{ Auth::user()->email }}',
              name: '{{ Auth::user()->name }}',
              // Puedes añadir más campos aquí según tu modelo de User
            });
          });
        </script>
        @endauth
    </head>
    <body class="font-sans antialiased">
        <!-- El punto donde Inertia inyectará tu aplicación de Vue -->
        @inertia
    </body>
</html>
