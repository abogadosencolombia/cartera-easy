<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// --- CONTROLADORES PRINCIPALES ---
use App\Http\Controllers\AnaliticaController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\CasoController;
use App\Http\Controllers\CooperativaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoCasoController;
use App\Http\Controllers\DocumentoGeneradoController;
use App\Http\Controllers\GeneradorDocumentoController;
use App\Http\Controllers\IntegracionController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PagoCasoController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\PlantillaDocumentoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ReporteCumplimientoController;
use App\Http\Controllers\RequisitoDocumentoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ValidacionLegalController;
use App\Http\Controllers\Admin\ReglaAlertaController;
use App\Http\Controllers\Admin\TasasInteresController;

// --- CONTROLADORES DE MÓDULOS ---
use App\Http\Controllers\Juridico\ArchivoIncidenteController;
use App\Http\Controllers\Juridico\DecisionComiteEticaController;
use App\Http\Controllers\Juridico\IncidenteJuridicoController;
use App\Http\Controllers\Juridico\IndicadoresController;
use App\Http\Controllers\Juridico\TicketDisciplinarioController;
use App\Services\IntegrationService;
use App\Http\Controllers\DocumentoLegalController;
use App\Http\Controllers\Admin\IntegracionTokenController;

use App\Http\Controllers\ContactoClienteController;
use App\Http\Controllers\Admin\GestoresController;
use App\Http\Controllers\ProcesoJudicialController;
use App\Http\Controllers\Web\AlertasPageController;

// --- CONTROLADORES DE API Y BÚSQUEDA ---
use App\Http\Controllers\Api\AlertasController;
use App\Http\Controllers\Api\DirectorySearchController;
use App\Http\Controllers\Api\JuzgadoSearchController;

// --- NUEVOS CONTROLADORES: GESTIÓN > HONORARIOS ---
use App\Http\Controllers\Gestion\Honorarios\ContratosController;

// --- CONTROLADORES DEL MÓDULO DE RADICADOS ---
use App\Http\Controllers\WebPushController;
use App\Http\Controllers\ProcesoRadicadoController;
use App\Models\ProcesoRadicado;
use App\Http\Controllers\DocumentoProcesoController;

// --- INICIO: NUEVO CONTROLADOR REVISIÓN DIARIA ---
use App\Http\Controllers\RevisionDiariaController;
// --- FIN: NUEVO CONTROLADOR REVISIÓN DIARIA ---

// =======================================================
// ===== NUEVAS DECLARACIONES PARA EL CHATBOT ============
// =======================================================
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Models\Message;
use App\Events\ChatbotResponseReceived;
use Illuminate\Support\Facades\Broadcast; // <-- Añadido
// =======================================================


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Broadcast::routes(['middleware' => ['web', 'auth']]);

// Ruta pública de bienvenida / login
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('Auth/Login', [
        'canResetPassword' => Route::has('password.request'),
        'status' => session('status'),
    ]);
});

// Rutas de prueba y simulación (pueden ser eliminadas en producción)
Route::get('/_push-test', function () {
    $u = auth()->user() ?: \App\Models\User::first();
    if ($u) $u->notify(new \App\Notifications\DebugPushNotification());
    return 'ok';
})->middleware('auth')->name('_push.test');

Route::get('/api/simulador/supersolidaria/validar/{nit}', function ($nit) {
    $cooperativasSimuladas = [
        '900123456-7' => ['nombre' => 'Cooperativa El Futuro', 'estado' => 'Activa', 'fecha_registro' => '2010-05-20'],
        '800987654-3' => ['nombre' => 'CoopProgreso', 'estado' => 'En Liquidación', 'fecha_registro' => '2005-11-15'],
        '123456789-0' => ['nombre' => 'Cooperativa Fantasma', 'estado' => 'Cancelada', 'fecha_registro' => '2001-01-10'],
    ];
    return response()->json($cooperativasSimuladas[$nit] ?? ['error' => 'Cooperativa no encontrada'], 404);
});

