<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BarberController extends Controller
{
    public function index(Request $request)
    {
        try {
            $barbers = Barber::where('barbershop_id', $request->user()->barbershop_id)
                ->paginate(15);

            return response()->json([
                'message' => 'Barbeiros listados com sucesso',
                'data' => $barbers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao listar barbeiros',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20'
            ]);

            $barber = Barber::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'barbershop_id' => $request->user()->barbershop_id
            ]);

            return response()->json([
                'message' => 'Barbeiro criado com sucesso',
                'data' => $barber
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao criar barbeiro',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $barber = Barber::where('barbershop_id', $request->user()->barbershop_id)
                ->where('id', $id)
                ->firstOrFail();

            return response()->json([
                'message' => 'Barbeiro encontrado',
                'data' => $barber
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Barbeiro não encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao buscar barbeiro',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20'
            ]);

            $barber = Barber::where('barbershop_id', $request->user()->barbershop_id)
                ->where('id', $id)
                ->firstOrFail();

            $barber->update($validated);

            return response()->json([
                'message' => 'Barbeiro atualizado com sucesso',
                'data' => $barber
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Barbeiro não encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar barbeiro',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $barber = Barber::where('barbershop_id', $request->user()->barbershop_id)
                ->where('id', $id)
                ->firstOrFail();

            $barber->delete();

            return response()->json([
                'message' => 'Barbeiro deletado com sucesso'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Barbeiro não encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao deletar barbeiro',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}