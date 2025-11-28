<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EspecialidadJuridica;
use App\Models\TipoProceso;
use App\Models\SubtipoProceso;
use App\Models\Subproceso;
use Illuminate\Support\Facades\DB; // <-- AÑADIDO para la transacción

class EstructuraProcesalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Vaciamos las tablas en el orden correcto (de L4 a L1)
        // para asegurar una actualización limpia cada vez.
        // ===================================================================
        // ===== CORRECCIÓN #1: Usamos 'especialidades' (tu nombre de tabla)
        // ===================================================================
        DB::statement('TRUNCATE TABLE subprocesos, subtipos_proceso, tipos_proceso, especialidades RESTART IDENTITY CASCADE');
        
        $this->command->info('Tablas de estructura procesal limpiadas.');

        $mapa = $this->getMapaProcesal();

        DB::transaction(function () use ($mapa) {
            foreach ($mapa as $espNombre => $tipos) {
                
                // --- Nivel 1: ESPECIALIDAD ---
                $especialidad = EspecialidadJuridica::firstOrCreate(['nombre' => $espNombre]);
                $this->command->info("Procesando Especialidad: $espNombre");

                foreach ($tipos as $tipoNombre => $subtiposConDetalles) {
                    
                    // --- Nivel 2: TIPO PROCESO ---
                    // Esta lógica ahora es correcta, ya que los $tipoNombre serán únicos
                    $tipoProceso = TipoProceso::updateOrCreate(
                        ['nombre' => $tipoNombre, 'especialidad_juridica_id' => $especialidad->id],
                        []
                    );

                    // --- Nivel 3: SUBTIPO PROCESO ---
                    foreach ($subtiposConDetalles as $subtipoNombre => $subprocesos) {
                        
                        $subtipoProceso = SubtipoProceso::firstOrCreate(
                            [
                                'tipo_proceso_id' => $tipoProceso->id,
                                'nombre' => $subtipoNombre,
                            ]
                        );

                        // --- Nivel 4: SUBPROCESO (El nuevo nivel) ---
                        foreach ($subprocesos as $subprocesoNombre) {
                            Subproceso::firstOrCreate(
                                [
                                    'subtipo_proceso_id' => $subtipoProceso->id,
                                    'nombre' => $subprocesoNombre,
                                ]
                            );
                        }
                    }
                }
            }
        });
        
        $this->command->info('¡Mapa procesal de 4 niveles cargado con éxito!');
    }

    /**
     * Define la estructura jerárquica completa de 4 NIVELES.
     */
    private function getMapaProcesal(): array
    {
        // Mapa completo de 4 niveles
        return [
            
            // ================================================================
            // ===== INICIO: NUEVA ESPECIALIDAD "EJECUTIVO"
            // ================================================================
            'EJECUTIVO' => [
                'PROCESO_EJECUTIVO_SINGULAR' => [ // L2 (Incluye Mayor y Menor Cuantía)
                    'SINGULAR_MINIMA_CUANTIA' => [ // L3
                        'Presentación de Demanda', // L4
                        'Medidas Cautelares', // L4
                        'Notificación y Mandamiento de Pago', // L4
                        'Audiencias', // L4
                        'Sentencia y Recursos', // L4
                        'Liquidación de Crédito y Costas', // L4
                    ],
                    'SINGULAR_MENOR_CUANTIA' => [ // L3
                        'Presentación de Demanda', // L4
                        'Medidas Cautelares', // L4
                        'Notificación y Mandamiento de Pago', // L4
                        'Audiencias', // L4
                        'Sentencia y Recursos', // L4
                        'Liquidación de Crédito y Costas', // L4
                    ],
                    'SINGULAR_MAYOR_CUANTIA' => [ // L3
                        'Presentación de Demanda', // L4
                        'Medidas Cautelares', // L4
                        'Notificación y Mandamiento de Pago', // L4
                        'Audiencias', // L4
                        'Sentencia y Recursos', // L4
                        'Liquidación de Crédito y Costas', // L4
                    ],
                ],
                'PROCESO_EJECUTIVO_DE_ALIMENTOS' => [ // L2
                    'ALIMENTOS_MINIMA_CUANTIA' => [ // L3
                        'Demanda Ejecutiva de Alimentos', // L4
                        'Mandamiento de Pago', // L4
                        'Embargo de Salario/Cuentas', // L4
                        'Liquidación de Cuotas', // L4
                    ],
                    'ALIMENTOS_MAYOR_Y_MENOR_CUANTIA' => [ // L3
                        'Demanda Ejecutiva de Alimentos', // L4
                        'Mandamiento de Pago', // L4
                        'Embargo de Salario/Cuentas/Bienes', // L4
                        'Liquidación de Cuotas', // L4
                    ],
                ],
                'PROCESO_EJECUTIVO_CON_GARANTIA_HIPOTECARIA' => [ // L2
                    'HIPOTECARIO' => [ // L3
                        'Demanda y Medidas Cautelares (Embargo y Secuestro)', // L4
                        'Mandamiento de Pago y Notificación', // L4
                        'Avalúo del Inmueble', // L4
                        'Remate del Inmueble', // L4
                    ],
                ],
                'PROCESO_EJECUTIVO_LABORAL' => [ // L2
                    'EJECUTIVO_LABORAL' => [ // L3
                        'Basado en Acta de Conciliación', // L4
                        'Basado en Sentencia Judicial', // L4
                        'Basado en Laudo Arbitral', // L4
                    ],
                ],
                // Añadí este basado en tu seeder de subtipos
                'PROCESO_EJECUTIVO_CON_GARANTIA_MOBILIARIA' => [ // L2 
                    'PRENDARIO' => [ // L3
                        'Demanda y Medidas Cautelares (Embargo y Secuestro de Mueble)', // L4
                        'Mandamiento de Pago y Notificación', // L4
                        'Remate del Bien Mueble', // L4
                    ],
                ],
            ],
            // ================================================================
            // ===== FIN: NUEVA ESPECIALIDAD "EJECUTIVO"
            // ================================================================

            'CIVIL' => [
                'DECLARATIVO' => [
                    'PROCESOS_DECLARATIVOS_VERBAL' => [
                        'Resolución/Incumplimiento de contrato',
                        'Responsabilidad civil contractual',
                        'Responsabilidad civil extracontractual (accidentes de tránsito, etc.)',
                        'Declaración de pertenencia (usucapión)',
                        'Servidumbres (imposición, modificación, extinción)',
                        'Rendición de cuentas',
                    ],
                    'PROCESOS_DECLARATIVOS_VERBAL_SUMARIO' => [
                        'Procesos de mínima cuantía',
                        'Asuntos de propiedad horizontal',
                        'Fijación/Aumento/Reducción de alimentos (cuando no es de familia)',
                    ],
                ],
                'DECLARATIVO_ESPECIAL' => [
                    'PROCESOS_DECLARATIVOS_ESPECIALES' => [
                        'Expropiación',
                        'Deslinde y amojonamiento',
                        'Proceso monitorio',
                        'Divisorios',
                    ],
                ],
                
                // ================================================================
                // ===== CORRECCIÓN #2: Nombres de L2 hechos únicos
                // ================================================================
                'EJECUTIVO_(CIVIL)' => [ // L2 (Nombre cambiado de 'EJECUTIVO' a 'EJECUTIVO (CIVIL)')
                    'EJECUTIVO_SINGULAR_(CIVIL)' => [ // L3
                        'Singular Mínima Cuantía (Civil)', // L4
                        'Singular Menor Cuantía (Civil)', // L4
                        'Singular Mayor Cuantía (Civil)', // L4
                    ],
                    'EJECUTIVO_HIPOTECARIO_(CIVIL)' => [ // L3
                        'Proceso Hipotecario (Civil)', // L4
                    ],
                    'EJECUTIVO_PRENDARIO_(CIVIL)' => [ // L3
                        'Proceso Prendario (Civil)', // L4
                    ],
                    'OBLIGACION_DE_HACER/NO_HACER' => [ // L3
                        'Obligación de Suscribir Documentos', // L4
                        'Obligación de Realizar una Obra', // L4
                    ],
                ],
                // ================================================================
                // ===== FIN: CORRECCIÓN #2
                // ================================================================
                
                'LIQUIDATORIO' => [
                    'PROCESOS_LIQUIDATORIOS' => [
                        'Disolución y liquidación de sociedades civiles/comerciales',
                    ],
                ],
                'JURISDICCIÓN_VOLUNTARIA' => [
                    'JURISDICCION_VOLUNTARIA' => [
                        'Licencias (interdicción, venta bienes incapaz)',
                        'Declaración de ausencia / muerte presunta',
                        'Cambio de nombre / corrección registro civil',
                    ],
                ],
            ],

            // --- RESTO DE TUS ESPECIALIDADES (SIN CAMBIOS) ---
            'CONSTITUCIONAL' => [
                'ACCION_CONSTITUCIONAL' => [
                    'ACCION_DE_TUTELA' => [
                        'Por derecho a la salud (medicamentos, cirugías, tratamientos)',
                        'Por derecho de petición',
                        'Por debido proceso (contra providencias judiciales, sanciones)',
                        'Por mínimo vital (salarios, pensiones)',
                        'Por hábeas data (centrales de riesgo)',
                        'Por estabilidad laboral reforzada (maternidad, salud)',
                    ],
                    'ACCION_DE_CUMPLIMIENTO' => [],
                    'ACCION_POPULAR' => [
                        'Protección de derechos colectivos (ambiente, moralidad administrativa)',
                    ],
                    'ACCION_DE_GRUPO' => [
                        'Indemnización de perjuicios a un grupo (mín. 20)',
                    ],
                    'HABEAS_CORPUS' => [
                        'Protección del derecho a la libertad',
                    ],
                ],
                'ACCION_PUBLICA' => [
                    'DEMANDA_DE_INCONSTITUCIONALIDAD' => [],
                ],
            ],
            'FAMILIA' => [
                'FAMILIA' => [
                    'PROCESOS_SOBRE_MATRIMONIO_Y_UNION_MARITAL' => [
                        'Declaración de unión marital de hecho',
                        'Disolución y liquidación de sociedad conyugal/patrimonial (contencioso)',
                    ],
                    'MENORES_CUSTODIA_Y_ALIMENTOS' => [
                        'Fijación/Aumento/Reducción/Exoneración de alimentos',
                        'Custodia y cuidado personal',
                        'Regulación de visitas',
                        'Permiso de salida del país',
                        'Procesos de adopción',
                    ],
                    'FILIACION_PATRIA_POTESTAD_Y_ESTADO_CIVIL' => [
                        'Investigación / Impugnación de paternidad/maternidad',
                        'Privación / Suspensión / Restablecimiento de patria potestad',
                        'Rectificación / Corrección de registro civil',
                    ],
                    'OTROS_PROCESOS_DE_FAMILIA' => [
                        'Interdicción / Rehabilitación de persona con discapacidad',
                        'Violencia intrafamiliar (medidas de protección)',
                    ],
                ],
            ],
            'COMERCIAL' => [
                'INSOLVENCIA_EMPRESARIAL' => [
                    'PROCESOS_DE_INSOLVENCIA_SUPERSOCIEDADES' => [
                        'Negociación de emergencia (NEAR)',
                        'Proceso de reorganización empresarial (Ley 1116)',
                        'Proceso de liquidación judicial',
                    ],
                ],
                'JURISDICCIONAL_ADMINISTRATIVO' => [
                    'PROCESOS_JURISDICCIONALES_SUPERSOCIEDADES' => [
                        'Conflictos societarios (impugnación de asambleas, etc.)',
                        'Acciones de responsabilidad contra administradores',
                        'Desestimación de la personalidad jurídica',
                    ],
                    'PROCESOS_JURISDICCIONALES_SIC' => [
                        'Competencia desleal',
                        'Infracción de derechos de propiedad industrial',
                        'Protección al consumidor (acciones jurisdiccionales)',
                    ],
                ],
                'JUDICIAL' => [
                    'PROCESOS_JUDICIALES_JUEZ_CIVIL' => [
                        'Cobro de títulos valores (facturas, cheques, pagarés)',
                        'Conflictos contractuales mercantiles (agencia, suministro, etc.)',
                    ],
                ],
            ],
            'LABORAL' => [
                'ORDINARIO' => [
                    'PROCESO_ORDINARIO_LABORAL' => [
                        'Reclamo de salarios, prestaciones sociales, indemnizaciones',
                        'Declaración de existencia de contrato realidad',
                        'Reintegro por fuero (sindical, salud, maternidad)',
                        'Conflictos pensionales (régimen privado)',
                    ],
                ],
                'ESPECIAL' => [
                    'PROCESOS_ESPECIALES_LABORALES' => [
                        'Fuero sindical (levantamiento, calificación de despido)',
                        'Acoso laboral',
                    ],
                ],
                
                // ================================================================
                // ===== CORRECCIÓN #2: Nombres de L2 hechos únicos
                // ================================================================
                'EJECUTIVO_(LABORAL)' => [ // L2 (Nombre cambiado de 'EJECUTIVO' a 'EJECUTIVO (LABORAL)')
                    'PROCESO_EJECUTIVO_LABORAL' => [ // L3
                // ================================================================
                // ===== FIN: CORRECCIÓN #2
                // ================================================================
                        'Cobro de actas de conciliación laboral',
                        'Cobro de sentencias laborales',
                    ],
                ],
                'COLECTIVO' => [
                    'CONFLICTOS_COLECTIVOS' => [
                        'Declaratoria de ilegalidad de cese de actividades/huelga',
                        'Convocatoria a tribunal de arbitramento',
                    ],
                ],
            ],
            'CONTENCIOSO_ADMINISTRATIVO' => [
                'MEDIO_DE_CONTROL' => [
                    'NULIDAD_SIMPLE' => [
                        'Contra actos administrativos de carácter general (decretos, resoluciones)',
                    ],
                    'NULIDAD_Y_RESTABLECIMIENTO_DEL_DERECHO' => [
                        'Contra actos administrativos de carácter particular (sanciones, negación de pensión)',
                        'Reclamos de personal de la fuerza pública',
                    ],
                    'REPARACION_DIRECTA' => [
                        'Falla en el servicio (responsabilidad médica, error judicial)',
                        'Ocupación temporal o permanente de inmueble',
                        'Daño especial (obras públicas, etc.)',
                    ],
                    'CONTROVERSIAS_CONTRACTUALES' => [
                        'Incumplimiento de contrato estatal',
                        'Liquidación judicial de contrato',
                        'Nulidad de contrato estatal',
                    ],
                    'NULIDAD_ELECTORAL' => [
                        'Contra actos de elección por voto popular o designación',
                    ],
                ],
                'EJECUTIVO_ADMINISTRATIVO' => [
                    'PROCESO_EJECUTIVO_ADMINISTRATIVO_COBRO_COACTIVO' => [
                        'Cobro de impuestos, tasas y contribuciones',
                        'Cobro de multas y sanciones administrativas',
                    ],
                ],
            ],
            'PENAL' => [
                'PENAL' => [
                    'SISTEMAS_PROCESALES_PENALES' => [
                        'Proceso ordinario (Ley 906 de 2004)',
                        'Proceso abreviado (Ley 1826 de 2017)',
                        'Sistema de responsabilidad penal para adolescentes (SRPA)',
                        'Proceso de Ley 600 de 2000 (residual/liquidación)',
                    ],
                    'MECANISMOS_Y_ACCIONES_PENALES' => [
                        'Preacuerdos y negociaciones',
                        'Principio de oportunidad',
                        'Acción de revisión',
                        'Extinción de dominio',
                    ],
                    'ETAPAS_Y_SOLICITUDES_CLAVE' => [
                        'Audiencias preliminares (captura, imputación, medida de aseguramiento)',
                        'Solicitud de pruebas (descubrimiento, enunciación)',
                        'Incidentes de reparación integral',
                        'Solicitudes ante juez de ejecución de penas (libertad condicional, redención)',
                    ],
                ],
            ],
            'ADMINISTRATIVO_Y_NOTARIAL' => [
                'NOTARIAL' => [
                    'TRAMITES_NOTARIALES' => [
                        'Divorcio / Sucesión / Liquidación sociedad conyugal (mutuo acuerdo)',
                        'Elaboración de escrituras (compraventa, hipoteca, testamento)',
                        'Autenticaciones, declaraciones extrajuicio, matrimonios civiles',
                    ],
                ],
                'ADMINISTRATIVO' => [
                    'ACTUACIONES_ADMINISTRATIVAS' => [
                        'Derechos de petición',
                        'Recursos administrativos (reposición, apelación, queja)',
                        'Actuaciones ante superintendencias (quejas, reclamaciones)',
                        'Trámites de licencias y permisos',
                    ],
                ],
            ],
        ];
    }
}