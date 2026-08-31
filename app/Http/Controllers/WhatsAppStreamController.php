<?php

namespace App\Http\Controllers;

use App\Services\WhatsApp\GrpcBridgeClient;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WhatsAppStreamController extends Controller
{
    public function stream(Request $request, GrpcBridgeClient $client): StreamedResponse
    {
        $tenantId = $request->query('tenant_id', 'default');

        return response()->stream(function () use ($client, $tenantId) {
            $lastState = null;
            $lastQr = null;

            // Send initial connection event
            echo "event: init\n";
            echo 'data: ' . json_encode(['connected' => true, 'timestamp' => time()]) . "\n\n";
            @ob_flush();
            @flush();

            $startTime = time();

            // Stream for up to 55 seconds (EventSource will auto-reconnect)
            while (time() - $startTime < 55) {
                if (connection_aborted()) {
                    break;
                }

                $status = $client->getStatus($tenantId);
                $currentState = $status['state'] ?? 'disconnected';
                $currentQr = $status['qr_code'] ?? '';

                if ($currentState !== $lastState || $currentQr !== $lastQr) {
                    $lastState = $currentState;
                    $lastQr = $currentQr;

                    echo "event: status_change\n";
                    echo 'data: ' . json_encode($status) . "\n\n";
                    @ob_flush();
                    @flush();
                } else {
                    // Send ping to keep connection alive
                    echo ": ping\n\n";
                    @ob_flush();
                    @flush();
                }

                usleep(800000); // 800ms check
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
