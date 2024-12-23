<?php
$services = [
    [
        'name' => 'API',
        'status' => 'Operational',
        'lastUpdated' => '2024-12-23 02:24 AM UTC'
    ],
    [
        'name' => 'Webhook Processing',
        'status' => 'Operational',
        'lastUpdated' => '2024-12-23 02:24 AM UTC'
    ],
    [
        'name' => 'Dashboard',
        'status' => 'Operational',
        'lastUpdated' => '2024-12-23 02:24 AM UTC'
    ],
    [
        'name' => 'Documentation',
        'status' => 'Maintenance',
        'lastUpdated' => '2024-12-23 02:24 AM UTC'
    ],
    [
        'name' => 'GitHub Integration (Service)',
        'status' => 'Operational',
        'lastUpdated' => '2024-12-23 02:24 AM UTC'
    ],
    [
        'name' => 'GitHub Workflows',
        'status' => 'Operational',
        'lastUpdated' => '2024-12-23 02:24 AM UTC'
    ]
];

header('Content-Type: application/json');
echo json_encode($services);