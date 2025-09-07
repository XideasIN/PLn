<?php
// Mock data for Consumer Loan Admin Dashboard

$workspace_metrics = [
    [
        'category' => 'USERS',
        'value' => '75',
        'unit' => '/100',
        'warning_text' => 'Users with too many key results',
        'warning_count' => 1,
        'color' => 'orange'
    ],
    [
        'category' => 'PLANS',
        'value' => '75',
        'unit' => '/100',
        'warning_text' => '1 plan without recent check-ins',
        'warning_count' => 1,
        'color' => 'orange'
    ],
    [
        'category' => 'KEY RESULTS',
        'value' => '80',
        'unit' => '/100',
        'warning_text' => '2 key results without assigned owners',
        'warning_count' => 1,
        'color' => 'orange'
    ],
    [
        'category' => 'TASKS',
        'value' => '75',
        'unit' => '/100',
        'warning_text' => '9 tasks overdue',
        'warning_count' => 1,
        'color' => 'orange'
    ]
];

$audit_scores = [
    [
        'title' => 'User Audit',
        'score' => 75,
        'percentage' => 25,
        'color' => 'teal'
    ],
    [
        'title' => 'Plan Audit',
        'score' => 75,
        'percentage' => 25,
        'color' => 'teal'
    ],
    [
        'title' => 'Key Results Audit',
        'score' => 80,
        'percentage' => 20,
        'color' => 'teal'
    ],
    [
        'title' => 'Tasks Audit',
        'score' => 75,
        'percentage' => 25,
        'color' => 'teal'
    ]
];

$recent_activities = [
    [
        'title' => 'CHECK IN CREATED',
        'color' => 'red',
        'data' => [15, 25, 20, 30, 18, 22, 16],
        'max_value' => 30
    ],
    [
        'title' => 'KEY RESULTS VIEWED',
        'color' => 'purple',
        'data' => [20, 35, 25, 40, 30, 38, 28],
        'max_value' => 40
    ],
    [
        'title' => 'TASKS UPDATED',
        'color' => 'blue',
        'data' => [18, 28, 22, 32, 26, 30, 24],
        'max_value' => 35
    ]
];

$navigation = [
    [
        'section' => 'ADMINISTRATION',
        'name' => 'HOME',
        'items' => [
            ['name' => 'Dashboard', 'href' => 'index.php', 'icon' => 'home'],
            ['name' => 'My loans', 'href' => 'pages/loans.php', 'icon' => 'file-text'],
        ]
    ],
    [
        'section' => 'WORK',
        'name' => 'WORK',
        'items' => [
            ['name' => 'Plans', 'href' => 'pages/plans.php', 'icon' => 'target'],
            ['name' => 'Strategy map', 'href' => 'pages/strategy.php', 'icon' => 'map'],
            ['name' => 'Standup', 'href' => 'pages/standup.php', 'icon' => 'users'],
        ]
    ],
    [
        'section' => 'REPORTS',
        'name' => 'REPORTS',
        'items' => [
            ['name' => 'Insights', 'href' => 'pages/insights.php', 'icon' => 'bar-chart-3'],
            ['name' => 'Dashboards', 'href' => 'pages/dashboards.php', 'icon' => 'layout-dashboard'],
            ['name' => 'Filters', 'href' => 'pages/filters.php', 'icon' => 'filter'],
            ['name' => 'Standup', 'href' => 'pages/reports-standup.php', 'icon' => 'users'],
        ]
    ],
    [
        'section' => 'ORG',
        'name' => 'ORG',
        'items' => [
            ['name' => 'People', 'href' => 'pages/people.php', 'icon' => 'user'],
            ['name' => 'Teams', 'href' => 'pages/teams.php', 'icon' => 'users'],
            ['name' => 'Help and supports', 'href' => 'pages/support.php', 'icon' => 'help-circle'],
        ]
    ]
];
?>