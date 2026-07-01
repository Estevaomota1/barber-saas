<?php
 
namespace App\Http\Controllers;
 
use App\Models\Barbershop;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
 
class BookingController extends Controller
{
    public function show($slug)
    {
        try {
            $barbershop = Barbershop::where('slug', $slug)
                ->with(['services' => function($q) {
                    $q->where('active', true)->orderBy('name');
                }, 'barbers'])
                ->firstOrFail();
 
            return response()->json([
                'success'    => true,
                'barbershop' => $barbershop,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
        public function myAppointments(Request $request, $slug)
        {
            try {
                                $request->validate([
                    'client_phone' => 'required|string',
                    'client_name'  => 'nullable|string',
                ]);
                $barbershop = Barbershop::where('slug', $slug)->firstOrFail();

                $appointments = Appointment::where('barbershop_id', $barbershop->id)
                    ->where('client_phone', $request->client_phone)
                    ->when($request->client_name, function ($query) use ($request) {
                    return $query->where('client_name', 'ILIKE', '%' . $request->client_name . '%');
                    })
                    ->whereIn('status', ['pending', 'confirmed'])// apenas ativos
                    ->with(['barber', 'service'])
                    ->orderBy('appointment_date', 'asc')
                    ->get();

                return response()->json([
                    'success'      => true,
                    'appointments' => $appointments,
                ]);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }
    public function availability(Request $request, $slug)
{
    try {
        $request->validate([
            'barber_id' => 'required|exists:barbers,id',
            'date'      => 'required|date',
            'duration'  => 'required|integer|min:1',
        ]);

        $barbershop = Barbershop::where('slug', $slug)->firstOrFail();

        $date = Carbon::parse($request->date);

        $dayName = strtolower($date->format('l'));
        $workingHours = $barbershop->working_hours ?? [];

        $workingDays = $workingHours['working_days'] ?? [];

        $dayConfig = $workingHours[$dayName] ?? null;

        // verifica se o dia está permitido
        if (!in_array($dayName, $workingDays) && !isset($dayConfig)) {
            return response()->json([
                'success' => true,
                'available' => [],
            ]);
        }

        $opening = $dayConfig['open'] ?? $workingHours['open'] ?? $barbershop->opening_time ?? '09:00';
        $closing = $dayConfig['close'] ?? $workingHours['close'] ?? $barbershop->closing_time ?? '18:00';
        $duration = (int) $request->duration;

        $slots   = [];
        $current = Carbon::parse($date->format('Y-m-d') . ' ' . $opening);
        $end     = Carbon::parse($date->format('Y-m-d') . ' ' . $closing);

        while ($current->copy()->addMinutes($duration)->lte($end)) {
            $slots[] = $current->format('H:i');
            $current->addMinutes($duration);
        }

        // Horários já ocupados por agendamentos
        $booked = Appointment::where('barber_id', $request->barber_id)
            ->whereDate('appointment_date', $date->format('Y-m-d'))
            ->whereNotIn('status', ['cancelled'])
            ->pluck('appointment_date')
            ->map(fn($d) => Carbon::parse($d)->format('H:i'))
            ->toArray();

        // Bloqueios manuais do barbeiro (pontuais ou recorrentes)
        $blocks = \App\Models\BarberBlock::where('barber_id', $request->barber_id)
            ->where(function ($q) use ($date, $dayName) {
                $q->whereDate('date', $date->format('Y-m-d'))
                  ->orWhere('day_of_week', $dayName);
            })
            ->get();

        $isBlocked = function ($slotTime) use ($blocks, $duration, $date) {
            $slotStart = Carbon::parse($date->format('Y-m-d') . ' ' . $slotTime);
            $slotEnd   = $slotStart->copy()->addMinutes($duration);

            foreach ($blocks as $block) {
                $blockStart = Carbon::parse($date->format('Y-m-d') . ' ' . $block->start_time);
                $blockEnd   = Carbon::parse($date->format('Y-m-d') . ' ' . $block->end_time);

                if ($slotStart->lt($blockEnd) && $slotEnd->gt($blockStart)) {
                    return true;
                }
            }
            return false;
        };

        $available = array_values(array_filter(
            $slots,
            fn($s) => !in_array($s, $booked) && !$isBlocked($s)
        ));

        return response()->json([
            'success'   => true,
            'available' => $available,
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()], 500);
    }
}
 
    public function store(Request $request, $slug)
{
    try {
        $request->validate([
            'service_id'   => 'required|exists:services,id',
            'barber_id'    => 'required|exists:barbers,id',
            'date'         => 'required|date',
            'time'         => 'required|string',
            'client_name'  => 'required|string',
            'client_phone' => 'required|string',
        ]);

        $barbershop      = Barbershop::where('slug', $slug)->firstOrFail();
        $service         = \App\Models\Service::findOrFail($request->service_id);
        $appointmentDate = Carbon::parse($request->date . ' ' . $request->time);

        // NOVO: impede que dois clientes marquem o mesmo horário
        $exists = Appointment::where('barber_id', $request->barber_id)
            ->where('appointment_date', $appointmentDate)
            ->whereNotIn('status', ['cancelled'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'error'   => 'Este horário acabou de ser reservado por outra pessoa. Escolha outro horário.',
            ], 422);
        }

        $appointment = Appointment::create([
            'barbershop_id'    => $barbershop->id,
            'service_id'       => $request->service_id,
            'barber_id'        => $request->barber_id,
            'appointment_date' => $appointmentDate,
            'status'           => 'pending',
            'price'            => $service->price,
            'service_name'     => $service->name,
            'client_name'      => $request->client_name,
            'client_phone'     => $request->client_phone,
            'cancel_token'     => Str::uuid(),
        ]);

        return response()->json([
            'success'      => true,
            'appointment'  => $appointment,
            'cancel_token' => $appointment->cancel_token,
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()], 500);
    }
}
 
    // GET /api/cancel/{token} — busca o agendamento pelo token
    public function cancelShow($token)  
    {
        try {
            $appointment = Appointment::where('cancel_token', $token)
                ->with(['barber', 'service'])
                ->firstOrFail();
 
            if ($appointment->status === 'cancelled') {
                return response()->json(['success' => false, 'error' => 'Este agendamento já foi cancelado.'], 400);
            }
 
            return response()->json([
                'success'     => true,
                'appointment' => [
                    'id'               => $appointment->id,
                    'client_name'      => $appointment->client_name,
                    'service_name'     => $appointment->service?->name ?? $appointment->service_name,
                    'barber_name'      => $appointment->barber?->name,
                    'appointment_date' => $appointment->appointment_date,
                    'status'           => $appointment->status,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Agendamento não encontrado.'], 404);
        }
    }
 
    // POST /api/cancel/{token} — executa o cancelamento
    public function cancelStore(Request $request, $token)
    {
        try {
            $appointment = Appointment::where('cancel_token', $token)->firstOrFail();
 
            if ($appointment->status === 'cancelled') {
                return response()->json(['success' => false, 'error' => 'Este agendamento já foi cancelado.'], 400);
            }
 
            $appointment->update([
                'status'        => 'cancelled',
                'cancelled_at'  => Carbon::now(),
                'cancel_reason' => $request->input('reason'),
            ]);
 
            return response()->json(['success' => true, 'message' => 'Agendamento cancelado com sucesso.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Agendamento não encontrado.'], 404);
        }
    }
}