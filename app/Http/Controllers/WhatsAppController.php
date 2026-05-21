<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\EvolutionService;
use Exception;

class WhatsAppController extends Controller
{
    protected $evolution;

    public function __construct(EvolutionService $evolution)
    {
        $this->evolution = $evolution;
    }

    public function connect()
    {
        try {
            $response = $this->evolution->connect();
            
            // Na v2, o connect retorna o base64 do QR Code
            $qr = $response["base64"] ?? $response["code"] ?? null;

            return response()->json([
                "success" => true,
                "base64" => $qr,
                "count" => $qr ? 1 : 0
            ]);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Erro ao conectar WhatsApp",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function status()
    {
        try {
            $status = $this->evolution->getInstanceStatus();
            return response()->json([
                "success" => true,
                "status" => ($status["instanceState"] ?? "") === "open" ? "connected" : "disconnected",
                "base64" => cache()->get("whatsapp_qrcode"),
            ]);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Erro ao buscar status",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function disconnect()
    {
        try {
            $this->evolution->logout();
            return response()->json(["success" => true]);
        } catch (Exception $e) {
            return response()->json(["success" => false, "error" => $e->getMessage()], 500);
        }
    }

    public function webhook(Request $request)
    {
        $payload = $request->all();
        \Log::info("WhatsApp Webhook:", $payload);

        $event = strtolower($request->input("event"));
        $data = $request->input("data");

        if (str_contains($event, "qrcode")) {
            $qrBase64 = $data["qrcode"]["base64"] ?? $data["base64"] ?? null;
            if ($qrBase64) {
                cache()->put("whatsapp_qrcode", $qrBase64, now()->addMinutes(2));
            }
        }

        if (str_contains($event, "connection")) {
            $status = $data["state"] ?? "close";
            cache()->put("whatsapp_status", $status, now()->addMinutes(60));
        }

        return response()->json(["ok" => true]);
    }
}
