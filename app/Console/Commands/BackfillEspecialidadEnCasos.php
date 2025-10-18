<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Caso;
use App\Models\EspecialidadJuridica;
use Illuminate\Support\Facades\DB;

class BackfillEspecialidadEnCasos extends Command
{
    protected $signature = 'casos:backfill-especialidad {--dry-run} {--batch=500}';
    protected $description = 'Asigna especialidad_id a casos según tipo/subtipo/naturaleza, con fallback seguro.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $batch = (int) $this->option('batch');
        $totalPend = Caso::whereNull('especialidad_id')->count();

        if ($totalPend === 0) {
            $this->info('No hay casos pendientes de especialidad. Todo OK.');
            return self::SUCCESS;
        }

        $this->line("Pendientes: {$totalPend}. ".($dry ? '[MODO DRY-RUN]' : '[EJECUCIÓN]'));

        // Asegura especialidades mínimas
        $ids = [];
        foreach ([
            'Civil','Penal','Laboral','Familia','Administrativo','Comercial',
            'Tributario','Migratorio','Consumo','Ambiental','Habeas Data / TI',
            'Cobro de Cartera'
        ] as $esp) {
            $ids[$esp] = EspecialidadJuridica::firstOrCreate(['nombre' => $esp])->id;
        }

        $stats = [];
        Caso::whereNull('especialidad_id')->orderBy('id')->chunkById($batch, function ($casos) use ($dry, $ids, &$stats) {
            DB::beginTransaction();
            try {
                foreach ($casos as $caso) {
                    $eId = $this->clasificar($caso, $ids);
                    $stats[$eId] = ($stats[$eId] ?? 0) + 1;

                    if (!$dry) {
                        $caso->update(['especialidad_id' => $eId]);
                    }
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error('Error en lote: '.$e->getMessage());
                throw $e;
            }
        });

        // Resumen
        $this->line('Resumen asignaciones:');
        foreach ($stats as $eId => $cnt) {
            $nom = optional(EspecialidadJuridica::find($eId))->nombre ?? $eId;
            $this->line("- {$nom}: {$cnt}");
        }

        $this->info($dry
            ? 'Dry-run finalizado. No se realizaron cambios.'
            : 'Backfill completado con éxito.');
        return self::SUCCESS;
    }

    private function clasificar($caso, array $ids): int
    {
        // Texto unificado (tolerante)
        $t = strtoupper(trim(
            ($caso->subtipo_proceso ?? '').' '.
            ($caso->tipo_proceso ?? '').' '.
            ($caso->naturaleza_proceso ?? '')
        ));

        // Reglas específicas (de mayor a menor precisión)
        $match = function(string $needle) use ($t): bool {
            return $needle !== '' && str_contains($t, strtoupper($needle));
        };

        // Mapeos directos por palabra clave
        if ($match('LABORAL'))                 return $ids['Laboral'];
        if ($match('PENAL'))                   return $ids['Penal'];
        if ($match('FAMILIA'))                 return $ids['Familia'];
        if ($match('ADMINISTRAT'))             return $ids['Administrativo'];
        if ($match('TRIBUT'))                  return $ids['Tributario'];
        if ($match('MIGRATOR') || $match('EXTRANJER')) return $ids['Migratorio'];
        if ($match('HABEAS') || $match('DATO')) return $ids['Habeas Data / TI'];
        if ($match('CONSUMO') || $match('CONSUMIDOR')) return $ids['Consumo'];
        if ($match('AMBIENTAL'))               return $ids['Ambiental'];
        if ($match('COMERCIAL') || $match('MERCANT'))  return $ids['Comercial'];

        // Subtipos típicos de cobro / ejecutivo
        foreach (['HIPOTECARIO','PRENDARIO','SINGULAR','PAGO DIRECTO','GARANTIA REAL','MUEBLE','DIVISORIO','CURADURIA'] as $k) {
            if ($match($k)) return $ids['Cobro de Cartera'];
        }

        // Fallback razonable
        if ($match('INSOLVENCIA'))             return $ids['Comercial'];
        if ($match('EJECUTIVO') || $match('COBRO') || $match('CARTERA')) return $ids['Cobro de Cartera'];

        // Último recurso
        return $ids['Civil'];
    }
}
