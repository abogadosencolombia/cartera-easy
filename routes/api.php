<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AlertasController;

// --- NUEVAS IMPORTACIONES PARA EL CHATBOT ---
use App\Events\ChatbotResponseReceived;
use App\Models\Message;


// =================================================================
// ===== GRUPO DE RUTAS PROTEGIDAS POR AUTENTICACIÓN (SANCTUM) =====
// =================================================================
Route::middleware('auth:sanctum')->group(function () {
    
    // --- Tus rutas de Alertas existentes ---
    Route::prefix('alertas')
        ->name('api.alertas.')
        ->group(function () {
            Route::get('/preventivas',       [AlertasController::class, 'preventivas'])->name('preventivas');
            Route::get('/reactivas',         [AlertasController::class, 'reactivas'])->name('reactivas');
            Route::get('/hallazgos',         [AlertasController::class, 'hallazgos'])->name('hallazgos');
            Route::get('/reporte-cliente',   [AlertasController::class, 'reporteCliente'])->name('reporte-cliente');
            Route::get('/vigilancia-judicial', [AlertasController::class, 'vigilanciaJudicial'])->name('vigilancia-judicial');
            Route::get('/preventivas/export',     [AlertasController::class, 'exportPreventivas'])->name('preventivas.export');
            Route::get('/reactivas/export',       [AlertasController::class, 'exportReactivas'])->name('reactivas.export');
            Route::get('/hallazgos/export',       [AlertasController::class, 'exportHallazgos'])->name('hallazgos.export');
            Route::get('/reporte-cliente/export', [AlertasController::class, 'exportReporteCliente'])->name('reporte-cliente.export');
            Route::get('/vigilancia-judicial/export', [AlertasController::class, 'exportVigilanciaJudicial'])->name('vigilancia-judicial.export');
        });

    // La ruta /chatbot/send se queda en web.php para usar la sesión del navegador.

});


// =================================================================
// ===== GRUPO DE RUTAS PÚBLICAS (SIN AUTENTICACIÓN) ==============
// =================================================================

// --- Ruta de salud que ya tenías ---
Route::get('/health', fn () => [
    'ok'   => true,
    'time' => now()->toDateTimeString(),
]);

// --- RUTA DEL CHATBOT (para recibir notificaciones de n8n) ---
// Esta ruta no necesita protección CSRF porque es una API externa la que la llama.
Route::post('/chatbot/notify', function (Request $request) {
    $validated = $request->validate([
        'response' => 'required|string',
        'userId' => 'required|integer',
    ]);

    $botMessage = Message::create([
        'user_id' => 14, // <-- USA EL ID QUE TE DIO TINKER
        'body' => $validated['response']
    ]);

    broadcast(new ChatbotResponseReceived(
        $botMessage->body,
        $validated['userId']
    ));

    return response()->json(['status' => 'notification_sent_to_user']);
});