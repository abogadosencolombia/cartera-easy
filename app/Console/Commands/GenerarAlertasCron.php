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

use App\Notifications\ProcesoRevisionNotification;
use Illuminate\Support\Facades\Notification;

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
        $procesos = ProcesoRadicado::paraSeguimiento()
            ->where(function($q) {
                $q->whereNotNull('fecha_proxima_revision')
                  ->orWhereNotNull('etapa_procesal_id');
            })
            ->with(['abogado', 'responsableRevision', 'etapaActual'])
            ->get();

        $countProcesos = 0;
        $countErrores = 0;
        $bar = $this->output->createProgressBar(count($procesos));
        $bar->start();

        foreach ($procesos as $proceso) {
            try {
                $hoy = Carbon::today();
                $tipoAlerta = null;

                // A. Por Fecha Próxima Revisión
                if ($proceso->fecha_proxima_revision) {
                    $fecha = Carbon::parse($proceso->fecha_proxima_revision)->startOfDay();
                    
                    if ($fecha->isSameDay($hoy)) {
                        $tipoAlerta = 'hoy';
                    } elseif ($fecha->isPast()) {
                        $tipoAlerta = 'vencida';
                    } elseif (abs($fecha->diffInDays($hoy)) <= 2 && $fecha->isFuture()) {
                        $tipoAlerta = 'proxima';
                    }
                }

                // B. Por SLA de Etapa (Si no hay alerta por fecha o es más urgente)
                if (!$tipoAlerta && $proceso->dias_para_vencer !== null) {
                    if ($proceso->dias_para_vencer < 0) {
                        $tipoAlerta = 'vencida';
                    } elseif ($proceso->dias_para_vencer <= 1) {
                        $tipoAlerta = 'hoy';
                    }
                }

                if ($tipoAlerta) {
                    $destinatarios = collect();
                    if ($proceso->abogado && !empty($proceso->abogado->email)) $destinatarios->push($proceso->abogado);
                    if ($proceso->responsableRevision && !empty($proceso->responsableRevision->email)) $destinatarios->push($proceso->responsableRevision);
                    
                    $destinatarios = $destinatarios->unique('id');

                    if ($destinatarios->isNotEmpty()) {
                        // Evitar duplicados el mismo día para la misma alerta
                        // Se usa LIKE porque la columna data en PostgreSQL es TEXT, no JSON
                        $yaNotificado = DB::table('notifications')
                            ->where('notifiable_type', 'App\Models\User')
                            ->whereIn('notifiable_id', $destinatarios->pluck('id'))
                            ->where('data', 'like', '%"proceso_id":' . $proceso->id . '%')
                            ->where('data', 'like', '%"estado":"' . $tipoAlerta . '"%')
                            ->whereDate('created_at', Carbon::today())
                            ->exists();

                        if (!$yaNotificado) {
                            // Límite de ráfaga (20) para evitar saturación de Hostinger
                            if ($countProcesos >= 20) {
                                $this->warn("Límite de ráfaga (20) alcanzado en procesos. El resto se enviará en la próxima ejecución.");
                                break;
                            }

                            Notification::send($destinatarios, new ProcesoRevisionNotification($proceso, $tipoAlerta));
                            $countProcesos++;
                            $this->info("Notificación enviada para Proceso #{$proceso->id}");
                            
                            // Pausa de 3 segundos obligatoria para estabilizar la conexión SMTP
                            sleep(3);
                        }
                    }
                }
            } catch (\Exception $e) {
                $countErrores++;
                $errorMessage = $e->getMessage();
                \Log::error("Error procesando alerta para Proceso #{$proceso->id}: " . $errorMessage);
                
                // CIRCUIT BREAKER: Si es un error de Rate Limit, detenemos todo el proceso
                if (str_contains($errorMessage, 'Ratelimit') || str_contains($errorMessage, '451')) {
                    $this->error("ALERTA: Se ha detectado un Rate Limit en Hostinger. Deteniendo envíos para evitar bloqueos permanentes.");
                    break;
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->output->newLine();
        $this->info("Procesos notificados: {$countProcesos}. Errores: {$countErrores}.");

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
                // Límite de ráfaga para pagos (máximo 20 por ejecución)
                if ($countPagos >= 20) {
                    $this->warn("Límite de ráfaga (20) alcanzado en pagos. El resto se procesará luego.");
                    break;
                }

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
                    try {
                        $enviado = $this->crearNotificacionPago($cuota, $admins, 'mora', $mensajePago, $tituloPago);
                        if ($enviado) {
                            $countPagos++;
                            // Pausa de seguridad obligatoria
                            sleep(3);
                        }
                    } catch (\Exception $e) {
                        $msg = $e->getMessage();
                        \Log::error("Error en pago cuota #{$cuota->id}: " . $msg);
                        if (str_contains($msg, 'Ratelimit') || str_contains($msg, '451')) {
                            $this->error("Rate Limit detectado en pagos. Abortando.");
                            break;
                        }
                        sleep(3);
                    }
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
            if ($user && !empty($user->email) && !str_contains($user->email, 'example.com')) {
                Mail::to($user->email)->send(new AlertaSistemaMailable(
                    $user->name, $titulo, $mensaje, $link, $detalles
                ));
                $this->info("Correo enviado a {$user->email}");
                // Pausa obligatoria para Hostinger
                sleep(3);
            } 
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            \Log::error("Error enviando correo a User #{$userId}: " . $msg);
            
            // CIRCUIT BREAKER
            if (str_contains($msg, 'Ratelimit') || str_contains($msg, '451')) {
                $this->error("Rate Limit detectado en enviarCorreo. Deteniendo proceso.");
                die(); // En un helper privado que se usa en bucles, die() es lo más seguro
            }
            sleep(3);
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
