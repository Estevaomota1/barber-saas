<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BarberController;

Route::get('/health', fn() => response()->json(['status' => 'ok']));
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('barbers', BarberController::class);
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('appointments', AppointmentController::class);
    Route::patch('appointments/{id}/status', [AppointmentController::class, 'updateStatus']);

});