Route::get('/probar-integracion-supersolidaria', function (IntegrationService $integrationService) {
    $nitDePrueba = '800987654-3';
    $urlDelSimulador = url('/api/simulador/supersolidaria/validar/' . $nitDePrueba);
    $respuesta = $integrationService->ejecutar('Supersolidaria (Simulador)', 'get', $urlDelSimulador);
    dump($respuesta);
    echo "<h1>Revisa la tabla 'integracion_externa_logs' en tu base de datos.</h1>";
});

// =================================================================
// ===== GRUPO PRINCIPAL DE RUTAS PROTEGIDAS POR AUTENTICACIÓN =====
// =================================================================
Route::middleware(['auth', 'verified'])->group(function () {

    // --- Push Notifications ---
    Route::post('/push/subscribe',   [WebPushController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [WebPushController::class, 'unsubscribe'])->name('push.unsubscribe');

    // --- Páginas de Alertas ---
    Route::get('/alertas/preventivas', [AlertasPageController::class, 'preventivas'])->name('alertas.preventivas');
    Route::get('/alertas/reactivas', [AlertasPageController::class, 'reactivas'])->name('alertas.reactivas');
    Route::get('/alertas/hallazgos', [AlertasPageController::class, 'hallazgos'])->name('alertas.hallazgos');
    Route::get('/alertas/reporte-cliente', [AlertasPageController::class, 'reporteCliente'])->name('alertas.reporte-cliente');
    Route::get('/alertas/vigilancia-judicial', [AlertasPageController::class, 'vigilanciaJudicial'])->name('alertas.vigilancia-judicial');

    // --- Contacto ---
    Route::post('/contacto-cliente', [ContactoClienteController::class, 'enviar'])->name('contacto.cliente.enviar');

    // --- Dashboard, Perfil y Notificaciones ---
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // --- INICIO: NUEVA RUTA REVISIÓN DIARIA ---
    Route::get('/revision-diaria', [RevisionDiariaController::class, 'index'])->name('revision.index');
    // --- INICIO: AÑADIR RUTA TOGGLE ---
    Route::post('/revision-diaria/toggle', [RevisionDiariaController::class, 'toggle'])->name('revision.toggle');
    // --- FIN: AÑADIR RUTA TOGGLE ---
    // --- FIN: NUEVA RUTA REVISIÓN DIARIA ---

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences.update');
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::patch('/notificaciones/{notificacion}/leer', [NotificacionController::class, 'marcarComoLeida'])->name('notificaciones.leer');
    Route::patch('/notificaciones/{notificacion}/atender', [NotificacionController::class, 'marcarComoAtendida'])->name('notificaciones.atender');
    
    // --- Reportes y Validaciones ---
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/exportar', [ReporteController::class, 'exportar'])->name('reportes.exportar');
    Route::get('/reportes/cumplimiento', ReporteCumplimientoController::class)->name('reportes.cumplimiento');
    Route::get('/reportes/cumplimiento/exportar', [ReporteController::class, 'exportarCumplimiento'])->name('reportes.cumplimiento.exportar');
    Route::put('/validaciones-legales/{validacion}/corregir', ValidacionLegalController::class)->name('validaciones.corregir');

    // --- Documentos Legales de Cooperativas ---
    Route::post('cooperativas/{cooperativa}/documentos', [DocumentoLegalController::class, 'store'])->name('cooperativas.documentos.store');
    Route::get('documentos-legales/{documento}', [DocumentoLegalController::class, 'show'])->name('documentos-legales.show');
    Route::delete('documentos-legales/{documento}', [DocumentoLegalController::class, 'destroy'])->name('documentos.destroy');

    // =================================================================
    // ===== BLOQUE DE RUTAS PARA RADICADOS (PROCESOS) =================
    // =================================================================
    Route::get('procesos', [ProcesoRadicadoController::class, 'index'])->name('procesos.index');
    Route::get('procesos/create', [ProcesoRadicadoController::class, 'create'])->name('procesos.create');
    Route::post('procesos', [ProcesoRadicadoController::class, 'store'])->name('procesos.store');
    Route::get('procesos/{proceso}', [ProcesoRadicadoController::class, 'show'])->name('procesos.show');
    Route::get('procesos/{proceso}/edit', [ProcesoRadicadoController::class, 'edit'])->name('procesos.edit');
    Route::patch('procesos/{proceso}', [ProcesoRadicadoController::class, 'update'])->name('procesos.update');
    Route::delete('procesos/{proceso}', [ProcesoRadicadoController::class, 'destroy'])->name('procesos.destroy');
    
    // --- RUTAS PARA LA IMPORTACIÓN DE EXCEL ---
    Route::get('procesos/import', [ProcesoRadicadoController::class, 'showImportForm'])->name('procesos.import');
    Route::post('procesos/import', [ProcesoRadicadoController::class, 'handleImport'])->name('procesos.import.store');
    
    // --- Documentos para Radicados ---
    Route::post('procesos/{proceso}/documentos', [DocumentoProcesoController::class, 'store'])->name('procesos.documentos.store');
    Route::delete('procesos/{proceso}/documentos/{documento}', [DocumentoProcesoController::class, 'destroy'])->name('procesos.documentos.destroy');
    Route::patch('/procesos/{proceso}/close', [ProcesoRadicadoController::class, 'close'])->name('procesos.close');
    Route::patch('/procesos/{proceso}/reopen', [ProcesoRadicadoController::class, 'reopen'])->name('procesos.reopen');

    // --- INICIO: Rutas CRUD Actuaciones para Radicados ---
    Route::post('procesos/{proceso}/actuaciones', [ProcesoRadicadoController::class, 'storeActuacion'])->name('procesos.actuaciones.store');
    Route::put('procesos/actuaciones/{actuacion}', [ProcesoRadicadoController::class, 'updateActuacion'])->name('procesos.actuaciones.update');
    Route::delete('procesos/actuaciones/{actuacion}', [ProcesoRadicadoController::class, 'destroyActuacion'])->name('procesos.actuaciones.destroy');
    // --- FIN: Rutas CRUD Actuaciones para Radicados ---

    // =================================================================
    // ===== RUTAS DE BÚSQUEDA ASÍNCRONA (CORREGIDAS Y UNIFICADAS) =====
    // =================================================================
    Route::get('/search/users', [DirectorySearchController::class, 'usuariosAbogadosYGestores'])->name('users.search');
    Route::get('/search/cooperativas', [DirectorySearchController::class, 'cooperativas'])->name('cooperativas.search');
    Route::get('/search/personas', [DirectorySearchController::class, 'personas'])->name('personas.search');
    Route::get('/juzgados/search', JuzgadoSearchController::class)->name('juzgados.search');
    Route::get('/search/tipos-proceso', [DirectorySearchController::class, 'tiposProceso'])->name('tipos-proceso.search');

    // --- RUTAS PARA GESTORES Y ABOGADOS (Y ADMINS) ---
    Route::middleware('role:admin,gestor,abogado')->group(function() {
        Route::resource('casos', CasoController::class);

        // --- INICIO: Rutas CRUD Actuaciones para Casos ---
        Route::post('casos/{caso}/actuaciones', [CasoController::class, 'storeActuacion'])->name('casos.actuaciones.store');
        Route::put('casos/actuaciones/{actuacion}', [CasoController::class, 'updateActuacion'])->name('casos.actuaciones.update');
        Route::delete('casos/actuaciones/{actuacion}', [CasoController::class, 'destroyActuacion'])->name('casos.actuaciones.destroy');
        // --- FIN: Rutas CRUD Actuaciones para Casos ---

        Route::resource('personas', PersonaController::class);
        Route::resource('cooperativas', CooperativaController::class);
        Route::resource('plantillas', PlantillaDocumentoController::class)->except(['show', 'edit', 'update']);
        Route::post('/plantillas/{plantilla}/clonar', [PlantillaDocumentoController::class, 'clonar'])->name('plantillas.clonar');
        Route::get('casos/{caso}/clonar', [CasoController::class, 'clonar'])->name('casos.clonar');
        Route::post('casos/{caso}/documentos', [DocumentoCasoController::class, 'store'])->name('casos.documentos.store');
        Route::get('documentos-caso/{documento}/view', [DocumentoCasoController::class, 'view'])->name('documentos-caso.view');
        Route::delete('documentos-caso/{documento}', [DocumentoCasoController::class, 'destroy'])->name('documentos-caso.destroy');
        Route::post('/casos/{caso}/pagos', [PagoCasoController::class, 'store'])->name('casos.pagos.store');
        Route::get('/pagos/{pago}/comprobante', [PagoCasoController::class, 'verComprobante'])->name('pagos.verComprobante');
        Route::post('/casos/{caso}/notificaciones', [NotificacionController::class, 'storeManual'])->name('casos.notificaciones.store');
        Route::get('/documentos-generados', [DocumentoGeneradoController::class, 'index'])->name('documentos-generados.index');
        Route::post('/documentos/generar', [GeneradorDocumentoController::class, 'generar'])->name('documentos.generar');
        Route::get('/documentos/{documento}/descargar-docx', [GeneradorDocumentoController::class, 'descargarDocx'])->name('documentos.descargar.docx');
        Route::get('/documentos/{documento}/descargar-pdf', [GeneradorDocumentoController::class, 'descargarPdf'])->name('documentos.descargar.pdf');
        Route::get('/procesos-judiciales/{caso}/edit', [ProcesoJudicialController::class, 'edit'])->name('procesos-judiciales.edit');
        Route::put('/procesos-judiciales/{caso}', [ProcesoJudicialController::class, 'update'])->name('procesos-judiciales.update');
        Route::post('personas/{persona}/restore', [PersonaController::class, 'restore'])->name('personas.restore');

        // --- GESTIÓN > HONORARIOS ---
        Route::get('/gestion/honorarios', [ContratosController::class, 'index'])->name('gestion.honorarios.index');
        Route::prefix('/gestion/honorarios/contratos')->name('honorarios.contratos.')->group(function () {
            Route::get('/', [ContratosController::class, 'index'])->name('index');
            Route::get('/crear', [ContratosController::class, 'create'])->name('create');
            Route::post('/', [ContratosController::class, 'store'])->name('store');
            
            // Rutas específicas (deben ir ANTES de las rutas con parámetros dinámicos)
            Route::get('/pagos/{pago_id}/comprobante', [ContratosController::class, 'verComprobante'])->name('pagos.verComprobante');
            Route::get('/cargos/{cargo_id}/comprobante', [ContratosController::class, 'verCargoComprobante'])->name('cargos.verComprobante');
            
            // Rutas con parámetros dinámicos
            Route::get('/{id}', [ContratosController::class, 'show'])->name('show');
            Route::delete('/{id}', [ContratosController::class, 'destroy'])->name('destroy'); // --- Ruta Eliminar Contrato ---
            Route::get('/{id}/reestructurar', [ContratosController::class, 'reestructurar'])->name('reestructurar');
            Route::get('/archivado/{id}', [ContratosController::class, 'showArchivado'])->name('showArchivado');
            
            // Acciones sobre contratos
            Route::post('/{id}/pagar', [ContratosController::class, 'pagar'])->name('pagar');
            Route::post('/{id}/cargos', [ContratosController::class, 'agregarCargo'])->name('cargos.store');
            Route::post('/{contrato_id}/cargos/pagar', [ContratosController::class, 'pagarCargo'])->name('cargos.pagar');
            Route::post('/{id}/activar', [ContratosController::class, 'activar'])->name('activar');
            Route::post('/{id}/cerrar', [ContratosController::class, 'cerrar'])->name('cerrar');
            Route::post('/{id}/saldar', [ContratosController::class, 'saldarContrato'])->name('saldar');
            Route::post('/{id}/reabrir', [ContratosController::class, 'reabrir'])->name('reabrir');
            Route::post('/{id}/resolver-litis', [ContratosController::class, 'resolverLitis'])->name('resolverLitis');
            
            // Documentos del contrato - CORREGIDO
            Route::post('/{id}/documento', [ContratosController::class, 'subirDocumento'])->name('documento.subir');
            Route::get('/{id}/documento', [ContratosController::class, 'verDocumento'])->name('documento.ver');
            Route::delete('/{id}/documento', [ContratosController::class, 'eliminarDocumento'])->name('documento.eliminar');
            
            // PDFs
            Route::get('/{id}/pdf/contrato', [ContratosController::class, 'pdfContrato'])->name('pdf.contrato');
            Route::get('/{id}/pdf/liquidacion', [ContratosController::class, 'pdfLiquidacion'])->name('pdf.liquidacion');

            // --- INICIO: Rutas CRUD Actuaciones ---
            Route::post('/{id}/actuaciones', [ContratosController::class, 'storeActuacion'])->name('actuaciones.store');
            // Usar un prefijo /actuaciones/ para las rutas de update y destroy
            Route::put('/actuaciones/{actuacion}', [ContratosController::class, 'updateActuacion'])->name('actuaciones.update');
            Route::delete('/actuaciones/{actuacion}', [ContratosController::class, 'destroyActuacion'])->name('actuaciones.destroy');
            // --- FIN: Rutas CRUD Actuaciones ---
        });
    });

    // --- RUTAS DE ADMINISTRACIÓN ---
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class);
        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::resource('tokens', IntegracionTokenController::class);
        Route::resource('reglas-alerta', ReglaAlertaController::class)->only(['index', 'store', 'destroy']);
        Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
        Route::get('/analitica', AnaliticaController::class)->name('analitica.index');
        Route::get('juridico/indicadores', IndicadoresController::class)->name('juridico.indicadores');
        Route::get('incidentes-juridicos/exportar', [IncidenteJuridicoController::class, 'export'])->name('incidentes-juridicos.exportar');
        Route::resource('incidentes-juridicos', IncidenteJuridicoController::class)->parameters(['incidentes-juridicos' => 'incidente']);
        Route::post('incidentes-juridicos/{incidente}/tickets', [TicketDisciplinarioController::class, 'store'])->name('incidentes-juridicos.tickets.store');
        Route::post('incidentes-juridicos/{incidente}/archivos', [ArchivoIncidenteController::class, 'store'])->name('incidentes-juridicos.archivos.store');
        Route::get('archivos-incidente/{archivo}/descargar', [ArchivoIncidenteController::class, 'descargar'])->name('archivos-incidente.descargar');
        Route::post('tickets-disciplinarios/{ticket}/decision', [DecisionComiteEticaController::class, 'store'])->name('tickets-disciplinarios.decision.store');
        Route::get('/tasas-interes', [TasasInteresController::class, 'index'])->name('tasas.index');
        Route::post('/tasas-interes', [TasasInteresController::class, 'store'])->name('tasas.store');
        Route::delete('/tasas-interes/{tasa_id}', [TasasInteresController::class, 'destroy'])->name('tasas.destroy');
        Route::get('/gestores', [GestoresController::class, 'index'])->name('gestores.index');
        Route::post('users/{user}/upload-card', [UserController::class, 'uploadProfessionalCard'])->name('users.upload.card');
        // Rutas para documentos generales del usuario
        Route::post('users/{user}/documents', [UserController::class, 'storeDocument'])->name('users.documents.store');
        Route::delete('users/documents/{document}', [UserController::class, 'destroyDocument'])->name('users.documents.destroy');
       });

    Route::middleware(['role:admin'])->group(function() {
        Route::get('requisitos', [RequisitoDocumentoController::class, 'index'])->name('requisitos.index');
        Route::post('requisitos', [RequisitoDocumentoController::class, 'store'])->name('requisitos.store');
        Route::delete('requisitos/{requisito}', [RequisitoDocumentoController::class, 'destroy'])->name('requisitos.destroy');
        Route::get('integraciones', [IntegracionController::class, 'index'])->name('integraciones.index');
        Route::get('integraciones/exportar', [IntegracionController::class, 'export'])->name('integraciones.exportar');
    });

    // --- RUTAS DE API PARA ALERTAS ---
    Route::prefix('api/alertas')->name('api.alertas.')->group(function () {
        Route::get('preventivas',   [AlertasController::class, 'preventivas'])->name('preventivas');
        Route::get('reactivas',     [AlertasController::class, 'reactivas'])->name('reactivas');
        Route::get('hallazgos',     [AlertasController::class, 'hallazgos'])->name('hallazgos');
        Route::get('reporte-cliente', [AlertasController::class, 'reporteCliente'])->name('reporte-cliente');
        Route::get('vigilancia-judicial', [AlertasController::class, 'vigilanciaJudicial'])->name('vigilancia-judicial');
        // Exportaciones
        Route::get('preventivas/export',   [AlertasController::class, 'exportPreventivas'])->name('preventivas.export');
        Route::get('reactivas/export',     [AlertasController::class, 'exportReactivas'])->name('reactivas.export');
        Route::get('hallazgos/export',     [AlertasController::class, 'exportHallazgos'])->name('hallazgos.export');
        Route::get('reporte-cliente/export', [AlertasController::class, 'exportReporteCliente'])->name('reporte-cliente.export');
        Route::get('vigilancia-judicial/export', [AlertasController::class, 'exportVigilanciaJudicial'])->name('vigilancia-judicial.export');
    });

    // =================================================================
    // ===== INICIO: RUTAS DEL CHATBOT AÑADIDAS AQUÍ ===================
    // =================================================================
    Route::post('/chatbot/send', function (Request $request) {
        $validated = $request->validate([
            'message' => 'required|string|max:4096',
        ]);
        
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        // Guardar mensaje del usuario
        $userMessage = Message::create([
            'user_id' => $user->id,
            'body' => $validated['message'],
        ]);
        
        // Enviar a n8n
        try {
            Http::post('https://cobrocartera-n8n.hrymiz.easypanel.host/webhook/messages-customers', [
                'message' => $userMessage->body,
                'userId' => $user->id,
            ]);
        } catch (\Exception $e) {
            \Log::error('[Chatbot] Error enviando a n8n: ' . $e->getMessage());
        }
        
        return response()->json($userMessage);
    })->name('chatbot.send');
    // =================================================================
    // ===== FIN: RUTA DE ENVÍO DEL CHATBOT ============================
    // =================================================================

}); // Fin del grupo principal de middleware

