<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AbogadosBotController;
Route::post('/abogados-bot/webhook',[AbogadosBotController::class,'receive']);
