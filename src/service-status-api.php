<?php
$date = new DateTime('now', new DateTimeZone('UTC'));
$services = [
    [
        'name' => 'API',
        'status' => 'Operational',
        'lastUpdated' => '2025-01-22 12:33 AM UTC'
    ],
    [
        'name' => 'Dashboard',
        'status' => 'Operational',
        'lastUpdated' => $date->format('Y-m-d h:i A T')
    ],
    [
        'name' => 'Documentation',
        'status' => 'Maintenance',
        'lastUpdated' => '2025-01-22 12:33 AM UTC'
    ],
    [
        'name' => 'GitHub Integration (Service)',
        'status' => 'Operational',
        'lastUpdated' => '2025-01-22 12:33 AM UTC'
    ],
    [
        'name' => 'GitHub Workflows',
        'status' => 'Operational',
        'lastUpdated' => '2025-01-22 12:33 AM UTC'
    ],
    [
        'name' => 'Webhook Processing',
        'status' => 'Operational',
        'lastUpdated' => '2025-01-22 12:33 AM UTC'
    ]
];

header('Content-Type: application/json');
echo json_encode($services);
