<?php

namespace App\Listeners;

use App\Events\WhatsAppMessageReceived;
use App\Services\WhatsApp\WhatsAppInteractiveApprovalService;
use Throwable;

class ProcessInteractiveWhatsAppApproval
{
    public function __construct(
        protected WhatsAppInteractiveApprovalService $service
    ) {}

    public function handle(WhatsAppMessageReceived $event): void
    {
        try {
            $this->service->process($event);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[ProcessInteractiveWhatsAppApproval] Error: ' . $e->getMessage());
        }
    }
}
