<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Caso;
use App\Models\ProcesoRadicado;
use App\Models\Contrato;
use Carbon\Carbon;

class PendientesRevisionExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $query;
    protected $type;

    public function __construct(Builder $query, string $type)
    {
        $this->query = $query;
        $this->type = $type;
    }

    /**
     * @return Builder
     */
    public function query()
    {
        return $this->query;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        switch ($this->type) {
            case 'casos':
                return [
                    'ID Caso', 'Referencia Crédito', 'Deudor', 'Documento Deudor',
                    'Cooperativa', 'Fecha Vencimiento', 'Última Revisión',
                    'Abogado Responsable', // <-- COLUMNA
                ];
            case 'radicados':
                return [
                    'ID Radicado', 'Número Radicado', 'Demandante', 'Documento Demandante',
                    'Demandado', 'Documento Demandado', 'Fecha Próxima Revisión', 'Última Revisión',
                    'Abogado Responsable', // <-- COLUMNA
                ];
            case 'contratos':
                return [
                    'ID Contrato', 'Cliente', 'Documento Cliente', 'Modalidad',
                    'Fecha Creación', 'Última Revisión',
                    'Abogado Responsable', // <-- COLUMNA
                ];
            default: return [];
        }
    }

    /**
     * @param mixed $item
     * @return array
     */
    public function map($item): array
    {
        // --- INICIO: USAR RELACIÓN CARGADA ---
        // Accede a la última revisión cargada ansiosamente por el controlador
        $ultimaRevision = $item->revisionesDiarias->first(); // Como cargamos con latest()->first(), solo habrá una o ninguna
        $ultimaRevisionFormateada = $ultimaRevision ? Carbon::parse($ultimaRevision->fecha_revision)->format('Y-m-d') : 'Nunca';
        // --- FIN: USAR RELACIÓN CARGADA ---

        switch ($this->type) {
            case 'casos':
                // --- ABOGADO (CORREGIDO) ---
                // Se usa solo 'name'
                $nombreAbogado = $item->user?->name ?? 'No Asignado';
                // ---
                return [
                    $item->id,
                    $item->referencia_credito ?? 'N/A',
                    // --- CORREGIDO CON NULLSAFE (?->) ---
                    $item->deudor?->nombre_completo ?? 'N/A',
                    $item->deudor?->numero_documento ?? 'N/A',
                    $item->cooperativa?->nombre ?? 'N/A',
                    // ---
                    $item->fecha_vencimiento ? Carbon::parse($item->fecha_vencimiento)->format('Y-m-d') : 'N/A',
                    $ultimaRevisionFormateada,
                    $nombreAbogado, // <-- NUEVO DATO
                ];
            case 'radicados':
                // --- ABOGADO (CORREGIDO) ---
                // Se usa solo 'name'
                $nombreAbogado = $item->abogado?->name ?? 'No Asignado';
                // ---
                return [
                    $item->id,
                    $item->radicado ?? 'N/A',
                    // --- CORREGIDO CON NULLSAFE (?->) ---
                    $item->demandante?->nombre_completo ?? 'N/A',
                    $item->demandante?->numero_documento ?? 'N/A',
                    $item->demandado?->nombre_completo ?? 'N/A',
                    $item->demandado?->numero_documento ?? 'N/A',
                    // ---
                    $item->fecha_proxima_revision ? Carbon::parse($item->fecha_proxima_revision)->format('Y-m-d') : 'N/A',
                    $ultimaRevisionFormateada,
                    $nombreAbogado, // <-- NUEVO DATO
                ];
            case 'contratos':
                // --- ABOGADO (CORREGIDO) ---
                // Se usa solo 'name'
                $nombreAbogado = $item->proceso?->abogado?->name ?? 'No Asignado';
                // ---
                return [
                    $item->id,
                    // --- CORREGIDO CON NULLSAFE (?->) ---
                    $item->cliente?->nombre_completo ?? 'N/A',
                    $item->cliente?->numero_documento ?? 'N/A',
                    // ---
                    $item->modalidad ?? 'N/A',
                    $item->created_at ? Carbon::parse($item->created_at)->format('Y-m-d') : 'N/A',
                    $ultimaRevisionFormateada,
                    $nombreAbogado, // <-- NUEVO DATO
                ];
            default: return [];
        }
    }
}

