<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BarberController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\BookingController;

// Rotas públicas de agendamento
Route::get('/booking/{slug}', [BookingController::class, 'show']);
Route::get('/booking/{slug}/availability', [BookingController::class, 'availability']);
Route::post('/booking/{slug}', [BookingController::class, 'store']);

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
    Route::get('/admin/barbershops', [AuthController::class, 'listBarbershops']);
    Route::post('/admin/barbershops/{id}/toggle-block', [AuthController::class, 'toggleBlock']);
    Route::post('/admin/barbershops/{id}/extend-trial', [AuthController::class, 'extendTrial']);

    // Barbearia do usuário logado
    Route::get('/my-barbershop', function(Request $request) {
        $barbershop = \App\Models\Barbershop::with('barbers')
            ->find($request->user()->barbershop_id);
        return response()->json($barbershop);
    });

    Route::put('/my-barbershop', function(Request $request) {
        $barbershop = \App\Models\Barbershop::find($request->user()->barbershop_id);
        $barbershop->update($request->only([
            'name', 'phone', 'address', 'description',
            'opening_time', 'closing_time', 'logo'
        ]));
        return response()->json($barbershop);
    });

    // Atualizar QR Pix de um barbeiro
    Route::put('/barbers/{barber}/pix', function(Request $request, $barber) {
        $barbershop = \App\Models\Barbershop::find($request->user()->barbershop_id);
        $barber = \App\Models\Barber::where('id', $barber)
            ->where('barbershop_id', $barbershop->id)
            ->firstOrFail();

        $data = [];
        if ($request->has('pix_qr')) $data['pix_qr'] = $request->input('pix_qr');
        if ($request->has('pix_key')) $data['pix_key'] = $request->input('pix_key');
        if (!empty($data)) $barber->update($data);

        return response()->json($barber);
    });

    // Serviços
    Route::get('/services', [ServiceController::class, 'index']);
    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{service}', [ServiceController::class, 'update']);
    Route::delete('/services/{service}', [ServiceController::class, 'destroy']);

    // Registro de vendedor
    Route::post('/register/vendor', [AuthController::class, 'registerVendor']);

    // WhatsApp
    Route::get('/whatsapp/status', [WhatsAppController::class, 'status']);
    Route::get('/whatsapp/connect', [WhatsAppController::class, 'connect']);
    Route::delete('/whatsapp/disconnect', [WhatsAppController::class, 'disconnect']);
    Route::get('/whatsapp/debug', [WhatsAppController::class, 'debug']);
    Route::post('/whatsapp/debug', [WhatsAppController::class, 'debug']);

    // Relatórios
    Route::get('/reports', [ReportController::class, 'index']);

    // Comissões
    Route::get('/commissions', [CommissionController::class, 'index']);
    Route::post('/commissions/generate/{appointment}', [CommissionController::class, 'generate']);
    Route::patch('/commissions/{commission}/pay', [CommissionController::class, 'markAsPaid']);

    // Comandas
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/items', [OrderController::class, 'addItem']);
    Route::delete('/orders/{order}/items/{item}', [OrderController::class, 'removeItem']);
    Route::patch('/orders/{order}/close', [OrderController::class, 'close']);

    // Produtos
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::patch('/products/{product}/stock', [ProductController::class, 'adjustStock']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    //cancelamento de agendamento
    Route::get('/cancel/{token}', [BookingController::class, 'cancelShow']);
    Route::post('/cancel/{token}', [BookingController::class, 'cancelStore']);
});