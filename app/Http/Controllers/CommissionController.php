<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\Appointment;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $query = Commission::with(['barber', 'appointment.client']);

        if ($request->barber_id) {
            $query->where('barber_id', $request->barber_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $commissions = $query->orderByDesc('created_at')->get();

        return response()->json([
            'success'          => true,
            'commissions'      => $commissions,
            'total_barber'     => $commissions->sum('barber_amount'),
            'total_barbershop' => $commissions->sum('barbershop_amount'),
        ]);
    }

    public function generate(Appointment $appointment)
    {
        if (!$appointment->barber_id || !$appointment->price) {
            return response()->json([
                'success' => false,
                'error'   => 'Agendamento precisa ter barbeiro e preço definidos.',
            ], 422);
        }

        if ($appointment->commission) {
            return response()->json([
                'success' => false,
                'error'   => 'Comissão já gerada para este agendamento.',
            ], 422);
        }

        $barber           = $appointment->barber;
        $rate             = $barber->commission_rate;
        $barberAmount     = round($appointment->price * ($rate / 100), 2);
        $barbershopAmount = round($appointment->price - $barberAmount, 2);

        $commission = Commission::create([
            'appointment_id'    => $appointment->id,
            'barber_id'         => $barber->id,
            'service_price'     => $appointment->price,
            'commission_rate'   => $rate,
            'barber_amount'     => $barberAmount,
            'barbershop_amount' => $barbershopAmount,
            'status'            => 'pending',
        ]);

        return response()->json([
            'success'    => true,
            'commission' => $commission->load('barber', 'appointment.client'),
        ]);
    }

    public function markAsPaid(Commission $commission)
    {
        $commission->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        return response()->json([
            'success'    => true,
            'commission' => $commission,
        ]);
    }
}