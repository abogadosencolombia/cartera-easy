<?php

namespace App\Exports;

use App\Models\ValidacionLegal;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CumplimientoReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        // Usamos la consulta que ya hemos filtrado en el controlador
        return $this->query->get();
    }

    /**
     * Define los encabezados de las columnas en el Excel.
     */
    public function headings(): array
    {
        return [
            'ID Caso',
            'Deudor',
            'Cooperativa',
            'Tipo de Falla',
            'Nivel de Riesgo',
            'Observación del Sistema',
            'Fecha de Detección',
        ];
    }

    /**
     * Mapea los datos de cada fila a las columnas correspondientes.
     */
    public function map($validacion): array
    {
        $tiposValidacionNombres = [
            'sin_pagare' => 'Falta Pagaré',
            'sin_carta_instrucciones' => 'Falta Carta de Instrucciones',
            'sin_certificacion_saldo' => 'Falta Certificación de Saldo',
            'plazo_excedido_sin_demanda' => 'Plazo para Demandar Excedido',
            // Añade otros si es necesario
        ];

        return [
            $validacion->caso->id,
            $validacion->caso->deudor->nombre_completo ?? 'N/A',
            $validacion->caso->cooperativa->nombre ?? 'N/A',
            $tiposValidacionNombres[$validacion->tipo] ?? $validacion->tipo,
            ucfirst($validacion->nivel_riesgo),
            $validacion->observacion,
            $validacion->ultima_revision->format('Y-m-d H:i'),
        ];
    }

    /**
     * Aplica estilos a la hoja de cálculo, como poner los encabezados en negrita.
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para la primera fila (encabezados)
            1    => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}
