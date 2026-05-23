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
        $webhookUrl = config('app.url') . '/api/whatsapp/webhook';

        Http::withHeaders(['apikey' => $this->evolutionKey])
            ->post("{$this->evolutionUrl}/webhook/set/{$this->instance}", [
                'url'               => $webhookUrl,
                'webhook_by_events' => true,
                'webhook_base64'    => true,
                'events'            => ['QRCODE_UPDATED', 'CONNECTION_UPDATE'],
            ]);

        Http::withHeaders(['apikey' => $this->evolutionKey])
            ->get("{$this->evolutionUrl}/instance/connect/{$this->instance}");

        $qr = cache()->get('whatsapp_qrcode');

        return response()->json(['base64' => $qr, 'count' => $qr ? 1 : 0]);
    }

    public function status()
    {
        $response  = Http::withHeaders(['apikey' => $this->evolutionKey])
            ->get("{$this->evolutionUrl}/instance/fetchInstances");

        $instances = $response->json();
        $instance  = collect($instances)->firstWhere('name', $this->instance);
        $qr        = cache()->get('whatsapp_qrcode');

        return response()->json([
            'status' => ($instance && $instance['connectionStatus'] === 'open') ? 'connected' : 'disconnected',
            'base64' => $qr,
        ]);
    }

    public function disconnect()
    {
        $response = Http::withHeaders(['apikey' => $this->evolutionKey])
            ->delete("{$this->evolutionUrl}/instance/logout/{$this->instance}");

        return response()->json($response->json());
    }

    public function debug(Request $request)
    {
        return response()->json([
            'success'  => true,
            'instance' => $this->instance,
            'api_url'  => $this->evolutionUrl,
            'key_set'  => !empty($this->evolutionKey),
            'method'   => $request->method(),
            'body'     => $request->all(),
        ]);
    }

    public function webhook(Request $request)
    {
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
    }
}