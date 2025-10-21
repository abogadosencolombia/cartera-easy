<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

// Importamos los modelos y políticas existentes
use App\Models\Cooperativa;
use App\Policies\CooperativaPolicy;
use App\Models\Caso;
use App\Policies\CasoPolicy;
use App\Models\Persona;
use App\Policies\PersonaPolicy;
use App\Models\PlantillaDocumento;
use App\Policies\PlantillaPolicy;
use App\Policies\ReportPolicy;
use App\Policies\UserPolicy;

// --- 1. IMPORTAMOS EL OBSERVER ---
use App\Observers\CasoObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registro de políticas existentes
        Gate::policy(Cooperativa::class, CooperativaPolicy::class);
        Gate::policy(Caso::class, CasoPolicy::class);
        Gate::policy(Persona::class, PersonaPolicy::class);
        Gate::policy(PlantillaDocumento::class, PlantillaPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // --- REGISTRO DEL NUEVO PROTOCOLO DE SEGURIDAD ---
        Gate::define('view-reports', [ReportPolicy::class, 'viewDashboard']);


        // Gate de administrador existente
        Gate::define('isAdmin', function (User $user) {
            return $user->tipo_usuario === 'admin';
        });

        // ===== 2. AQUÍ REGISTRAMOS NUESTRO OBSERVER PARA CASOS =====
        // Esta línea activa el motor que crea las validaciones automáticamente.
        Caso::observe(CasoObserver::class);
    }
}
