<?php

namespace Tests\Feature;

use App\Http\Controllers\NotificacionController;
use App\Jobs\ProcesarAlertasProgramadas;
use Illuminate\Support\Carbon;
use ReflectionClass;
use Tests\TestCase;

class NotificacionScheduleTimeTest extends TestCase
{
    public function test_date_only_notification_defaults_to_business_hour(): void
    {
        config(['app.timezone' => 'America/Bogota']);

        $fecha = $this->normalizarFechaProgramada('2026-06-10');

        $this->assertSame('2026-06-10 08:00:00', $fecha->format('Y-m-d H:i:s'));
        $this->assertSame('America/Bogota', $fecha->timezoneName);
    }

    public function test_explicit_morning_time_is_preserved(): void
    {
        config(['app.timezone' => 'America/Bogota']);

        $fecha = $this->normalizarFechaProgramada('2026-06-10 07:15:00');

        $this->assertSame('2026-06-10 07:15:00', $fecha->format('Y-m-d H:i:s'));
    }

    public function test_explicit_evening_time_is_preserved(): void
    {
        config(['app.timezone' => 'America/Bogota']);

        $fecha = $this->normalizarFechaProgramada('2026-06-10 20:30:00');

        $this->assertSame('2026-06-10 20:30:00', $fecha->format('Y-m-d H:i:s'));
    }


    public function test_programmed_alert_processor_rejects_night_hours(): void
    {
        $this->assertFalse($this->estaEnHorarioLaboral('2026-06-10 23:30:00'));
        $this->assertFalse($this->estaEnHorarioLaboral('2026-06-11 03:00:00'));
    }

    public function test_programmed_alert_processor_allows_business_hours_on_weekdays(): void
    {
        $this->assertTrue($this->estaEnHorarioLaboral('2026-06-11 08:00:00'));
        $this->assertTrue($this->estaEnHorarioLaboral('2026-06-11 17:59:00'));
    }

    public function test_programmed_alert_processor_rejects_weekends(): void
    {
        $this->assertFalse($this->estaEnHorarioLaboral('2026-06-13 10:00:00'));
    }

    private function estaEnHorarioLaboral(string $fecha): bool
    {
        $reflection = new ReflectionClass(new ProcesarAlertasProgramadas());
        $method = $reflection->getMethod('estaEnHorarioLaboral');
        $method->setAccessible(true);

        return $method->invoke(new ProcesarAlertasProgramadas(), Carbon::parse($fecha, 'America/Bogota'));
    }

    private function normalizarFechaProgramada(?string $fecha)
    {
        $reflection = new ReflectionClass(new NotificacionController());
        $method = $reflection->getMethod('normalizarFechaProgramada');
        $method->setAccessible(true);

        return $method->invoke(new NotificacionController(), $fecha);
    }
}
