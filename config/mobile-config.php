<?php
/**
 * Mobile Application Configuration
 * Configuration settings for iOS/Android mobile applications
 */

// Mobile API Configuration
define('MOBILE_API_VERSION', '2.0');
define('MOBILE_API_MIN_VERSION', '1.0');
define('MOBILE_SESSION_TIMEOUT', 1800); // 30 minutes
define('MOBILE_TOKEN_EXPIRY', 86400); // 24 hours

// Mobile App Features
$mobile_features = [
    'biometric_authentication' => true,
    'push_notifications' => true,
    'offline_mode' => true,
    'document_camera' => true,
    'live_chat' => true,
    'payment_integration' => true,
    'loan_calculator' => true,
    'document_upload' => true,
    'geolocation' => false,
    'dark_mode' => true,
    'multi_language' => true,
    'accessibility' => true
];

// File Upload Limits
$mobile_upload_limits = [
    'max_file_size' => 10485760, // 10MB
    'max_files_per_upload' => 5,
    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
    'max_total_size' => 52428800 // 50MB total
];

// Push Notification Configuration
$push_notification_config = [
    'ios' => [
        'enabled' => true,
        'certificate_path' => '../certificates/ios_push.pem',
        'sandbox_mode' => true,
        'bundle_id' => 'com.yourdomain.loanapp'
    ],
    'android' => [
        'enabled' => true,
        'server_key' => 'YOUR_FCM_SERVER_KEY',
        'sender_id' => 'YOUR_FCM_SENDER_ID',
        'package_name' => 'com.yourdomain.loanapp'
    ]
];

// Mobile API Rate Limits
$mobile_rate_limits = [
    'requests_per_hour' => 5000,
    'requests_per_minute' => 100,
    'login_attempts_per_hour' => 10,
    'password_reset_per_day' => 5
];

// Mobile Security Settings
$mobile_security = [
    'require_ssl' => true,
    'api_key_required' => true,
    'token_encryption' => true,
    'request_signing' => false,
    'ip_whitelist' => [],
    'blocked_countries' => [],
    'min_password_length' => 8,
    'require_2fa' => false,
    'session_fingerprinting' => true
];

// Mobile App Store Configuration
$app_store_config = [
    'ios' => [
        'app_id' => '1234567890',
        'app_store_url' => 'https://apps.apple.com/app/id1234567890',
        'current_version' => '2.1.0',
        'minimum_version' => '2.0.0',
        'force_update_version' => '1.9.0'
    ],
    'android' => [
        'package_name' => 'com.yourdomain.loanapp',
        'play_store_url' => 'https://play.google.com/store/apps/details?id=com.yourdomain.loanapp',
        'current_version' => '2.1.0',
        'minimum_version' => '2.0.0',
        'force_update_version' => '1.9.0'
    ]
];

// Mobile Analytics Configuration
$mobile_analytics = [
    'enabled' => true,
    'providers' => [
        'firebase' => [
            'enabled' => true,
            'project_id' => 'your-firebase-project'
        ],
        'mixpanel' => [
            'enabled' => false,
            'token' => 'YOUR_MIXPANEL_TOKEN'
        ],
        'amplitude' => [
            'enabled' => false,
            'api_key' => 'YOUR_AMPLITUDE_KEY'
        ]
    ],
    'events_to_track' => [
        'app_launch',
        'user_login',
        'loan_application_start',
        'loan_application_submit',
        'document_upload',
        'payment_made',
        'support_contact',
        'app_crash'
    ]
];

// Mobile Error Handling
$mobile_error_config = [
    'crash_reporting' => [
        'enabled' => true,
        'service' => 'crashlytics', // crashlytics, sentry, bugsnag
        'api_key' => 'YOUR_CRASHLYTICS_KEY'
    ],
    'error_logging' => [
        'enabled' => true,
        'log_level' => 'error', // debug, info, warning, error
        'max_log_size' => 10485760 // 10MB
    ]
];

// Mobile Performance Monitoring
$mobile_performance = [
    'monitoring_enabled' => true,
    'performance_tracking' => [
        'api_response_times' => true,
        'screen_load_times' => true,
        'network_requests' => true,
        'memory_usage' => false
    ],
    'thresholds' => [
        'api_timeout' => 30, // seconds
        'slow_api_threshold' => 3, // seconds
        'memory_warning_threshold' => 80 // percentage
    ]
];

