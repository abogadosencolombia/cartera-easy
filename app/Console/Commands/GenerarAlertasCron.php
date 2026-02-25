<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProcesoRadicado;
use App\Models\NotificacionCaso;
use App\Models\Caso; // Importante
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\AlertaSistemaMailable;

class GenerarAlertasCron extends Command
{
    protected $signature = 'alertas:procesar-vencimientos';
    protected $description = 'Genera alertas de mora y vencimientos priorizando el Dashboard.';

    public function handle()
    {
        $this->info('--> Iniciando escaneo de vencimientos (Hora Col: ' . now()->format('h:i A') . ')...');
        
        $admins = User::where('tipo_usuario', 'admin')->where('estado_activo', true)->get();

        // ------------------------------------------
        // 1. REVISIÓN DE PROCESOS JUDICIALES
        // ------------------------------------------
        $this->info("1. Analizando Procesos Judiciales...");
        $procesos = ProcesoRadicado::where('estado', '!=', 'Terminado') 
            ->whereNotNull('fecha_proxima_revision')
            ->with(['abogado', 'responsable'])
            ->get();

        $countProcesos = 0;
        $bar = $this->output->createProgressBar(count($procesos));
        $bar->start();

        foreach ($procesos as $proceso) {
            $fecha = Carbon::parse($proceso->fecha_proxima_revision);
            $hoy = Carbon::today();
            $mensaje = null;
            $titulo = "";

            if ($fecha->diffInDays($hoy) == 2 && $fecha->isFuture()) {
                $titulo = "Próxima Revisión";
                $mensaje = "⏳ El proceso {$proceso->radicado} requiere revisión el " . $fecha->format('d/m/Y');
            } elseif ($fecha->isSameDay($hoy)) {
                $titulo = "Revisión para HOY";
                $mensaje = "⚖️ ATENCIÓN: Hoy debes revisar el proceso {$proceso->radicado} inmediatamente.";
            } elseif ($fecha->isPast()) {
                $titulo = "Revisión VENCIDA";
                $mensaje = "⚠️ URGENTE: La revisión del proceso {$proceso->radicado} está atrasada desde el " . $fecha->format('d/m/Y');
            }

            if ($mensaje) {
                $enviado = $this->createSystemNotification($proceso, $admins, $mensaje, $titulo);
                if ($enviado) $countProcesos++;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->output->newLine();

        // ------------------------------------------
        // 2. PAGOS DE HONORARIOS (CORREGIDO)
        // ------------------------------------------
        $this->info("2. Analizando Pagos en Mora...");
        
        if (class_exists(\App\Models\ContratoCuota::class)) {
            // Buscamos todas las cuotas PENDIENTES de contratos ACTIVOS
            $cuotas = \App\Models\ContratoCuota::where('estado', 'PENDIENTE')
                ->whereHas('contrato', fn($q) => $q->where('estado', 'ACTIVO'))
                ->with(['contrato.cliente', 'contrato.proceso', 'contrato'])
                ->get();

            $countPagos = 0;

            foreach ($cuotas as $cuota) {
                $fechaRaw = $cuota->fecha_programada ?? $cuota->fecha_vencimiento ?? $cuota->fecha ?? null;
                if (!$fechaRaw) continue;

                $fecha = Carbon::parse($fechaRaw);
                $hoy = Carbon::today();
                $monto = number_format($cuota->valor ?? $cuota->monto ?? 0, 0);
                $cliente = $cuota->contrato->cliente->nombre_completo ?? 'Cliente';
                
                $mensajePago = null;
                $tituloPago = "";

                // ALERTA 1: Faltan 3 días
                if ($fecha->diffInDays($hoy) == 3 && $fecha->isFuture()) {
                    $tituloPago = "Próximo Cobro";
                    $mensajePago = "💰 La cuota #{$cuota->numero} de {$cliente} por \${$monto} vence pronto (" . $fecha->format('d/m') . ").";
                
                // ALERTA 2: Es HOY
                } elseif ($fecha->isSameDay($hoy)) {
                    $tituloPago = "Cobrar HOY";
                    $mensajePago = "💲 La cuota de {$cliente} vence HOY. Gestionar pago.";
                
                // ALERTA 3: MORA (CORREGIDO - Detecta cualquier fecha pasada)
                } elseif ($fecha->isPast()) { 
                    $diasMora = $fecha->diffInDays($hoy);
                    $tituloPago = "Pago en MORA ({$diasMora} días)";
                    $mensajePago = "🔴 ALERTA: {$cliente} tiene la cuota vencida hace {$diasMora} días. Iniciar cobro jurídico si aplica.";
                }

                if ($mensajePago) {
                    $enviado = $this->crearNotificacionPago($cuota, $admins, 'mora', $mensajePago, $tituloPago);
                    if ($enviado) $countPagos++;
                }
            }
            $this->info("Pagos gestionados y notificados: {$countPagos}");
        } else {
            $this->warn("No se encontró el modelo ContratoCuota.");
        }

        $this->info('--> Ejecución finalizada exitosamente.');
    }

    // --- MÉTODOS PRIVADOS ---

    private function enviarCorreo($userId, $titulo, $mensaje, $link, $detalles = null)
    {
        try {
            $user = User::find($userId);
            if ($user && $user->email && !str_contains($user->email, 'example.com')) {
                Mail::to($user->email)->send(new AlertaSistemaMailable(
                    $user->name, $titulo, $mensaje, $link, $detalles
                ));
                // Pequeña pausa para no saturar el servidor de correo
                usleep(500000); // 0.5 segundos
            } 
        } catch (\Exception $e) {
            // Silenciamos error de correo para no detener el proceso principal
            // $this->error("Error correo: " . $e->getMessage());
        }
    }

    private function createSystemNotification($proceso, $admins, $mensaje, $titulo)
    {
        $destinatarios = collect();
        foreach($admins as $a) $destinatarios->push($a->id);
        if($proceso->abogado_id) $destinatarios->push($proceso->abogado_id);
        if($proceso->responsable_revision_id) $destinatarios->push($proceso->responsable_revision_id);

        $link = url('/procesos/' . $proceso->id);
        $detalles = 'Radicado: ' . $proceso->radicado;
        $algunoGuardado = false;

        foreach ($destinatarios->unique() as $userId) {
            if ($this->guardarNotificacionSiNoExiste($userId, $mensaje, $titulo, $link, $detalles, 'Proceso')) {
                $algunoGuardado = true;
                $this->enviarCorreo($userId, $titulo, $mensaje, $link, $detalles);
            }
        }
        return $algunoGuardado;
    }

    private function crearNotificacionPago($cuota, $admins, $tipo, $mensaje, $titulo)
    {
        $destinatarios = collect();
        foreach($admins as $a) $destinatarios->push($a->id);
        
        // Notificar también al abogado del caso/proceso si existe
        if ($cuota->contrato->proceso && $cuota->contrato->proceso->abogado_id) {
            $destinatarios->push($cuota->contrato->proceso->abogado_id);
        }

        $casoId = $cuota->contrato->caso_id;
        // Validamos que el caso exista para evitar error de llave foránea
        if ($casoId && !DB::table('casos')->where('id', $casoId)->exists()) {
            $casoId = null;
        }

        $link = $casoId ? route('casos.show', $casoId) : '#';
        $detalles = 'Cuota contrato #' . $cuota->contrato->id;
        $algunoGuardado = false;

        foreach ($destinatarios->unique() as $userId) {
            // Verificamos duplicados para no spamear el mismo día
            $yaEnviado = NotificacionCaso::where('user_id', $userId)
                ->where('mensaje', $mensaje)
                ->whereDate('created_at', Carbon::today())
                ->exists();

            if (!$yaEnviado) {
                // Intentamos guardar en notificaciones de caso
                if ($casoId) {
                    NotificacionCaso::create([
                        'user_id' => $userId,
                        'caso_id' => $casoId,
                        'tipo'    => $tipo,
                        'mensaje' => $mensaje,
                        'fecha_envio' => now(),
                        'leido' => false,
                        'prioridad' => 'alta'
                    ]);
                }
                
                // SIEMPRE guardamos en la campanita general del dashboard
                $this->guardarNotificacionGeneral($userId, $mensaje, $titulo, $link, $detalles);
                
                $algunoGuardado = true;
                $this->enviarCorreo($userId, $titulo, $mensaje, $link, $detalles);
            }
        }
        return $algunoGuardado;
    }

    // Helper para evitar duplicados en la tabla notifications general
    private function guardarNotificacionSiNoExiste($userId, $mensaje, $titulo, $link, $detalles, $tipoObj) {
        $yaExiste = DB::table('notifications')
            ->where('notifiable_id', $userId)
            ->where('data', 'like', '%' . substr($mensaje, 0, 40) . '%') // Busca coincidencia parcial del texto
            ->whereDate('created_at', Carbon::today())
            ->exists();

        if (!$yaExiste) {
            $this->guardarNotificacionGeneral($userId, $mensaje, $titulo, $link, $detalles);
            return true;
        }
        return false;
    }

    private function guardarNotificacionGeneral($userId, $mensaje, $titulo, $link, $detalles) {
        DB::table('notifications')->insert([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\AlertaSistema', // Nombre genérico
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $userId,
            'data' => json_encode([
                'title' => $titulo,
                'message' => $mensaje,
                'link' => $link,
                'details' => $detalles,
                'deadline' => Carbon::today()->format('Y-m-d')
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}