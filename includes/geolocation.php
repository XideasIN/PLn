<?php
/**
 * Geolocation Service for IP-based Country Detection
 * Automatically detects client location and populates forms accordingly
 */

class GeolocationService {
    private $pdo;
    private $fallback_country = 'USA';
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Get client's real IP address
     */
    public function getClientIP() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',     // CloudFlare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load Balancer/Proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = trim($_SERVER[$key]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    foreach ($ips as $single_ip) {
                        $single_ip = trim($single_ip);
                        if (filter_var($single_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                            return $single_ip;
                        }
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Detect country from IP address using multiple methods
     */
    public function detectCountryFromIP($ip = null) {
        if (!$ip) {
            $ip = $this->getClientIP();
        }
        
        // Skip detection for local/private IPs
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $this->fallback_country;
        }
        
        // Try multiple detection methods
        $country = $this->detectViaCloudFlare() ?: 
                  $this->detectViaGeoIP($ip) ?: 
                  $this->detectViaFreeAPI($ip) ?: 
                  $this->fallback_country;
        
        // Log the detection for audit
        $this->logGeolocationAttempt($ip, $country);
        
        return $country;
    }
    
    /**
     * Detect country via CloudFlare headers
     */
    private function detectViaCloudFlare() {
        if (isset($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $cf_country = strtoupper($_SERVER['HTTP_CF_IPCOUNTRY']);
            return $this->mapCountryCode($cf_country);
        }
        return null;
    }
    
    /**
     * Detect country via GeoIP extension (if available)
     */
    private function detectViaGeoIP($ip) {
        if (function_exists('geoip_country_code_by_name')) {
            try {
                $country_code = geoip_country_code_by_name($ip);
                return $this->mapCountryCode($country_code);
            } catch (Exception $e) {
                error_log("GeoIP detection failed: " . $e->getMessage());
            }
        }
        return null;
    }
    
    /**
     * Detect country via free API service
     */
    private function detectViaFreeAPI($ip) {
        try {
            // Use ip-api.com (free, no key required)
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'user_agent' => 'LoanFlow-GeolocationService/1.0'
                ]
            ]);
            
