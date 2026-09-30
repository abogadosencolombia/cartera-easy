<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AbogadosBotController;
Route::get('/abogados-bot',[AbogadosBotController::class,'dashboard'])->middleware(['auth','role:admin']);
Route::post('/abogados-bot/google/connect',[AbogadosBotController::class,'googleStart'])->middleware(['auth','role:admin']);
Route::get('/abogados-bot/google/connect',[AbogadosBotController::class,'googleStart'])->middleware(['auth','role:admin']);
Route::get('/abogados-bot/google/callback',[AbogadosBotController::class,'googleCallback'])->middleware(['auth','role:admin']);
