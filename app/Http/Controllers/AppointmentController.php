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
            $validated = $request->validate([
                'client_id'        => 'required|exists:clients,id',
                'appointment_date' => 'required|date',
                'barber_id'        => 'nullable|exists:barbers,id',
                'service_id'       => 'nullable|exists:services,id',
                'price'            => 'nullable|numeric',
                'service_name'     => 'nullable|string',
            ]);

            $client = Client::where('id', $validated['client_id'])
                ->where('barbershop_id', $request->user()->barbershop_id)
                ->firstOrFail();

            // Bloqueia horário duplicado
            $exists = Appointment::where('appointment_date', $validated['appointment_date'])
                ->where('barber_id', $validated['barber_id'] ?? null)
                ->whereNotIn('status', ['cancelled'])
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'Já existe um agendamento nesse horário'], 400);
            }

            $appointment = Appointment::create([
                'client_id'        => $client->id,
                'barbershop_id'    => $request->user()->barbershop_id,
                'appointment_date' => $validated['appointment_date'],
                'barber_id'        => $validated['barber_id'] ?? null,
                'service_id'       => $validated['service_id'] ?? null,
                'price'            => $validated['price'] ?? null,
                'service_name'     => $validated['service_name'] ?? null,
                'status'           => 'pending',
            ]);

            return response()->json([
                'message' => 'Agendamento criado com sucesso',
                'data'    => $appointment->load(['client', 'barber', 'service']),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação', 'errors' => $e->errors()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Cliente não encontrado ou não pertence à sua barbearia'], 404);
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