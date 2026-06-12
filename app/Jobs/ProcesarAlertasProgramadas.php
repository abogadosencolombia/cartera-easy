<?php

namespace App\Jobs;

use App\Models\IncidenteJuridico;
use App\Models\NotificacionCaso;
use App\Models\User;
use App\Notifications\AlertaProgramadaNotification;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\WebPushChannel;
use Throwable;

class ProcesarAlertasProgramadas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_FINALES_POR_EJECUCION = 200;
    private const MAX_RECORDATORIOS_POR_EJECUCION = 25;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('scheduler');
    }

    public function handle(): array
    {
        $tz = config('app.timezone', 'America/Bogota');
        $now = now($tz)->seconds(0);

        $stats = [
            'finales_enviadas' => 0,
            'recordatorios_enviados' => 0,
            'sin_destinatario' => 0,
            'errores' => 0,
        ];

        $this->procesarAlertasVencidas($now, $stats);
        $this->procesarRecordatoriosFuturos($now, $stats);

        if ($stats['finales_enviadas'] > 0 || $stats['recordatorios_enviados'] > 0 || $stats['errores'] > 0) {
            Log::info('Alertas programadas procesadas.', $stats);
        }

        return $stats;
    }

    private function consultaPendientes()
    {
        return NotificacionCaso::query()
            ->where('tipo', 'alerta_manual')
            ->where(function ($query) {
                $query->where('completed', false)
                    ->orWhereNull('completed');
            })
            ->deExpedientesEnSeguimiento();
    }

    private function procesarAlertasVencidas(CarbonInterface $now, array &$stats): void
    {
        $this->consultaPendientes()
            ->where(function ($query) use ($now) {
                $query->whereNull('programado_en')
                    ->orWhere('programado_en', '<=', $now->toDateTimeString());
            })
            ->with(['user.pushSubscriptions'])
            ->orderBy('programado_en')
            ->orderBy('id')
            ->limit(self::MAX_FINALES_POR_EJECUCION)
            ->get()
            ->each(function (NotificacionCaso $alerta) use ($now, &$stats) {
                if ($this->entregar($alerta, true, $now, $stats)) {
                    if ($alerta->prioridad === 'alta') {
                        $this->crearIncidenteAltaPrioridad($alerta);
                    }

                    $alerta->forceFill([
                        'fecha_envio' => $now,
                        'last_sent_at' => $now,
                        'completed' => true,
                    ])->save();

                    $stats['finales_enviadas']++;
                }
            });
    }

    private function procesarRecordatoriosFuturos(CarbonInterface $now, array &$stats): void
    {
        $this->consultaPendientes()
            ->whereNotNull('programado_en')
            ->where('programado_en', '>', $now->copy()->addHour()->toDateTimeString())
            ->where(function ($query) use ($now) {
                $query->whereNull('last_sent_at')
                    ->orWhere('last_sent_at', '<', $now->copy()->startOfDay()->toDateTimeString());
            })
            ->with(['user.pushSubscriptions'])
            ->orderBy('programado_en')
            ->orderBy('id')
            ->limit(self::MAX_RECORDATORIOS_POR_EJECUCION)
            ->get()
            ->each(function (NotificacionCaso $alerta) use ($now, &$stats) {
                if ($this->entregar($alerta, false, $now, $stats)) {
                    $alerta->forceFill(['last_sent_at' => $now])->save();
                    $stats['recordatorios_enviados']++;
                }
            });
    }

    private function entregar(NotificacionCaso $alerta, bool $esFinal, CarbonInterface $now, array &$stats): bool
    {
        $user = $alerta->user;

        if (!$user) {
            $alerta->forceFill([
                'last_sent_at' => $now,
                'completed' => $esFinal ? true : $alerta->completed,
            ])->save();
            $stats['sin_destinatario']++;
            return true;
        }

        $emailEsperado = $this->quiereCorreo($user);
        $pushEsperado = $this->quierePush($user);
        $falloCorreo = false;
        $falloPush = false;

        if ($emailEsperado) {
            try {
                $user->notify(new AlertaProgramadaNotification(
                    $alerta->caso_id,
                    $alerta->mensaje,
                    $esFinal,
                    $alerta->prioridad ?? 'media',
                    $alerta->proceso_id,
                    ['mail']
                ));
            } catch (Throwable $exception) {
                $falloCorreo = true;
                $stats['errores']++;
                $this->registrarFallo($alerta, $user, 'mail', $exception);
            }
        }

        if ($pushEsperado) {
            try {
                $user->notify(new AlertaProgramadaNotification(
                    $alerta->caso_id,
                    $alerta->mensaje,
                    $esFinal,
                    $alerta->prioridad ?? 'media',
                    $alerta->proceso_id,
                    [WebPushChannel::class]
                ));
            } catch (Throwable $exception) {
                $falloPush = true;
                $stats['errores']++;
                $this->registrarFallo($alerta, $user, 'webpush', $exception);
            }
        }

        if (!$emailEsperado && !$pushEsperado) {
            Log::info('Alerta programada sin canales externos habilitados.', [
                'notificacion_id' => $alerta->id,
                'user_id' => $user->id,
            ]);
            return true;
        }

        if ($emailEsperado && $falloCorreo) {
            return false;
        }

        return !$pushEsperado || !$falloPush || $emailEsperado;
    }

    private function quiereCorreo(User $user): bool
    {
        return !empty($user->email)
            && filter_var($user->email, FILTER_VALIDATE_EMAIL)
            && (bool) data_get($user->preferencias_notificacion, 'email', true);
    }

    private function quierePush(User $user): bool
    {
        if (!(bool) data_get($user->preferencias_notificacion, 'in-app', true)) {
            return false;
        }

        if ($user->relationLoaded('pushSubscriptions')) {
            return $user->pushSubscriptions->isNotEmpty();
        }

        return $user->pushSubscriptions()->exists();
    }

    private function registrarFallo(NotificacionCaso $alerta, User $user, string $canal, Throwable $exception): void
    {
        Log::error('Fallo enviando alerta programada.', [
            'notificacion_id' => $alerta->id,
            'user_id' => $user->id,
            'canal' => $canal,
            'error' => $exception->getMessage(),
        ]);
    }

    private function crearIncidenteAltaPrioridad(NotificacionCaso $alerta): void
    {
        try {
            IncidenteJuridico::create([
                'usuario_responsable_id' => $alerta->user_id,
                'origen' => 'auditoria',
                'asunto' => 'Alerta de Alta Prioridad Vencida',
                'descripcion' => "Se genero un incidente automatico porque una alerta de prioridad ALTA se vencio sin ser completada.\n\nMensaje de la alerta: {$alerta->mensaje}\nCaso ID: #{$alerta->caso_id}\nProceso ID: #{$alerta->proceso_id}",
                'estado' => 'pendiente',
                'fecha_registro' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('No se pudo crear incidente por alerta de alta prioridad.', [
                'notificacion_id' => $alerta->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
