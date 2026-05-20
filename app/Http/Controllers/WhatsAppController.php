<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhatsAppController extends Controller
{
    private string $evolutionUrl = 'https://evolution-api-latest-swzi.onrender.com';
    private string $evolutionKey = 'barber-evolution-2026-xyz';
    private string $instance = 'barbearia_principal';

    public function connect()
    {
        $webhookUrl = config('app.url') . '/api/whatsapp/webhook';

        // Configura o webhook na Evolution API
        Http::withHeaders(['apikey' => $this->evolutionKey])
            ->post("{$this->evolutionUrl}/webhook/set/{$this->instance}", [
                'url' => $webhookUrl,
                'webhook_by_events' => true,
                'webhook_base64' => true,
                'events' => ['QRCODE_UPDATED', 'CONNECTION_UPDATE'],
            ]);

        // Tenta conectar
        Http::withHeaders(['apikey' => $this->evolutionKey])
            ->get("{$this->evolutionUrl}/instance/connect/{$this->instance}");

        // Retorna QR do cache se já tiver
        $qr = cache()->get('whatsapp_qrcode');
        return response()->json(['base64' => $qr, 'count' => $qr ? 1 : 0]);
    }

    public function status()
    {
        $response = Http::withHeaders(['apikey' => $this->evolutionKey])
            ->get("{$this->evolutionUrl}/instance/fetchInstances");

        $instances = $response->json();
        $instance = collect($instances)->firstWhere('name', $this->instance);

        $qr = cache()->get('whatsapp_qrcode');

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

    public function webhook(Request $request)
    {
        $event = strtolower($request->input('event'));
        $data = $request->input('data');

        if ($event === 'qrcode.updated' || $event === 'qrcode_updated') {
            $qrBase64 = $data['qrcode']['base64'] ?? null;
            if ($qrBase64) {
                cache()->put('whatsapp_qrcode', $qrBase64, now()->addMinutes(2));
            }
        }

        if ($event === 'connection.update' || $event === 'connection_updated') {
            $status = $data['state'] ?? 'close';
            cache()->put('whatsapp_status', $status, now()->addMinutes(60));
        }

        return response()->json(['ok' => true]);
    }
}