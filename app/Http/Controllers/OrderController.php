<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Commission;
use App\Models\Appointment;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    // Lista todas as comandas
    public function index(Request $request)
    {
        $query = Order::with(['barber', 'client', 'items']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->barber_id) {
            $query->where('barber_id', $request->barber_id);
        }

        return response()->json([
            'success' => true,
            'orders'  => $query->orderByDesc('created_at')->get(),
        ]);
    }

    // Cria uma comanda para um agendamento
    public function store(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
            'barber_id'      => 'required|exists:barbers,id',
            'client_id'      => 'required|exists:clients,id',
            'notes'          => 'nullable|string',
        ]);

        $existing = Order::where('appointment_id', $request->appointment_id)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'error'   => 'Já existe uma comanda para este agendamento.',
            ], 422);
        }

        $order = Order::create([
            'appointment_id' => $request->appointment_id,
            'barber_id'      => $request->barber_id,
            'client_id'      => $request->client_id,
            'status'         => 'open',
            'total'          => 0,
            'notes'          => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'order'   => $order->load('barber', 'client', 'items'),
        ]);
    }

    // Mostra uma comanda
    public function show(Order $order)
    {
        return response()->json([
            'success' => true,
            'order'   => $order->load('barber', 'client', 'items', 'appointment'),
        ]);
    }

    // Adiciona item na comanda
    public function addItem(Request $request, Order $order)
    {
        $request->validate([
            'name'     => 'required|string',
            'type'     => 'required|in:service,product',
            'price'    => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($order->status === 'closed') {
            return response()->json([
                'success' => false,
                'error'   => 'Comanda já fechada.',
            ], 422);
        }

        $subtotal = $request->price * $request->quantity;

        $item = OrderItem::create([
            'order_id' => $order->id,
            'name'     => $request->name,
            'type'     => $request->type,
            'price'    => $request->price,
            'quantity' => $request->quantity,
            'subtotal' => $subtotal,
        ]);

        // Atualiza o total da comanda
        $order->total = $order->items()->sum('subtotal');
        $order->save();

        return response()->json([
            'success' => true,
            'item'    => $item,
            'total'   => $order->total,
        ]);
    }

    // Remove item da comanda
    public function removeItem(Order $order, OrderItem $item)
    {
        if ($order->status === 'closed') {
            return response()->json([
                'success' => false,
                'error'   => 'Comanda já fechada.',
            ], 422);
        }

        $item->delete();

        $order->total = $order->items()->sum('subtotal');
        $order->save();

        return response()->json([
            'success' => true,
            'total'   => $order->total,
        ]);
    }

    // Fecha a comanda e gera comissão automaticamente
    public function close(Order $order)
    {
        if ($order->status === 'closed') {
            return response()->json([
                'success' => false,
                'error'   => 'Comanda já fechada.',
            ], 422);
        }

        $order->update([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);

        // Gera comissão automaticamente
        $barber           = $order->barber;
        $rate             = $barber->commission_rate;
        $barberAmount     = round($order->total * ($rate / 100), 2);
        $barbershopAmount = round($order->total - $barberAmount, 2);

        Commission::create([
            'appointment_id'    => $order->appointment_id,
            'barber_id'         => $barber->id,
            'service_price'     => $order->total,
            'commission_rate'   => $rate,
            'barber_amount'     => $barberAmount,
            'barbershop_amount' => $barbershopAmount,
            'status'            => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'order'   => $order->load('barber', 'client', 'items'),
        ]);
    }
}