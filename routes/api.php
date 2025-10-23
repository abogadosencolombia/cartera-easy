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
    \Log::info('[Chatbot Notify] Datos recibidos de n8n:', $request->all());
    
    $validated = $request->validate([
        'message' => 'required_without:response|string',
        'response' => 'required_without:message|string',
        'userId' => 'required|integer',
    ]);

    // Acepta tanto 'message' como 'response'
    $messageBody = $validated['response'] ?? $validated['message'];

    // ✅ Crear mensaje del bot
    $botMessage = Message::create([
        'user_id' => null, 
        'body' => $messageBody
    ]);

    \Log::info('[Chatbot Notify] Broadcasting mensaje:', [
        'message_id' => $botMessage->id,
        'user_id' => $validated['userId'],
        'body' => $messageBody
    ]);

    // ✅ Broadcast al usuario específico
    try {
        broadcast(new ChatbotResponseReceived(
            $botMessage->body,
            $validated['userId']
        ));
        \Log::info('[Chatbot Notify] ✅ Broadcast enviado exitosamente');
    } catch (\Exception $e) {
        \Log::error('[Chatbot Notify] ❌ Error en broadcast: ' . $e->getMessage());
    }

    return response()->json([
        'status' => 'success',
        'message_id' => $botMessage->id
    ]);
});