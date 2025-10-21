<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles Los roles permitidos que pasamos desde la ruta.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Si el 'tipo_usuario' del usuario autenticado no está en la lista de roles permitidos...
        if (!in_array($request->user()->tipo_usuario, $roles)) {
            // ...le negamos el acceso con un error 403 (Prohibido).
            abort(403, 'Acceso no autorizado.');
        }

        // Si el rol es correcto, le permitimos continuar.
        return $next($request);
    }
}
