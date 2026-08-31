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
        // 1. Try direct HTTP bridge status query
        try {
            $response = Http::timeout(1.5)->get("http://{$this->host}:{$this->httpPort}/status", [
                'tenant_id' => $tenantId,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'state' => $data['state'] ?? 'disconnected',
                    'phone_number' => $data['phone_number'] ?? '',
                    'profile_name' => $data['profile_name'] ?? '',
                    'tenant_id' => $tenantId,
                    'qr_code' => $data['qr_code'] ?? '',
                    'updated_at' => $data['updated_at'] ?? now()->toIso8601String(),
                ];
            }
        } catch (Throwable) {
            // Fallback to shared DB
        }

        // 2. Fallback to Shared DB query
        try {
            $session = WhatsAppSession::where('tenant_id', $tenantId)->first();
            if ($session) {
                return [
                    'state' => $session->status ?? 'disconnected',
                    'phone_number' => $session->phone_number ?? '',
                    'profile_name' => $session->profile_name ?? '',
                    'tenant_id' => $tenantId,
                    'qr_code' => $session->qr_code ?? '',
                    'updated_at' => $session->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                    'connected_at' => $session->connected_at?->toIso8601String(),
                ];
            }
        } catch (Throwable $e) {
            Log::warning('[WhatsApp] Error reading session from database: ' . $e->getMessage());
        }

        return [
            'state' => 'disconnected',
            'phone_number' => '',
            'profile_name' => '',
            'tenant_id' => $tenantId,
            'qr_code' => '',
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Request connection / QR code generation
     */
    public function connect(string $tenantId = 'default'): array
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
            $response = Http::timeout(3.0)->post("http://{$this->host}:{$this->httpPort}/connect", [
                'tenant_id' => $tenantId,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => $data['status'] ?? 'connecting',
                    'qr_code' => $data['qr_code'] ?? '',
                    'message' => $data['message'] ?? 'Conectando ao WhatsApp...',
                ];
            }
        } catch (Throwable $e) {
            Log::warning('[WhatsApp] HTTP connect call failed: ' . $e->getMessage());
        }

        return [
            'status' => 'connecting',
            'message' => 'Solicitação enviada. Aguardando QR Code.',
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
