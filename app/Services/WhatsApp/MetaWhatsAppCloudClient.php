<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaWhatsAppCloudClient
{
    private string $accessToken;
    private string $phoneNumberId;
    private string $apiVersion;
    private string $baseUrl;

    public function __construct(
        ?string $accessToken = null,
        ?string $phoneNumberId = null,
        ?string $apiVersion = null
    ) {
        $this->accessToken = $accessToken ?? (string) config('services.meta_whatsapp.access_token', env('META_WHATSAPP_ACCESS_TOKEN', ''));
        $this->phoneNumberId = $phoneNumberId ?? (string) config('services.meta_whatsapp.phone_number_id', env('META_WHATSAPP_PHONE_NUMBER_ID', ''));
        $this->apiVersion = $apiVersion ?? (string) config('services.meta_whatsapp.api_version', 'v21.0');
        $this->baseUrl = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}";
    }

    /**
     * Check if Meta WhatsApp API credentials are configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->accessToken) && !empty($this->phoneNumberId);
    }

    /**
     * Test connection to Meta Graph API
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Credenciais da Meta Cloud API (META_WHATSAPP_ACCESS_TOKEN e META_WHATSAPP_PHONE_NUMBER_ID) não configuradas.',
            ];
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(5)
                ->get($this->baseUrl);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'display_phone_number' => $data['display_phone_number'] ?? null,
                    'verified_name' => $data['verified_name'] ?? null,
                    'quality_rating' => $data['quality_rating'] ?? null,
                ];
            }

            return [
                'success' => false,
                'status_code' => $response->status(),
                'error' => $response->json()['error']['message'] ?? $response->body(),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send standard text message
     */
    public function sendMessage(string $to, string $body, string $tenantId = 'default'): array
    {
        $cleanPhone = preg_replace('/\D/', '', $to);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $cleanPhone,
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => $body,
            ],
        ];

        return $this->dispatchToMeta($cleanPhone, $payload, $body, $tenantId);
    }

    /**
     * Send interactive message with native clickable buttons (Up to 3 buttons)
     * e.g. [Aprovar] [Recusar] [Remarcar]
     */
    public function sendInteractiveButtons(string $to, string $bodyText, array $buttons, string $tenantId = 'default'): array
    {
        $cleanPhone = preg_replace('/\D/', '', $to);

        $formattedButtons = [];
        foreach (array_slice($buttons, 0, 3) as $btn) {
            $formattedButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => substr((string) $btn['id'], 0, 256),
                    'title' => substr((string) $btn['title'], 0, 20),
                ],
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $cleanPhone,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => $bodyText,
                ],
                'action' => [
                    'buttons' => $formattedButtons,
                ],
            ],
        ];

        return $this->dispatchToMeta($cleanPhone, $payload, $bodyText, $tenantId);
    }

    /**
     * Send pre-approved Message Template (HSM)
     */
    public function sendTemplate(string $to, string $templateName, string $languageCode = 'pt_BR', array $components = [], string $tenantId = 'default'): array
    {
        $cleanPhone = preg_replace('/\D/', '', $to);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $cleanPhone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
                'components' => $components,
            ],
        ];

        return $this->dispatchToMeta($cleanPhone, $payload, "Template: {$templateName}", $tenantId);
    }

    /**
     * Dispatch request to Meta Graph API
     */
    private function dispatchToMeta(string $phone, array $payload, string $summaryBody, string $tenantId): array
    {
        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(8)
                ->post("{$this->baseUrl}/messages", $payload);

            $resData = $response->json();

            if ($response->successful()) {
                $messageId = $resData['messages'][0]['id'] ?? null;

                try {
                    WhatsAppLog::create([
                        'tenant_id' => $tenantId,
                        'direction' => 'outbound',
                        'phone' => $phone,
                        'status' => 'sent',
                        'message_id' => $messageId,
                        'message_body' => $summaryBody,
                        'metadata' => [
                            'driver' => 'meta_cloud_api',
                            'response' => $resData,
                        ],
                    ]);
                } catch (Throwable $e) {}

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'response' => $resData,
                ];
            }

            $errorMessage = $resData['error']['message'] ?? $response->body();
            Log::error("[Meta Cloud API] Failed to send message to {$phone}: {$errorMessage}");

            try {
                WhatsAppLog::create([
                    'tenant_id' => $tenantId,
                    'direction' => 'outbound',
                    'phone' => $phone,
                    'status' => 'failed',
                    'message_body' => $summaryBody,
                    'error_message' => $errorMessage,
                    'metadata' => [
                        'driver' => 'meta_cloud_api',
                        'status_code' => $response->status(),
                    ],
                ]);
            } catch (Throwable $e) {}

            return [
                'success' => false,
                'status_code' => $response->status(),
                'error' => $errorMessage,
            ];
        } catch (Throwable $e) {
            Log::error("[Meta Cloud API] Exception sending message to {$phone}: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
