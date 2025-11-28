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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
// ===== INICIO DE LA MODIFICACIÓN (FIX ERROR 500) =====
use Illuminate\Support\Str;
// ===== FIN DE LA MODIFICACIÓN =====

class NotificacionController extends Controller
{
    /**
     * Muestra la bandeja de notificaciones unificada (Casos y Tareas).
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        // ===== INICIO DE LA MODIFICACIÓN (MARCAR LEÍDAS AL ENTRAR) =====
        // 1. Marca leídas las notificaciones de Tareas (Sistema Laravel)
        // Usamos ->get() y luego ->markAsRead() para disparar eventos si los hubiera
        $unreadTaskNotifications = $user->unreadNotifications;
        if ($unreadTaskNotifications->isNotEmpty()) {
            $unreadTaskNotifications->markAsRead();
        }
        
        // 2. Marca leídas las notificaciones de Casos (Tu sistema)
        // También marcamos como leídas las de 'NotificacionCaso' al entrar
        NotificacionCaso::where('user_id', $user->id)
            ->where('leido', false)
            ->update(['leido' => true]);
        // ===== FIN DE LA MODIFICACIÓN =====


        // 1. OBTENER NOTIFICACIONES DE CASOS (Tu sistema)
        $queryCasos = NotificacionCaso::with('caso');

        if ($user->tipo_usuario !== 'admin') {
            $queryCasos->where('user_id', $user->id);
        } else {
            $queryCasos->when($request->filled('user_id'), function ($q) use ($request) {
                $q->where('user_id', $request->input('user_id'));
            });
        }

        $queryCasos->where('fecha_envio', '<=', now());
        $queryCasos->when($request->input('leido') === 'si', fn ($q) => $q->where('leido', true));
        $queryCasos->when($request->input('leido') === 'no', fn ($q) => $q->where('leido', false));
        // ***** CORRECCIÓN AQUÍ: Tu filtro original decía 'tipo', debe ser 'tipo_alerta' *****
        $queryCasos->when($request->filled('tipo'), fn ($q) => $q->where('tipo_alerta', 'Ilike', '%' . $request->input('tipo') . '%'));
        
        $notificacionesCasos = $queryCasos->get();

        // 2. OBTENER NOTIFICACIONES DE TAREAS (Sistema Laravel)
        $queryTareas = $user->notifications(); // Ya está filtrado por usuario

        // Aplicar filtros de lectura
        $queryTareas->when($request->input('leido') === 'si', fn ($q) => $q->whereNotNull('read_at'));
        $queryTareas->when($request->input('leido') === 'no', fn ($q) => $q->whereNull('read_at'));

        // Si se filtra por 'tipo' (que solo existe en Casos), ocultamos las tareas
        $queryTareas->when($request->filled('tipo'), fn ($q) => $q->where('id', null));
        
        $notificacionesTareas = $queryTareas->get();

        // 3. UNIR, ORDENAR Y PAGINAR
        $todasLasNotificaciones = $notificacionesCasos->merge($notificacionesTareas);
        $notificacionesOrdenadas = $todasLasNotificaciones->sortByDesc('created_at');

        $page = $request->input('page', 1);
        $perPage = 15; // O el número que uses
        $items = $notificacionesOrdenadas->forPage($page, $perPage)->values();
        
        $notificacionesPaginadas = new LengthAwarePaginator(
            $items,
            $notificacionesOrdenadas->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        
        return Inertia::render('Notificaciones/Index', [
            'notificaciones' => $notificacionesPaginadas, // Usamos las paginadas
            'filtros' => $request->only(['leido', 'tipo', 'user_id']),
            'tipos_alerta' => ['mora', 'vencimiento', 'inactividad', 'documento_faltante', 'alerta_manual'], // Tu array original
            'usuarios' => User::whereIn('tipo_usuario', ['admin', 'abogado'])->get(['id', 'name']), // Tu consulta original
        ]);
    }

    /**
     * Marca una notificación (de Caso o Tarea) como leída.
     */
    public function marcarComoLeida(Request $request, $id): RedirectResponse
    {
        $user = Auth::user();

        // ===== INICIO DE LA MODIFICACIÓN (FIX ERROR 500) =====
        // Las notificaciones de Tarea usan UUID (string 36 chars), las de Caso usan BigInt (numérico)
        
        if (Str::isUuid($id)) {
            // Es una Notificación de TAREA (DatabaseNotification)
            $notificacionTarea = $user->notifications()->where('id', $id)->first();
            
            if ($notificacionTarea) {
                $notificacionTarea->markAsRead();
                // Usamos back(303) para forzar a Inertia a recargar los props (actualiza el contador)
                return back(303)->with('success', 'Tarea marcada como leída.');
            }

        } else {
            // Es una Notificación de CASO (NotificacionCaso)
            $notificacionCaso = NotificacionCaso::find($id);

            if ($notificacionCaso) {
                if ($notificacionCaso->user_id !== $user->id && $user->tipo_usuario !== 'admin') {
                    abort(403, 'Acción no autorizada.');
                }
                
                if (!$notificacionCaso->leido) {
                    $notificacionCaso->update(['leido' => true]);
                    NotificacionLeida::create([
                        'user_id' => $user->id,
                        'notificacion_id' => $notificacionCaso->id,
                        'leido_en' => now()
                    ]);
                }
                // Usamos back(303) para forzar a Inertia a recargar los props (actualiza el contador)
                return back(303)->with('success', 'Notificación marcada como leída.');
            }
        }
        // ===== FIN DE LA MODIFICACIÓN =====

        // Si no se encuentra en ninguna
        return back(303)->with('error', 'No se pudo encontrar la notificación.');
    }


    public function marcarComoAtendida(NotificacionCaso $notificacion)
    {
        // ... (Tu método original - sin cambios)
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
        // ... (Tu método original - sin cambios)
        $validated = $request->validate([
            'mensaje'       => 'required|string|max:1000',
            'programado_para' => 'nullable|date|after_or_equal:today',
            'prioridad'     => 'nullable|in:baja,media,alta',
        ]);
        $programadoEn = !empty($validated['programado_para'])
            ? \Carbon\Carbon::parse($validated['programado_para'])
            : null;
        $destinatarios = array_unique(array_filter([$caso->user_id, auth()->id()]));
        foreach ($destinatarios as $uid) {
            $caso->notificaciones()->create([
                'user_id'       => $uid,
                'tipo'          => 'alerta_manual',
                'mensaje'       => $validated['mensaje'],
                'prioridad'     => $validated['prioridad'] ?? 'media',
                'fecha_envio'   => $programadoEn ?? now(),
                'programado_en' => $programadoEn,
            ]);
        }
        return back()->with('success', 'Alerta manual programada exitosamente.');
    }
}