// =================================================================
// ===== RUTA PÚBLICA PARA NOTIFICACIONES DE N8N =====================
// =================================================================
// Route::post('/chatbot/notify', function (Request $request) {
//     $validated = $request->validate([
//         'response' => 'required|string',
//         'userId' => 'required|integer',
//     ]);

//     $botMessage = Message::create([
//         'user_id' => 0,
//         'body' => $validated['response']
//     ]);

//     broadcast(new ChatbotResponseReceived(
//         $botMessage->body,
//         $validated['userId']
//     ));

//     return response()->json(['status' => 'notification_sent_to_user']);
// })->name('chatbot.notify');


// Radicados - Rutas que necesitan estar fuera del middleware principal
Route::model('proceso', ProcesoRadicado::class);

// Rutas de descarga de documentos (requieren autenticación)
Route::get('documentos-proceso/{documento}', [DocumentoProcesoController::class, 'show'])
    ->middleware('auth')->name('documentos-proceso.show');
Route::get('documentos-proceso/{documento}/ver', [DocumentoProcesoController::class, 'view'])
    ->middleware('auth')->name('documentos-proceso.view');
Route::get('documentos-proceso/{documento}/descargar', [DocumentoProcesoController::class, 'download'])
    ->middleware('auth')->name('documentos-proceso.download');

// Archivo de rutas de autenticación (login, registro, etc.)
require __DIR__.'/auth.php';

