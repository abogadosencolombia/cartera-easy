<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CalculateLateFees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:calculate-late-fees';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calcula y acumula los intereses de mora para cuotas y cargos vencidos.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Iniciando cálculo y acumulación de intereses de mora...');
        $today = Carbon::today();

        // ===== INICIO DE LA CORRECCIÓN CLAVE =====
        // La lógica se ha corregido para encontrar la tasa más reciente que ya ha entrado en vigencia.
        // 1. Filtra todas las tasas cuya 'fecha_vigencia' es hoy o una fecha pasada.
        // 2. De ese grupo de tasas válidas, ordena por la más reciente ('fecha_vigencia' descendente).
        // 3. Toma la primera, que será la tasa vigente correcta.
        $tasaVigente = DB::table('intereses_tasas')
                          ->where('fecha_vigencia', '<=', $today)
                          ->orderByDesc('fecha_vigencia')
                          ->first();
        // ===== FIN DE LA CORRECCIÓN CLAVE =====

        if (!$tasaVigente) {
            $this->error('No se encontró una tasa de interés vigente para la fecha de hoy. Verifica la tabla `intereses_tasas`. Abortando.');
            return self::FAILURE;
        }

        $tasa_ea_decimal = $tasaVigente->tasa_ea / 100;
        // Fórmula correcta para la tasa diaria efectiva
        $tasa_diaria = pow(1 + $tasa_ea_decimal, 1/365) - 1;
        
        $this->info("Tasa vigente encontrada (ID: {$tasaVigente->id}): {$tasaVigente->tasa_ea}% EA del {$tasaVigente->fecha_vigencia}. Tasa diaria calculada: " . number_format($tasa_diaria * 100, 6) . "%");

        $this->procesarCuotasVencidas($today, $tasa_diaria);
        $this->procesarCargosVencidos($today, $tasa_diaria);

        $this->info('Cálculo de intereses finalizado exitosamente.');
        return self::SUCCESS;
    }

    /**
     * Procesa las cuotas de contratos que están vencidas.
     */
    private function procesarCuotasVencidas(Carbon $today, float $tasa_diaria): void
    {
        $this->line("\n[Procesando Cuotas Vencidas]");
        $cuotasVencidas = DB::table('contrato_cuotas as cc')
            ->join('contratos as c', 'cc.contrato_id', '=', 'c.id')
            ->whereIn('c.estado', ['ACTIVO', 'PAGOS_PENDIENTES', 'EN_MORA'])
            ->where('cc.estado', 'PENDIENTE')
            ->where('cc.fecha_vencimiento', '<', $today)
            ->select('cc.id', 'cc.valor', 'cc.fecha_vencimiento', 'c.id as contrato_id')
            ->get();

        if ($cuotasVencidas->isEmpty()) {
            $this->info("No se encontraron cuotas vencidas para procesar.");
            return;
        }
        $this->info("Se encontraron {$cuotasVencidas->count()} cuotas vencidas.");

        foreach ($cuotasVencidas as $cuota) {
            $diasVencidos = Carbon::parse($cuota->fecha_vencimiento)->diffInDays($today);
            $intereses_totales = round(($cuota->valor * $tasa_diaria) * $diasVencidos, 2);

            DB::table('contrato_cuotas')
                ->where('id', $cuota->id)
                ->update(['intereses_mora_acumulados' => $intereses_totales]);
            
            // Actualizar el estado del contrato a EN_MORA si aún no lo está
            DB::table('contratos')->where('id', $cuota->contrato_id)->update(['estado' => 'EN_MORA']);
            
            $this->line(" -> Cuota #{$cuota->id} (Contrato #{$cuota->contrato_id}): {$diasVencidos} días vencidos. Mora actualizada a $" . number_format($intereses_totales, 2));
        }
    }

    /**
     * Procesa los cargos adicionales de contratos que están vencidos.
     */
    private function procesarCargosVencidos(Carbon $today, float $tasa_diaria): void
    {
        $this->line("\n[Procesando Cargos Vencidos]");
        
        $cargosVencidos = DB::table('contrato_cargos as cc')
            ->join('contratos as c', 'cc.contrato_id', '=', 'c.id')
            ->whereIn('c.estado', ['ACTIVO', 'PAGOS_PENDIENTES', 'EN_MORA'])
            ->where('cc.estado', 'PENDIENTE')
            ->whereNotNull('cc.fecha_inicio_intereses')
            ->where('cc.fecha_inicio_intereses', '<', $today)
            ->select('cc.id', 'cc.monto', 'cc.fecha_inicio_intereses', 'c.id as contrato_id')
            ->get();
            
        if ($cargosVencidos->isEmpty()) {
            $this->info("No se encontraron cargos con mora activa para procesar.");
            return;
        }
        $this->info("Se encontraron {$cargosVencidos->count()} cargos vencidos.");


        foreach ($cargosVencidos as $cargo) {
            $diasDeMora = Carbon::parse($cargo->fecha_inicio_intereses)->diffInDays($today);

            if ($diasDeMora <= 0) continue;

            $intereses_totales = round(($cargo->monto * $tasa_diaria) * $diasDeMora, 2);

            DB::table('contrato_cargos')
                ->where('id', $cargo->id)
                ->update(['intereses_mora_acumulados' => $intereses_totales]);

            // Actualizar el estado del contrato a EN_MORA si aún no lo está
            DB::table('contratos')->where('id', $cargo->contrato_id)->update(['estado' => 'EN_MORA']);
            
            $this->line(" -> Cargo #{$cargo->id} (Contrato #{$cargo->contrato_id}): {$diasDeMora} días de mora. Mora actualizada a $" . number_format($intereses_totales, 2));
        }
    }
}
