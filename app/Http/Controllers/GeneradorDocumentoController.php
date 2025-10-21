<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\DocumentoGenerado;
use App\Models\PlantillaDocumento;
use App\Models\User; // <-- ¡ESTA ES LA LÍNEA QUE FALTABA!
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Http\RedirectResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpWord\IOFactory;

class GeneradorDocumentoController extends Controller
{
    /**
     * Orquesta la generación de documentos DOCX y PDF a partir de una plantilla.
     */
    public function generar(Request $request): RedirectResponse
    {
        $validatedData = $request->validate([
            'plantilla_id' => 'required|exists:plantillas_documento,id',
            'caso_id' => 'required|exists:casos,id',
            'es_confidencial' => 'required|boolean',
            'observaciones' => 'nullable|string|max:2000',
        ]);

        try {
            $plantilla = PlantillaDocumento::findOrFail($validatedData['plantilla_id']);
            $caso = Caso::with(['deudor', 'cooperativa', 'user', 'documentos', 'codeudor1', 'codeudor2'])->findOrFail($validatedData['caso_id']);
            $user = Auth::user();

            if (!$plantilla->archivo || !Storage::disk('local')->exists($plantilla->archivo)) {
                throw new Exception("El archivo de la plantilla base (.docx) no fue encontrado en el servidor.");
            }
            
            $templateProcessor = new TemplateProcessor(Storage::disk('local')->path($plantilla->archivo));

            // --- 1. REEMPLAZO DE VARIABLES SIMPLES ---
            $this->reemplazarVariablesSimples($templateProcessor, $caso, $user);
            
            // --- 2. PROCESAMIENTO DE BLOQUES CONDICIONALES (CODEUDORES) ---
            $this->procesarBloqueCodeudor($templateProcessor, 'bloque_codeudor1', $caso->codeudor1);
            $this->procesarBloqueCodeudor($templateProcessor, 'bloque_codeudor2', $caso->codeudor2);

            // --- 3. PROCESAMIENTO DE BLOQUES REPETITIVOS (DOCUMENTOS) ---
            $this->procesarBloqueDocumentos($templateProcessor, $caso->documentos);
            
            // --- 4. GUARDADO Y CREACIÓN DE REGISTROS ---
            $nombreBase = "{$caso->id}_{$plantilla->id}_" . time();
            $rutaDirectorioDestino = "private/documentos_generados/caso_{$caso->id}";
            Storage::disk('local')->makeDirectory($rutaDirectorioDestino);

            $rutaDocx = "{$rutaDirectorioDestino}/{$nombreBase}.docx";
            $templateProcessor->saveAs(Storage::disk('local')->path($rutaDocx));
            
            $rutaPdf = $this->generarPdfDesdeDocx($rutaDocx, $rutaDirectorioDestino, $nombreBase);

            DocumentoGenerado::create([
                'caso_id' => $caso->id, 'plantilla_documento_id' => $plantilla->id, 'user_id' => $user->id,
                'nombre_base' => $nombreBase, 'ruta_archivo_docx' => $rutaDocx, 'ruta_archivo_pdf' => $rutaPdf,
                'version_plantilla' => $plantilla->version, 'observaciones' => $validatedData['observaciones'],
                'es_confidencial' => $validatedData['es_confidencial'], 'metadatos' => ['ip_origen' => $request->ip()]
            ]);

            return back()->with('success', '¡Documentos DOCX y PDF generados con éxito!');

        } catch (Exception $e) {
            report($e);
            return back()->with('error', 'Error al generar el documento: ' . $e->getMessage());
        }
    }

