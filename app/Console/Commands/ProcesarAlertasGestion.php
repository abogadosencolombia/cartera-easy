<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NotaGestion;
use App\Notifications\GestionDiariaNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProcesarAlertasGestion extends Command
{
    protected $signature = "gestion:procesar-alertas";
    protected $description = "Procesa las notificaciones de la Hoja de Ruta Diaria (vencimientos).";

    public function handle()
    {
        $this->info("--> Iniciando escaneo de Hoja de Ruta Diaria...");

        // Limitamos la carga inicial para no saturar memoria
        $notas = NotaGestion::where("is_completed", false)
            ->with("user")
            ->get();

        $enviadosEnEstaRonda = 0;
        $maximoCorreosPorRonda = 15; // Límite de seguridad para Hostinger

        foreach ($notas as $nota) {
            // Si ya alcanzamos el límite de ráfaga, paramos para esperar a la siguiente ejecución del cron
            if ($enviadosEnEstaRonda >= $maximoCorreosPorRonda) {
                $this->warn("Límite de ráfaga alcanzado ($maximoCorreosPorRonda). El resto se procesará en la próxima ejecución.");
                break;
            }

            if (!$nota->user || empty($nota->user->email)) continue;

            $ahora = now();
            $expira = Carbon::parse($nota->expires_at);
            $notificado = false;

            // CASO 1: Vence en menos de 1 hora (60 mins)
            if ($expira->isFuture() && $ahora->diffInMinutes($expira) <= 60) {
                if (!$this->yaNotificadoRecientemente($nota->user_id, $nota->id, "proxima", 2)) {
                    $notificado = $this->enviarNotificacionSegura($nota, "proxima");
                }
            }

            // CASO 2: Ya está vencida
            if ($expira->isPast()) {
                if (!$this->yaNotificadoRecientemente($nota->user_id, $nota->id, "vencida", 1)) {
                    $notificado = $this->enviarNotificacionSegura($nota, "vencida");
                }
            }

            if ($notificado) {
                $enviadosEnEstaRonda++;
                // Pausa de 3 segundos para estabilizar la conexión SMTP
                sleep(3); 
            }
        }

        $this->info("--> Escaneo finalizado. Enviados en esta ronda: $enviadosEnEstaRonda");
    }

    /**
     * Envía la notificación capturando errores de SMTP para no romper el ciclo.
     */
    private function enviarNotificacionSegura($nota, $tipo)
    {
        try {
            $nota->user->notify(new GestionDiariaNotification($nota, $tipo));
            $this->info("OK: Notificación $tipo enviada a {$nota->user->name} (Tarea #{$nota->id})");
            return true;
        } catch (\Exception $e) {
            \Log::error("Error SMTP en ProcesarAlertasGestion (Tarea #{$nota->id}): " . $e->getMessage());
            $this->error("FAIL: No se pudo enviar a {$nota->user->name}. Error registrado en log.");
            return false;
        }
    }

    private function yaNotificadoRecientemente($userId, $notaId, $estado, $horas = 1)
    {
        // Buscamos en la tabla de notificaciones de Laravel (notifications)
        return DB::table("notifications")
            ->where("notifiable_id", $userId)
            ->where("data", "like", "%\"nota_id\":$notaId%")
            ->where("data", "like", "%\"estado\":\"$estado\"%")
            ->where("created_at", ">=", now()->subHours($horas))
            ->exists();
    }
}
