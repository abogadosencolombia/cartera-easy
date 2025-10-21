<?php

namespace App\Http\Controllers;

use App\Models\DocumentoGenerado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

class DocumentoGeneradoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DocumentoGenerado::class);
        $user = Auth::user();

        $query = DocumentoGenerado::with(['caso.deudor', 'usuario', 'plantilla']);

        if ($user->tipo_usuario !== 'admin') {
            $cooperativaIds = $user->cooperativas->pluck('id');
            $query->whereHas('caso', fn ($q) => $q->whereIn('cooperativa_id', $cooperativaIds));
        }

        // --- LÓGICA DE FILTRADO COMPLETA ---
        $query->when($request->input('usuario_id'), fn($q, $id) => $q->where('user_id', $id));
        $query->when($request->input('tipo_plantilla'), fn($q, $tipo) => $q->whereHas('plantilla', fn($sq) => $sq->where('tipo', $tipo)));
        $query->when($request->input('fecha_desde'), fn($q, $fecha) => $q->whereDate('created_at', '>=', $fecha));
        $query->when($request->input('fecha_hasta'), fn($q, $fecha) => $q->whereDate('created_at', '<=', $fecha));
        
        $query->orderBy('created_at', 'desc');
        
        // Obtenemos los usuarios para el filtro
        $usuariosGeneradores = User::whereIn('tipo_usuario', ['admin', 'gestor', 'abogado'])->get(['id', 'name']);

        return Inertia::render('DocumentosGenerados/Index', [
            'documentos' => $query->paginate(15)->withQueryString(),
            'usuarios' => $usuariosGeneradores,
            'filtros' => $request->only(['usuario_id', 'tipo_plantilla', 'fecha_desde', 'fecha_hasta']),
        ]);
    }
}
