<?php
$date = new DateTime('now', new DateTimeZone('UTC'));
$services = [
    [
        'name' => 'API',
        'status' => 'Operational',
        'lastUpdated' => '2025-01-16 22:39 PM UTC'
    ],
    [
        'name' => 'Dashboard',
        'status' => 'Operational',
        'lastUpdated' => $date->format('Y-m-d h:i A T')
    ],
    [
        'name' => 'Documentation',
        'status' => 'Maintenance',
        'lastUpdated' => '2025-01-16 22:39 PM UTC'
    ],
    [
        'name' => 'GitHub Integration (Service)',
        'status' => 'Operational',
        'lastUpdated' => '2025-01-16 22:39 PM UTC'
    ],
    [
        'name' => 'GitHub Workflows',
        'status' => 'Operational',
        'lastUpdated' => '2025-01-16 22:39 PM UTC'
    ],
    [
        'name' => 'Webhook Processing',
        'status' => 'Operational',
        'lastUpdated' => '2025-01-16 22:39 PM UTC'
    ]
];

header('Content-Type: application/json');
echo json_encode($services);
