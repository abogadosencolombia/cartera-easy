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
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\AlertaSistemaMailable;

class NotificacionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        // 1. OBTENER NOTIFICACIONES DE CASOS (Tabla Personalizada)
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
        $queryCasos->when($request->filled('tipo'), fn ($q) => $q->where('tipo', 'Ilike', '%' . $request->input('tipo') . '%'));
        
        $notificacionesCasos = $queryCasos->get();

        // 2. OBTENER NOTIFICACIONES DE TAREAS (Sistema Laravel - Notifications Table)
        $queryTareas = $user->notifications(); 

        $queryTareas->when($request->input('leido') === 'si', fn ($q) => $q->whereNotNull('read_at'));
        $queryTareas->when($request->input('leido') === 'no', fn ($q) => $q->whereNull('read_at'));
        
        // --- CORRECCIÓN DEL FILTRO PARA SISTEMA ---
        // Aquí mapeamos el filtro del frontend ('vencimiento', 'mora') a las clases reales de Laravel
        $queryTareas->when($request->filled('tipo'), function ($q) use ($request) {
            $tipo = $request->input('tipo');
            $mapaTipos = [
                'vencimiento'   => 'App\Notifications\AlertaProceso', // Legal
                'mora'          => 'App\Notifications\AlertaPago',    // Pagos
                'alerta_manual' => 'App\Notifications\AlertaManual'
            ];

            if (isset($mapaTipos[$tipo])) {
                $q->where('type', $mapaTipos[$tipo]);
            } else {
                // Si el filtro no coincide con nada conocido, no mostramos notificaciones de sistema
                $q->where('id', null);
            }
        });
        
        $notificacionesTareas = $queryTareas->get();

        // 3. UNIR Y PAGINAR
        $todas = $notificacionesCasos->merge($notificacionesTareas);
        $ordenadas = $todas->sortByDesc(function ($item) {
            return $item->fecha_envio ?? $item->created_at;
        });

        $page = $request->input('page', 1);
        $perPage = 15; 
        $items = $ordenadas->forPage($page, $perPage)->values();
        
        $paginadas = new LengthAwarePaginator(
            $items,
            $ordenadas->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        
        return Inertia::render('Notificaciones/Index', [
            'notificaciones' => $paginadas,
            'filtros' => $request->only(['leido', 'tipo', 'user_id']),
            'tipos_alerta' => ['revision_proxima', 'revision_hoy', 'revision_vencida', 'pago_proximo', 'pago_hoy', 'pago_vencido', 'alerta_manual'],
            'usuarios' => User::whereIn('tipo_usuario', ['admin', 'abogado'])->get(['id', 'name']),
        ]);
    }

    public function marcarComoLeida(Request $request, $id): RedirectResponse
    {
        $user = Auth::user();
        if (Str::isUuid($id)) {
            $notificacion = $user->notifications()->where('id', $id)->first();
            if ($notificacion) {
                $notificacion->markAsRead();
                return back(303)->with('success', 'Marcada como leída.');
            }
        } else {
            $notificacion = NotificacionCaso::find($id);
            if ($notificacion) {
                if (!$notificacion->leido) {
                    $notificacion->update(['leido' => true]);
                    NotificacionLeida::create(['user_id' => $user->id, 'notificacion_id' => $notificacion->id, 'leido_en' => now()]);
                }
                return back(303)->with('success', 'Marcada como leída.');
            }
        }
        return back(303);
    }

    public function marcarComoAtendida($id)
    {
        // Verificar primero en tabla personalizada
        $notificacion = NotificacionCaso::find($id);
        if ($notificacion) {
            if (!$notificacion->atendida_en) {
                $notificacion->atendida_en = now();
                $notificacion->save();
            }
            return back()->with('success', 'Atendida.');
        } 
        
        // Si no, intentar marcar como leída la de sistema (las de sistema no tienen "atendida", solo leída)
        $user = Auth::user();
        $notifSistema = $user->notifications()->where('id', $id)->first();
        if ($notifSistema) {
            $notifSistema->markAsRead();
            return back()->with('success', 'Notificación archivada.');
        }

        return back()->with('error', 'Notificación no encontrada.');
    }

    /**
     * NUEVO MÉTODO: Eliminar Notificación
     */
    public function destroy($id)
    {
        $user = Auth::user();

        // 1. Intentar borrar si es UUID (Sistema)
        if (Str::isUuid($id)) {
            $notificacion = $user->notifications()->where('id', $id)->first();
            if ($notificacion) {
                $notificacion->delete();
                return back(303)->with('success', 'Notificación eliminada correctamente.');
            }
        } 
        // 2. Intentar borrar si es ID Numérico (Caso)
        else {
            $notificacion = NotificacionCaso::find($id);
            // Solo permitir borrar si es dueño o admin
            if ($notificacion && ($notificacion->user_id == $user->id || $user->tipo_usuario === 'admin')) {
                $notificacion->delete();
                return back(303)->with('success', 'Notificación eliminada correctamente.');
            }
        }

        return back(303)->with('error', 'No se pudo eliminar la notificación.');
    }

    /**
     * ELIMINAR NOTIFICACIONES (RESPETA FILTROS)
     */
    public function clearAll(Request $request)
    {
        $user = Auth::user();

        // 1. Borrar notificaciones de la tabla personalizada (NotificacionCaso)
        $queryCasos = NotificacionCaso::query();
        
        if ($user->tipo_usuario !== 'admin') {
            $queryCasos->where('user_id', $user->id);
        } else {
            // Si es admin, respetamos el filtro de usuario si existe
            if ($request->filled('user_id')) {
                $queryCasos->where('user_id', $request->input('user_id'));
            }
            // Si no hay filtro de usuario y es admin, borrará todas las de la tabla
            // para que la vista global del admin se limpie.
        }
        
        // Respetamos filtros de estado y tipo
        if ($request->input('leido') === 'si') $queryCasos->where('leido', true);
        if ($request->input('leido') === 'no') $queryCasos->where('leido', false);
        if ($request->filled('tipo')) $queryCasos->where('tipo', 'Ilike', '%' . $request->input('tipo') . '%');

        $queryCasos->delete();

        // 2. Borrar notificaciones del sistema de Laravel (propias)
        // Estas siempre son privadas al usuario actual
        $queryTareas = $user->notifications();
        if ($request->input('leido') === 'si') $queryTareas->whereNotNull('read_at');
        if ($request->input('leido') === 'no') $queryTareas->whereNull('read_at');
        // El filtro de tipo para tareas es más complejo por las clases, 
        // por simplicidad si hay filtro de tipo 'legal' o 'pagos' las borramos también.
        
        $queryTareas->delete();

        return back(303)->with('success', 'Notificaciones eliminadas correctamente.');
    }

    public function storeManual(Request $request, Caso $caso): RedirectResponse
    {
         $validated = $request->validate([
            'mensaje' => 'required|string|max:1000',
            'prioridad' => 'nullable|in:baja,media,alta',
            'fecha_programada' => 'nullable|date',
         ]);
         
         $fechaEnvio = $request->filled('fecha_programada') ? Carbon::parse($validated['fecha_programada']) : now();

         // 1. Recopilar todos los IDs de usuarios que deben recibir la notificación
         $userIds = collect();
         
         // El usuario que la crea
         $userIds->push(Auth::id());
         
         // El responsable principal del caso
         if ($caso->user_id) $userIds->push($caso->user_id);
         
         // Otros abogados/gestores asignados al caso
         $assignedUsers = $caso->users()->pluck('users.id');
         $userIds = $userIds->merge($assignedUsers);
         
         // Todos los administradores del sistema
         $admins = User::where('tipo_usuario', 'admin')->pluck('id');
         $userIds = $userIds->merge($admins);

         // Limpiar duplicados y valores nulos
         $targetUsers = $userIds->unique()->filter();

         // 2. Crear un registro de notificación para cada usuario
         foreach ($targetUsers as $uid) {
             $caso->notificaciones()->create([
                'user_id' => $uid,
                'tipo' => 'alerta_manual',
                'mensaje' => $validated['mensaje'],
                'fecha_envio' => $fechaEnvio,
                'prioridad' => $validated['prioridad'] ?? 'media',
                'leido' => false 
             ]);
         }

         // Enviar correo solo al responsable principal si es para hoy
         if ($fechaEnvio->isToday() && $caso->user_id) {
             try {
                 $user = User::find($caso->user_id);
                 if ($user && $user->email) {
                     Mail::to($user->email)->send(new AlertaSistemaMailable(
                         $user->name,
                         'Nueva Alerta Manual',
                         $validated['mensaje'],
                         route('casos.show', $caso->id),
                         'Alerta creada manualmente sobre el caso #' . $caso->id
                     ));
                 }
             } catch (\Exception $e) {}
         }

         return back()->with('success', 'Alerta creada para todos los responsables y administradores.');
    }
}