    private function reemplazarVariablesSimples(TemplateProcessor $templateProcessor, Caso $caso, User $user): void
    {
        $templateProcessor->setValues([
            'deudor_nombre_completo' => $caso->deudor->nombre_completo ?? 'N/A',
            'deudor_tipo_documento' => $caso->deudor->tipo_documento_texto ?? 'N/A',
            'deudor_numero_documento' => $caso->deudor->numero_documento ?? 'N/A',
            'deudor_direccion1' => $caso->deudor->direccion1 ?? 'N/A',
            'deudor_ciudad1' => $caso->deudor->ciudad1 ?? 'N/A',
            'deudor_celular_1' => $caso->deudor->celular_1 ?? 'N/A',
            'deudor_email_1' => $caso->deudor->email_1 ?? 'N/A',
            'cooperativa_nombre' => $caso->cooperativa->nombre ?? 'N/A',
            'cooperativa_nit' => $caso->cooperativa->NIT ?? 'N/A',
            'cooperativa_rep_legal' => $caso->cooperativa->representante_legal ?? 'N/A',
            'cooperativa_email_judicial' => $caso->cooperativa->email_notificacion_judicial ?? 'N/A',
            'abogado_nombre' => $caso->user->name ?? 'No asignado',
            'abogado_email' => $caso->user->email ?? 'No asignado',
            'fecha_actual' => now()->isoFormat('D [de] MMMM [de] YYYY'),
            'caso_referencia_credito' => $caso->referencia_credito ?? 'N/A',
            'caso_radicado_interno' => $caso->id,
            'caso_origen_documental' => $caso->tipo_garantia_asociada ?? 'N/A',
            'caso_fecha_apertura' => $caso->fecha_apertura ? $caso->fecha_apertura->isoFormat('LL') : 'N/A',
            'caso_monto_total' => '$' . number_format($caso->monto_total, 0, ',', '.'),
            'caso_estado_proceso' => $caso->estado_proceso ?? 'N/A',
            'caso_tasa_mora' => $caso->tasa_mora ?? 'N/A',
            'caso_tipo_proceso' => $caso->tipo_proceso ?? 'N/A',
        ]);
    }

    private function procesarBloqueCodeudor(TemplateProcessor $templateProcessor, string $blockName, $codeudor): void
    {
        if ($codeudor) {
            $prefix = str_replace('bloque_', '', $blockName);
            $templateProcessor->setValue("{$prefix}_nombre_completo", $codeudor->nombre_completo);
            $templateProcessor->setValue("{$prefix}_numero_documento", $codeudor->numero_documento);
        } else {
            $templateProcessor->deleteBlock($blockName);
        }
    }

    private function procesarBloqueDocumentos(TemplateProcessor $templateProcessor, $documentos): void
    {
        if ($documentos && $documentos->count() > 0) {
            $templateProcessor->cloneBlock('bloque_documentos', $documentos->count(), true, true);
            foreach ($documentos as $index => $doc) {
                $itemIndex = $index + 1;
                $templateProcessor->setValue("doc_item_tipo#{$itemIndex}", $doc->tipo_documento);
                $templateProcessor->setValue("doc_item_nombre#{$itemIndex}", $doc->archivo_nombre_original ?? 'N/A');
                $templateProcessor->setValue("doc_item_fecha#{$itemIndex}", $doc->fecha_carga ? $doc->fecha_carga->format('d/m/Y') : 'N/A');
            }
        } else {
            $templateProcessor->deleteBlock('bloque_documentos');
        }
    }
    
    private function generarPdfDesdeDocx(string $rutaDocxProcesado, string $directorio, string $nombreBase): string
    {
        try {
            $phpWord = IOFactory::load(Storage::disk('local')->path($rutaDocxProcesado));
            $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
            $htmlContent = $htmlWriter->getContent();
            $styledHtml = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/><style> body { font-family: DejaVu Sans, sans-serif; font-size: 11px; } table { border-collapse: collapse; width: 100%; } td, th { text-align: left; padding: 4px; } </style></head><body>' . $htmlContent . '</body></html>';
            $pdf = Pdf::loadHTML($styledHtml);
            $rutaCompleta = "{$directorio}/{$nombreBase}.pdf";
            Storage::disk('local')->put($rutaCompleta, $pdf->output());
            return $rutaCompleta;
        } catch (Exception $e) {
            report($e);
            return ''; 
        }
    }

    public function descargarDocx(DocumentoGenerado $documento)
    {
        $this->verificarAcceso($documento);
        if (empty($documento->ruta_archivo_docx) || !Storage::exists($documento->ruta_archivo_docx)) {
            return back()->with('error', 'El archivo DOCX no se encuentra en el servidor.');
        }
        return Storage::disk('local')->download($documento->ruta_archivo_docx, $documento->nombre_base . '.docx');
    }

    public function descargarPdf(DocumentoGenerado $documento)
    {
        $this->verificarAcceso($documento);
        if (empty($documento->ruta_archivo_pdf) || !Storage::exists($documento->ruta_archivo_pdf)) {
            return back()->with('error', 'El archivo PDF no se encuentra o no se pudo generar.');
        }
        return Storage::disk('local')->download($documento->ruta_archivo_pdf, $documento->nombre_base . '.pdf');
    }

    private function verificarAcceso(DocumentoGenerado $documento)
    {
        if ($documento->es_confidencial && Auth::user()->tipo_usuario === 'cli') {
            abort(403, 'Acceso denegado a este documento.');
        }
    }
}