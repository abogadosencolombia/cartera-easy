<?php

namespace App\Services;

use App\Models\Caso;
use App\Models\RequisitoDocumento;
use App\Models\ValidacionLegal;
use Illuminate\Support\Facades\Log;

class ValidacionLegalService
{
    /**
     * Genera o actualiza todas las validaciones para un caso.
     */
    public function generarValidacionesParaCaso(Caso $caso): void
    {
        Log::info("--- [ValidacionLegalService] Iniciando para Caso ID: {$caso->id} ---");

        $requisitos = RequisitoDocumento::where('tipo_proceso', $caso->tipo_proceso)->get();

        if ($requisitos->isEmpty()) {
            Log::info("-> No se encontraron requisitos para el tipo de proceso '{$caso->tipo_proceso}'.");
            return;
        }

        Log::info("-> Se encontraron {$requisitos->count()} requisitos aplicables.");

        foreach ($requisitos as $requisito) {
            $nombreDocumento = $requisito->tipo_documento_requerido;
            $tipoValidacion = $this->mapearDocumentoATipoValidacion($nombreDocumento);

            if (is_null($tipoValidacion)) {
                Log::warning("--> Documento requerido '{$nombreDocumento}' no tiene un tipo de validación legal mapeado. Saltando.");
                continue;
            }

            $documentoExiste = $caso->documentos()->where('tipo_documento', $nombreDocumento)->exists();

            ValidacionLegal::updateOrCreate(
                [ 'caso_id' => $caso->id, 'tipo' => $tipoValidacion ],
                [
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
     * Mapea el nombre de un documento al tipo de validación del enum.
     */
    private function mapearDocumentoATipoValidacion(string $nombreDocumento): ?string
    {
        $mapa = [
            'pagaré' => 'sin_pagaré',
            'carta instrucciones' => 'sin_carta_instrucciones',
            'certificación saldo' => 'sin_certificacion_saldo',
        ];
        return $mapa[strtolower($nombreDocumento)] ?? null;
    }
}