<?php

namespace App\Http\Controllers\Api;

use App\Events\WhatsAppMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppLog;
use App\Models\WhatsAppNotificationQueue;
use App\Services\WhatsApp\WhatsAppInteractiveApprovalService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaWhatsAppWebhookController extends Controller
{
    /**
     * Webhook Verification Challenge (GET)
     * Meta sends a GET request to verify endpoint ownership.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        $expectedToken = config('services.meta_whatsapp.verify_token');

        if ($mode === 'subscribe' && $token && hash_equals((string) $expectedToken, (string) $token)) {
            Log::info('[Meta Webhook] Verification challenge accepted successfully.');
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('[Meta Webhook] Verification challenge failed. Invalid verify token provided.', [
            'received_token' => $token,
            'mode' => $mode,
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Webhook Inbound Events (POST)
     * Receives message replies, button clicks, and delivery statuses from Meta.
     */
    public function handle(Request $request, WhatsAppInteractiveApprovalService $approvalService)
    {
        $payload = $request->all();

        // 1. Verify that this is a WhatsApp Business Account notification
        if (($payload['object'] ?? '') !== 'whatsapp_business_account') {
            return response()->json(['status' => 'ignored'], 200);
        }

        Log::info('[Meta Webhook] Event payload received', $payload);

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                // ----------------------------------------------------
                // A. Message Status Updates (sent, delivered, read, failed)
                // ----------------------------------------------------
                if (!empty($value['statuses'])) {
                    $this->handleStatusUpdates($value['statuses']);
                }

                // ----------------------------------------------------
                // B. Inbound Messages & Interactive Button Clicks
                // ----------------------------------------------------
                if (!empty($value['messages'])) {
                    foreach ($value['messages'] as $msg) {
                        $this->processInboundMessage($msg, $value, $approvalService);
                    }
                }
            }
        }

        // Meta requires an immediate HTTP 200 response to acknowledge receipt
        return response()->json(['status' => 'EVENT_RECEIVED'], 200);
    }

    /**
     * Process an incoming message or button click
     */
    protected function processInboundMessage(array $msg, array $value, WhatsAppInteractiveApprovalService $approvalService): void
    {
        $sender = $msg['from'] ?? '';
        $msgId = $msg['id'] ?? null;
        $type = $msg['type'] ?? 'text';
        $text = '';
        $appointmentId = null;

        // Extract message body or interactive button payload
        if ($type === 'text') {
            $text = trim($msg['text']['body'] ?? '');
        } elseif ($type === 'button') {
            // Quick reply button
            $text = trim($msg['button']['payload'] ?? $msg['button']['text'] ?? '');
        } elseif ($type === 'interactive') {
            $interactive = $msg['interactive'] ?? [];
            if (($interactive['type'] ?? '') === 'button_reply') {
                $text = trim($interactive['button_reply']['id'] ?? $interactive['button_reply']['title'] ?? '');
            } elseif (($interactive['type'] ?? '') === 'list_reply') {
                $text = trim($interactive['list_reply']['id'] ?? $interactive['list_reply']['title'] ?? '');
            }
        }

        // Normalize payload like "SIM_39" or "REMARCAR_39"
        if (preg_match('/^(?:SIM|APROVAR)[_-](\d+)$/i', $text, $m)) {
            $appointmentId = (int) $m[1];
            $text = "SIM #{$appointmentId}";
        } elseif (preg_match('/^(?:NAO|RECUSAR|CANCELAR)[_-](\d+)$/i', $text, $m)) {
            $appointmentId = (int) $m[1];
            $text = "NAO #{$appointmentId}";
        } elseif (preg_match('/^(?:REMARCAR|REAGENDAR)[_-](\d+)$/i', $text, $m)) {
            $appointmentId = (int) $m[1];
            $text = "REMARCAR #{$appointmentId}";
        } elseif (preg_match('/#(\d+)/', $text, $m)) {
            $appointmentId = (int) $m[1];
        }

        Log::info("[Meta Webhook] Incoming message: from={$sender}, type={$type}, text=\"{$text}\", appt_id=" . ($appointmentId ?? 'none'));

        // Register in WhatsApp Logs table
        try {
            WhatsAppLog::create([
                'tenant_id' => 'default',
                'direction' => 'inbound',
                'phone' => $sender,
                'status' => 'received',
                'message_id' => $msgId,
                'message_body' => $text,
                'metadata' => [
                    'source' => 'meta_cloud_api',
                    'type' => $type,
                    'raw' => $msg,
                ],
            ]);
        } catch (Throwable $e) {}

        if (!$text) return;

        // Build metadata for interactive approval service
        $metadata = [
            'source' => 'meta_cloud_api',
            'phone' => $sender,
            'message' => $text,
            'appointment_id' => $appointmentId,
            'message_id' => $msgId,
            'jid' => "{$sender}@s.whatsapp.net",
            'context_info' => [
                'quoted_text' => $msg['context']['id'] ?? null,
            ],
            'raw_payload' => $msg,
        ];

        // Dispatch domain event
        $event = new WhatsAppMessageReceived(
            phone: $sender,
            message: $text,
            tenantId: 'default',
            messageId: $msgId,
            metadata: $metadata
        );

        event($event);

        // Synchronously process approval/rejection/rescheduling
        try {
            $result = $approvalService->process($event);
            Log::info('[Meta Webhook] Interactive approval handled result', ['result' => $result]);
        } catch (Throwable $e) {
            Log::error('[Meta Webhook] Interactive approval error: ' . $e->getMessage());
        }
    }

    /**
     * Handle delivery receipt and read status updates from Meta
     */
    protected function handleStatusUpdates(array $statuses): void
    {
        foreach ($statuses as $st) {
            $wamid = $st['id'] ?? null;
            $status = $st['status'] ?? null; // sent, delivered, read, failed
            $recipient = $st['recipient_id'] ?? null;

            if (!$wamid || !$status) continue;

            Log::debug("[Meta Webhook] Status update for {$wamid}: {$status} (recipient: {$recipient})");

            // Update in WhatsApp logs
            try {
                WhatsAppLog::where('message_id', $wamid)->update([
                    'status' => $status,
                ]);
            } catch (Throwable $e) {}

            // Update queue item status if read/delivered
            if (in_array($status, ['delivered', 'read'])) {
                try {
                    WhatsAppNotificationQueue::where('recipient_phone', 'LIKE', "%{$recipient}%")
                        ->where('status', 'pending')
                        ->orderByDesc('id')
                        ->limit(1)
                        ->update(['status' => 'sent']);
                } catch (Throwable $e) {}
            }
        }
    }
}
