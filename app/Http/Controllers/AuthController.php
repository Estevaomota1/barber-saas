<?php
 
namespace App\Http\Controllers;
 
use App\Models\User;
use App\Models\Barbershop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
 
class AuthController extends Controller
{
    /**
     * Register a new user and barbershop
     */
    public function register(Request $request)
    {
        try {
            // ✅ Validação obrigatória
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'password' => 'required|string|min:6|confirmed',
                'barbershop_name' => 'required|string|max:255'
            ]);
 
            // ✅ Usa firstOrCreate para evitar duplicação
            $barbershop = Barbershop::firstOrCreate(
                ['email' => $validated['email']],
                ['name' => $validated['barbershop_name']]
            );
 
            // ✅ Cria o usuário
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'barbershop_id' => $barbershop->id
            ]);
 
            // ✅ Gera o token
            $token = $user->createToken('auth_token')->plainTextToken;
 
            return response()->json([
                'message' => 'Registrado com sucesso',
                'user' => $user,
                'token' => $token
            ], 201);
 
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao registrar',
                'error' => $e->getMessage()
            ], 500);
        }
    }
 
    /**
     * Login user
     */
    public function login(Request $request)
    {
        try {
            // ✅ Validação
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string'
            ]);
 
            // ✅ Busca o usuário
            $user = User::where('email', $validated['email'])->first();
 
            if (!$user || !Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'message' => 'Email ou senha inválidos'
                ], 401);
            }
 
            // ✅ Gera o token
            $token = $user->createToken('auth_token')->plainTextToken;
 
            return response()->json([
                'message' => 'Login realizado com sucesso',
                'user' => $user,
                'token' => $token
            ], 200);
 
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao fazer login',
                'error' => $e->getMessage()
            ], 500);
        }
    }
 
    /**
     * Get authenticated user
     */
    public function me(Request $request)
{
    return response()->json([
        'message' => 'Perfil recuperado com sucesso',
        'user' => $request->user()
    ]);
}
 
    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
 
        return response()->json([
            'message' => 'Logout realizado com sucesso'
        ]);
    }
}