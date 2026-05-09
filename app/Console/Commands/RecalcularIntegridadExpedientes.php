<?php

namespace App\Console\Commands;

use App\Models\Caso;
use App\Models\ProcesoRadicado;
use App\Services\ExpedienteIntegrityService;
use Illuminate\Console\Command;

class RecalcularIntegridadExpedientes extends Command
{
    protected $signature = 'expedientes:recalcular-integridad {--casos : Recalcular solo casos} {--radicados : Recalcular solo radicados}';

    protected $description = 'Recalcula el score y resumen de integridad de casos y radicados existentes.';

    public function handle(ExpedienteIntegrityService $integrity): int
    {
        $onlyCasos = (bool) $this->option('casos');
        $onlyRadicados = (bool) $this->option('radicados');
        $runCasos = !$onlyRadicados || $onlyCasos;
        $runRadicados = !$onlyCasos || $onlyRadicados;

        if ($runCasos) {
            $this->info('Recalculando integridad de casos...');
            $this->recalculate(Caso::query()->orderBy('id'), $integrity);
        }

        if ($runRadicados) {
            $this->info('Recalculando integridad de radicados...');
            $this->recalculate(ProcesoRadicado::query()->orderBy('id'), $integrity);
        }

        $this->info('Recalculo de integridad finalizado.');

        return self::SUCCESS;
    }

    private function recalculate($query, ExpedienteIntegrityService $integrity): void
    {
        $bar = $this->output->createProgressBar((clone $query)->count());
        $bar->start();

        $query->chunkById(100, function ($items) use ($integrity, $bar) {
            foreach ($items as $item) {
                $integrity->refresh($item);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }
}
