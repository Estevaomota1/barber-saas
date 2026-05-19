<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhatsAppController extends Controller
{
    private string $evolutionUrl = 'https://evolution-api-latest-swzi.onrender.com';
    private string $evolutionKey = 'barber-evolution-2026-xyz';
    private string $instance = 'barbearia_principal';

    public function status()
    {
        $response = Http::withHeaders(['apikey' => $this->evolutionKey])
            ->get("{$this->evolutionUrl}/instance/fetchInstances");

        $instances = $response->json();
        $instance = collect($instances)->firstWhere('name', $this->instance);

        if (!$instance) {
            return response()->json(['status' => 'disconnected']);
        }

        return response()->json([
            'status' => $instance['connectionStatus'] === 'open' ? 'connected' : 'disconnected',
            'instance' => $instance,
        ]);
    }

    public function connect()
    {
        $response = Http::withHeaders(['apikey' => $this->evolutionKey])
            ->get("{$this->evolutionUrl}/instance/connect/{$this->instance}");

        return response()->json($response->json());
    }

    public function disconnect()
    {
        $response = Http::withHeaders(['apikey' => $this->evolutionKey])
            ->delete("{$this->evolutionUrl}/instance/logout/{$this->instance}");

        return response()->json($response->json());
    }
}