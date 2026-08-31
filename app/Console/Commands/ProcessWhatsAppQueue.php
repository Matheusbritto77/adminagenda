<?php

namespace App\Console\Commands;

use App\Models\WhatsAppNotificationQueue;
use App\Services\WhatsApp\GrpcBridgeClient;
use Illuminate\Console\Command;
use Throwable;

class ProcessWhatsAppQueue extends Command
{
    protected $signature = 'whatsapp:process-queue {--daemon : Executar continuamente em loop} {--sleep=3 : Segundos entre cada ciclo}';

    protected $description = 'Processa a fila de mensagens do WhatsApp pendentes no banco do Agendae e dispara via gRPC';

    public function handle(GrpcBridgeClient $client): int
    {
        $isDaemon = $this->option('daemon');
        $sleepSeconds = (int) $this->option('sleep');

        $this->info('Iniciando processamento da fila de notificações do WhatsApp...');

        do {
            try {
                $pendingMessages = WhatsAppNotificationQueue::query()
                    ->where('status', 'pending')
                    ->where('scheduled_for', '<=', now())
                    ->orderBy('id', 'asc')
                    ->take(15)
                    ->get();

                if ($pendingMessages->isNotEmpty()) {
                    $this->info("Processando {$pendingMessages->count()} mensagens da fila...");

                    foreach ($pendingMessages as $item) {
                        $item->update(['status' => 'processing', 'attempts' => $item->attempts + 1]);

                        $this->line("Disparando para {$item->recipient_phone} (Tenant: {$item->user_id})...");

                        $result = $client->sendMessage(
                            to: $item->recipient_phone,
                            body: $item->message_body,
                            tenantId: 'default' // or (string) $item->user_id
                        );

                        if (($result['status'] ?? '') === 'sent') {
                            $item->update([
                                'status' => 'sent',
                                'sent_at' => now(),
                                'error_message' => null,
                            ]);
                            $this->info("✓ Enviada com sucesso para {$item->recipient_phone}");
                        } else {
                            $errorMessage = $result['error'] ?? 'Falha ao enviar mensagem';
                            $newStatus = $item->attempts >= 3 ? 'failed' : 'pending';

                            $item->update([
                                'status' => $newStatus,
                                'error_message' => $errorMessage,
                            ]);
                            $this->error("✗ Falha ao enviar para {$item->recipient_phone}: {$errorMessage}");
                        }
                    }
                }
            } catch (Throwable $e) {
                $this->error("Erro no ciclo de processamento da fila: " . $e->getMessage());
            }

            if ($isDaemon) {
                sleep($sleepSeconds);
            }
        } while ($isDaemon);

        return Command::SUCCESS;
    }
}