            $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=countryCode", false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['countryCode'])) {
                    return $this->mapCountryCode($data['countryCode']);
                }
            }
        } catch (Exception $e) {
            error_log("API geolocation failed: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Map country codes to our supported countries
     */
    private function mapCountryCode($code) {
        $code = strtoupper($code);
        
        $mapping = [
            'US' => 'USA',
            'USA' => 'USA',
            'CA' => 'CAN', 
            'CAN' => 'CAN',
            'GB' => 'GBR',
            'UK' => 'GBR',
            'GBR' => 'GBR',
            'AU' => 'AUS',
            'AUS' => 'AUS'
        ];
        
        return $mapping[$code] ?? $this->fallback_country;
    }
    
    /**
     * Get country-specific form data
     */
    public function getCountryFormData($country_code = null) {
        if (!$country_code) {
            $country_code = $this->detectCountryFromIP();
        }
        
        require_once 'countries.php';
        $country_settings = getCountrySettings($country_code);
        
        return [
            'country_code' => $country_code,
            'country_name' => $country_settings['name'],
            'currency' => $country_settings['currency'],
            'currency_symbol' => $country_settings['currency_symbol'],
            'phone_format' => $country_settings['phone_format'],
            'postal_format' => $country_settings['postal_format'],
            'id_format' => $country_settings['id_format'],
            'id_label' => $country_settings['id_label'],
            'timezone' => $country_settings['timezone'],
            'states_provinces' => getStatesProvinces($country_code),
            'state_label' => $country_code === 'USA' ? 'State' : 'Province'
        ];
    }
    
    /**
     * Auto-populate application form based on detected country
     */
    public function getAutoPopulatedFormData($ip = null) {
        $country_code = $this->detectCountryFromIP($ip);
        $form_data = $this->getCountryFormData($country_code);
        
        // Add JavaScript-ready data
        $form_data['js_config'] = [
            'country_code' => $country_code,
            'phone_placeholder' => $this->getPhonePlaceholder($country_code),
            'postal_placeholder' => $this->getPostalPlaceholder($country_code),
            'id_placeholder' => $this->getIdPlaceholder($country_code)
        ];
        
        return $form_data;
    }
    
    /**
     * Get phone number placeholder based on country
     */
    private function getPhonePlaceholder($country_code) {
        $placeholders = [
            'USA' => '(555) 123-4567',
            'CAN' => '(555) 123-4567', 
            'GBR' => '+44 1234 567890',
            'AUS' => '+61 4 1234 5678'
        ];
        
        return $placeholders[$country_code] ?? $placeholders['USA'];
    }
    
    /**
     * Get postal code placeholder based on country
     */
    private function getPostalPlaceholder($country_code) {
        $placeholders = [
            'USA' => '12345-6789',
            'CAN' => 'A1A 1A1',
            'GBR' => 'SW1A 1AA', 
            'AUS' => '2000'
        ];
        
        return $placeholders[$country_code] ?? $placeholders['USA'];
    }
    
    /**
     * Get ID number placeholder based on country
     */
    private function getIdPlaceholder($country_code) {
        $placeholders = [
            'USA' => '123-45-6789',
            'CAN' => '123-456-789',
            'GBR' => 'AB 12 34 56 C',
            'AUS' => '123-456-789'
        ];
        
        return $placeholders[$country_code] ?? $placeholders['USA'];
    }
    
    /**
     * Check if country is allowed for applications
     */
    public function isCountryAllowed($country_code) {
        $allowed_countries = getSystemSetting('allowed_countries', 'USA,CAN,GBR,AUS');
        $allowed_array = explode(',', $allowed_countries);
        
        return in_array($country_code, $allowed_array);
    }
    
    /**
     * Get the detection method used for the last geolocation
     * @return string
     */
    public function getDetectionMethod() {
        return $this->detection_method ?? 'unknown';
    }
    
    /**
     * Get country information
     * @param string $country_code
     * @return array|null
     */
    public function getCountryInfo($country_code) {
        $country_code = strtoupper($country_code);
        
        $countries = [
            'USA' => [
                'name' => 'United States',
                'currency' => 'USD',
                'currency_symbol' => '$',
                'phone_code' => '+1',
                'continent' => 'North America'
            ],
            'CAN' => [
                'name' => 'Canada',
                'currency' => 'CAD',
                'currency_symbol' => 'C$',
                'phone_code' => '+1',
                'continent' => 'North America'
            ],
            'GBR' => [
                'name' => 'United Kingdom',
                'currency' => 'GBP',
                'currency_symbol' => '£',
                'phone_code' => '+44',
                'continent' => 'Europe'
            ],
            'AUS' => [
                 'name' => 'Australia',
                 'currency' => 'AUD',
                 'currency_symbol' => 'A$',
                 'phone_code' => '+61',
                 'continent' => 'Oceania'
             ],
             'NZL' => [
                 'name' => 'New Zealand',
                 'currency' => 'NZD',
                 'currency_symbol' => 'NZ$',
                 'phone_code' => '+64',
                 'continent' => 'Oceania'
             ],
             'IRL' => [
                 'name' => 'Ireland',
                 'currency' => 'EUR',
                 'currency_symbol' => '€',
                 'phone_code' => '+353',
                 'continent' => 'Europe'
             ],
             'ZAF' => [
                 'name' => 'South Africa',
                 'currency' => 'ZAR',
                 'currency_symbol' => 'R',
                 'phone_code' => '+27',
                 'continent' => 'Africa'
             ]
         ];
        
        return $countries[$country_code] ?? null;
    }
    
    /**
     * Get restriction reason for a country
     * @param string $country_code
     * @return string
     */
    public function getRestrictionReason($country_code) {
        if ($this->isCountryAllowed($country_code)) {
            return '';
        }
        
        return 'Loan applications are currently not available in this country due to regulatory restrictions.';
    }
    
    /**
     * Get alternative options for restricted countries
     * @param string $country_code
     * @return array
     */
    public function getAlternativeOptions($country_code) {
        if ($this->isCountryAllowed($country_code)) {
            return [];
        }
        
        return [
            'contact_support' => [
                'title' => 'Contact Support',
                'description' => 'Speak with our support team about alternative options',
                'action' => 'mailto:' . (getCompanySettings()['email'] ?? 'support@loanflow.com')
            ],
            'check_updates' => [
                'title' => 'Check for Updates',
                'description' => 'We are constantly expanding our services to new regions',
                'action' => '/updates'
            ]
        ];
    }
    
    /**
     * Log geolocation attempt for audit
     */
    private function logGeolocationAttempt($ip, $detected_country) {
        try {
            if (function_exists('logAudit')) {
                logAudit('geolocation_detection', 'system', null, null, [
                    'ip_address' => $ip,
                    'detected_country' => $detected_country,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                ]);
            }
        } catch (Exception $e) {
            error_log("Geolocation audit logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get geolocation statistics for admin
     */
    public function getGeolocationStats($days = 30) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    JSON_EXTRACT(new_values, '$.detected_country') as country,
                    COUNT(*) as detection_count,
                    DATE(created_at) as detection_date
                FROM audit_logs 
                WHERE action = 'geolocation_detection' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY country, DATE(created_at)
                ORDER BY detection_date DESC, detection_count DESC
            ");
            
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Geolocation stats query failed: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Helper function to get geolocation service instance
 */
function getGeolocationService() {
    return new GeolocationService();
}

/**
 * Quick function to detect country from current request
 */
function detectClientCountry() {
    $service = new GeolocationService();
    return $service->detectCountryFromIP();
}

/**
 * Get auto-populated form data for current client
 */
function getClientFormData() {
    $service = new GeolocationService();
    return $service->getAutoPopulatedFormData();
}
?>