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
        \Log::info('Store - Dados recebidos:', $request->all());
        
        $barber = new Barber();
        $barber->name = $request->name;
        $barber->phone = $request->phone;
        $barber->photo = $request->photo;
        $barber->barbershop_id = $request->user()->barbershop_id; // ⬅️ ESSENCIAL!
        $barber->photo = $request->photo;

\Log::info('BEFORE SAVE', [
    'photo_length' => strlen($barber->photo ?? ''),
]);
        $barber->save();
        \Log::info('STORE BARBER', [
    'photo_exists' => isset($request->photo),
    'photo_length' => strlen($request->photo ?? ''),
    'keys' => array_keys($request->all()),
]);
        \Log::info('Store - Barbeiro salvo:', $barber->toArray());
        
        return response()->json(['success' => true, 'data' => $barber], 201);
    } catch (\Exception $e) {
        \Log::error('Store - Erro:', ['error' => $e->getMessage()]);
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
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
        $barber = Barber::findOrFail($id);
        
        // Log para debug
        \Log::info('Update - Dados recebidos:', $request->all());
        
        $barber->name = $request->name ?? $barber->name;
        $barber->phone = $request->phone ?? $barber->phone;
        
        // Só atualiza a foto se veio no request
        if ($request->has('photo')) {
            $barber->photo = $request->photo;
        }
        
        $barber->save();
        
        \Log::info('Update - Barbeiro atualizado:', $barber->toArray());
        
        return response()->json([
            'success' => true,
            'data' => $barber
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Update - Erro:', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
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
