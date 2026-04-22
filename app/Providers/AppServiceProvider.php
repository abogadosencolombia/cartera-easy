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

// ===== INICIO DE LA MODIFICACIÓN (MÓDULO DE TAREAS) =====
use App\Models\Tarea;
use App\Policies\TareaPolicy;
// ===== FIN DE LA MODIFICACIÓN =====

// --- 1. IMPORTAMOS LOS OBSERVERS ---
use App\Observers\CasoObserver;
use App\Observers\AuditoriaObserver;

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

        // ===== INICIO DE LA MODIFICACIÓN (MÓDULO DE TAREAS) =====
        // Aquí le decimos a Laravel que use TareaPolicy para el modelo Tarea
        Gate::policy(Tarea::class, TareaPolicy::class);
        // ===== FIN DE LA MODIFICACIÓN =====


        // --- REGISTRO DEL NUEVO PROTOCOLO DE SEGURIDAD ---
        Gate::define('view-reports', [ReportPolicy::class, 'viewDashboard']);


        // Gate de administrador existente
        Gate::define('isAdmin', function (User $user) {
            return $user->tipo_usuario === 'admin';
        });

        // ===== 2. AQUÍ REGISTRAMOS NUESTROS OBSERVERS =====
        // Esta línea activa el motor que crea las validaciones automáticamente.
        Caso::observe(CasoObserver::class);
        
        // Esta línea activa el vigilante de seguridad de auditoría.
        \App\Models\AuditoriaEvento::observe(AuditoriaObserver::class);
    }
}