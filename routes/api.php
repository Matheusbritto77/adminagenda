<?php

use App\Events\WhatsAppMessageReceived;
use App\Services\WhatsApp\WhatsAppInteractiveApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Direct Internal Bridge for Inbound WhatsApp Events from agenwpp
 * Endpoint: POST /api/whatsapp/inbound-event
 */
Route::post('/whatsapp/inbound-event', function (Request $request, WhatsAppInteractiveApprovalService $approvalService) {
    $payload = $request->all();
    $phone = $payload['phone'] ?? '';
    $text = $payload['message'] ?? '';
    $tenantId = $payload['tenant_id'] ?? 'default';
    $messageId = $payload['message_id'] ?? null;

    Log::info("[API Inbound Event] Processing message from {$phone}: \"{$text}\" (Tenant: {$tenantId})", $payload);

    if ($phone && $text) {
        $event = new WhatsAppMessageReceived($phone, $text, $tenantId, $messageId, $payload);
        event($event);

        try {
            $result = $approvalService->process($event);
            return response()->json([
                'status' => 'success',
                'approval_result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('[API Inbound Approval Error] ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    return response()->json(['status' => 'ignored']);
});