// Mobile Offline Configuration
$mobile_offline = [
    'enabled' => true,
    'cache_duration' => 86400, // 24 hours
    'offline_features' => [
        'view_loan_status' => true,
        'view_documents' => true,
        'loan_calculator' => true,
        'contact_info' => true,
        'faq' => true
    ],
    'sync_on_connect' => true,
    'max_offline_data' => 104857600 // 100MB
];

// Mobile Accessibility Configuration
$mobile_accessibility = [
    'voice_over_support' => true,
    'high_contrast_mode' => true,
    'large_text_support' => true,
    'screen_reader_support' => true,
    'keyboard_navigation' => true,
    'color_blind_support' => true
];

// Mobile Localization
$mobile_localization = [
    'default_language' => 'en',
    'supported_languages' => ['en', 'es', 'fr'],
    'rtl_support' => false,
    'currency_formats' => [
        'en' => 'USD',
        'es' => 'USD',
        'fr' => 'USD'
    ],
    'date_formats' => [
        'en' => 'MM/dd/yyyy',
        'es' => 'dd/MM/yyyy',
        'fr' => 'dd/MM/yyyy'
    ]
];

// Mobile Deep Linking
$mobile_deep_linking = [
    'enabled' => true,
    'url_scheme' => 'loanapp',
    'universal_links' => [
        'ios' => 'https://yourdomain.com/app',
        'android' => 'https://yourdomain.com/app'
    ],
    'supported_routes' => [
        '/loan/{id}' => 'loan_details',
        '/payment/{id}' => 'payment_details',
        '/document/{id}' => 'document_viewer',
        '/profile' => 'user_profile'
    ]
];

// Mobile Testing Configuration
$mobile_testing = [
    'test_mode' => false,
    'beta_testing' => [
        'enabled' => true,
        'testflight_enabled' => true,
        'play_console_testing' => true
    ],
    'feature_flags' => [
        'new_ui_enabled' => false,
        'advanced_calculator' => true,
        'biometric_login' => true,
        'chat_support' => true
    ]
];

// Export configuration arrays
return [
    'features' => $mobile_features,
    'upload_limits' => $mobile_upload_limits,
    'push_notifications' => $push_notification_config,
    'rate_limits' => $mobile_rate_limits,
    'security' => $mobile_security,
    'app_store' => $app_store_config,
    'analytics' => $mobile_analytics,
    'error_handling' => $mobile_error_config,
    'performance' => $mobile_performance,
    'offline' => $mobile_offline,
    'accessibility' => $mobile_accessibility,
    'localization' => $mobile_localization,
    'deep_linking' => $mobile_deep_linking,
    'testing' => $mobile_testing
];

// Helper functions for mobile configuration
function getMobileConfig($section = null) {
    $config = include __FILE__;
    
    if ($section) {
        return $config[$section] ?? [];
    }
    
    return $config;
}

function isMobileFeatureEnabled($feature) {
    global $mobile_features;
    return $mobile_features[$feature] ?? false;
}

function getMobileUploadLimit($type) {
    global $mobile_upload_limits;
    return $mobile_upload_limits[$type] ?? null;
}

function getMobileRateLimit($type) {
    global $mobile_rate_limits;
    return $mobile_rate_limits[$type] ?? 1000;
}

function isMobileVersionSupported($version, $platform) {
    global $app_store_config;
    
    if (!isset($app_store_config[$platform])) {
        return false;
    }
    
    $config = $app_store_config[$platform];
    return version_compare($version, $config['minimum_version'], '>=');
}

function shouldForceUpdate($version, $platform) {
    global $app_store_config;
    
    if (!isset($app_store_config[$platform])) {
        return false;
    }
    
    $config = $app_store_config[$platform];
    return version_compare($version, $config['force_update_version'], '<=');
}

function getMobilePushConfig($platform) {
    global $push_notification_config;
    return $push_notification_config[$platform] ?? [];
}

function isMobileOfflineFeatureEnabled($feature) {
    global $mobile_offline;
    return $mobile_offline['offline_features'][$feature] ?? false;
}

function getMobileLanguageConfig($language = null) {
    global $mobile_localization;
    
    if ($language) {
        return [
            'currency' => $mobile_localization['currency_formats'][$language] ?? 'USD',
            'date_format' => $mobile_localization['date_formats'][$language] ?? 'MM/dd/yyyy'
        ];
    }
    
    return $mobile_localization;
}

?>