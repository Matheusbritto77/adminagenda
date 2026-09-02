<?php

namespace App\Services\WhatsApp;

use App\Events\WhatsAppMessageReceived;
use App\Models\AgendaeUser;
use App\Models\Appointment;
use App\Models\AppointmentFlowLog;
use App\Models\TeamMember;
use App\Models\WhatsAppNotificationQueue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppInteractiveApprovalService
{
    public function __construct(
        protected GrpcBridgeClient $grpcClient
    ) {}

    /**
     * Process incoming WhatsApp messages for interactive SIM/NAO appointment approval
     */
    public function process(WhatsAppMessageReceived $event): ?array
    {
        $rawMessage = trim($event->message);
        $cleanPhone = preg_replace('/\D/', '', $event->phone);

        Log::info("[WhatsApp Event Listener] Processing incoming message from {$cleanPhone}: \"{$rawMessage}\"");

        // 1. Identify Intent (Approve or Reject)
        $isApproval = false;
        $isRejection = false;
        $appointmentId = null;

        $cleanedText = trim(preg_replace('/[^\p{L}\p{N}\s#]/u', '', $rawMessage));

        // 🛑 Anti-Loop Guard 1: Approval/rejection commands are short (e.g. "SIM", "SIM 37", "NAO").
        // Long messages (like notifications or receipts with > 25 chars) are NEVER approval commands!
        if (mb_strlen($cleanedText) > 25) {
            return null;
        }

        if (preg_match('/^(?:SIM|S|APROVAR|OK|1)\b(?:\s*#?(\d+))?$/i', $cleanedText, $matches)) {
            $isApproval = true;
            if (!empty($matches[1])) {
                $appointmentId = (int) $matches[1];
            }
        } elseif (preg_match('/^(?:NAO|NÃO|N|RECUSAR|CANCELAR|2)\b(?:\s*#?(\d+))?$/i', $cleanedText, $matches)) {
            $isRejection = true;
            if (!empty($matches[1])) {
                $appointmentId = (int) $matches[1];
            }
        }

        if (!$isApproval && !$isRejection) {
            return null; // Regular chat message, ignore
        }

        $meta = !empty($event->payload) ? $event->payload : ($event->metadata ?? []);

        // 📌 Extract appointment ID from event payload or quoted text if provided by agenwpp
        if (!$appointmentId && !empty($meta['appointment_id'])) {
            $appointmentId = (int) $meta['appointment_id'];
            Log::info("[WhatsApp Event Listener] Resolved Appointment #{$appointmentId} from Event metadata/payload");
        }

        if (!$appointmentId && !empty($meta['context_info']['quoted_text'])) {
            if (preg_match('/#(\d+)/', $meta['context_info']['quoted_text'], $m)) {
                $appointmentId = (int) $m[1];
                Log::info("[WhatsApp Event Listener] Resolved Appointment #{$appointmentId} from Quoted Text in Event metadata");
            }
        }

        // 2. Locate Target Appointment in Agendae database
        $appointment = null;

        if ($appointmentId) {
            $appointment = Appointment::with(['service', 'teamMember', 'user'])->find($appointmentId);
        }

        // 🛑 Anti-Loop Guard 2: Idempotency. If appointment is already confirmed/cancelled, STOP immediately!
        if ($appointment && $appointment->status !== 'pending') {
            Log::info("[WhatsApp Event Listener] Appointment #{$appointment->id} is already in status '{$appointment->status}'. Halting duplicate approval execution.");
            return ['status' => 'already_processed', 'appointment_id' => $appointment->id];
        }

        // Search by recent notification sent to this phone
        if (!$appointment) {
            $recentQueueItem = WhatsAppNotificationQueue::query()
                ->where(function ($q) use ($cleanPhone) {
                    $q->where('recipient_phone', 'LIKE', "%{$cleanPhone}%")
                      ->orWhere('recipient_phone', 'LIKE', '%' . substr($cleanPhone, -8) . '%');
                })
                ->whereNotNull('appointment_id')
                ->orderBy('id', 'desc')
                ->first();

            if ($recentQueueItem && $recentQueueItem->appointment_id) {
                $candidate = Appointment::with(['service', 'teamMember', 'user'])->find($recentQueueItem->appointment_id);
                if ($candidate && $candidate->status === 'pending') {
                    $appointment = $candidate;
                }
            }
        }

        // Search by company owner / team member phone
        if (!$appointment) {
            $userIds = AgendaeUser::where(function ($q) use ($cleanPhone) {
                $q->where('phone', 'LIKE', "%{$cleanPhone}%")
                  ->orWhere('phone', 'LIKE', '%' . substr($cleanPhone, -8) . '%');
            })->pluck('id')->toArray();

            $teamUserIds = TeamMember::where(function ($q) use ($cleanPhone) {
                $q->where('phone', 'LIKE', "%{$cleanPhone}%")
                  ->orWhere('phone', 'LIKE', '%' . substr($cleanPhone, -8) . '%');
            })->pluck('user_id')->toArray();

            $allCompanyIds = array_unique(array_merge($userIds, $teamUserIds));

            if (!empty($allCompanyIds)) {
                $appointment = Appointment::with(['service', 'teamMember', 'user'])
                    ->whereIn('user_id', $allCompanyIds)
                    ->where('status', 'pending')
                    ->orderBy('id', 'desc')
                    ->first();
            }
        }

        if (!$appointment) {
            Log::info("[WhatsApp Event Listener] No specific pending appointment matches response from {$cleanPhone}. Doing nothing.");
            return null;
        }

        $serviceName = $appointment->service?->name ?? 'Serviço';
        $companyName = $appointment->user?->name ?? 'Estabelecimento';
        $formattedDate = $appointment->appointment_date ? Carbon::parse($appointment->appointment_date)->format('d/m/Y') : '';
        $formattedTime = $appointment->appointment_time ? substr($appointment->appointment_time, 0, 5) : '';

        // 3. Process Approval
        if ($isApproval) {
            $appointment->update([
                'status' => 'confirmed',
            ]);

            try {
                AppointmentFlowLog::create([
                    'user_id' => $appointment->user_id,
                    'appointment_id' => $appointment->id,
                    'event_type' => 'status_changed',
                    'level' => 'success',
                    'channel' => 'whatsapp',
                    'title' => 'Agendamento Aprovado via WhatsApp (gRPC Event)',
                    'description' => "O estabelecimento/profissional respondeu 'SIM' no WhatsApp e aprovou o agendamento de {$appointment->client_name}.",
                    'metadata' => [
                        'phone' => $cleanPhone,
                        'raw_message' => $rawMessage,
                        'new_status' => 'confirmed',
                    ],
                    'created_at' => now(),
                ]);
            } catch (Throwable) {}

            // Enqueue customer confirmation notification
            if (!empty($appointment->client_phone)) {
                $customerMessage = "🎉 *Agendamento Confirmado!*\n\nOlá, {$appointment->client_name}! O seu agendamento em *{$companyName}* foi aprovado com sucesso:\n📅 *Data:* {$formattedDate}\n⏰ *Horário:* {$formattedTime}\n✂️ *Serviço:* {$serviceName}\n\nEsperamos por você! Obrigado pela preferência.";
                
                WhatsAppNotificationQueue::create([
                    'user_id' => $appointment->user_id,
                    'appointment_id' => $appointment->id,
                    'recipient_phone' => $appointment->client_phone,
                    'recipient_name' => $appointment->client_name,
                    'message_type' => 'confirmed',
                    'message_body' => $customerMessage,
                    'status' => 'pending',
                    'scheduled_for' => now(),
                ]);
            }

            // Enqueue notification for assigned professional / team member if different from sender
            if ($appointment->teamMember && !empty($appointment->teamMember->phone)) {
                $teamPhone = preg_replace('/\D/', '', $appointment->teamMember->phone);
                if ($teamPhone && $teamPhone !== $cleanPhone && $teamPhone !== preg_replace('/\D/', '', $appointment->client_phone ?? '')) {
                    $teamMessage = "📅 *Novo Agendamento Confirmado para Você!*\n\n"
                        . "👤 *Cliente:* {$appointment->client_name}\n"
                        . "📅 *Data:* {$formattedDate} às {$formattedTime}\n"
                        . "✂️ *Serviço:* {$serviceName}\n"
                        . "📞 *WhatsApp do Cliente:* {$appointment->client_phone}";

                    WhatsAppNotificationQueue::create([
                        'user_id' => $appointment->user_id,
                        'appointment_id' => $appointment->id,
                        'recipient_phone' => $teamPhone,
                        'recipient_name' => $appointment->teamMember->name,
                        'message_type' => 'confirmed',
                        'message_body' => $teamMessage,
                        'status' => 'pending',
                        'scheduled_for' => now(),
                    ]);
                }
            }

            // ⏰ Schedule Advance Reminder NOW that the appointment is confirmed
            if (!empty($appointment->client_phone) && $appointment->appointment_date && $appointment->appointment_time) {
                try {
                    $appointmentDateTime = Carbon::parse($appointment->appointment_date->format('Y-m-d') . ' ' . $appointment->appointment_time);
                    $reminderTime = $appointmentDateTime->copy()->subHours(2);
                    
                    if ($reminderTime->isFuture()) {
                        $reminderBody = "⏰ *Lembrete de Agendamento - {$companyName}*\n\n"
                            . "Olá, {$appointment->client_name}! Lembramos que você tem um horário marcado hoje:\n"
                            . "📅 *Data:* {$formattedDate}\n"
                            . "⏰ *Horário:* {$formattedTime}\n"
                            . "✂️ *Serviço:* {$serviceName}\n\n"
                            . "Esperamos por você!";

                        WhatsAppNotificationQueue::create([
                            'user_id' => $appointment->user_id,
                            'appointment_id' => $appointment->id,
                            'recipient_phone' => $appointment->client_phone,
                            'recipient_name' => $appointment->client_name,
                            'message_type' => 'reminder',
                            'message_body' => $reminderBody,
                            'status' => 'pending',
                            'scheduled_for' => $reminderTime,
                        ]);
                    }
                } catch (\Throwable) {}
            }

            // Send confirmation receipt to the person who responded SIM
            $replyText = "✅ *Agendamento #{$appointment->id} Aprovado com Sucesso!*\n\n"
                . "👤 *Cliente:* {$appointment->client_name}\n"
                . "📅 *Data:* {$formattedDate} às {$formattedTime}\n"
                . "✂️ *Serviço:* {$serviceName}\n\n"
                . "✨ O cliente foi notificado pelo WhatsApp com a confirmação!";

            $targetRecipient = !empty($meta['jid']) ? $meta['jid'] : $cleanPhone;
            $this->grpcClient->sendMessage($targetRecipient, $replyText, $event->tenantId);

            Log::info("[WhatsApp Event Listener] Appointment #{$appointment->id} APPROVED and confirmed.");

            return [
                'action' => 'approved',
                'appointment_id' => $appointment->id,
                'reply' => $replyText,
            ];
        }

        // 4. Process Rejection
        if ($isRejection) {
            $appointment->update([
                'status' => 'cancelled',
            ]);

            try {
                AppointmentFlowLog::create([
                    'user_id' => $appointment->user_id,
                    'appointment_id' => $appointment->id,
                    'event_type' => 'status_changed',
                    'level' => 'danger',
                    'channel' => 'whatsapp',
                    'title' => 'Agendamento Recusado via WhatsApp (gRPC Event)',
                    'description' => "O estabelecimento/profissional respondeu 'NAO' no WhatsApp e recusou o agendamento de {$appointment->client_name}.",
                    'metadata' => [
                        'phone' => $cleanPhone,
                        'raw_message' => $rawMessage,
                        'new_status' => 'cancelled',
                    ],
                    'created_at' => now(),
                ]);
            } catch (Throwable) {}

            // Cancel any pending scheduled reminders for this appointment
            try {
                WhatsAppNotificationQueue::where('appointment_id', $appointment->id)
                    ->where('message_type', 'reminder')
                    ->where('status', 'pending')
                    ->update(['status' => 'cancelled']);
            } catch (\Throwable) {}

            // Enqueue customer cancellation notification
            if (!empty($appointment->client_phone)) {
                $customerMessage = "🚫 *Agendamento Cancelado*\n\nOlá, {$appointment->client_name}. Lamentamos informar que o seu pedido de agendamento em *{$companyName}* para o dia {$formattedDate} às {$formattedTime} não pôde ser aceito no momento.";
                
                WhatsAppNotificationQueue::create([
                    'user_id' => $appointment->user_id,
                    'appointment_id' => $appointment->id,
                    'recipient_phone' => $appointment->client_phone,
                    'recipient_name' => $appointment->client_name,
                    'message_type' => 'cancelled',
                    'message_body' => $customerMessage,
                    'status' => 'pending',
                    'scheduled_for' => now(),
                ]);
            }

            // Enqueue notification for assigned professional / team member if different from sender
            if ($appointment->teamMember && !empty($appointment->teamMember->phone)) {
                $teamPhone = preg_replace('/\D/', '', $appointment->teamMember->phone);
                if ($teamPhone && $teamPhone !== $cleanPhone && $teamPhone !== preg_replace('/\D/', '', $appointment->client_phone ?? '')) {
                    $teamMessage = "🚫 *Agendamento #{$appointment->id} Cancelado/Recusado*\n\n"
                        . "👤 *Cliente:* {$appointment->client_name}\n"
                        . "📅 *Data:* {$formattedDate} às {$formattedTime}\n"
                        . "✂️ *Serviço:* {$serviceName}";

                    WhatsAppNotificationQueue::create([
                        'user_id' => $appointment->user_id,
                        'appointment_id' => $appointment->id,
                        'recipient_phone' => $teamPhone,
                        'recipient_name' => $appointment->teamMember->name,
                        'message_type' => 'cancelled',
                        'message_body' => $teamMessage,
                        'status' => 'pending',
                        'scheduled_for' => now(),
                    ]);
                }
            }

            // Send rejection receipt to the person who responded NAO
            $replyText = "🚫 *Agendamento #{$appointment->id} Recusado.*\n\n"
                . "👤 *Cliente:* {$appointment->client_name}\n"
                . "📅 *Data:* {$formattedDate} às {$formattedTime}\n\n"
                . "O cliente foi notificado sobre o cancelamento.";

            $targetRecipient = !empty($meta['jid']) ? $meta['jid'] : $cleanPhone;
            $this->grpcClient->sendMessage($targetRecipient, $replyText, $event->tenantId);

            Log::info("[WhatsApp Event Listener] Appointment #{$appointment->id} REJECTED.");

            return [
                'action' => 'rejected',
                'appointment_id' => $appointment->id,
                'reply' => $replyText,
            ];
        }

        return null;
    }
}
