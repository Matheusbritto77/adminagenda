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

        return response()->json([
            'status' => 'success',
            'message' => 'Event dispatched and processed via listener',
        ]);
    }

    return response()->json(['status' => 'ignored']);
});

/**
 * Meta Official WhatsApp Cloud API Webhook Endpoints
 * Verification: GET /api/whatsapp/meta-webhook
 * Inbound Events & Replies: POST /api/whatsapp/meta-webhook
 */
Route::get('/whatsapp/meta-webhook', [\App\Http\Controllers\Api\MetaWhatsAppWebhookController::class, 'verify']);
Route::post('/whatsapp/meta-webhook', [\App\Http\Controllers\Api\MetaWhatsAppWebhookController::class, 'handle']);

