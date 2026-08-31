<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\WhatsAppNotificationQueue;
use App\Services\WhatsApp\GrpcBridgeClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ListenWhatsAppInbound extends Command
{
    protected $signature = 'whatsapp:listen-inbound';

    protected $description = 'Escuta mensagens recebidas no WhatsApp e processa aprovação/recusa de agendamentos por texto (SIM / NAO)';

    public function handle(GrpcBridgeClient $grpcClient): int
    {
        $this->info('Iniciando escuta de mensagens recebidas no WhatsApp (Inbound Approval Bot)...');

        try {
            Redis::subscribe(['whatsapp:messages'], function (string $rawMessage) use ($grpcClient) {
                try {
                    $payload = json_decode($rawMessage, true);
                    if (!isset($payload['messages']) || !is_array($payload['messages'])) {
                        return;
                    }

                    foreach ($payload['messages'] as $msg) {
                        if ($msg['key']['fromMe'] ?? false) {
                            continue;
                        }

                        $senderJid = $msg['key']['remoteJid'] ?? '';
                        if (empty($senderJid) || str_contains($senderJid, '@g.us')) {
                            continue; // Ignorar grupos
                        }

                        $phone = preg_replace('/[^0-9]/', '', explode('@', $senderJid)[0]);
                        $text = trim(strtolower(
                            $msg['message']['conversation'] ??
                            $msg['message']['extendedTextMessage']['text'] ??
                            ''
                        ));

                        if (empty($text)) {
                            continue;
                        }

                        $this->line("[Inbound] Mensagem recebida de {$phone}: '{$text}'");

                        $isApproval = in_array($text, ['sim', 'confirmo', 'confirmar', 'aprovar', 'aprovado', 'ok', 'yes', '1', 'positivo', 'aceito', 'aceitar'], true);
                        $isRejection = in_array($text, ['nao', 'não', 'recusar', 'recusado', 'cancelar', 'cancelado', 'no', '2', 'negativo'], true);

                        if (!$isApproval && !$isRejection) {
                            continue;
                        }

                        // Buscar agendamento pendente mais recente
                        $appointment = Appointment::query()
                            ->where('status', 'pending')
                            ->with(['service', 'teamMember', 'user'])
                            ->latest('id')
                            ->first();

                        if (!$appointment) {
                            $this->line("[Inbound] Nenhum agendamento pendente encontrado.");
                            continue;
                        }

                        $serviceName = $appointment->service?->name ?? 'Serviço';
                        $dateFormatted = $appointment->appointment_date ? $appointment->appointment_date->format('d/m/Y') : '';
                        $timeFormatted = $appointment->appointment_time;

                        if ($isApproval) {
                            $this->info("[Inbound] Aprovando agendamento #{$appointment->id} do cliente {$appointment->client_name}...");

                            $appointment->update(['status' => 'confirmed']);

                            // Enfileirar notificação para o cliente
                            WhatsAppNotificationQueue::create([
                                'user_id' => $appointment->user_id,
                                'appointment_id' => $appointment->id,
                                'recipient_phone' => $appointment->client_phone,
                                'recipient_name' => $appointment->client_name,
                                'message_type' => 'confirmed',
                                'message_body' => "🎉 *Agendamento Aprovado!*\n\nOlá, {$appointment->client_name}! Seu agendamento foi aprovado pelo profissional com sucesso:\n📅 *Data:* {$dateFormatted} às {$timeFormatted}\n✂️ *Serviço:* {$serviceName}\n\nEsperamos por você!",
                                'status' => 'pending',
                                'scheduled_for' => now(),
                            ]);

                            // Resposta para quem aprovou
                            $grpcClient->sendMessage(
                                to: $phone,
                                body: "✅ *Agendamento Aprovado com Sucesso!*\n\nO cliente *{$appointment->client_name}* ({$serviceName} em {$dateFormatted} às {$timeFormatted}) foi notificado da confirmação.",
                                tenantId: 'default'
                            );
                        } elseif ($isRejection) {
                            $this->warn("[Inbound] Recusando agendamento #{$appointment->id} do cliente {$appointment->client_name}...");

                            $appointment->update(['status' => 'cancelled']);

                            // Enfileirar cancelamento para o cliente
                            WhatsAppNotificationQueue::create([
                                'user_id' => $appointment->user_id,
                                'appointment_id' => $appointment->id,
                                'recipient_phone' => $appointment->client_phone,
                                'recipient_name' => $appointment->client_name,
                                'message_type' => 'cancelled',
                                'message_body' => "⚠️ *Aviso de Agendamento Cancelado*\n\nOlá, {$appointment->client_name}! Informamos que seu pedido de agendamento para {$dateFormatted} às {$timeFormatted} ({$serviceName}) foi cancelado pelo estabelecimento.",
                                'status' => 'pending',
                                'scheduled_for' => now(),
                            ]);

                            // Resposta para quem recusou
                            $grpcClient->sendMessage(
                                to: $phone,
                                body: "❌ *Agendamento Recusado!*\n\nO agendamento do cliente *{$appointment->client_name}* foi cancelado e ele foi notificado.",
                                tenantId: 'default'
                            );
                        }
                    }
                } catch (Throwable $err) {
                    $this->error("[Inbound] Erro ao processar mensagem: " . $err->getMessage());
                }
            });
        } catch (Throwable $e) {
            $this->error("[Inbound] Erro na conexão Redis: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
