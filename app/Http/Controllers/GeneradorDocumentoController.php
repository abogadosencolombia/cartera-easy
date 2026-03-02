<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\DocumentoGenerado;
use App\Models\PlantillaDocumento;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        // 1. Validación estricta de entrada
        $validatedData = $request->validate([
            'plantilla_id' => ['required', Rule::exists(PlantillaDocumento::class, 'id')],
            'caso_id' => 'required|exists:casos,id',
            'es_confidencial' => 'required|boolean',
            'observaciones' => 'nullable|string|max:2000',
        ]);

        try {
            // 2. Carga de datos
            $plantilla = PlantillaDocumento::findOrFail($validatedData['plantilla_id']);
            $caso = Caso::with([
                'deudor', 
                'cooperativa', 
                'user',          
                'documentos',    
                'codeudores'     
            ])->findOrFail($validatedData['caso_id']);
            
            $user = Auth::user();

            // 3. Verificación física
            if (!$plantilla->archivo || !Storage::disk('local')->exists($plantilla->archivo)) {
                throw new Exception("El archivo físico de la plantilla (.docx) no existe en el servidor.");
            }

            setlocale(LC_TIME, 'es_ES.UTF-8');
            Carbon::setLocale('es');

            // 4. Inicializar procesador
            $templateProcessor = new TemplateProcessor(Storage::disk('local')->path($plantilla->archivo));

            // === MOTOR DE REEMPLAZO DE VARIABLES ===

            // A. Variables Simples
            $this->procesarVariablesSimples($templateProcessor, $caso);

            // B. Codeudores (Lista)
            $textoCodeudores = "No hay codeudores vinculados a este caso.";
            if ($caso->codeudores->count() > 0) {
                $lista = [];
                foreach ($caso->codeudores as $index => $codeudor) {
                    $num = $index + 1;
                    $doc = $codeudor->numero_documento ?? 'S/N';
                    $tel = $codeudor->celular ?? 'S/N';
                    $lista[] = "{$num}. {$codeudor->nombre_completo} (Doc: {$doc}) - Tel: {$tel}";
                }
                $textoCodeudores = implode("\n", $lista); 
            }
            $templateProcessor->setValue('texto_codeudores', $textoCodeudores);

            // C. Documentos Adjuntos (Lista)
            $textoDocumentos = "No hay documentos adjuntos en el expediente.";
            if ($caso->documentos->count() > 0) {
                $lista = [];
                foreach ($caso->documentos as $doc) {
                    $fecha = $doc->fecha_carga ? Carbon::parse($doc->fecha_carga)->format('d/m/Y') : 'N/A';
                    $tipo = $doc->tipo_documento ?? 'Documento';
                    $nombre = $doc->archivo_nombre_original ?? 'Archivo';
                    $lista[] = "• {$tipo}: {$nombre} (Cargado: {$fecha})";
                }
                $textoDocumentos = implode("\n", $lista);
            }
            $templateProcessor->setValue('texto_documentos', $textoDocumentos);

            // === FIN MOTOR ===

            // 5. Rutas y Nombres
            $nombreDeudorClean = Str::slug($caso->deudor->nombre_completo ?? "caso-{$caso->id}");
            $nombrePlantillaClean = Str::slug($plantilla->nombre ?? "plantilla");
            $timestamp = time();
            
            $nombreBase = "{$nombreDeudorClean}-{$nombrePlantillaClean}-{$timestamp}";
            $rutaDirectorioDestino = "private/documentos_generados/caso_{$caso->id}";
            
            if (!Storage::disk('local')->exists($rutaDirectorioDestino)) {
                Storage::disk('local')->makeDirectory($rutaDirectorioDestino);
            }

            $rutaDocx = "{$rutaDirectorioDestino}/{$nombreBase}.docx";
            $outputPath = Storage::disk('local')->path($rutaDocx);

            // 6. Guardar DOCX
            $templateProcessor->saveAs($outputPath);

            // 7. Generar PDF
            $rutaPdf = null; // Inicializar como null explícitamente
            try {
                if (Storage::disk('local')->exists($rutaDocx)) {
                    $rutaPdf = $this->convertirDocxAPdf($rutaDocx, $rutaDirectorioDestino, $nombreBase);
                }
            } catch (\Exception $e) {
                Log::warning("No se pudo generar el PDF automático: " . $e->getMessage());
            }

            // 8. Registrar BD
            DocumentoGenerado::create([
                'caso_id' => $caso->id,
                'plantilla_documento_id' => $plantilla->id,
                'user_id' => $user->id,
                'nombre_base' => $nombreBase,
                'ruta_archivo_docx' => $rutaDocx,
                'ruta_archivo_pdf' => $rutaPdf, // Puede ser null
                'version_plantilla' => $plantilla->version ?? '1.0',
                'observaciones' => $validatedData['observaciones'],
                'es_confidencial' => $validatedData['es_confidencial'],
                'metadatos' => [
                    'ip_origen' => $request->ip(),
                    'navegador' => $request->userAgent(),
                    'fecha_generacion' => now()->toIso8601String()
                ]
            ]);

            return back()->with('success', '¡Documento generado exitosamente! Puedes descargarlo abajo.');

        } catch (Exception $e) {
            Log::error("Error crítico generando documento: " . $e->getMessage());
            return back()->with('error', 'Error del sistema: ' . $e->getMessage());
        }
    }

    private function procesarVariablesSimples(TemplateProcessor $templateProcessor, Caso $caso): void
    {
        $deudor = $caso->deudor;
        $cooperativa = $caso->cooperativa;
        $abogado = $caso->user;

        $valores = [
            'fecha_actual' => Carbon::now()->isoFormat('D [de] MMMM [de] YYYY'),
            'fecha_actual_corta' => Carbon::now()->format('d/m/Y'),
            'anio_actual' => Carbon::now()->format('Y'),
            'caso_radicado' => $caso->radicado ?? 'S/R',
            'caso_id' => $caso->id,
            'caso_referencia' => $caso->referencia_credito ?? 'S/R',
            'caso_monto_total' => '$' . number_format($caso->monto_total ?? 0, 0, ',', '.'),
            'caso_deuda_actual' => '$' . number_format($caso->monto_deuda_actual ?? 0, 0, ',', '.'),
            'caso_tipo_proceso' => $caso->tipo_proceso ?? 'No especificado',
            'caso_estado' => $caso->estado_proceso ?? $caso->etapa_procesal ?? 'Activo',
            'caso_garantia' => $caso->tipo_garantia_asociada ?? 'No especificada',
            'caso_juzgado' => $caso->juzgado ? $caso->juzgado->nombre : 'No asignado',
            'caso_fecha_apertura' => $caso->fecha_apertura ? Carbon::parse($caso->fecha_apertura)->format('d/m/Y') : 'N/A',
            'deudor_nombre' => $deudor->nombre_completo ?? 'N/A',
            'deudor_documento' => $deudor->numero_documento ?? 'N/A',
            'deudor_tipo_doc' => $deudor->tipo_documento ?? 'CC',
            'deudor_direccion' => $deudor->direccion1 ?? 'Sin dirección',
            'deudor_ciudad' => $deudor->ciudad1 ?? '',
            'deudor_telefono' => $deudor->celular_1 ?? 'Sin teléfono',
            'deudor_email' => $deudor->email_1 ?? 'Sin email',
            'coop_nombre' => $cooperativa->nombre ?? 'N/A',
            'coop_nit' => $cooperativa->NIT ?? 'N/A',
            'coop_representante' => $cooperativa->representante_legal ?? 'N/A',
            'coop_email' => $cooperativa->email_notificacion_judicial ?? '',
            'coop_direccion' => $cooperativa->direccion ?? '',
            'abogado_nombre' => $abogado->name ?? 'N/A',
            'abogado_email' => $abogado->email ?? 'N/A',
            'abogado_telefono' => $abogado->telefono ?? '',
            
            'deudor_nombre_completo' => $deudor->nombre_completo ?? '',
            'deudor_numero_documento' => $deudor->numero_documento ?? '',
            'deudor_celular_1' => $deudor->celular_1 ?? '',
            'deudor_email_1' => $deudor->email_1 ?? '',
            'cooperativa_nombre' => $cooperativa->nombre ?? '',
            'cooperativa_nit' => $cooperativa->NIT ?? '',
            'cooperativa_rep_legal' => $cooperativa->representante_legal ?? '',
            'caso_radicado_interno' => $caso->id,
            'caso_referencia_credito' => $caso->referencia_credito ?? '',
            'caso_origen_documental' => $caso->tipo_garantia_asociada ?? '',
            'caso_estado_proceso' => $caso->estado_proceso ?? '',
        ];
        
        foreach($valores as $key => $val) {
             $templateProcessor->setValue($key, (string)($val ?? ''));
        }
    }

    private function convertirDocxAPdf(string $rutaDocxRelativa, string $directorioDestino, string $nombreBase): string
    {
        $docxFullPath = Storage::disk('local')->path($rutaDocxRelativa);
        
        if (!file_exists($docxFullPath)) return '';

        try {
            $phpWord = IOFactory::load($docxFullPath);
            $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
            
            $tempHtmlFile = tempnam(sys_get_temp_dir(), 'docx2pdf');
            $htmlWriter->save($tempHtmlFile);
            
            $htmlContent = file_get_contents($tempHtmlFile);
            unlink($tempHtmlFile);

            if (empty(trim($htmlContent))) return '';

            // Limpieza CSS para PDF
            $htmlContent = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', "", $htmlContent);
            $htmlContent = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $htmlContent);
            preg_match("/<body[^>]*>(.*?)<\/body>/is", $htmlContent, $matches);
            $bodyContent = $matches[1] ?? $htmlContent;
            $bodyContent = strip_tags($bodyContent, '<div><p><span><table><thead><tbody><tr><td><th><img><b><i><u><br><h1><h2><h3><h4><ul><ol><li><strong><em>');
            
            $css = '<style>body{font-family:"Helvetica",sans-serif;font-size:11pt;line-height:1.4}table{width:100%;border-collapse:collapse;margin:10px 0}th,td{border:1px solid #ccc;padding:5px}p{margin-bottom:8px;text-align:justify}</style>';
            $finalHtml = "<html><head><meta charset='utf-8'>{$css}</head><body>{$bodyContent}</body></html>";

            $pdf = Pdf::loadHTML($finalHtml)->setPaper('letter', 'portrait');
            
            $rutaPdfRelativa = "{$directorioDestino}/{$nombreBase}.pdf";
            Storage::disk('local')->put($rutaPdfRelativa, $pdf->output());

            return $rutaPdfRelativa;

        } catch (Exception $e) {
            Log::error("Fallo en conversión PDF: " . $e->getMessage());
            return '';
        }
    }

    public function descargarDocx(DocumentoGenerado $documento)
    {
        $this->verificarAcceso($documento);

        if (!Storage::disk('local')->exists($documento->ruta_archivo_docx)) {
            return back()->with('error', 'El archivo original no se encuentra en el servidor.');
        }
        return Storage::disk('local')->download($documento->ruta_archivo_docx, $documento->nombre_base . '.docx');
    }

    // ✅ CORRECCIÓN CLAVE AQUÍ
    public function descargarPdf(DocumentoGenerado $documento)
    {
        $this->verificarAcceso($documento);

        // 1. Verificar si la ruta es NULL o vacía
        if (empty($documento->ruta_archivo_pdf)) {
             // Opción A: Redirigir a descargar DOCX automáticamente
             return back()->with('warning', 'El PDF no se pudo generar automáticamente. Descargue la versión DOCX.');
        }

        // 2. Verificar si el archivo físico existe
        if (!Storage::disk('local')->exists($documento->ruta_archivo_pdf)) {
            return back()->with('error', 'El archivo PDF físico no se encuentra en el servidor.');
        }

        return Storage::disk('local')->download($documento->ruta_archivo_pdf, $documento->nombre_base . '.pdf');
    }

    private function verificarAcceso(DocumentoGenerado $documento)
    {
        $user = Auth::user();
        if (in_array($user->tipo_usuario, ['admin', 'gestor', 'abogado'])) {
            return true;
        }
        if ($user->tipo_usuario === 'cliente') {
            if ($documento->es_confidencial) abort(403);
            $caso = $documento->caso;
            if ($caso->deudor_id !== $user->persona_id) abort(403);
        }
        return true;
    }
}