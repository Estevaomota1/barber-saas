<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class WhatsAppController extends Controller
{
    private function getApiConfig()
    {
        return [
            "url" => rtrim(env("EVOLUTION_URL", "https://evolution-api-latest-swzi.onrender.com"), "/"),
            "key" => env("EVOLUTION_KEY", "barber-evolution-2026-xyz"),
            "instance" => env("EVOLUTION_INSTANCE", "barbearia_principal"),
        ];
    }

    /**
     * ROTA DE EMERGÊNCIA PARA DEBUG
     * Esta rota serve para descobrirmos por que o 500 acontece
     */
    public function debug()
    {
        try {
            $config = $this->getApiConfig();
            
            // Teste de conexão simples com a Evolution API
            $testResponse = Http::withHeaders([
                "apikey" => $config["key"]
            ])->get($config["url"]);

            return response()->json([
                "status" => "SISTEMA ONLINE",
                "laravel_env" => env("APP_ENV"),
                "evolution_url" => $config["url"],
                "evolution_instance" => $config["instance"],
                "api_test_status" => $testResponse->status(),
                "api_test_body" => $testResponse->json(),
                "message" => "Se você está vendo isso, o Laravel ESTÁ FUNCIONANDO e o erro 500 sumiu desta rota."
            ]);
        } catch (Exception $e) {
            return response()->json([
                "status" => "ERRO NO DEBUG",
                "exception" => get_class($e),
                "message" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ], 500);
        }
    }

    public function status()
    {
        try {
            $config = $this->getApiConfig();
            $response = Http::withHeaders([
                "apikey" => $config["key"],
                "Content-Type" => "application/json",
            ])->get("{$config["url"]}/instance/connectionState/{$config["instance"]}");

            if ($response->failed()) {
                return response()->json([
                    "success" => false, 
                    "message" => "API Evolution retornou erro", 
                    "details" => $response->json()
                ], $response->status());
            }

            $data = $response->json();
            $state = $data["instanceState"] ?? "close";

            return response()->json([
                "success" => true,
                "status" => ($state === "open") ? "connected" : "disconnected",
                "base64" => cache()->get("whatsapp_qrcode"),
            ]);

        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Erro interno ao processar status",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function connect()
    {
        try {
            $config = $this->getApiConfig();
            $response = Http::withHeaders([
                "apikey" => $config["key"],
                "Content-Type" => "application/json",
            ])->get("{$config["url"]}/instance/connect/{$config["instance"]}");

            if ($response->failed()) {
                return response()->json([
                    "success" => false,
                    "message" => "Erro ao gerar QR Code na Evolution API",
                    "details" => $response->json()
                ], $response->status());
            }

            $data = $response->json();
            $qr = $data["base64"] ?? $data["code"] ?? null;

            return response()->json([
                "success" => true,
                "base64" => $qr,
                "count" => $qr ? 1 : 0
            ]);

        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Erro interno ao gerar conexão",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function disconnect()
    {
        try {
            $config = $this->getApiConfig();
            $response = Http::withHeaders([
                "apikey" => $config["key"],
                "Content-Type" => "application/json",
            ])->get("{$config["url"]}/instance/logout/{$config["instance"]}");

            if ($response->failed()) {
                return response()->json(["success" => false, "message" => "Erro ao desconectar"], $response->status());
            }

            cache()->forget("whatsapp_qrcode");
            return response()->json(["success" => true]);

        } catch (Exception $e) {
            return response()->json(["success" => false, "error" => $e->getMessage()], 500);
        }
    }

    public function webhook(Request $request)
    {
        try {
            $payload = $request->all();
            $event = strtolower($request->input("event", ""));
            $data = $request->input("data");

            if (str_contains($event, "qrcode")) {
                $qrBase64 = $data["qrcode"]["base64"] ?? $data["base64"] ?? null;
                if ($qrBase64) {
                    cache()->put("whatsapp_qrcode", $qrBase64, now()->addMinutes(5));
                }
            }

            if (str_contains($event, "connection")) {
                $status = $data["state"] ?? "close";
                cache()->put("whatsapp_status", $status, now()->addHours(1));
            }

            return response()->json(["ok" => true]);
        } catch (Exception $e) {
            return response()->json(["ok" => false], 500);
        }
    }
}
