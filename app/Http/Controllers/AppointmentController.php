<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    /**
     * List all appointments for the authenticated user's barbershop
     */
    public function index(Request $request)
{
    try {
        $query = Appointment::whereHas('client', function ($query) use ($request) {
            $query->where('barbershop_id', $request->user()->barbershop_id);
        })->with('client');

        // Filtro por data
        if ($request->has('date')) {
            $query->whereDate('appointment_date', $request->date);
        }

        $appointments = $query->paginate(15);

            return response()->json([
                'message' => 'Agendamentos listados com sucesso',
                'data' => $appointments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao listar agendamentos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new appointment
     */
    public function store(Request $request)
    {
        try {
            // ✅ Validação obrigatória
            $validated = $request->validate([
                'client_id' => 'required|exists:clients,id',
                'appointment_date' => 'required|date'
            ]);

            // ✅ Verifica se o cliente pertence à barbearia do usuário
            $client = Client::where('id', $validated['client_id'])
                ->where('barbershop_id', $request->user()->barbershop_id)
                ->firstOrFail();

            // ✅ Evita agendamento duplicado no mesmo horário
            $exists = Appointment::where('appointment_date', $validated['appointment_date'])
                ->whereHas('client', function ($query) use ($request) {
                    $query->where('barbershop_id', $request->user()->barbershop_id);
                })
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Já existe um agendamento nesse horário'
                ], 400);
            }

            // ✅ Cria o agendamento
            $appointment = Appointment::create([
                'client_id' => $client->id,
                'appointment_date' => $validated['appointment_date']
            ]);

            return response()->json([
                'message' => 'Agendamento criado com sucesso',
                'data' => $appointment
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Cliente não encontrado ou não pertence à sua barbearia'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao criar agendamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific appointment
     */
    public function show(Request $request, $id)
    {
        try {
            $appointment = Appointment::where('id', $id)
                ->whereHas('client', function ($query) use ($request) {
                    $query->where('barbershop_id', $request->user()->barbershop_id);
                })
                ->with('client')
                ->firstOrFail();

            return response()->json([
                'message' => 'Agendamento encontrado',
                'data' => $appointment
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Agendamento não encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao buscar agendamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an appointment
     */
    public function update(Request $request, $id)
    {
        try {
            // ✅ Validação obrigatória
            $validated = $request->validate([
                'appointment_date' => 'required|date'
            ]);

            // ✅ Busca o agendamento
            $appointment = Appointment::where('id', $id)
                ->whereHas('client', function ($query) use ($request) {
                    $query->where('barbershop_id', $request->user()->barbershop_id);
                })
                ->firstOrFail();

            // ✅ Evita duplicação com nova data
            $exists = Appointment::where('appointment_date', $validated['appointment_date'])
                ->where('id', '!=', $id)
                ->whereHas('client', function ($query) use ($request) {
                    $query->where('barbershop_id', $request->user()->barbershop_id);
                })
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Já existe um agendamento nesse horário'
                ], 400);
            }

            // ✅ Atualiza
            $appointment->update($validated);

            return response()->json([
                'message' => 'Agendamento atualizado com sucesso',
                'data' => $appointment
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Agendamento não encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar agendamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an appointment
     */
    public function destroy(Request $request, $id)
    {
        try {
            $appointment = Appointment::where('id', $id)
                ->whereHas('client', function ($query) use ($request) {
                    $query->where('barbershop_id', $request->user()->barbershop_id);
                })
                ->firstOrFail();

            $appointment->delete();

            return response()->json([
                'message' => 'Agendamento deletado com sucesso'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Agendamento não encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao deletar agendamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}