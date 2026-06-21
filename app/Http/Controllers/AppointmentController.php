<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $barbershopId = $request->user()->barbershop_id;

            $query = Appointment::where(function($q) use ($barbershopId) {
                $q->where('barbershop_id', $barbershopId)
                  ->orWhereHas('client', fn($q) => $q->where('barbershop_id', $barbershopId));
            })->with(['client', 'barber', 'service']);

            if ($request->has('date')) {
                $query->whereDate('appointment_date', $request->date);
            }

            $appointments = $query->orderByDesc('appointment_date')->paginate(15);

            return response()->json([
                'message' => 'Agendamentos listados com sucesso',
                'data'    => $appointments,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao listar agendamentos', 'error' => $e->getMessage()], 500);
        }
    }

   public function store(Request $request)
    {
        try {
            $request->validate([
                'barber_id' => 'required|exists:barbers,id',
                'service_id' => 'required|exists:services,id',
                'date' => 'required|date',
                'time' => 'required',
                'barbershop_id' => 'nullable|exists:barbershops,id'
            ]);

            // Tenta identificar a barbearia
            $barbershopId = $request->barbershop_id;
            if (!$barbershopId && $request->user() && $request->user()->barbershop) {
                $barbershopId = $request->user()->barbershop->id;
            }

            if (!$barbershopId) {
                return response()->json(['message' => 'Barbearia não identificada.'], 422);
            }

            // Verificar se já existe agendamento
            $exists = Appointment::where('barber_id', $request->barber_id)
                ->where('date', $request->date)
                ->where('time', $request->time)
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'Horário já ocupado'], 422);
            }

            $appointment = Appointment::create([
                'barbershop_id' => $barbershopId,
                'barber_id' => $request->barber_id,
                'service_id' => $request->service_id,
                'client_id' => $request->user() ? $request->user()->id : null,
                'date' => $request->date,
                'time' => $request->time,
                'status' => 'pending',
            ]);

            return response()->json($appointment, 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Erro de validação', 'errors' => $e->errors()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Recurso não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao criar agendamento', 'error' => $e->getMessage()], 500);
        }
    }
    public function show(Request $request, $id)
    {
        try {
            $barbershopId = $request->user()->barbershop_id;

            $appointment = Appointment::where('id', $id)
                ->where(function($q) use ($barbershopId) {
                    $q->where('barbershop_id', $barbershopId)
                      ->orWhereHas('client', fn($q) => $q->where('barbershop_id', $barbershopId));
                })
                ->with(['client', 'barber', 'service'])
                ->firstOrFail();

            return response()->json(['message' => 'Agendamento encontrado', 'data' => $appointment]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Agendamento não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao buscar agendamento', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'appointment_date' => 'required|date',
                'barber_id'        => 'nullable|exists:barbers,id',
                'service_id'       => 'nullable|exists:services,id',
                'price'            => 'nullable|numeric',
                'service_name'     => 'nullable|string',
            ]);

            $barbershopId = $request->user()->barbershop_id;

            $appointment = Appointment::where('id', $id)
                ->where(function($q) use ($barbershopId) {
                    $q->where('barbershop_id', $barbershopId)
                      ->orWhereHas('client', fn($q) => $q->where('barbershop_id', $barbershopId));
                })
                ->firstOrFail();

            // Bloqueia duplicado no update
            $exists = Appointment::where('appointment_date', $validated['appointment_date'])
                ->where('barber_id', $validated['barber_id'] ?? $appointment->barber_id)
                ->where('id', '!=', $id)
                ->whereNotIn('status', ['cancelled'])
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'Já existe um agendamento nesse horário'], 400);
            }

            $appointment->update($validated);

            return response()->json([
                'message' => 'Agendamento atualizado com sucesso',
                'data'    => $appointment->load(['client', 'barber', 'service']),
            ]);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação', 'errors' => $e->errors()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Agendamento não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao atualizar agendamento', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $barbershopId = $request->user()->barbershop_id;

            $appointment = Appointment::where('id', $id)
                ->where(function($q) use ($barbershopId) {
                    $q->where('barbershop_id', $barbershopId)
                      ->orWhereHas('client', fn($q) => $q->where('barbershop_id', $barbershopId));
                })
                ->firstOrFail();

            $appointment->delete();

            return response()->json(['message' => 'Agendamento deletado com sucesso']);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Agendamento não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao deletar agendamento', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:pending,confirmed,cancelled,completed',
            ]);

            $barbershopId = $request->user()->barbershop_id;

            $appointment = Appointment::where('id', $id)
                ->where(function($q) use ($barbershopId) {
                    $q->where('barbershop_id', $barbershopId)
                      ->orWhereHas('client', fn($q) => $q->where('barbershop_id', $barbershopId));
                })
                ->firstOrFail();

            $appointment->update(['status' => $validated['status']]);

            return response()->json([
                'message' => 'Status atualizado com sucesso',
                'data'    => $appointment,
            ]);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Status inválido', 'errors' => $e->errors()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Agendamento não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao atualizar status', 'error' => $e->getMessage()], 500);
        }
    }
}