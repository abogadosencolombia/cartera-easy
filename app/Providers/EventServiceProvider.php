<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
// --- Importaciones de tus modelos y observers ---
use App\Models\Caso;
use App\Models\ValidacionLegal;
use App\Observers\CasoObserver;
use App\Observers\ValidacionLegalObserver;
// --- Importaciones de tus eventos y listeners (si los tienes) ---
use App\Events\ReporteExportado;
use App\Listeners\RegistrarExportacionDeReporte;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use App\Models\PagoCaso;
use App\Observers\PagoCasoObserver;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The model observers for your application.
     *
     * Este array es el método más directo y recomendado para registrar observers.
     * Le decimos a Laravel explícitamente qué observer vigila a qué modelo.
     *
     * @var array
     */
    protected $observers = [
        // Conexión 1: Cuando un Caso cambie, notifica a CasoObserver.
        Caso::class => [CasoObserver::class],
        
        // Conexión 2: Cuando una ValidacionLegal cambie, notifica a ValidacionLegalObserver.
        ValidacionLegal::class => [ValidacionLegalObserver::class],

        // ===== ¡ESTA ES LA LÍNEA QUE FALTABA! =====
        // Conexión 3: Cuando un PagoCaso se cree, notifica a PagoCasoObserver.
        PagoCaso::class => [PagoCasoObserver::class],
    ];

    /**
     * The event to listener mappings for the application.
     * Mantenemos tus listeners existentes para otras funcionalidades.
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        ReporteExportado::class => [
            RegistrarExportacionDeReporte::class,
        ],
        // ...otros listeners que puedas tener...
    ];


    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
