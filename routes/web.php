<?php

use App\Events\WhatsAppMessageReceived;
use App\Services\WhatsApp\WhatsAppInteractiveApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

/**
 * Direct Internal Bridge for Inbound WhatsApp Events from agenwpp
 */
Route::post('/api/whatsapp/inbound-event', function (Request $request, WhatsAppInteractiveApprovalService $approvalService) {
    $payload = $request->all();
    $phone = $payload['phone'] ?? '';
    $text = $payload['message'] ?? '';
    $tenantId = $payload['tenant_id'] ?? 'default';
    $messageId = $payload['message_id'] ?? null;

    Log::info("[HTTP Inbound Event] Received message from {$phone}: \"{$text}\" (Tenant: {$tenantId})", $payload);

    if ($phone && $text) {
        $event = new WhatsAppMessageReceived($phone, $text, $tenantId, $messageId, $payload);
        event($event);

        // Directly execute approval service synchronously as well for instant response
        try {
            $result = $approvalService->process($event);
            return response()->json([
                'status' => 'success',
                'approval_result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('[HTTP Inbound Approval Error] ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    return response()->json(['status' => 'ignored']);
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
