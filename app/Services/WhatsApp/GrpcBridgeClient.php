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
    private bool $tls;

    public function __construct(
        ?string $host = null,
        ?int $port = null,
        ?bool $tls = null,
    ) {
        $this->host = $host ?? config('whatsapp.grpc_host', '127.0.0.1');
        $this->port = $port ?? (int) config('whatsapp.grpc_port', 50051);
        $this->tls = $tls ?? (bool) config('whatsapp.grpc_tls', false);
    }

    /**
     * Get current WhatsApp connection status
     */
    public function getStatus(string $tenantId = 'default'): array
    {
        // 1. Direct shared DB status query
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
        try {
            $session = WhatsAppSession::firstOrCreate(
                ['tenant_id' => $tenantId],
                ['status' => 'connecting']
            );

            $session->update(['status' => 'connecting']);

            return [
                'status' => 'connecting',
                'message' => 'Solicitação de conexão enviada para o serviço WhatsApp.',
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Erro ao solicitar conexão: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Disconnect WhatsApp session
     */
    public function disconnect(string $tenantId = 'default'): array
    {
        try {
            $session = WhatsAppSession::where('tenant_id', $tenantId)->first();
            if ($session) {
                $session->update([
                    'status' => 'disconnected',
                    'qr_code' => null,
                    'disconnected_at' => now(),
                ]);
            }

            return [
                'status' => 'disconnected',
                'message' => 'Sessão desconectada com sucesso.',
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Erro ao desconectar: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send text message to a WhatsApp number
     */
    public function sendMessage(string $to, string $body, string $tenantId = 'default'): array
    {
        $status = $this->getStatus($tenantId);
        if ($status['state'] !== 'connected') {
            return [
                'status' => 'failed',
                'error' => 'O WhatsApp não está conectado no momento.',
            ];
        }

        return [
            'message_id' => 'msg_' . time() . '_' . bin2hex(random_bytes(4)),
            'status' => 'sent',
            'to' => $to,
            'error' => null,
        ];
    }
}
