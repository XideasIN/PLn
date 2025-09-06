<?php
/**
 * Country Configuration
 * LoanFlow Personal Loan Management System
 */

// Country settings and formats
$country_settings = [
    'USA' => [
        'name' => 'United States',
        'currency' => 'USD',
        'currency_symbol' => '$',
        'phone_format' => '(###) ###-####',
        'phone_regex' => '/^\(\d{3}\) \d{3}-\d{4}$/',
        'postal_format' => '#####-####',
        'postal_regex' => '/^\d{5}(-\d{4})?$/',
        'tax_id_format' => '###-##-####',
        'tax_id_regex' => '/^\d{3}-\d{2}-\d{4}$/',
        'tax_id_label' => 'SSN',
        'timezone' => 'America/New_York',
        'states_provinces' => [
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
            'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
            'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
            'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
            'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
            'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
            'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
            'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
            'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
            'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
            'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
            'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
            'WI' => 'Wisconsin', 'WY' => 'Wyoming'
        ]
    ],
    'CAN' => [
        'name' => 'Canada',
        'currency' => 'CAD',
        'currency_symbol' => 'C$',
        'phone_format' => '(###) ###-####',
        'phone_regex' => '/^\(\d{3}\) \d{3}-\d{4}$/',
        'postal_format' => 'A#A #A#',
        'postal_regex' => '/^[A-Za-z]\d[A-Za-z] \d[A-Za-z]\d$/',
        'tax_id_format' => '###-###-###',
        'tax_id_regex' => '/^\d{3}-\d{3}-\d{3}$/',
        'tax_id_label' => 'SIN',
        'timezone' => 'America/Toronto',
        'states_provinces' => [
            'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba',
            'NB' => 'New Brunswick', 'NL' => 'Newfoundland and Labrador',
            'NS' => 'Nova Scotia', 'ON' => 'Ontario', 'PE' => 'Prince Edward Island',
            'QC' => 'Quebec', 'SK' => 'Saskatchewan', 'NT' => 'Northwest Territories',
            'NU' => 'Nunavut', 'YT' => 'Yukon'
        ]
    ],
    'GBR' => [
        'name' => 'United Kingdom',
        'currency' => 'GBP',
        'currency_symbol' => '£',
        'phone_format' => '+44 #### ######',
        'phone_regex' => '/^\+44 \d{4} \d{6}$/',
        'postal_format' => 'AA## #AA',
        'postal_regex' => '/^[A-Za-z]{1,2}\d[A-Za-z\d]? \d[A-Za-z]{2}$/',
        'tax_id_format' => 'AA ##### A',
        'tax_id_regex' => '/^[A-Za-z]{2} \d{6} [A-Za-z]$/',
        'tax_id_label' => 'NINO',
        'timezone' => 'Europe/London',
        'states_provinces' => [
            'ENG' => 'England', 'SCT' => 'Scotland', 'WLS' => 'Wales', 'NIR' => 'Northern Ireland'
        ]
    ],
    'AUS' => [
        'name' => 'Australia',
        'currency' => 'AUD',
        'currency_symbol' => 'A$',
        'phone_format' => '+61 # #### ####',
        'phone_regex' => '/^\+61 \d \d{4} \d{4}$/',
        'postal_format' => '####',
        'postal_regex' => '/^\d{4}$/',
        'tax_id_format' => '###-###-###',
        'tax_id_regex' => '/^\d{3}-\d{3}-\d{3}$/',
        'tax_id_label' => 'TFN',
        'timezone' => 'Australia/Sydney',
        'states_provinces' => [
            'NSW' => 'New South Wales', 'VIC' => 'Victoria', 'QLD' => 'Queensland',
            'WA' => 'Western Australia', 'SA' => 'South Australia', 'TAS' => 'Tasmania',
            'ACT' => 'Australian Capital Territory', 'NT' => 'Northern Territory'
        ]
    ]
];

