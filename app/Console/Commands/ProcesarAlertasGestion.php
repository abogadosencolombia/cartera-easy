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

        foreach ($notas as $nota) {
            if (!$nota->user) continue;

            $ahora = now();
            $expira = Carbon::parse($nota->expires_at);

            // CASO 1: Vence en menos de 1 hora (60 mins)
            if ($expira->isFuture() && $ahora->diffInMinutes($expira) <= 60) {
                // Verificar si ya le enviamos la alerta de "proxima" hoy
                $yaNotificadoHoy = $this->yaNotificadoRecientemente($nota->user_id, $nota->id, "proxima", 2); 
                
                if (!$yaNotificadoHoy) {
                    $nota->user->notify(new GestionDiariaNotification($nota, "proxima"));
                    $this->info("Notificación PROXIMA enviada a {$nota->user->name} para tarea #{$nota->id}");
                    usleep(500000); // 0.5s pausa seguridad mail
                }
            }

            // CASO 2: Ya está vencida
            if ($expira->isPast()) {
                // Notificar cada 1 hora (60 mins)
                $yaNotificadoRecientemente = $this->yaNotificadoRecientemente($nota->user_id, $nota->id, "vencida", 1);

                if (!$yaNotificadoRecientemente) {
                    $nota->user->notify(new GestionDiariaNotification($nota, "vencida"));
                    $this->info("Notificación VENCIDA enviada a {$nota->user->name} para tarea #{$nota->id}");
                    usleep(500000); // 0.5s pausa seguridad mail
                }
            }
        }

        $this->info("--> Escaneo de Hoja de Ruta finalizado.");
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
