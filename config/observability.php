<?php

return [
    'grafana_url' => env(
        'GRAFANA_DASHBOARD_URL',
        'http://127.0.0.1:3000/d/village-service-logs/village-service-application-logs',
    ),
];
