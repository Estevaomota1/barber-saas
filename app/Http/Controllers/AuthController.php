<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Barbershop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AuthController extends Controller
{
    // Registro de dono de barbearia
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'             => 'required|string|max:255',
                'email'            => 'required|email|unique:users',
                'password'         => 'required|string|min:6|confirmed',
                'barbershop_name'  => 'required|string|max:255',
            ]);

            $barbershop = Barbershop::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name'          => $validated['barbershop_name'],
                    'trial_ends_at' => Carbon::now()->addDays(7),
                ]
            );

            $user = User::create([
                'name'           => $validated['name'],
                'email'          => $validated['email'],
                'password'       => Hash::make($validated['password']),
                'barbershop_id'  => $barbershop->id,
                'role'           => 'owner',
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Registrado com sucesso',
                'user'    => $user,
                'token'   => $token,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao registrar', 'error' => $e->getMessage()], 500);
        }
    }

    // Registro de vendedor (sem barbearia própria)
    public function registerVendor(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|unique:users',
                'password' => 'required|string|min:6|confirmed',
            ]);

            // Vendedor precisa estar vinculado a uma barbearia existente via email da barbearia
            // Por simplicidade, vinculamos à barbearia do convite se existir, ou deixamos null
            $user = User::create([
                'name'          => $validated['name'],
                'email'         => $validated['email'],
                'password'      => Hash::make($validated['password']),
                'barbershop_id' => null,
                'role'          => 'vendor',
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Vendedor registrado com sucesso',
                'user'    => $user,
                'token'   => $token,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao registrar', 'error' => $e->getMessage()], 500);
        }
    }

    // Login unificado — retorna role para o frontend redirecionar
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email'    => 'required|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $validated['email'])->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                return response()->json(['message' => 'Email ou senha inválidos'], 401);
            }

            // Verifica bloqueio (apenas para owners)
            if ($user->role === 'owner' && $user->barbershop_id) {
                $barbershop = Barbershop::find($user->barbershop_id);
                if ($barbershop) {
                    // Bloqueio manual
                    if ($barbershop->blocked_at) {
                        return response()->json([
                            'message' => 'Conta bloqueada. ' . ($barbershop->blocked_reason ?? 'Entre em contato com o suporte.'),
                        ], 403);
                    }
                    // Trial expirado
                    if ($barbershop->trial_ends_at && Carbon::now()->isAfter($barbershop->trial_ends_at)) {
                        return response()->json([
                            'message' => 'Seu período de teste expirou. Entre em contato para assinar o plano.',
                        ], 403);
                    }
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Login realizado com sucesso',
                'user'    => $user,
                'role'    => $user->role,
                'token'   => $token,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao fazer login', 'error' => $e->getMessage()], 500);
        }
    }

    public function me(Request $request)
    {
        return response()->json([
            'message' => 'Perfil recuperado com sucesso',
            'user'    => $request->user(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout realizado com sucesso']);
    }

    // Admin: lista todas as barbearias
    public function listBarbershops(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Acesso negado'], 403);
        }

        $barbershops = Barbershop::with(['users' => function($q) {
            $q->where('role', 'owner')->select('id', 'name', 'email', 'barbershop_id', 'created_at');
        }])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function($b) {
            $trialEnds = $b->trial_ends_at ? Carbon::parse($b->trial_ends_at) : null;
            return [
                'id'             => $b->id,
                'name'           => $b->name,
                'email'          => $b->email,
                'slug'           => $b->slug,
                'owner'          => $b->users->first(),
                'trial_ends_at'  => $b->trial_ends_at,
                'trial_days_left'=> $trialEnds ? max(0, Carbon::now()->diffInDays($trialEnds, false)) : null,
                'trial_expired'  => $trialEnds ? Carbon::now()->isAfter($trialEnds) : false,
                'blocked_at'     => $b->blocked_at,
                'blocked_reason' => $b->blocked_reason,
                'created_at'     => $b->created_at,
            ];
        });

        return response()->json(['barbershops' => $barbershops]);
    }

    // Admin: bloquear/desbloquear barbearia
    public function toggleBlock(Request $request, $id)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Acesso negado'], 403);
        }

        $barbershop = Barbershop::findOrFail($id);

        if ($barbershop->blocked_at) {
            $barbershop->update(['blocked_at' => null, 'blocked_reason' => null]);
            return response()->json(['message' => 'Barbearia desbloqueada']);
        } else {
            $barbershop->update([
                'blocked_at'     => Carbon::now(),
                'blocked_reason' => $request->input('reason', 'Pagamento pendente'),
            ]);
            return response()->json(['message' => 'Barbearia bloqueada']);
        }
    }

    // Admin: estender trial
    public function extendTrial(Request $request, $id)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Acesso negado'], 403);
        }

        $days = $request->input('days', 7);
        $barbershop = Barbershop::findOrFail($id);
        $barbershop->update([
            'trial_ends_at' => Carbon::now()->addDays($days),
            'blocked_at'    => null,
        ]);

        return response()->json(['message' => "Trial estendido por {$days} dias"]);
    }
}