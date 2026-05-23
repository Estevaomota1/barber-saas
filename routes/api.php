<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BarberController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\CommissionController;

Route::get('/test-vivo', function() {
    return "SISTEMA VIVO";
});
Route::get('/health', fn() => response()->json(['status' => 'ok']));
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/whatsapp/webhook', [WhatsAppController::class, 'webhook']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('barbers', BarberController::class);
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('appointments', AppointmentController::class);
    Route::patch('appointments/{id}/status', [AppointmentController::class, 'updateStatus']);

    // WhatsApp
    Route::get('/whatsapp/status', [WhatsAppController::class, 'status']);
    Route::get('/whatsapp/connect', [WhatsAppController::class, 'connect']);
    Route::delete('/whatsapp/disconnect', [WhatsAppController::class, 'disconnect']);
    Route::get('/whatsapp/debug', [WhatsAppController::class, 'debug']);
    Route::post('/whatsapp/debug', [WhatsAppController::class, 'debug']);

    // Comissões
    Route::get('/commissions', [CommissionController::class, 'index']);
    Route::post('/commissions/generate/{appointment}', [CommissionController::class, 'generate']);
    Route::patch('/commissions/{commission}/pay', [CommissionController::class, 'markAsPaid']);
});