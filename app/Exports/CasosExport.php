<?php

namespace App\Exports;

use App\Models\Caso;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class CasosExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithColumnFormatting
{
    protected $filtros;

    public function __construct($filtros = [])
    {
        $this->filtros = $filtros;
    }

    public function query()
    {
        $query = Caso::query()
            ->with([
                'deudor',
                'codeudores',
                'cooperativa',
                'user',         // Abogado/Gestor responsable
                'juzgado',
                'especialidad',
                'actuaciones'   // Cargamos actuaciones para mostrar la última
            ])
            ->latest('updated_at');

        if (!empty($this->filtros['search'])) {
            $search = $this->filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('radicado', 'ilike', "%{$search}%")
                  ->orWhere('referencia_credito', 'ilike', "%{$search}%")
                  ->orWhereHas('deudor', function($sq) use ($search) {
                      $sq->where('nombre_completo', 'ilike', "%{$search}%")
                         ->orWhere('numero_documento', 'ilike', "%{$search}%");
                  });
            });
        }
        
        if (!empty($this->filtros['abogado_id'])) {
            $query->where('user_id', $this->filtros['abogado_id']);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID Caso',
            'Referencia Crédito',
            'Radicado',
            'Número Caso (Interno)',
            'Estado',
            'Etapa Procesal',
            'Cooperativa',
            'Abogado / Gestor',
            
            // --- DATOS DEUDOR ---
            'Deudor: Nombre',
            'Deudor: Documento',
            'Deudor: Teléfono',
            'Deudor: Email',
            'Deudor: Dirección',
            
            // --- CODEUDORES ---
            'Codeudores (Detalle Completo)',

            // --- JURÍDICO ---
            'Especialidad',
            'Tipo Proceso',
            'Subtipo Proceso',
            'Subproceso',
            'Juzgado',
            'Tipo Garantía',
            'Origen Documental',

            // --- FINANCIERO ---
            'Monto Inicial',
            'Deuda Actual',
            'Total Pagado',

            // --- FECHAS ---
            'Fecha Apertura',
            'Fecha Vencimiento',
            'Fecha Inicio Crédito',
            'Fecha Creación',

            // --- GESTIÓN ---
            'Medio Contacto',
            'Link Carpeta Drive',
            'Última Actuación (Notas)', // Renombrado para que sea más claro
            'Bloqueado',
            'Motivo Bloqueo',
            'Nota Cierre'
        ];
    }

    public function map($caso): array
    {
        $deudor = $caso->deudor;
        
        // --- LÓGICA DE DATOS DEUDOR ---
        $telefono = $deudor ? ($deudor->celular_1 ?? $deudor->celular_2 ?? $deudor->telefono_fijo ?? $deudor->celular ?? '') : '';
        $email = $deudor ? ($deudor->correo_1 ?? $deudor->correo_2 ?? $deudor->email ?? '') : '';
        
        $direccion = '';
        if ($deudor) {
            $direccion = $deudor->direccion ?? $deudor->direccion_residencia ?? '';
            if (empty($direccion) && !empty($deudor->addresses)) {
                $addrs = is_string($deudor->addresses) ? json_decode($deudor->addresses, true) : $deudor->addresses;
                if (is_array($addrs) && !empty($addrs)) {
                    $first = reset($addrs);
                    $direccion = is_string($first) ? $first : ($first['direccion'] ?? implode(' ', $first));
                }
            }
        }
        
        // --- CODEUDORES ---
        $listaCodeudores = "Sin codeudores";
        if ($caso->codeudores && $caso->codeudores->count() > 0) {
            $listaCodeudores = $caso->codeudores->map(function($c) {
                $tel = $c->celular_1 ?? $c->celular ?? $c->telefono ?? 'Sin tel';
                return "• " . strtoupper($c->nombre_completo) . " (CC: {$c->numero_documento}) - Tel: {$tel}";
            })->implode("\n");
        }

        // --- ÚLTIMA ACTUACIÓN ---
        // Buscamos la actuación más reciente para mostrar algo útil en "Notas"
        $ultimaActuacion = $caso->actuaciones->sortByDesc('fecha_actuacion')->first();
        $textoActuacion = '';
        if ($ultimaActuacion) {
            $fechaAct = Carbon::parse($ultimaActuacion->fecha_actuacion)->format('d/m/Y');
            $textoActuacion = "({$fechaAct}) - " . strip_tags($ultimaActuacion->nota); // strip_tags limpia HTML si usas editor enriquecido
        } else {
            $textoActuacion = $caso->notas_legales; // Fallback al campo antiguo si no hay actuaciones
        }

        // --- SOLUCIÓN CAMPOS VACÍOS ---
        $numeroCaso = $caso->numero_caso ?? $caso->numero_interno ?? $caso->id;

        return [
            $caso->id,
            $caso->referencia_credito . ' ', 
            $caso->radicado,
            $numeroCaso,
            strtoupper($caso->estado ?? 'ACTIVO'),
            $caso->etapa_procesal,
            $caso->cooperativa ? $caso->cooperativa->nombre : 'N/A',
            $caso->user ? $caso->user->name : 'Sin Asignar',

            // Deudor
            $deudor ? strtoupper($deudor->nombre_completo) : 'N/A',
            $deudor ? ($deudor->tipo_documento . ' ' . $deudor->numero_documento) : 'N/A',
            $telefono,
            $email,
            $direccion, // La ciudad va implícita aquí o no se muestra por solicitud

            // Codeudores
            $listaCodeudores,

            // Jurídico
            $caso->especialidad ? $caso->especialidad->nombre : '',
            $caso->tipo_proceso,
            $caso->subtipo_proceso,
            $caso->subproceso,
            $caso->juzgado ? $caso->juzgado->nombre : '',
            $caso->tipo_garantia_asociada,
            $caso->origen_documental,

            // Financiero
            $caso->monto_total,
            $caso->monto_deuda_actual,
            $caso->monto_total_pagado,

            // Fechas
            $this->formatDate($caso->fecha_apertura),
            $this->formatDate($caso->fecha_vencimiento),
            $this->formatDate($caso->fecha_inicio_credito),
            $caso->created_at->format('d/m/Y H:i'),

            // Gestión
            $caso->medio_contacto,
            $caso->link_drive,
            $textoActuacion, // Aquí va la última actuación real
            $caso->bloqueado ? 'SÍ' : 'NO',
            $caso->motivo_bloqueo,
            $caso->nota_cierre,
        ];
    }

    private function formatDate($date)
    {
        return $date ? Carbon::parse($date)->format('d/m/Y') : '';
    }

    public function columnFormats(): array
    {
        return [
            // Ajustamos las columnas financieras (R, S, T)
            'R' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE, 
            'S' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'T' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('1')->getFont()->setBold(true);
        $sheet->getStyle('1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
        
        $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        // Codeudores
        $sheet->getStyle('N')->getAlignment()->setWrapText(true); 
        $sheet->getColumnDimension('N')->setWidth(50);
        
        // Notas / Actuaciones (Columna AA aprox, ajustada por eliminaciones)
        // Calculamos la letra: A=1... Z=26, AA=27.
        // ID(A), Ref(B), Rad(C), Num(D), Est(E), Eta(F), Coop(G), Abo(H)
        // DeuNom(I), Doc(J), Tel(K), Mail(L), Dir(M)
        // Cod(N)
        // Esp(O), Tip(P), Sub(Q), SubP(R), Juz(S), Gar(T), Ori(U)
        // Mon(V), Deu(W), Pag(X)
        // FecAp(Y), FecVen(Z), FecIni(AA), FecCre(AB)
        // Med(AC), Link(AD), Not(AE)...
        
        // Ajustamos la columna de Notas (AE según cálculo rápido, pero usamos getHighestColumn para seguridad en estilos generales o letras fijas si estamos seguros)
        $sheet->getStyle('AE')->getAlignment()->setWrapText(true);
        $sheet->getColumnDimension('AE')->setWidth(60); 

        $sheet->getColumnDimension('I')->setWidth(30); // Nombre deudor
    }
}