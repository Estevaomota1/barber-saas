<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller
{
    /**
     * List all clients for the authenticated user's barbershop
     */
    public function index(Request $request)
    {
        try {
            $clients = Client::where('barbershop_id', $request->user()->barbershop_id)
                ->paginate(15);

            return response()->json([
                'message' => 'Clientes listados com sucesso',
                'data' => $clients
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao listar clientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new client
     */
    public function store(Request $request)
    {
        try {
            // ✅ Validação obrigatória
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20'
            ]);

            // ✅ Cria o cliente
            $client = Client::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'barbershop_id' => $request->user()->barbershop_id
            ]);

            return response()->json([
                'message' => 'Cliente criado com sucesso',
                'data' => $client
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao criar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific client
     */
    public function show(Request $request, $id)
    {
        try {
            $client = Client::where('barbershop_id', $request->user()->barbershop_id)
                ->where('id', $id)
                ->firstOrFail();

            return response()->json([
                'message' => 'Cliente encontrado',
                'data' => $client
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Cliente não encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao buscar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a client
     */
    public function update(Request $request, $id)
    {
        try {
            // ✅ Validação obrigatória
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20'
            ]);

            // ✅ Busca e atualiza
            $client = Client::where('barbershop_id', $request->user()->barbershop_id)
                ->where('id', $id)
                ->firstOrFail();

            $client->update($validated);

            return response()->json([
                'message' => 'Cliente atualizado com sucesso',
                'data' => $client
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Cliente não encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao atualizar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a client
     */
    public function destroy(Request $request, $id)
    {
        try {
            $client = Client::where('barbershop_id', $request->user()->barbershop_id)
                ->where('id', $id)
                ->firstOrFail();

            $client->delete();

            return response()->json([
                'message' => 'Cliente deletado com sucesso'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Cliente não encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao deletar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}