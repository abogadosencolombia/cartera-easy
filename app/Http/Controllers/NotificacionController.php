<?php

namespace App\Http\Controllers;

use App\Models\NotificacionCaso;
use App\Models\Caso;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use App\Models\NotificacionLeida;
use Carbon\Carbon;

class NotificacionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $query = NotificacionCaso::with('caso'); 

        if ($user->tipo_usuario !== 'admin') {
            $query->where('user_id', $user->id);
        } else {
            $query->when($request->filled('user_id'), function ($q) use ($request) {
                $q->where('user_id', $request->input('user_id'));
            });
        }

        $query->where('fecha_envio', '<=', now());
        $query->when($request->input('leido') === 'si', fn ($q) => $q->where('leido', true));
        $query->when($request->input('leido') === 'no', fn ($q) => $q->where('leido', false));
        $query->when($request->filled('tipo'), fn ($q) => $q->where('tipo', 'like', '%' . $request->input('tipo') . '%'));
        $notificaciones = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return Inertia::render('Notificaciones/Index', [
            'notificaciones' => $notificaciones,
            'filtros' => $request->only(['leido', 'tipo', 'user_id']),
            'tipos_alerta' => ['mora', 'vencimiento', 'inactividad', 'documento_faltante', 'alerta_manual'],
            'usuarios' => User::whereIn('tipo_usuario', ['admin', 'abogado'])->get(['id', 'name']),
        ]);
    }

    public function marcarComoLeida(NotificacionCaso $notificacion): RedirectResponse
    {
        // ===== POLÍTICA DE ACCESO MEJORADA =====
        // Permitir si la notificación es del usuario O si el usuario es un admin.
        if ($notificacion->user_id !== Auth::id() && Auth::user()->tipo_usuario !== 'admin') {
            abort(403, 'Acción no autorizada.');
        }

        if (!$notificacion->leido) {
            $notificacion->update(['leido' => true]);
            NotificacionLeida::create([
                'user_id' => Auth::id(), // Siempre registra quién hizo la acción
                'notificacion_id' => $notificacion->id,
                'leido_en' => now()
            ]);
        }
        return back()->with('success', 'Notificación marcada como leída.');
    }

    public function marcarComoAtendida(NotificacionCaso $notificacion)
    {
        // ===== POLÍTICA DE ACCESO MEJORADA =====
        // Permitir si la notificación es del usuario O si el usuario es un admin.
        if ($notificacion->user_id !== Auth::id() && Auth::user()->tipo_usuario !== 'admin') {
            abort(403, 'Acción no autorizada.');
        }

        if (!$notificacion->atendida_en) {
            $notificacion->atendida_en = now();
            $notificacion->save();
        }

        return back()->with('success', '¡Notificación atendida! Buen trabajo.');
    }

    public function storeManual(Request $request, Caso $caso): RedirectResponse
    {
        // Si aún no agregas el campo en el front, deja "nullable" para no romper
        $validated = $request->validate([
            'mensaje'         => 'required|string|max:1000',
            'programado_para' => 'nullable|date|after_or_equal:today',
            'prioridad'       => 'nullable|in:baja,media,alta',
        ]);

        $programadoEn = !empty($validated['programado_para'])
            ? \Carbon\Carbon::parse($validated['programado_para'])
            : null;

        // envía al titular del caso y a ti (si estás logueado) como antes
        $destinatarios = array_unique(array_filter([$caso->user_id, auth()->id()]));

        foreach ($destinatarios as $uid) {
            $caso->notificaciones()->create([
                'user_id'       => $uid,
                'tipo'          => 'alerta_manual',
                'mensaje'       => $validated['mensaje'],
                'prioridad'     => $validated['prioridad'] ?? 'media', // <-- aquí usa lo recibido
                'fecha_envio'   => $programadoEn ?? now(),
                'programado_en' => $programadoEn,
            ]);
        }

        return back()->with('success', 'Alerta manual programada exitosamente.');
    }
}