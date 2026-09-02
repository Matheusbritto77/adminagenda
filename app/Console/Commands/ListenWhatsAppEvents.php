<?php

namespace App\Console\Commands;

use App\Events\WhatsAppMessageReceived;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ListenWhatsAppEvents extends Command
{
    protected $signature = 'whatsapp:listen-events';

    protected $description = 'Escuta o canal de eventos em tempo real do gRPC/Redis do agenwpp e dispara os Eventos do Laravel';

    public function handle(): int
    {
        $this->info('Iniciando ouvinte de eventos gRPC/Redis do WhatsApp...');

        try {
            Redis::subscribe(['whatsapp:events'], function (string $message) {
                try {
                    $payload = json_decode($message, true);
                    if (!$payload || !is_array($payload)) return;

                    $type = $payload['type'] ?? '';

                    if ($type === 'message_received') {
                        $phone = $payload['phone'] ?? '';
                        $text = $payload['message'] ?? '';
                        $tenantId = $payload['tenant_id'] ?? 'default';
                        $messageId = $payload['message_id'] ?? null;

                        if ($phone && $text) {
                            $this->line("📩 Mensagem recebida de {$phone}: \"{$text}\"");
                            event(new WhatsAppMessageReceived($phone, $text, $tenantId, $messageId, $payload));
                        }
                    } elseif ($type === 'connected') {
                        $this->info("🟢 WhatsApp conectado: +{$payload['phone_number']}");
                    } elseif ($type === 'disconnected') {
                        $this->warn("🔴 WhatsApp desconectado: {$payload['friendly_reason']}");
                    }
                } catch (Throwable $e) {
                    $this->error('Erro ao processar mensagem do Redis: ' . $e->getMessage());
                }
            });
        } catch (Throwable $e) {
            $this->error('Falha de conexão com o Redis: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
