<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    private string $evolutionUrl;
    private string $evolutionKey;
    private string $instance;

    public function __construct()
    {
        $this->evolutionUrl = rtrim(env('EVOLUTION_API_URL', 'https://evolution-api-latest-swzi.onrender.com'), '/');
        $this->evolutionKey = env('EVOLUTION_API_KEY', 'barber-evolution-2026-xyz');
        $this->instance     = env('EVOLUTION_INSTANCE', 'barbearia_principal');
    }

    public function connect()
{
    try {
        $webhookUrl = config('app.url') . '/api/whatsapp/webhook';

        Http::withHeaders(['apikey' => $this->evolutionKey])
            ->post("{$this->evolutionUrl}/webhook/set/{$this->instance}", [
                'url'               => $webhookUrl,
                'webhook_by_events' => true,
                'webhook_base64'    => true,
                'events'            => ['QRCODE_UPDATED', 'CONNECTION_UPDATE'],
            ]);

        $connectResponse = Http::withHeaders(['apikey' => $this->evolutionKey])
            ->get("{$this->evolutionUrl}/instance/connect/{$this->instance}");

        $connectData = $connectResponse->json();

        Log::info('Evolution connect response:', $connectData ?? []);

        // A Evolution API geralmente já devolve o QR direto nessa chamada
        $qr = $connectData['base64']
            ?? $connectData['qrcode']['base64']
            ?? null;

        if ($qr) {
            cache()->put('whatsapp_qrcode', $qr, now()->addMinutes(2));
        } else {
            // Se não veio direto, tenta o que já estiver em cache (via webhook)
            $qr = cache()->get('whatsapp_qrcode');
        }

        return response()->json([
            'base64' => $qr,
            'count'  => $qr ? 1 : 0,
            'raw'    => $connectData, // 👈 temporário, pra debug — remove depois
        ]);

    } catch (\Exception $e) {
        Log::error('WhatsApp connect error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'error'   => $e->getMessage(),
            'line'    => $e->getLine(),
            'file'    => $e->getFile(),
        ], 500);
    }
}

    public function status()
    {
        try {
            $response  = Http::withHeaders(['apikey' => $this->evolutionKey])
                ->get("{$this->evolutionUrl}/instance/fetchInstances");

            $instances = $response->json();
            $instance  = collect($instances)->firstWhere('name', $this->instance);
            $qr        = cache()->get('whatsapp_qrcode');

            return response()->json([
                'status' => ($instance && $instance['connectionStatus'] === 'open') ? 'connected' : 'disconnected',
                'base64' => $qr,
            ]);

        } catch (\Exception $e) {
            Log::error('WhatsApp status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function disconnect()
    {
        try {
            $response = Http::withHeaders(['apikey' => $this->evolutionKey])
                ->delete("{$this->evolutionUrl}/instance/logout/{$this->instance}");

            return response()->json($response->json());

        } catch (\Exception $e) {
            Log::error('WhatsApp disconnect error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function debug(Request $request)
    {
        try {
            return response()->json([
                'success'      => true,
                'instance'     => $this->instance,
                'api_url'      => $this->evolutionUrl,
                'key_set'      => !empty($this->evolutionKey),
                'method'       => $request->method(),
                'body'         => $request->all(),
                'php_version'  => PHP_VERSION,
                'laravel'      => app()->version(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ], 500);
        }
    }

    public function webhook(Request $request)
    {
        try {
            $payload = $request->all();
            Log::info('WhatsApp Webhook:', $payload);

            $event = strtolower($request->input('event'));
            $data  = $request->input('data');

            if (str_contains($event, 'qrcode')) {
                $qrBase64 = $data['qrcode']['base64'] ?? $data['base64'] ?? null;
                if ($qrBase64) {
                    cache()->put('whatsapp_qrcode', $qrBase64, now()->addMinutes(2));
                }
            }

            if (str_contains($event, 'connection')) {
                $status = $data['state'] ?? 'close';
                cache()->put('whatsapp_status', $status, now()->addMinutes(60));
            }

            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            Log::error('WhatsApp webhook error: ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}