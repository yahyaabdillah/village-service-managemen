<?php

return [
    'enabled' => env('WHATSAPP_NOTIFICATIONS_ENABLED', false),
    'bridge_url' => rtrim(env('WHATSAPP_BRIDGE_URL', 'http://127.0.0.1:3100'), '/'),
    'bridge_token' => env('WHATSAPP_BRIDGE_TOKEN'),
    'queue_connection' => env('WHATSAPP_QUEUE_CONNECTION', 'sync'),
    'document_max_size_kb' => (int) env('WHATSAPP_DOCUMENT_MAX_SIZE_KB', 5120),

    // Batas pengiriman untuk melindungi akun WhatsApp bridge dari flagging/ban
    // akibat mengirim pesan terlalu cepat/banyak.
    'rate_limit_per_minute' => (int) env('WHATSAPP_RATE_LIMIT_PER_MINUTE', 20),
    'rate_limit_per_recipient' => (int) env('WHATSAPP_RATE_LIMIT_PER_RECIPIENT', 5),
];
