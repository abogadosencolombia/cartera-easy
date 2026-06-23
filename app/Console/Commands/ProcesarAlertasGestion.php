<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NotaGestion;
use App\Notifications\GestionDiariaNotification;
use App\Support\SmtpCircuitBreaker;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcesarAlertasGestion extends Command
{
    protected $signature = "gestion:procesar-alertas";
    protected $description = "Procesa las notificaciones de la Hoja de Ruta Diaria (vencimientos).";

    public function handle(): int
    {
        if (SmtpCircuitBreaker::active()) {
            $until = SmtpCircuitBreaker::until();
            $this->warn("SMTP en cooldown hasta {$until?->toDateTimeString()}. Se omite esta ejecución.");
            return self::SUCCESS;
        }

        $this->info("--> Iniciando escaneo de Hoja de Ruta Diaria...");

        $notas = NotaGestion::where("is_completed", false)
            ->where('expires_at', '<=', now()->addHour())
            ->with("user:id,name,email,preferencias_notificacion")
            ->orderBy('expires_at')
            ->limit(50)
            ->get();

        $enviadosEnEstaRonda = 0;
        $maximoCorreosPorRonda = $this->mailBurstLimit();

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
                    $this->pauseForSmtp();
                }
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                Log::error("Error SMTP en ProcesarAlertasGestion (Tarea #{$nota->id}): " . $errorMessage);
                $this->error("FAIL: No se pudo enviar a {$nota->user->name}. " . $errorMessage);

                if (SmtpCircuitBreaker::isRateLimited($errorMessage)) {
                    $until = SmtpCircuitBreaker::trip($errorMessage);
                    Log::warning('SMTP en cooldown para alertas de gestión.', [
                        'hasta' => $until->toDateTimeString(),
                        'error' => $errorMessage,
                    ]);
                    $this->warn("Rate limit/timeout SMTP detectado. Cooldown hasta {$until->toDateTimeString()}.");
                    return self::SUCCESS;
                }
                
                // Aun en error no crítico, pausamos para no saturar SMTP.
                $this->pauseForSmtp();
            }
        }

        $this->info("--> Escaneo finalizado. Enviados: $enviadosEnEstaRonda");

        return self::SUCCESS;
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

    private function mailBurstLimit(): int
    {
        return max(1, (int) config('mail.alerts.burst_limit', 2));
    }

    private function pauseForSmtp(): void
    {
        $seconds = max(0, (int) config('mail.alerts.pause_seconds', 30));

        if ($seconds > 0) {
            $this->info("Pausa de {$seconds}s para SMTP...");
            sleep($seconds);
        }
    }

}
