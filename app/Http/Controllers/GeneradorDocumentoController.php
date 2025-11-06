<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\DocumentoGenerado;
use App\Models\PlantillaDocumento;
use App\Models\User; // Asegúrate que este 'use' esté presente
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // Importante para el diagnóstico
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Http\RedirectResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpWord\IOFactory;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class GeneradorDocumentoController extends Controller
{
    public function generar(Request $request): RedirectResponse
    {
        $validatedData = $request->validate([
            'plantilla_id' => ['required', Rule::exists(PlantillaDocumento::class, 'id')],
            'caso_id' => 'required|exists:casos,id',
            'es_confidencial' => 'required|boolean',
            'observaciones' => 'nullable|string|max:2000',
        ]);

        try {
            $plantilla = PlantillaDocumento::findOrFail($validatedData['plantilla_id']);
            $caso = Caso::with([
                'deudor', 'cooperativa', 'user', 'documentos', 'codeudor1', 'codeudor2'
            ])->findOrFail($validatedData['caso_id']);
            $user = Auth::user(); // Usuario que genera

            if (!$plantilla->archivo || !Storage::disk('local')->exists($plantilla->archivo)) {
                throw new Exception("El archivo de la plantilla base (.docx) no fue encontrado.");
            }

            setlocale(LC_TIME, 'es_ES.UTF-8');
            Carbon::setLocale('es');

            $templateProcessor = new TemplateProcessor(Storage::disk('local')->path($plantilla->archivo));

            // Diagnóstico: Ver qué variables detecta PHPWord
            $variablesDetectadas = $templateProcessor->getVariables();
            Log::info("Variables detectadas en plantilla:", $variablesDetectadas); // Usar Log::info

            // 1. Reemplazo de variables simples
            $this->reemplazarVariablesSimples($templateProcessor, $caso);

            // 2. Procesamiento de bloques con diagnóstico mejorado
            $this->procesarBloqueCodeudorMejorado($templateProcessor, 'bloque_codeudor1', $caso->codeudor1, 'codeudor1');
            $this->procesarBloqueCodeudorMejorado($templateProcessor, 'bloque_codeudor2', $caso->codeudor2, 'codeudor2');
            $this->procesarBloqueDocumentosMejorado($templateProcessor, $caso->documentos);

            // 3. Guardar archivos
            $nombreBase = Str::slug(($caso->deudor->nombre_completo ?? ('caso-' . $caso->id)) . '-' . ($plantilla->nombre ?? 'plantilla-' . $plantilla->id) . '-' . time());
            $rutaDirectorioDestino = "private/documentos_generados/caso_{$caso->id}";
            Storage::disk('local')->makeDirectory($rutaDirectorioDestino);

            $rutaDocx = "{$rutaDirectorioDestino}/{$nombreBase}.docx";
            $outputPath = Storage::disk('local')->path($rutaDocx);

            if (!is_writable(dirname($outputPath))) {
                throw new Exception("El directorio de destino '{$rutaDirectorioDestino}' no tiene permisos de escritura.");
            }

            $templateProcessor->saveAs($outputPath);

            $rutaPdf = '';
            if (Storage::disk('local')->exists($rutaDocx)) {
                $rutaPdf = $this->generarPdfDesdeDocx($rutaDocx, $rutaDirectorioDestino, $nombreBase);
            } else {
                 Log::warning("No se pudo guardar el archivo DOCX en: " . $outputPath);
             }


            DocumentoGenerado::create([
                'caso_id' => $caso->id,
                'plantilla_documento_id' => $plantilla->id,
                'user_id' => $user->id,
                'nombre_base' => $nombreBase,
                'ruta_archivo_docx' => $rutaDocx,
                'ruta_archivo_pdf' => $rutaPdf,
                'version_plantilla' => $plantilla->version ?? 'N/A',
                'observaciones' => $validatedData['observaciones'],
                'es_confidencial' => $validatedData['es_confidencial'],
                'metadatos' => ['ip_origen' => $request->ip()]
            ]);

            return back()->with('success', '¡Documento(s) generados con éxito!');

        } catch (Exception $e) {
            report($e); // Loguear el error completo
            $userMessage = 'Error al generar el documento. ';

            // Mensaje de error más específico basado en la excepción
            if (Str::contains($e->getMessage(), ['XML', 'cloneBlock', 'Cannot find block'])) {
                $userMessage .= 'PROBLEMA EN LA PLANTILLA: Los tags ${bloque_...} están mal formateados. Solución: Abre el archivo .docx, BORRA los tags ${bloque_codeudor1}, ${/bloque_codeudor1}, ${bloque_codeudor2}, ${/bloque_codeudor2}, ${bloque_documentos}, ${/bloque_documentos} y REESCRÍBELOS manualmente (no copies/pegues). Guarda y vuelve a subir la plantilla.';
            } else if (Str::contains($e->getMessage(), ['permisos', 'escribible'])) {
                $userMessage .= 'Problema de permisos en el servidor.';
            } else {
                $userMessage .= 'Error: ' . $e->getMessage();
            }

            return back()->with('error', $userMessage);
        }
    }

    private function reemplazarVariablesSimples(TemplateProcessor $templateProcessor, Caso $caso): void
    {
        $abogado = $caso->user;
        $deudor = $caso->deudor;
        $cooperativa = $caso->cooperativa;

        $templateProcessor->setValues([
            // Deudor
            'deudor_nombre_completo' => $deudor->nombre_completo ?? '',
            'deudor_tipo_documento' => $deudor->tipo_documento_texto ?? '',
            'deudor_numero_documento' => $deudor->numero_documento ?? '',
            'deudor_direccion1' => $deudor->direccion1 ?? '',
            'deudor_ciudad1' => $deudor->ciudad1 ?? '',
            'deudor_celular_1' => $deudor->celular_1 ?? '',
            'deudor_email_1' => $deudor->email_1 ?? '',

            // Cooperativa
            'cooperativa_nombre' => $cooperativa->nombre ?? '',
            'cooperativa_nit' => $cooperativa->NIT ?? '',
            'cooperativa_rep_legal' => $cooperativa->representante_legal ?? '',
            'cooperativa_email_judicial' => $cooperativa->email_notificacion_judicial ?? '',

            // Abogado
            'abogado_nombre' => $abogado->name ?? '',
            'abogado_email' => $abogado->email ?? '',

            // Caso
            'fecha_actual' => Carbon::now()->isoFormat('D [de] MMMM [de] YYYY'),
            'caso_referencia_credito' => $caso->referencia_credito ?? '',
            'caso_radicado_interno' => $caso->id,
            'caso_origen_documental' => $caso->tipo_garantia_asociada ?? '',
            'caso_fecha_apertura' => $caso->fecha_apertura ? Carbon::parse($caso->fecha_apertura)->isoFormat('LL') : '',
            'caso_monto_total' => '$' . number_format($caso->monto_total ?? 0, 0, ',', '.'),
            'caso_estado_proceso' => $caso->estado_proceso ?? $caso->etapa_procesal ?? '',
            'caso_tasa_mora' => $caso->tasa_mora ?? '',
            'caso_tipo_proceso' => $caso->tipo_proceso ?? '',
        ]);
    }

    /**
     * Versión mejorada con mejor diagnóstico y manejo de errores
     */
    private function procesarBloqueCodeudorMejorado(
        TemplateProcessor $templateProcessor,
        string $blockName,
        $codeudor,
        string $prefix // 'codeudor1' o 'codeudor2'
    ): void {
        $variables = $templateProcessor->getVariables();
        // PHPWord espera los tags sin ${} y sin / para getVariables
        $startTag = $blockName; // 'bloque_codeudor1'
        $endTag = '/' . $blockName; // '/bloque_codeudor1'
        $bloqueExiste = in_array($startTag, $variables) && in_array($endTag, $variables);

        Log::info("Procesando bloque '{$blockName}': Existe=" . ($bloqueExiste ? 'SÍ' : 'NO')); // Usar Log::info

        if (!$bloqueExiste) {
            Log::warning("ADVERTENCIA: Tags del bloque '{$blockName}' NO detectados en la plantilla. Verifica que estén escritos correctamente: \${{$blockName}} y \${/{$blockName}}"); // Usar Log::warning
            return; // No intentar procesar si no se detectan los tags
        }

        try {
            if ($codeudor) {
                // Clonar bloque una vez
                $templateProcessor->cloneBlock($blockName, 1, true, false); // El false al final es importante para bloques simples

                // Rellenar valores DENTRO del bloque clonado (usando setValue simple)
                $templateProcessor->setValue("{$prefix}_nombre_completo", $codeudor->nombre_completo ?? '');
                $templateProcessor->setValue("{$prefix}_numero_documento", $codeudor->numero_documento ?? '');

                Log::info("Bloque '{$blockName}' clonado y rellenado exitosamente"); // Usar Log::info
            } else {
                // Eliminar bloque si no hay codeudor (usando cloneBlock con 0)
                $templateProcessor->cloneBlock($blockName, 0);
                Log::info("Bloque '{$blockName}' eliminado (no hay datos de codeudor)"); // Usar Log::info
            }
        } catch (\Exception $e) {
            // Capturar error específico de cloneBlock
            Log::error("ERROR al procesar bloque '{$blockName}': " . $e->getMessage()); // Usar Log::error
            // Lanzar una nueva excepción con mensaje claro para el usuario
            throw new Exception(
                "Error al procesar el bloque '{$blockName}'. El XML de la plantilla está corrupto. " .
                "Solución: Abre el .docx, borra los tags \${{$blockName}} y \${/{$blockName}} " .
                "y reescríbelos manualmente.",
                0, // Código de error
                $e  // Excepción original
            );
        }
    }

    /**
     * Versión mejorada del procesamiento de documentos con diagnóstico
     */
    private function procesarBloqueDocumentosMejorado(TemplateProcessor $templateProcessor, $documentos): void
    {
        $blockName = 'bloque_documentos';
        $variables = $templateProcessor->getVariables();
        $startTag = $blockName;
        $endTag = '/' . $blockName;
        $bloqueExiste = in_array($startTag, $variables) && in_array($endTag, $variables);

        Log::info("Procesando bloque '{$blockName}': Existe=" . ($bloqueExiste ? 'SÍ' : 'NO') . ", Documentos=" . ($documentos ? $documentos->count() : 0)); // Usar Log::info

        if (!$bloqueExiste) {
            Log::warning("Tags del bloque '{$blockName}' NO detectados. Verifica: \${{{$blockName}}} y \${/{$blockName}}"); // Usar Log::warning
            return;
        }

        $count = $documentos ? $documentos->count() : 0;

        try {
            if ($count > 0) {
                // Clonar el bloque tantas veces como documentos
                $templateProcessor->cloneBlock($blockName, $count, true, true); // true, true para bloques con múltiples variables internas

                // Rellenar cada iteración
                foreach ($documentos as $index => $doc) {
                    $itemIndex = $index + 1;
                    // PHPWord añade #index a las variables dentro de bloques clonados
                    $templateProcessor->setValue("doc_item_tipo#{$itemIndex}", $doc->tipo_documento ?? '');
                    $templateProcessor->setValue("doc_item_nombre#{$itemIndex}", $doc->archivo_nombre_original ?? '');
                    $templateProcessor->setValue("doc_item_fecha#{$itemIndex}",
                        $doc->fecha_carga ? Carbon::parse($doc->fecha_carga)->format('d/m/Y') : ''
                    );
                }

                Log::info("Bloque '{$blockName}' clonado {$count} veces y rellenado"); // Usar Log::info
            } else {
                // Sin documentos, eliminar el bloque
                $templateProcessor->cloneBlock($blockName, 0);
                Log::info("Bloque '{$blockName}' eliminado (sin documentos)"); // Usar Log::info
            }
        } catch (\Exception $e) {
            Log::error("ERROR al procesar bloque '{$blockName}': " . $e->getMessage()); // Usar Log::error
            throw new Exception(
                "Error procesando el bloque '{$blockName}'. Verifica el XML de la plantilla.",
                0,
                $e
            );
        }
    }


    private function generarPdfDesdeDocx(string $rutaDocxProcesado, string $directorio, string $nombreBase): string
    {
        $docxPath = Storage::disk('local')->path($rutaDocxProcesado);
        if (!file_exists($docxPath)) {
            Log::error("El archivo DOCX para generar PDF no existe: {$docxPath}"); // Usar Log::error
            return '';
        }

        try {
            if (!is_writable(sys_get_temp_dir())) {
                throw new Exception("El directorio temporal del sistema no es escribible.");
            }

            $phpWord = IOFactory::load($docxPath);
            $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');

            $tempHtmlPath = tempnam(sys_get_temp_dir(), 'phpwordhtml_') . '.html';
            if ($tempHtmlPath === false) {
                throw new Exception("No se pudo crear el archivo HTML temporal.");
            }

            $htmlWriter->save($tempHtmlPath);
            $htmlContent = file_get_contents($tempHtmlPath);
            unlink($tempHtmlPath);

            if (empty(trim($htmlContent))) {
                Log::warning("Conversión DOCX→HTML resultó vacía para: {$rutaDocxProcesado}"); // Usar Log::warning
            }

            $styledHtml = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>'
                        . '<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}table{border-collapse:collapse;width:100%}td,th{text-align:left;padding:4px;border:1px solid #ddd}img{max-width:100%;height:auto}</style>'
                        . '</head><body>' . $htmlContent . '</body></html>';

            $pdf = Pdf::loadHTML($styledHtml);
            $rutaCompletaPdf = "{$directorio}/{$nombreBase}.pdf";
            $pdfOutputPath = Storage::disk('local')->path($rutaCompletaPdf);

            if (!is_writable(dirname($pdfOutputPath))) {
                throw new Exception("El directorio para PDF '{$directorio}' no es escribible.");
            }

            Storage::disk('local')->put($rutaCompletaPdf, $pdf->output());

            if (!Storage::disk('local')->exists($rutaCompletaPdf)) {
                throw new Exception("No se pudo guardar el PDF en: {$rutaCompletaPdf}");
            }

            return $rutaCompletaPdf;

        } catch (Exception $e) {
            report($e); // Loguear el error completo
            return ''; // Retornar vacío si falla la generación de PDF
        }
    }

    // Métodos de descarga sin cambios (asegúrate que verificarAcceso esté bien)
    public function descargarDocx(DocumentoGenerado $documento)
    {
        $this->verificarAcceso($documento);
        if (empty($documento->ruta_archivo_docx) || !Storage::disk('local')->exists($documento->ruta_archivo_docx)) {
            return back()->with('error', 'El archivo DOCX no se encuentra.');
        }
        return Storage::disk('local')->download($documento->ruta_archivo_docx, $documento->nombre_base . '.docx');
    }

    public function descargarPdf(DocumentoGenerado $documento)
    {
        $this->verificarAcceso($documento);
        if (empty($documento->ruta_archivo_pdf) || !Storage::disk('local')->exists($documento->ruta_archivo_pdf)) {
            // Fallback opcional si el PDF no existe pero el DOCX sí
            if (!empty($documento->ruta_archivo_docx) && Storage::disk('local')->exists($documento->ruta_archivo_docx)) {
                 return back()->with('warning', 'El archivo PDF no está disponible. Intenta descargar el archivo DOCX.');
                 // O redirigir a la descarga DOCX: return $this->descargarDocx($documento);
            }
            return back()->with('error', 'El archivo PDF no se encuentra o no se pudo generar.');
        }
        return Storage::disk('local')->download($documento->ruta_archivo_pdf, $documento->nombre_base . '.pdf');
    }

    // Asegúrate que esta función refleje tu lógica de permisos real
    private function verificarAcceso(DocumentoGenerado $documento)
    {
        $user = Auth::user();
        // Admins, gestores, abogados ven todo?
        if (in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
            return;
        }

        // Clientes no ven confidenciales
        if ($documento->es_confidencial && $user->tipo_usuario === 'cli') {
            abort(403, 'Acceso denegado a documento confidencial.');
        }

        // Clientes solo ven documentos de sus casos? (Asegúrate que $user->persona_id existe y es correcto)
        if ($user->tipo_usuario === 'cli') {
            // Cargar la relación 'caso' si no está ya cargada
            $documento->loadMissing('caso');
            $casoDelDocumento = $documento->caso;
            // Verificar si el usuario está relacionado al caso (como deudor, codeudor, etc.)
            if (!$casoDelDocumento || (
                    $casoDelDocumento->deudor_id !== $user->persona_id &&
                    $casoDelDocumento->codeudor1_id !== $user->persona_id && // Añadir si aplica
                    $casoDelDocumento->codeudor2_id !== $user->persona_id    // Añadir si aplica
                )) {
                abort(403, 'No tienes permiso para acceder a documentos de este caso.');
            }
        } else {
             // Si hay otros tipos de usuario, definir sus permisos aquí o denegar por defecto
             // abort(403, 'Tipo de usuario no autorizado para ver este documento.');
        }
    }
}

