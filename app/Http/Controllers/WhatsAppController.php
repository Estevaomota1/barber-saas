public function connect()
{
    try {
        // 1. Define a URL do webhook que a Evolution vai chamar
        $webhookUrl = config('app.url') . '/api/whatsapp/webhook';

        // 2. Configura o webhook na Evolution API
        $webhookResponse = Http::withHeaders([
            'apikey' => $this->evolutionKey,
        ])->post("{$this->evolutionUrl}/webhook/set/{$this->instance}", [
            'enabled'         => true,
            'url'             => $webhookUrl,
            'webhookByEvents' => true,
            'webhookBase64'   => true,
            'events'          => ['QRCODE_UPDATED', 'CONNECTION_UPDATE'],
        ]);

        // Log do resultado do webhook (opcional, mas útil para debug)
        Log::info('Webhook set response:', [
            'status' => $webhookResponse->status(),
            'body'   => $webhookResponse->json(),
        ]);

        // Se o webhook falhar, ainda tentamos conectar, mas registramos o erro
        if (!$webhookResponse->successful()) {
            Log::warning('Falha ao configurar webhook na Evolution', [
                'status' => $webhookResponse->status(),
                'error'  => $webhookResponse->body(),
            ]);
            // Não interrompe o fluxo – a conexão pode funcionar mesmo sem webhook
        }

        // 3. Solicita a conexão da instância (isso gera o QR Code)
        $connectResponse = Http::withHeaders(['apikey' => $this->evolutionKey])
            ->get("{$this->evolutionUrl}/instance/connect/{$this->instance}");

        $connectData = $connectResponse->json();
        Log::info('Evolution connect response:', $connectData ?? []);

        // 4. Extrai o QR Code (base64) da resposta
        $qr = $connectData['base64']
            ?? $connectData['qrcode']['base64']
            ?? null;

        // Se veio o QR, guarda em cache para consultas futuras (ex: polling)
        if ($qr) {
            cache()->put('whatsapp_qrcode', $qr, now()->addMinutes(2));
        } else {
            // Se não veio direto, tenta pegar do cache (pode ter vindo via webhook)
            $qr = cache()->get('whatsapp_qrcode');
        }

        // 5. Retorna o QR Code (ou null) + dados brutos para debug (opcional)
        return response()->json([
            'base64' => $qr,
            'count'  => $qr ? 1 : 0,
            'raw'    => $connectData, // 👈 temporário, pode remover depois
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