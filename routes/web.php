<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Http\Request;

// --- CONTROLADORES ---
use App\Http\Controllers\ChatwootProxyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\CasoController;
use App\Http\Controllers\CooperativaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\TareaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RevisionDiariaController;
use App\Http\Controllers\PlantillaDocumentoController;
use App\Http\Controllers\RequisitoDocumentoController;
use App\Http\Controllers\Admin\IntegracionTokenController;
use App\Http\Controllers\Admin\GestoresController;
use App\Http\Controllers\Admin\TasasInteresController;
use App\Http\Controllers\Admin\ReglaAlertaController;
use App\Http\Controllers\Gestion\Honorarios\ContratosController;
use App\Http\Controllers\ProcesoRadicadoController;
use App\Http\Controllers\JuzgadoController;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| 🛡️ CHATWOOT PROXY (MÁXIMA PRIORIDAD)
|--------------------------------------------------------------------------
*/
// Estas rutas deben ir ANTES que cualquier otra cosa para evitar el 404
Route::any('app/{any?}', [ChatwootProxyController::class, 'proxy'])->where('any', '.*')->middleware('web');
Route::any('api/v1/{any?}', [ChatwootProxyController::class, 'proxy'])->where('any', '.*')->middleware('web');
Route::any('auth/{any?}', [ChatwootProxyController::class, 'proxy'])->where('any', '.*')->middleware('web');
Route::any('rails/{any?}', [ChatwootProxyController::class, 'proxy'])->where('any', '.*')->middleware('web');

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Broadcast::routes(['middleware' => ['web', 'auth']]);

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('api/gestion-diaria')->name('api.gestion.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\GestionDiariaController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Api\GestionDiariaController::class, 'store'])->name('store');
        Route::patch('/{id}/complete', [\App\Http\Controllers\Api\GestionDiariaController::class, 'complete'])->name('complete');
        Route::delete('/{id}', [\App\Http\Controllers\Api\GestionDiariaController::class, 'destroy'])->name('destroy');
        Route::get('/search-despacho', [\App\Http\Controllers\Api\GestionDiariaController::class, 'searchDespacho'])->name('search-despacho');
        Route::get('/search-vinculacion', [\App\Http\Controllers\Api\GestionDiariaController::class, 'searchVinculacion'])->name('search-vinculacion');
        Route::get('/archivo/{id}', [\App\Http\Controllers\Api\GestionDiariaController::class, 'downloadArchivo'])->name('download-archivo');
        Route::get('/archivo/{id}/view', [\App\Http\Controllers\Api\GestionDiariaController::class, 'viewArchivo'])->name('view-archivo');
    });

    Route::resource('cooperativas', CooperativaController::class);
    Route::get('/personas/exportar-excel', [PersonaController::class, 'exportExcel'])->name('personas.export.excel');
    Route::resource('personas', PersonaController::class);
    Route::post('/personas/{persona}/documentos', [PersonaController::class, 'uploadDocument'])->name('personas.upload_document');
    Route::get('procesos/exportar', [ProcesoRadicadoController::class, 'exportarExcel'])->name('procesos.exportar');
    Route::get('procesos/import', [ProcesoRadicadoController::class, 'showImportForm'])->name('procesos.import');
    Route::post('procesos/import', [ProcesoRadicadoController::class, 'handleImport'])->name('procesos.import.store');
    Route::resource('procesos', ProcesoRadicadoController::class);
    Route::get('casos/exportar', [CasoController::class, 'export'])->name('casos.export');
    Route::resource('casos', CasoController::class);
    Route::get('/gestion/honorarios', [ContratosController::class, 'index'])->name('gestion.honorarios.index');
    Route::prefix('/gestion/honorarios/contratos')->name('honorarios.contratos.')->group(function () {
        Route::get('/', [ContratosController::class, 'index'])->name('index');
        Route::get('/crear', [ContratosController::class, 'create'])->name('create');
        Route::post('/', [ContratosController::class, 'store'])->name('store');
        Route::get('/{id}', [ContratosController::class, 'show'])->name('show');
        Route::delete('/{id}', [ContratosController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class);
        Route::resource('tokens', IntegracionTokenController::class);
        Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
        Route::get('/tareas', [TareaController::class, 'index'])->name('tareas.index');
        Route::get('/gestores', [GestoresController::class, 'index'])->name('gestores.index');
        Route::get('/tasas-interes', [TasasInteresController::class, 'index'])->name('tasas.index');
        Route::get('/reglas-alerta', [ReglaAlertaController::class, 'index'])->name('reglas-alerta.index');
    });

    Route::middleware('juzgado.access')->group(function () {
        Route::get('juzgados', [JuzgadoController::class, 'index'])->name('juzgados.index');
    });
    
    Route::resource('plantillas', PlantillaDocumentoController::class);
    Route::resource('requisitos', RequisitoDocumentoController::class);
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/revision-diaria', [RevisionDiariaController::class, 'index'])->name('revision.index');
});

require __DIR__.'/api_alertas.php';
require __DIR__.'/auth.php';

Route::get('/csrf-token', function (Request $request) {
    return response()->json(['csrf_token' => csrf_token()]);
})->middleware('web');

// Resto de fallbacks de Chatwoot al final
Route::any('assets/{any?}', [ChatwootProxyController::class, 'proxy'])->where('any', '.*')->middleware('web');
Route::any('brand-assets/{any?}', [ChatwootProxyController::class, 'proxy'])->where('any', '.*')->middleware('web');
Route::any('vite/{any?}', [ChatwootProxyController::class, 'proxy'])->where('any', '.*')->middleware('web');
Route::any('packs/{any?}', [ChatwootProxyController::class, 'proxy'])->where('any', '.*')->middleware('web');
Route::any('storage/{any?}', [ChatwootProxyController::class, 'proxy'])->where('any', '.*')->middleware('web');
Route::any('sw.js', [ChatwootProxyController::class, 'proxy'])->middleware('web');
Route::any('chatwoot-sw.js', [ChatwootProxyController::class, 'proxy'])->middleware('web');
Route::any('manifest.json', [ChatwootProxyController::class, 'proxy'])->middleware('web');
Route::any('cable/{any?}', [ChatwootProxyController::class, 'proxy'])->where('any', '.*')->middleware('web');
