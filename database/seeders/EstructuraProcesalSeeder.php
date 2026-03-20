<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EspecialidadJuridica;
use App\Models\TipoProceso;
use App\Models\SubtipoProceso;
use App\Models\Subproceso;
use Illuminate\Support\Facades\DB;

class EstructuraProcesalSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Actualizando estructura procesal incrementalmente (SIN BORRADO)...');

        $mapa = $this->getMapaProcesal();

        DB::transaction(function () use ($mapa) {
            foreach ($mapa as $espNombre => $tipos) {
                // --- Nivel 1: ESPECIALIDAD ---
                $especialidad = EspecialidadJuridica::updateOrCreate(['nombre' => $espNombre]);

                foreach ($tipos as $tipoNombre => $subtiposConDetalles) {
                    // --- Nivel 2: TIPO PROCESO ---
                    $tipoProceso = TipoProceso::updateOrCreate(
                        ['nombre' => $tipoNombre, 'especialidad_juridica_id' => $especialidad->id]
                    );

                    // --- Nivel 3: SUBTIPO PROCESO ---
                    foreach ($subtiposConDetalles as $subtipoNombre => $subprocesos) {
                        $subtipoProceso = SubtipoProceso::updateOrCreate(
                            [
                                'tipo_proceso_id' => $tipoProceso->id,
                                'nombre' => $subtipoNombre,
                            ]
                        );

                        // --- Nivel 4: SUBPROCESO ---
                        // Aseguramos que siempre sea un array
                        $subprocesosArray = is_array($subprocesos) ? $subprocesos : [$subprocesos];
                        foreach ($subprocesosArray as $subprocesoNombre) {
                            if (!empty($subprocesoNombre)) {
                                Subproceso::updateOrCreate(
                                    [
                                        'subtipo_proceso_id' => $subtipoProceso->id,
                                        'nombre' => $subprocesoNombre,
                                    ]
                                );
                            }
                        }
                    }
                }
            }
        });
        
        $this->command->info('¡Mapa procesal actualizado con éxito!');
    }

    private function getMapaProcesal(): array
    {
        return [
            'CIVIL' => [
                'PROCESOS_DECLARATIVOS' => [
                    'VERBAL_MAYOR_Y_MENOR_CUANTIA' => [
                        'Resolución de contrato',
                        'Responsabilidad civil contractual',
                        'Responsabilidad civil extracontractual',
                        'Declaración de pertenencia',
                        'Servidumbres',
                        'Rendición de cuentas',
                    ],
                    'VERBAL_SUMARIO_MINIMA_CUANTIA' => [
                        'Asuntos de mínima cuantía',
                        'Propiedad horizontal',
                        'Reparación de daños por tránsito',
                    ],
                ],
                'PROCESOS_DECLARATIVOS_ESPECIALES' => [
                    'MONITORIO' => ['Cobro de deuda de mínima cuantía'],
                    'DIVISORIO' => ['División material del bien', 'Venta en pública subasta'],
                    'EXPROPIACION' => ['Vía administrativa', 'Vía judicial'],
                    'DESLINDE_Y_AMOJONAMIENTO' => ['Fijación de linderos'],
                ],
                'PROCESOS_EJECUTIVOS' => [
                    'EJECUTIVO_SINGULAR' => [
                        'Mínima Cuantía',
                        'Menor Cuantía',
                        'Mayor Cuantía',
                    ],
                    'EJECUTIVO_CON_GARANTIA_REAL' => [
                        'Proceso Hipotecario',
                        'Proceso Prendario',
                    ],
                    'EJECUTIVO_CON_GARANTIA_MOBILIARIA' => [
                        'Ejecución Ley 1676 de 2013',
                    ],
                ],
                'PROCESOS_LIQUIDATORIOS' => [
                    'SUCESION' => ['Notarial', 'Judicial'],
                    'LIQUIDACION_DE_SOCIEDADES' => ['Comerciales', 'Civiles'],
                ],
                'JURISDICCION_VOLUNTARIA' => [
                    'APOYOS_PARA_LA_TOMA_DE_DECISIONES' => [
                        'Designación de apoyos (Ley 1996/19)',
                        'Adjudicación de apoyos',
                        'Acuerdos de apoyo',
                    ],
                    'LICENCIAS_Y_OTRAS' => [
                        'Venta de bienes de incapaz',
                        'Declaración de ausencia / muerte presunta',
                        'Corrección de registro civil',
                    ],
                ],
            ],
            'CONTENCIOSO_ADMINISTRATIVO' => [
                'MEDIOS_DE_CONTROL' => [
                    'NULIDAD_Y_RESTABLECIMIENTO_DEL_DERECHO' => [
                        'Contra actos administrativos particulares',
                        'Asuntos pensionales / laborales estatales',
                    ],
                    'REPARACION_DIRECTA' => [
                        'Falla en el servicio',
                        'Error judicial',
                        'Ocupación de inmueble',
                    ],
                    'CONTROVERSIAS_CONTRACTUALES' => [
                        'Incumplimiento contrato estatal',
                        'Liquidación judicial contrato',
                    ],
                    'NULIDAD_SIMPLE' => [
                        'Contra actos generales',
                    ],
                ],
                'EJECUTIVO_ADMINISTRATIVO' => [
                    'COBRO_COACTIVO' => [
                        'Impuestos y Tasas',
                        'Sanciones Administrativas',
                    ],
                ],
            ],
            'FAMILIA' => [
                'ASUNTOS_MENORES_Y_ALIMENTOS' => [
                    'Fijación/Aumento/Exoneración de alimentos' => [],
                    'Custodia y regulación de visitas' => [],
                    'Permiso de salida del país' => [],
                ],
                'ESTADO_CIVIL_Y_PATERNIDAD' => [
                    'Investigación de paternidad' => [],
                    'Impugnación de paternidad' => [],
                    'Privación de patria potestad' => [],
                ],
                'MATRIMONIO_Y_SOCIEDAD_PATRIMONIAL' => [
                    'Divorcio contencioso' => [],
                    'Unión marital de hecho' => [],
                    'Disolución y liquidación de sociedad conyugal' => [],
                ],
            ],
            'COMERCIAL' => [
                'PROCESOS_SOCIETARIOS' => [
                    'Impugnación de asambleas' => [],
                    'Responsabilidad de administradores' => [],
                ],
                'INSOLVENCIA_SUPERSOCIEDADES' => [
                    'Reorganización Empresarial (Ley 1116)' => [],
                    'Liquidación Judicial' => [],
                ],
                'INSOLVENCIA_PERSONA_NATURAL' => [
                    'Insolvencia Persona Natural No Comerciante' => [],
                ],
            ],
            'LABORAL' => [
                'ORDINARIOS' => [
                    'Proceso Ordinario de Primera Instancia' => [],
                    'Proceso de Única Instancia' => [],
                ],
                'ESPECIALES' => [
                    'Fuero Sindical' => [],
                    'Acoso Laboral' => [],
                ],
                'EJECUTIVO_LABORAL' => [
                    'Cobro de sentencias / actas conciliación' => [],
                ],
            ],
            'CONSTITUCIONAL' => [
                'ACCIONES_CONSTITUCIONALES' => [
                    'ACCION_DE_TUTELA' => ['Salud', 'Petición', 'Debido Proceso', 'Mínimo Vital'],
                    'ACCION_POPULAR' => ['Derechos Colectivos'],
                    'ACCION_DE_GRUPO' => ['Indemnización colectiva'],
                    'HABEAS_CORPUS' => ['Privación ilegal libertad'],
                ],
            ],
            'PENAL' => [
                'SISTEMAS_PROCESALES' => [
                    'PROC_ORDINARIO_LEY_906_DE_2004' => ['Indagación', 'Investigación', 'Juicio'],
                    'PROC_ABREVIADO_LEY_1826_DE_2017' => ['Querellables / Flagrancia'],
                    'SISTEMA_RESP_PENAL_ADOLESCENTES' => ['SRPA'],
                ],
            ],
        ];
    }
}
