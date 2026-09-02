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
     * Get list of candidate hosts to try if primary host has DNS resolution failure
     */
    private function getCandidateHosts(): array
    {
        $hosts = array_filter([$this->host]);
        $fallbacks = ['agenwpp', 'agenda-wpp', 'agenda-agenwpp', '127.0.0.1', 'localhost', 'host.docker.internal'];
        foreach ($fallbacks as $fb) {
            if (!in_array($fb, $hosts)) {
                $hosts[] = $fb;
            }
        }
        return $hosts;
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

        foreach ($this->getCandidateHosts() as $candidateHost) {
            // 1. Test TCP Socket on gRPC port
            $fp = @fsockopen($candidateHost, $this->port, $errno, $errstr, 1.5);
            if ($fp) {
                $socketConnected = true;
                fclose($fp);
                $this->host = $candidateHost;
            }

            // 2. Test HTTP Bridge health on port 50052
            try {
                $response = Http::timeout(2.0)->get("http://{$candidateHost}:{$this->httpPort}/health");
                if ($response->successful()) {
                    $httpConnected = true;
                    $httpResponse = $response->json();
                    $this->host = $candidateHost;
                    $errorMessage = null;
                    break;
                }
            } catch (Throwable $e) {
                if (!$errorMessage) {
                    $errorMessage = "HTTP Bridge ({$candidateHost}:{$this->httpPort}) falhou: " . $e->getMessage();
                }
            }
        }

        $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'connected' => $socketConnected || $httpConnected,
            'socket_connected' => $socketConnected,
            'http_connected' => $httpConnected,
            'latency_ms' => $latencyMs,
            'host' => $this->host,
            'port' => $this->port,
            'http_port' => $this->httpPort,
            'tls' => $this->tls,
            'http_info' => $httpResponse,
            'error' => ($socketConnected || $httpConnected) ? null : $errorMessage,
        ];
    }

    /**
     * Fetch WhatsApp connection status from MySQL session record and HTTP bridge
     */
    public function getStatus(string $tenantId = 'default'): array
    {
        // 1. Try DB read first
        try {
            $session = WhatsAppSession::where('tenant_id', $tenantId)->first();
            if ($session) {
                return [
                    'status' => $session->status ?? 'disconnected',
                    'phone_number' => $session->phone_number ?? '',
                    'profile_name' => $session->profile_name ?? '',
                    'qr_code' => $session->qr_code ?? '',
                    'pairing_code' => $session->pairing_code ?? '',
                    'connected_at' => $session->connected_at ? $session->connected_at->toIso8601String() : null,
                    'disconnected_at' => $session->disconnected_at ? $session->disconnected_at->toIso8601String() : null,
                    'updated_at' => $session->updated_at ? $session->updated_at->toIso8601String() : null,
                ];
            }
        } catch (Throwable $e) {
            Log::warning('[WhatsApp] DB status check failed: ' . $e->getMessage());
        }

        // 2. Fallback to HTTP Bridge query
        foreach ($this->getCandidateHosts() as $candidateHost) {
            try {
                $response = Http::timeout(2.0)->get("http://{$candidateHost}:{$this->httpPort}/status", [
                    'tenant_id' => $tenantId,
                ]);

                if ($response->successful()) {
                    $this->host = $candidateHost;
                    return $response->json();
                }
            } catch (Throwable) {}
        }

        return [
            'status' => 'disconnected',
            'phone_number' => '',
            'profile_name' => '',
            'qr_code' => '',
            'pairing_code' => '',
            'connected_at' => null,
            'disconnected_at' => null,
            'updated_at' => null,
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
        $payload = ['tenant_id' => $tenantId];
        if ($phoneNumber) {
            $payload['phone_number'] = $phoneNumber;
        }

        foreach ($this->getCandidateHosts() as $candidateHost) {
            try {
                $response = Http::timeout(3.5)->post("http://{$candidateHost}:{$this->httpPort}/connect", $payload);

                if ($response->successful()) {
                    $this->host = $candidateHost;
                    $data = $response->json();
                    return [
                        'status' => $data['status'] ?? 'connecting',
                        'qr_code' => $data['qr_code'] ?? '',
                        'pairing_code' => $data['pairing_code'] ?? '',
                        'message' => $data['message'] ?? 'Conectando ao WhatsApp...',
                    ];
                }
            } catch (Throwable $e) {
                Log::warning("[WhatsApp] HTTP connect call to {$candidateHost} failed: " . $e->getMessage());
            }
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
        foreach ($this->getCandidateHosts() as $candidateHost) {
            try {
                $response = Http::timeout(3.0)->post("http://{$candidateHost}:{$this->httpPort}/disconnect", [
                    'tenant_id' => $tenantId,
                ]);

                if ($response->successful()) {
                    $this->host = $candidateHost;
                    return $response->json();
                }
            } catch (Throwable) {}
        }

        // Fallback update DB
        try {
            $session = WhatsAppSession::where('tenant_id', $tenantId)->first();
            if ($session) {
                $session->update([
                    'status' => 'disconnected',
                    'qr_code' => null,
                    'pairing_code' => null,
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
        $lastError = 'Host não encontrado';

        foreach ($this->getCandidateHosts() as $candidateHost) {
            try {
                $response = Http::timeout(25.0)->post("http://{$candidateHost}:{$this->httpPort}/send-message", [
                    'tenant_id' => $tenantId,
                    'to' => $to,
                    'body' => $body,
                ]);

                if ($response->successful()) {
                    $this->host = $candidateHost;
                    return $response->json();
                }

                $error = $response->json('error');
                if ($error) {
                    return [
                        'status' => 'failed',
                        'error' => $error,
                    ];
                }
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        return [
            'status' => 'failed',
            'error' => "Falha na comunicação com o agenwpp ({$this->host}:{$this->httpPort}): {$lastError}",
        ];
    }
}
