<?php

namespace App\Services\WhatsApp;

class GrpcBridgeClient
{
    public function __construct(
        private readonly string $host = '',
        private readonly int $port = 50051,
        private readonly bool $tls = false,
    ) {
    }

    public function sendMessage(array $payload): array
    {
        return [
            'status' => 'not_implemented',
            'payload' => $payload,
            'connection' => sprintf('%s:%d', $this->host, $this->port),
            'tls' => $this->tls,
        ];
    }
}
