<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AlertasController;

Route::middleware(['auth','verified'])
    ->prefix('api/alertas')
    ->name('api.alertas.')
    ->group(function () {
        Route::get('preventivas',         [AlertasController::class, 'preventivas'])->name('preventivas');
        Route::get('reactivas',           [AlertasController::class, 'reactivas'])->name('reactivas');
        Route::get('hallazgos',           [AlertasController::class, 'hallazgos'])->name('hallazgos');
        Route::get('reporte-cliente',     [AlertasController::class, 'reporteCliente'])->name('reporte-cliente');
        Route::get('vigilancia-judicial', [AlertasController::class, 'vigilanciaJudicial'])->name('vigilancia-judicial');

        // Export
        Route::get('preventivas/export',         [AlertasController::class, 'exportPreventivas'])->name('preventivas.export');
        Route::get('reactivas/export',           [AlertasController::class, 'exportReactivas'])->name('reactivas.export');
        Route::get('hallazgos/export',           [AlertasController::class, 'exportHallazgos'])->name('hallazgos.export');
        Route::get('reporte-cliente/export',     [AlertasController::class, 'exportReporteCliente'])->name('reporte-cliente.export');
        Route::get('vigilancia-judicial/export', [AlertasController::class, 'exportVigilanciaJudicial'])->name('vigilancia-judicial.export');
    });