// Payment methods by country
$payment_methods = [
    'USA' => ['wire_transfer', 'crypto'],
    'CAN' => ['e_transfer', 'crypto'],
    'GBR' => ['wire_transfer', 'crypto'],
    'AUS' => ['wire_transfer', 'crypto']
];

// Get country settings
function getCountrySettings($country_code = 'USA') {
    global $country_settings;
    return $country_settings[$country_code] ?? $country_settings['USA'];
}

// Get all supported countries
function getSupportedCountries() {
    global $country_settings;
    return array_keys($country_settings);
}

// Get states/provinces for a country
function getStatesProvinces($country_code = 'USA') {
    $settings = getCountrySettings($country_code);
    return $settings['states_provinces'] ?? [];
}

// Get payment methods for a country
function getPaymentMethods($country_code = 'USA') {
    global $payment_methods;
    return $payment_methods[$country_code] ?? $payment_methods['USA'];
}

// Format phone number according to country
function formatPhoneNumber($phone, $country_code = 'USA') {
    $settings = getCountrySettings($country_code);
    
    // Remove all non-digit characters
    $digits = preg_replace('/\D/', '', $phone);
    
    switch ($country_code) {
        case 'USA':
        case 'CAN':
            if (strlen($digits) == 10) {
                return sprintf('(%s) %s-%s', 
                    substr($digits, 0, 3),
                    substr($digits, 3, 3),
                    substr($digits, 6, 4)
                );
            }
            break;
        case 'GBR':
            if (strlen($digits) >= 10) {
                return sprintf('+44 %s %s',
                    substr($digits, -10, 4),
                    substr($digits, -6)
                );
            }
            break;
        case 'AUS':
            if (strlen($digits) == 9) {
                return sprintf('+61 %s %s %s',
                    substr($digits, 0, 1),
                    substr($digits, 1, 4),
                    substr($digits, 5, 4)
                );
            }
            break;
    }
    
    return $phone; // Return original if formatting fails
}

// Validate phone number format
function validatePhoneNumber($phone, $country_code = 'USA') {
    $settings = getCountrySettings($country_code);
    return preg_match($settings['phone_regex'], $phone);
}

// Validate postal/zip code format
function validatePostalCode($postal, $country_code = 'USA') {
    $settings = getCountrySettings($country_code);
    return preg_match($settings['postal_regex'], $postal);
}

// Validate tax ID format
function validateTaxId($tax_id, $country_code = 'USA') {
    $settings = getCountrySettings($country_code);
    return preg_match($settings['tax_id_regex'], $tax_id);
}

// Get country from IP address (basic implementation)
function getCountryFromIP($ip = null) {
    if (!$ip) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    // This is a basic implementation - in production, use a proper IP geolocation service
    // For now, return USA as default
    return 'USA';
}

// Format currency amount
function formatCurrency($amount, $country_code = 'USA') {
    $settings = getCountrySettings($country_code);
    return $settings['currency_symbol'] . number_format($amount, 2);
}

// Convert currency (basic implementation - in production, use real exchange rates)
function convertCurrency($amount, $from_currency, $to_currency = 'USD') {
    if ($from_currency === $to_currency) {
        return $amount;
    }
    
    // Basic exchange rates (update with real rates in production)
    $rates = [
        'USD' => 1.0,
        'CAD' => 0.75,
        'GBP' => 1.25,
        'AUD' => 0.68
    ];
    
    $usd_amount = $amount / ($rates[$from_currency] ?? 1);
    return $usd_amount * ($rates[$to_currency] ?? 1);
}

// Get timezone for country
function getCountryTimezone($country_code = 'USA') {
    $settings = getCountrySettings($country_code);
    return $settings['timezone'] ?? 'UTC';
}

// Get label for tax ID field
function getTaxIdLabel($country_code = 'USA') {
    $settings = getCountrySettings($country_code);
    return $settings['tax_id_label'] ?? 'Tax ID';
}

// Check if country is supported
function isCountrySupported($country_code) {
    return in_array($country_code, getSupportedCountries());
}
?>
