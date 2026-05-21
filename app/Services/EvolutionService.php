<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EvolutionService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $instance;

    public function __construct()
    {
        // Usando env diretamente para evitar problemas de cache de config no Render inicialmente
        $this->baseUrl = rtrim(env("EVOLUTION_URL", "https://evolution-api-latest-swzi.onrender.com"), "/");
        $this->apiKey = env("EVOLUTION_KEY", "barber-evolution-2026-xyz");
        $this->instance = env("EVOLUTION_INSTANCE", "barbearia_principal");
    }

    private function request($endpoint, $method = "GET", $data = [])
    {
        try {
            $response = Http::withHeaders([
                "apikey" => $this->apiKey,
                "Content-Type" => "application/json",
            ])->send($method, "{$this->baseUrl}{$endpoint}", [
                "json" => $data
            ]);

            if ($response->failed()) {
                Log::error("Evolution API Error: {$endpoint}", [
                    "status" => $response->status(),
                    "body" => $response->json()
                ]);
                throw new Exception("Evolution API returned error: " . ($response->json()["message"] ?? "Unknown error"), $response->status());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::critical("Connection failed to Evolution API: " . $e->getMessage());
            throw $e;
        }
    }

    public function getInstanceStatus()
    {
        // v2: /instance/connectionState/{instance}
        return $this->request("/instance/connectionState/{$this->instance}");
    }

    public function connect()
    {
        // v2: /instance/connect/{instance}
        return $this->request("/instance/connect/{$this->instance}", "GET");
    }

    public function logout()
    {
        return $this->request("/instance/logout/{$this->instance}", "GET");
    }
}
