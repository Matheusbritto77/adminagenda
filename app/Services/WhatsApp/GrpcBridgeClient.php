<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GrpcBridgeClient
{
    private string $host;
    private int $port;
    private int $httpPort;
    private bool $tls;

    public function __construct(
        ?string $host = null,
        ?int $port = null,
        ?bool $tls = null,
    ) {
        $this->host = $host ?? config('whatsapp.grpc_host', '127.0.0.1');
        $this->port = $port ?? (int) config('whatsapp.grpc_port', 50051);
        $this->httpPort = (int) env('WHATSAPP_HTTP_PORT', 50052);
        $this->tls = $tls ?? (bool) config('whatsapp.grpc_tls', false);
    }

    /**
     * Test TCP socket connectivity and HTTP bridge health check
     */
    public function testConnection(): array
    {
        $startTime = microtime(true);
        $socketConnected = false;
        $httpConnected = false;
        $httpResponse = null;
        $errorMessage = null;

        // 1. Test TCP Socket on gRPC port
        $fp = @fsockopen($this->host, $this->port, $errno, $errstr, 2.0);
        if ($fp) {
            $socketConnected = true;
            fclose($fp);
        } else {
            $errorMessage = "Socket gRPC ({$this->host}:{$this->port}) falhou: {$errstr} ({$errno})";
        }

        // 2. Test HTTP Bridge health on port 50052
        try {
            $response = Http::timeout(2.5)->get("http://{$this->host}:{$this->httpPort}/health");
            if ($response->successful()) {
                $httpConnected = true;
                $httpResponse = $response->json();
            }
        } catch (Throwable $e) {
            if (!$errorMessage) {
                $errorMessage = "HTTP Bridge ({$this->host}:{$this->httpPort}) falhou: " . $e->getMessage();
            }
        }

        $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'success' => $socketConnected || $httpConnected,
            'socket_connected' => $socketConnected,
            'http_connected' => $httpConnected,
            'host' => $this->host,
            'grpc_port' => $this->port,
            'http_port' => $this->httpPort,
            'latency_ms' => $latencyMs,
            'error' => $errorMessage,
            'http_data' => $httpResponse,
        ];
    }

    /**
     * Get current WhatsApp connection status
     */
    public function getStatus(string $tenantId = 'default'): array
    {
        // 1. Try direct HTTP bridge status query (source of truth)
        try {
            $response = Http::timeout(1.2)->get("http://{$this->host}:{$this->httpPort}/status", [
                'tenant_id' => $tenantId,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $state = $data['state'] ?? 'disconnected';
                $phone = $data['phone_number'] ?? '';
                $profile = $data['profile_name'] ?? '';
                $qrCode = $data['qr_code'] ?? '';
                $pairingCode = $data['pairing_code'] ?? '';

                // Keep MySQL record in 100% sync with live state
                try {
                    $session = WhatsAppSession::where('tenant_id', $tenantId)->first();
                    if ($session && ($session->status !== $state || ($phone && $session->phone_number !== $phone))) {
                        $session->update([
                            'status' => $state,
                            'phone_number' => $phone ?: $session->phone_number,
                            'profile_name' => $profile ?: $session->profile_name,
                            'qr_code' => $qrCode ?: null,
                            'pairing_code' => $pairingCode ?: null,
                        ]);
                    }
                } catch (Throwable) {}

                return [
                    'state' => $state,
                    'phone_number' => $phone,
                    'profile_name' => $profile,
                    'tenant_id' => $tenantId,
                    'qr_code' => $qrCode,
                    'pairing_code' => $pairingCode,
                    'updated_at' => $data['updated_at'] ?? now()->toIso8601String(),
                ];
            }
        } catch (Throwable) {
            // Live service is unreachable (instance down/crashed)
            // Immediately mark disconnected in DB so UI never shows false connected state!
            try {
                $session = WhatsAppSession::where('tenant_id', $tenantId)->first();
                if ($session && $session->status !== 'disconnected') {
                    $session->update(['status' => 'disconnected', 'qr_code' => null, 'pairing_code' => null]);
                }
            } catch (Throwable) {}

            return [
                'state' => 'disconnected',
                'phone_number' => '',
                'profile_name' => '',
                'tenant_id' => $tenantId,
                'qr_code' => '',
                'pairing_code' => '',
                'updated_at' => now()->toIso8601String(),
            ];
        }

        return [
            'state' => 'disconnected',
            'phone_number' => '',
            'profile_name' => '',
            'tenant_id' => $tenantId,
            'qr_code' => '',
            'pairing_code' => '',
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Request connection / QR code generation or Pairing Code with Phone Number
     */
    public function connect(string $tenantId = 'default', ?string $phoneNumber = null): array
    {
        // Update DB state
        try {
            $session = WhatsAppSession::firstOrCreate(
                ['tenant_id' => $tenantId],
                ['status' => 'connecting']
            );
            $session->update(['status' => 'connecting']);
        } catch (Throwable) {}

        // Send HTTP trigger to agenwpp
        try {
            $payload = ['tenant_id' => $tenantId];
            if ($phoneNumber) {
                $payload['phone_number'] = $phoneNumber;
            }

            $response = Http::timeout(3.0)->post("http://{$this->host}:{$this->httpPort}/connect", $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => $data['status'] ?? 'connecting',
                    'qr_code' => $data['qr_code'] ?? '',
                    'pairing_code' => $data['pairing_code'] ?? '',
                    'message' => $data['message'] ?? 'Conectando ao WhatsApp...',
                ];
            }
        } catch (Throwable $e) {
            Log::warning('[WhatsApp] HTTP connect call failed: ' . $e->getMessage());
        }

        return [
            'status' => 'connecting',
            'message' => 'Solicitação enviada. Aguardando código de conexão.',
        ];
    }

    /**
     * Disconnect WhatsApp session
     */
    public function disconnect(string $tenantId = 'default'): array
    {
        // Send HTTP trigger to agenwpp
        try {
            $response = Http::timeout(3.0)->post("http://{$this->host}:{$this->httpPort}/disconnect", [
                'tenant_id' => $tenantId,
            ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (Throwable) {}

        // Fallback update DB
        try {
            $session = WhatsAppSession::where('tenant_id', $tenantId)->first();
            if ($session) {
                $session->update([
                    'status' => 'disconnected',
                    'qr_code' => null,
                    'disconnected_at' => now(),
                ]);
            }
        } catch (Throwable) {}

        return [
            'status' => 'disconnected',
            'message' => 'Sessão desconectada com sucesso.',
        ];
    }

    /**
     * Send text message to a WhatsApp number
     */
    public function sendMessage(string $to, string $body, string $tenantId = 'default'): array
    {
        try {
            $response = Http::timeout(5.0)->post("http://{$this->host}:{$this->httpPort}/send-message", [
                'tenant_id' => $tenantId,
                'to' => $to,
                'body' => $body,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'status' => 'failed',
                'error' => $response->json('error') ?? 'Erro ao enviar mensagem via bridge.',
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'failed',
                'error' => 'Falha na comunicação com o agenwpp: ' . $e->getMessage(),
            ];
        }
    }
}
