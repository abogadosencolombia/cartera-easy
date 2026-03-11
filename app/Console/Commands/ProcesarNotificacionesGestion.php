<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NotaGestion;
use App\Notifications\NotaGestionAlerta;
use Carbon\Carbon;

class ProcesarNotificacionesGestion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gestion:procesar-alertas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa las alertas de tiempo de las notas de gestión de los abogados';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ahora = now();

        // 1. Alerta preventiva: 1 hora antes de vencer (8 horas límite - 1 hora = 7 horas de creación)
        $notasCasiVencen = NotaGestion::where('is_completed', false)
            ->where('notified_before', false)
            ->where('expires_at', '<=', $ahora->copy()->addHour())
            ->where('expires_at', '>', $ahora)
            ->get();

        foreach ($notasCasiVencen as $nota) {
            try {
                $nota->user->notify(new NotaGestionAlerta($nota, 'before_expiry'));
                $this->info("Notificación preventiva enviada para nota ID: {$nota->id}");
            } catch (\Exception $e) {
                $this->error("Error notificando nota ID: {$nota->id} - " . $e->getMessage());
            }
            // Marcamos como notificado igualmente para no reintentar infinitamente si falla el mail
            $nota->update(['notified_before' => true]);
        }

        // 2. Alerta de vencimiento inmediato (al llegar a las 8 horas)
        $notasVencidas = NotaGestion::where('is_completed', false)
            ->where('notified_after', false)
            ->where('expires_at', '<=', $ahora)
            ->get();

        foreach ($notasVencidas as $nota) {
            try {
                $nota->user->notify(new NotaGestionAlerta($nota, 'expired'));
                $this->info("Notificación de vencimiento enviada para nota ID: {$nota->id}");
            } catch (\Exception $e) {
                $this->error("Error notificando vencimiento nota ID: {$nota->id} - " . $e->getMessage());
            }
            $nota->update(['notified_after' => true]);
        }

        // 3. Alertas periódicas: Cada hora después de vencer si sigue pendiente
        $notasPeriodic = NotaGestion::where('is_completed', false)
            ->where('expires_at', '<', $ahora)
            ->where(function($q) use ($ahora) {
                $q->whereNull('last_periodic_notification_at')
                  ->orWhere('last_periodic_notification_at', '<=', $ahora->copy()->subHour());
            })
            ->get();

        foreach ($notasPeriodic as $nota) {
            try {
                $nota->user->notify(new NotaGestionAlerta($nota, 'periodic'));
                $this->info("Notificación periódica enviada para nota ID: {$nota->id}");
            } catch (\Exception $e) {
                $this->error("Error notificando periódica nota ID: {$nota->id} - " . $e->getMessage());
            }
            $nota->update(['last_periodic_notification_at' => $ahora]);
        }

        $this->info('Proceso de alertas de gestión finalizado.');
    }
}
