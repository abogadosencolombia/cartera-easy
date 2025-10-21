<?php

namespace App\Jobs;

use App\Models\NotificacionCaso;
use App\Notifications\AlertaProgramadaNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcesarAlertasProgramadas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct()
    {
        // Forzamos cola del job (evita redefinir la propiedad del trait)
        $this->onQueue('scheduler');
    }

    public function handle(): void
    {
        $tz  = config('app.timezone');
        $now = now($tz)->seconds(0);

        NotificacionCaso::query()
            ->where('tipo', 'alerta_manual')
            ->where('completed', false)
            ->with('user')
            ->orderBy('id')
            ->chunkById(200, function ($lote) use ($now, $tz) {
                foreach ($lote as $a) {
                    $user = $a->user;
                    if (!$user) {
                        $a->forceFill(['completed' => true, 'last_sent_at' => $now])->save();
                        continue;
                    }

                    // Inmediata (sin fecha)
                    if (is_null($a->programado_en)) {
                        $user->notify(new AlertaProgramadaNotification(
                            $a->caso_id, $a->mensaje, true, $a->prioridad ?? 'media'
                        ));
                        $a->forceFill([
                            'fecha_envio'  => $now,
                            'last_sent_at' => $now,
                            'completed'    => true,
                        ])->save();
                        continue;
                    }

                    $target = $a->programado_en->timezone($tz)->seconds(0);
                    $diff   = $now->diffInMinutes($target, false);

                    if ($diff <= 0) {
                        // Llegó o se pasó
                        $user->notify(new AlertaProgramadaNotification(
                            $a->caso_id, $a->mensaje, true, $a->prioridad ?? 'media'
                        ));
                        $a->forceFill([
                            'fecha_envio'  => $now,
                            'last_sent_at' => $now,
                            'completed'    => true,
                        ])->save();
                        continue;
                    }

                    // Recordatorio diario solo si falta ≥ 60min o es otro día
                    if (!$target->isSameDay($now) || $diff >= 60) {
                        $ultimo = $a->last_sent_at?->timezone($tz);
                        if (is_null($ultimo) || $ultimo->lt($now->copy()->startOfDay())) {
                            $user->notify(new AlertaProgramadaNotification(
                                $a->caso_id, $a->mensaje, false, $a->prioridad ?? 'media'
                            ));
                            $a->forceFill(['last_sent_at' => $now])->save();
                        }
                    }
                }
            });
    }
}
