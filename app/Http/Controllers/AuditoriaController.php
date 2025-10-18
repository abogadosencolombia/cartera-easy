<?php

    namespace App\Http\Controllers;

    use App\Models\AuditoriaEvento;
    use App\Models\User;
    use Illuminate\Http\Request;
    use Inertia\Inertia;
    use Inertia\Response;
    use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    class AuditoriaController extends Controller
    {
        use AuthorizesRequests;

        public function index(Request $request): Response
        {
            // Aquí podríamos añadir una política de seguridad, por ahora solo para admins.
            $this->authorize('isAdmin');

            $query = AuditoriaEvento::with('usuario'); // Cargar la relación con el usuario

            // Aplicar filtros si existen en la petición
            $query->when($request->filled('user_id'), function ($q) use ($request) {
                $q->where('user_id', $request->input('user_id'));
            });

            $query->when($request->filled('evento'), function ($q) use ($request) {
                $q->where('evento', 'like', '%' . $request->input('evento') . '%');
            });
            
            $query->when($request->filled('fecha_desde'), function ($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->input('fecha_desde'));
            });

            $query->when($request->filled('fecha_hasta'), function ($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->input('fecha_hasta'));
            });

            // Obtenemos los eventos paginados para no sobrecargar la vista
            $eventos = $query->latest()->paginate(20)->withQueryString();

            return Inertia::render('Admin/Auditoria/Index', [
                'eventos' => $eventos,
                'usuarios' => User::all(['id', 'name']), // Para el filtro de usuarios
                'filtros' => $request->only(['user_id', 'evento', 'fecha_desde', 'fecha_hasta']),
            ]);
        }
    }
    