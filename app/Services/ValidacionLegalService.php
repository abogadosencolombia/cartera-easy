<?php

namespace App\Services;

use App\Models\Caso;
use App\Models\RequisitoDocumento;
use App\Models\ValidacionLegal;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ValidacionLegalService
{
    /**
     * Genera o actualiza todas las validaciones para un caso.
     */
    public function generarValidacionesParaCaso(Caso $caso): void
    {
        Log::info("--- [ValidacionLegalService] Iniciando para Caso ID: {$caso->id} ---");

        $requisitos = RequisitoDocumento::whereHas('tipoProceso', function ($query) use ($caso) {
                $query->where('nombre', $caso->tipo_proceso);
            })
            ->where(function ($query) use ($caso) {
                $query->where('cooperativa_id', $caso->cooperativa_id)
                    ->orWhereNull('cooperativa_id');
            })
            ->get();

        if ($requisitos->isEmpty()) {
            Log::info("-> No se encontraron requisitos para el tipo de proceso '{$caso->tipo_proceso}'.");
            return;
        }

        Log::info("-> Se encontraron {$requisitos->count()} requisitos aplicables.");

        foreach ($requisitos as $requisito) {
            $nombreDocumento = $requisito->tipo_documento_requerido;
            $documentos = $caso->documentos()->pluck('tipo_documento')
                ->map(fn ($tipo) => $this->normalizarDocumento($tipo));
            $documentoExiste = $documentos->contains($this->normalizarDocumento($nombreDocumento));

            ValidacionLegal::updateOrCreate(
                ['caso_id' => $caso->id, 'requisito_id' => $requisito->id],
                [
                    'tipo' => 'documento_requerido',
                    'estado' => $documentoExiste ? 'cumple' : 'incumple',
                    'observacion' => $documentoExiste
                        ? "Documento '{$nombreDocumento}' adjunto correctamente."
                        : "Falta el documento obligatorio: '{$nombreDocumento}'.",
                    'ultima_revision' => now(),
                    'nivel_riesgo' => 'medio',
                ]
            );
            Log::info("--> Validación para '{$tipoValidacion}' procesada. Estado: " . ($documentoExiste ? 'CUMPLE' : 'INCUMPLE'));
        }
        Log::info("--- [ValidacionLegalService] Finalizado para Caso ID: {$caso->id} ---");
    }

    /**
     * Normaliza nombres para comparar "pagaré", "pagare" y variantes de mayúsculas.
     */
    private function normalizarDocumento(string $nombreDocumento): string
    {
        return Str::of($nombreDocumento)->ascii()->lower()->squish()->toString();
    }
}
