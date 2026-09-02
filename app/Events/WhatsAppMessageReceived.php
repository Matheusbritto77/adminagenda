<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppMessageReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $tenantId;
    public string $phone;
    public string $message;
    public ?string $messageId;
    public array $metadata;

    public function __construct(
        string $phone,
        string $message,
        string $tenantId = 'default',
        ?string $messageId = null,
        array $metadata = []
    ) {
        $this->phone = $phone;
        $this->message = $message;
        $this->tenantId = $tenantId;
        $this->messageId = $messageId;
        $this->metadata = $metadata;
    }
}
