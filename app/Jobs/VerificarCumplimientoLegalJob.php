<?php

namespace App\Jobs;

use App\Models\Caso;
use App\Models\ValidacionLegal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class VerificarCumplimientoLegalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const RIESGO_POR_TIPO = [
        'poder_vencido' => 'alto',
        'tasa_usura' => 'alto',
        'plazo_excedido_sin_demanda' => 'alto',
        'sin_pagare' => 'medio',
        'sin_carta_instrucciones' => 'medio',
        'tipo_proceso_vs_garantia' => 'medio',
        'sin_certificacion_saldo' => 'bajo',
        'documento_faltante_para_radicar' => 'bajo',
    ];

    public function handle(): void
    {
        // Se cargan todas las relaciones necesarias para evitar múltiples consultas
        $casos = Caso::whereIn('estado', ['activo', 'prejuridico'])
            ->with(['cooperativa.configuracionLegal', 'documentos'])
            ->get();

        foreach ($casos as $caso) {
            // Verificación crucial: nos aseguramos de que el caso tenga una cooperativa asignada.
            if (!$caso->cooperativa) {
                continue; // Si no tiene cooperativa, no podemos seguir.
            }
            
            // Verificación crucial: nos aseguramos de que la cooperativa tenga su configuración.
            $config = $caso->cooperativa->configuracionLegal;
            if (!$config) {
                continue; // Si no hay configuración, no se pueden aplicar las reglas.
            }

            $this->verificarPlazoDemanda($caso, $config);
            $this->verificarDocumento($caso, $config, 'sin_pagare', 'pagare', 'exige_pagare');
            $this->verificarDocumento($caso, $config, 'sin_carta_instrucciones', 'carta instrucciones', 'exige_carta_instrucciones');
            $this->verificarDocumento($caso, $config, 'sin_certificacion_saldo', 'certificacion saldo', 'exige_certificacion_saldo');
        }
    }

    private function verificarPlazoDemanda(Caso $caso, $config): void
    {
        $tipo = 'plazo_excedido_sin_demanda';
        
        // Verificación crucial: nos aseguramos de que la fecha de asignación exista.
        if (!$caso->fecha_asignacion) {
            return; // Si no hay fecha, no se puede calcular el plazo.
        }

        if (isset($caso->radicado) && $caso->radicado) {
            $estado = 'no_aplica';
            $observacion = 'El caso ya ha sido radicado.';
        } else {
            $diasDesdeAsignacion = Carbon::now()->diffInDays($caso->fecha_asignacion);
            $plazoExcedido = $diasDesdeAsignacion > $config->dias_maximo_para_demandar;
            $estado = $plazoExcedido ? 'incumple' : 'cumple';
            $observacion = $plazoExcedido
                ? "Han pasado {$diasDesdeAsignacion} días, excediendo el límite de {$config->dias_maximo_para_demandar}."
                : "Dentro del plazo ({$diasDesdeAsignacion}/{$config->dias_maximo_para_demandar} días).";
        }
        $this->actualizarValidacion($caso, $tipo, $estado, $observacion);
    }

    private function verificarDocumento(Caso $caso, $config, string $tipoValidacion, string $tipoDocEnBD, string $campoConfig): void
    {
        if ($config->$campoConfig) {
            // La relación 'documentos' debe existir en el modelo Caso.
            $tieneDocumento = $caso->documentos()->where('tipo_documento', $tipoDocEnBD)->exists();
            $estado = $tieneDocumento ? 'cumple' : 'incumple';
            $observacion = $tieneDocumento ? 'Documento registrado correctamente.' : 'Documento faltante según la configuración de la cooperativa.';
        } else {
            $estado = 'no_aplica';
            $observacion = 'La cooperativa no exige este documento.';
        }
        $this->actualizarValidacion($caso, $tipoValidacion, $estado, $observacion);
    }

    private function actualizarValidacion(Caso $caso, string $tipo, string $estado, string $observacion): void
    {
        ValidacionLegal::updateOrCreate(
            ['caso_id' => $caso->id, 'tipo' => $tipo],
            [
                'estado' => $estado,
                'observacion' => $observacion,
                'ultima_revision' => now(),
                'nivel_riesgo' => self::RIESGO_POR_TIPO[$tipo] ?? 'medio',
            ]
        );
    }
}
