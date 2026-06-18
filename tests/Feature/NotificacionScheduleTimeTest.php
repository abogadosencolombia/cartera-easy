<?php

namespace Tests\Feature;

use App\Http\Controllers\NotificacionController;
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

    private function normalizarFechaProgramada(?string $fecha)
    {
        $reflection = new ReflectionClass(new NotificacionController());
        $method = $reflection->getMethod('normalizarFechaProgramada');
        $method->setAccessible(true);

        return $method->invoke(new NotificacionController(), $fecha);
    }
}
