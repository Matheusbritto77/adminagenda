<?php

return [
    'grpc_host' => env('WHATSAPP_GRPC_HOST', '127.0.0.1'),
    'grpc_port' => env('WHATSAPP_GRPC_PORT', 50051),
    'grpc_tls' => env('WHATSAPP_GRPC_TLS', false),
];
