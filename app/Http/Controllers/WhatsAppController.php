<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class WhatsAppController extends Controller
{
    // Configurações centralizadas para fácil manutenção
    private function getApiConfig()
    {
        return [
            "url" => rtrim(env("EVOLUTION_URL", "https://evolution-api-latest-swzi.onrender.com"), "/"),
            "key" => env("EVOLUTION_KEY", "barber-evolution-2026-xyz"),
            "instance" => env("EVOLUTION_INSTANCE", "barbearia_principal"),
        ];
    }

    /**
     * Verifica o status da conexão do WhatsApp
     */
    public function status()
    {
        try {
            $config = $this->getApiConfig();
            Log::info("Checking WhatsApp Status for instance: {$config["instance"]}");

            // Endpoint v2: /instance/connectionState/{instance}
            $response = Http::withHeaders([
                "apikey" => $config["key"],
                "Content-Type" => "application/json",
            ])->get("{$config["url"]}/instance/connectionState/{$config["instance"]}");

            if ($response->failed()) {
                Log::error("Evolution API returned error in status()", ["body" => $response->body()]);
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
                "debug" => [
                    "instanceState" => $state
                ]
            ]);

        } catch (Exception $e) {
            Log::critical("Fatal error in WhatsAppController@status: " . $e->getMessage());
            return response()->json([
                "success" => false,
                "message" => "Erro interno ao processar status",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gera o QR Code para conexão
     */
    public function connect()
    {
        try {
            $config = $this->getApiConfig();
            Log::info("Requesting QR Code for instance: {$config["instance"]}");

            // Endpoint v2: /instance/connect/{instance}
            $response = Http::withHeaders([
                "apikey" => $config["key"],
                "Content-Type" => "application/json",
            ])->get("{$config["url"]}/instance/connect/{$config["instance"]}");

            if ($response->failed()) {
                Log::error("Evolution API returned error in connect()", ["body" => $response->body()]);
                return response()->json([
                    "success" => false,
                    "message" => "Erro ao gerar QR Code na Evolution API",
                    "details" => $response->json()
                ], $response->status());
            }

            $data = $response->json();
            // Na v2, o QR Code vem em "base64" ou "code"
            $qr = $data["base64"] ?? $data["code"] ?? null;

            if (!$qr) {
                return response()->json([
                    "success" => false,
                    "message" => "QR Code não encontrado na resposta da API",
                ], 404);
            }

            return response()->json([
                "success" => true,
                "base64" => $qr,
                "count" => 1
            ]);

        } catch (Exception $e) {
            Log::critical("Fatal error in WhatsAppController@connect: " . $e->getMessage());
            return response()->json([
                "success" => false,
                "message" => "Erro interno ao gerar conexão",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desconecta a instância
     */
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

    /**
     * Webhook para receber atualizações da Evolution API
     */
    public function webhook(Request $request)
    {
        try {
            $payload = $request->all();
            Log::info("WhatsApp Webhook Received", $payload);

            $event = strtolower($request->input("event", ""));
            $data = $request->input("data");

            if (empty($data)) {
                return response()->json(["ok" => true, "message" => "No data"], 200);
            }

            // Evento de QR Code
            if (str_contains($event, "qrcode")) {
                $qrBase64 = $data["qrcode"]["base64"] ?? $data["base64"] ?? null;
                if ($qrBase64) {
                    cache()->put("whatsapp_qrcode", $qrBase64, now()->addMinutes(5));
                    Log::info("QR Code updated in cache");
                }
            }

            // Evento de Conexão
            if (str_contains($event, "connection")) {
                $status = $data["state"] ?? "close";
                cache()->put("whatsapp_status", $status, now()->addHours(1));
                Log::info("WhatsApp connection state updated to: " . $status);
            }

            return response()->json(["ok" => true]);

        } catch (Exception $e) {
            Log::error("Error processing WhatsApp Webhook: " . $e->getMessage());
            return response()->json(["ok" => false], 500);
        }
    }
}
