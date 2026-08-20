<?php

return [
    'uploads' => [
        'scan_enabled' => env('UPLOAD_SCAN_ENABLED', false),
        'scanner_command' => env('UPLOAD_SCANNER_COMMAND', 'clamscan --no-summary --infected'),
        'scanner_timeout' => (int) env('UPLOAD_SCANNER_TIMEOUT', 30),
        'blocked_signatures' => array_filter(array_map('trim', explode(',', env('UPLOAD_BLOCKED_SIGNATURES', 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')))),
    ],
];
