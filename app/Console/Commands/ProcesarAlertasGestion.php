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

        $notas = NotaGestion::where("is_completed", false)
            ->with("user")
            ->get();

        $enviadosEnEstaRonda = 0;
        $maximoCorreosPorRonda = 15; // Límite de seguridad para Hostinger

        foreach ($notas as $nota) {
            if ($enviadosEnEstaRonda >= $maximoCorreosPorRonda) {
                $this->warn("Límite de ráfaga alcanzado ($maximoCorreosPorRonda). El resto se procesará en la próxima ejecución.");
                break;
            }

            if (!$nota->user || empty($nota->user->email)) continue;

            $ahora = now();
            $expira = Carbon::parse($nota->expires_at);
            $notificado = false;

            try {
                // CASO 1: Vence en menos de 1 hora
                if ($expira->isFuture() && $ahora->diffInMinutes($expira) <= 60) {
                    if (!$this->yaNotificadoRecientemente($nota->user_id, $nota->id, "proxima", 2)) {
                        $nota->user->notify(new GestionDiariaNotification($nota, "proxima"));
                        $this->info("OK: Notificación proxima enviada a {$nota->user->name} (Tarea #{$nota->id})");
                        $notificado = true;
                    }
                }

                // CASO 2: Ya está vencida
                if (!$notificado && $expira->isPast()) {
                    if (!$this->yaNotificadoRecientemente($nota->user_id, $nota->id, "vencida", 1)) {
                        $nota->user->notify(new GestionDiariaNotification($nota, "vencida"));
                        $this->info("OK: Notificación vencida enviada a {$nota->user->name} (Tarea #{$nota->id})");
                        $notificado = true;
                    }
                }

                if ($notificado) {
                    $enviadosEnEstaRonda++;
                    $this->info("Pausa de 3s para SMTP...");
                    sleep(3); 
                }
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                \Log::error("Error SMTP en ProcesarAlertasGestion (Tarea #{$nota->id}): " . $errorMessage);
                $this->error("FAIL: No se pudo enviar a {$nota->user->name}. " . $errorMessage);

                // CIRCUIT BREAKER: Detenemos si es Rate Limit
                if (str_contains($errorMessage, 'Ratelimit') || str_contains($errorMessage, '451')) {
                    $this->error("ALERTA: Rate Limit detectado en Hostinger. Abortando ejecución.");
                    return;
                }
                
                // Aun en error no crítico, pausamos 3s
                sleep(3);
            }
        }

        $this->info("--> Escaneo finalizado. Enviados: $enviadosEnEstaRonda");
    }

    private function yaNotificadoRecientemente($userId, $notaId, $estado, $horas = 1)
    {
        return DB::table("notifications")
            ->where("notifiable_id", $userId)
            ->where("data", "like", "%\"nota_id\":$notaId%")
            ->where("data", "like", "%\"estado\":\"$estado\"%")
            ->where("created_at", ">=", now()->subHours($horas))
            ->exists();
    }
}
