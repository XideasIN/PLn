<?php
/**
 * CAPTCHA Protection System
 * LoanFlow Personal Loan Management System
 * Supports reCAPTCHA v3, hCaptcha, and custom CAPTCHA
 */

class CaptchaManager {
    
    private static $providers = [
        'recaptcha' => 'Google reCAPTCHA v3',
        'hcaptcha' => 'hCaptcha',
        'custom' => 'Custom Math CAPTCHA'
    ];
    
    /**
     * Get enabled CAPTCHA provider
     */
    public static function getProvider() {
        return getSystemSetting('captcha_provider', 'custom');
    }
    
    /**
     * Check if CAPTCHA is enabled
     */
    public static function isEnabled() {
        return getSystemSetting('captcha_enabled', '1') === '1';
    }
    
    /**
     * Get CAPTCHA site key
     */
    public static function getSiteKey() {
        $provider = self::getProvider();
        switch ($provider) {
            case 'recaptcha':
                return getSystemSetting('recaptcha_site_key', '');
            case 'hcaptcha':
                return getSystemSetting('hcaptcha_site_key', '');
            default:
                return '';
        }
    }
    
    /**
     * Get CAPTCHA secret key
     */
    private static function getSecretKey() {
        $provider = self::getProvider();
        switch ($provider) {
            case 'recaptcha':
                return getSystemSetting('recaptcha_secret_key', '');
            case 'hcaptcha':
                return getSystemSetting('hcaptcha_secret_key', '');
            default:
                return '';
        }
    }
    
    /**
     * Generate custom math CAPTCHA
     */
    public static function generateMathCaptcha() {
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $operator = rand(0, 1) ? '+' : '-';
        
        if ($operator === '+') {
            $answer = $num1 + $num2;
            $question = "$num1 + $num2";
        } else {
            // Ensure positive result
            if ($num1 < $num2) {
                $temp = $num1;
                $num1 = $num2;
                $num2 = $temp;
            }
            $answer = $num1 - $num2;
            $question = "$num1 - $num2";
        }
        
        // Store in session
        $_SESSION['captcha_answer'] = $answer;
        $_SESSION['captcha_time'] = time();
        
        return [
            'question' => $question,
            'answer' => $answer
        ];
    }
    
    /**
     * Generate CAPTCHA HTML
     */
    public static function generateHTML($form_id = '') {
        if (!self::isEnabled()) {
            return '';
        }
        
        $provider = self::getProvider();
        $site_key = self::getSiteKey();
        
        switch ($provider) {
            case 'recaptcha':
                if (!$site_key) return '';
                return '<div class="g-recaptcha" data-sitekey="' . htmlspecialchars($site_key) . '" data-callback="onRecaptchaSuccess"></div>';
                
            case 'hcaptcha':
                if (!$site_key) return '';
                return '<div class="h-captcha" data-sitekey="' . htmlspecialchars($site_key) . '"></div>';
                
            case 'custom':
            default:
                $captcha = self::generateMathCaptcha();
                return '
                    <div class="captcha-container">
                        <label for="captcha_answer" class="form-label">Security Question:</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <strong>' . htmlspecialchars($captcha['question']) . ' = ?</strong>
                            </span>
                            <input type="number" class="form-control" id="captcha_answer" name="captcha_answer" 
                                   required placeholder="Answer" min="0" max="100">
                        </div>
                        <small class="form-text text-muted">Please solve the math problem above</small>
                    </div>';
        }
    }
    
    /**
     * Generate CAPTCHA JavaScript
     */
    public static function generateJS($form_id = '', $callback = '') {
        if (!self::isEnabled()) {
            return '';
        }
        
        $provider = self::getProvider();
        $site_key = self::getSiteKey();
        
        switch ($provider) {
            case 'recaptcha':
                if (!$site_key) return '';
                return "
                    <script src='https://www.google.com/recaptcha/api.js' async defer></script>
                    <script>
                        function onRecaptchaSuccess(token) {
                            console.log('reCAPTCHA verified');
                            " . ($callback ? $callback . '();' : '') . "
                        }
                        
                        function executeRecaptcha(action) {
                            return new Promise((resolve, reject) => {
                                grecaptcha.ready(function() {
                                    grecaptcha.execute('" . $site_key . "', {action: action}).then(function(token) {
                                        resolve(token);
                                    }).catch(function(error) {
                                        reject(error);
                                    });
                                });
                            });
                        }
                    </script>";
                    
            case 'hcaptcha':
                if (!$site_key) return '';
                return "
                    <script src='https://js.hcaptcha.com/1/api.js' async defer></script>
                    <script>
                        function onHcaptchaSuccess(token) {
                            console.log('hCaptcha verified');
                            " . ($callback ? $callback . '();' : '') . "
                        }
                    </script>";
                    
            default:
                return '';
        }
    }
    
    /**
     * Verify CAPTCHA response
     */
    public static function verify($response = null, $action = null) {
        if (!self::isEnabled()) {
            return true; // If CAPTCHA is disabled, always pass
        }
        
        $provider = self::getProvider();
        
        switch ($provider) {
            case 'recaptcha':
                return self::verifyRecaptcha($response, $action);
                
            case 'hcaptcha':
                return self::verifyHcaptcha($response);
                
            case 'custom':
            default:
                return self::verifyMathCaptcha($response);
        }
    }
    
    /**
     * Verify Google reCAPTCHA
     */
    private static function verifyRecaptcha($token, $action = null) {
        $secret_key = self::getSecretKey();
        if (!$secret_key || !$token) {
            return false;
        }
        
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => $secret_key,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 10
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            error_log('reCAPTCHA verification failed: Network error');
            return false;
        }
        
        $json = json_decode($result, true);
        
        if (!isset($json['success']) || !$json['success']) {
            error_log('reCAPTCHA verification failed: ' . json_encode($json));
            return false;
        }
        
        // Check score for reCAPTCHA v3
        if (isset($json['score'])) {
            $min_score = floatval(getSystemSetting('recaptcha_min_score', '0.5'));
            if ($json['score'] < $min_score) {
                error_log('reCAPTCHA score too low: ' . $json['score']);
                return false;
            }
        }
        
        // Check action if provided
        if ($action && isset($json['action']) && $json['action'] !== $action) {
            error_log('reCAPTCHA action mismatch: ' . $json['action'] . ' vs ' . $action);
            return false;
        }
        
        return true;
    }
    
    /**
     * Verify hCaptcha
     */
    private static function verifyHcaptcha($token) {
        $secret_key = self::getSecretKey();
        if (!$secret_key || !$token) {
            return false;
        }
        
        $url = 'https://hcaptcha.com/siteverify';
        $data = [
            'secret' => $secret_key,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 10
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            error_log('hCaptcha verification failed: Network error');
            return false;
        }
        
        $json = json_decode($result, true);
        
        if (!isset($json['success']) || !$json['success']) {
            error_log('hCaptcha verification failed: ' . json_encode($json));
            return false;
        }
        
        return true;
    }
    
    /**
     * Verify custom math CAPTCHA
     */
    private static function verifyMathCaptcha($answer) {
        // Check if session has captcha data
        if (!isset($_SESSION['captcha_answer']) || !isset($_SESSION['captcha_time'])) {
            return false;
        }
        
        // Check if captcha is not too old (5 minutes max)
        if (time() - $_SESSION['captcha_time'] > 300) {
            unset($_SESSION['captcha_answer'], $_SESSION['captcha_time']);
            return false;
        }
        
        // Verify answer
        $correct = intval($_SESSION['captcha_answer']) === intval($answer);
        
        // Clear captcha data after verification
        unset($_SESSION['captcha_answer'], $_SESSION['captcha_time']);
        
        return $correct;
    }
    
    /**
     * Get CAPTCHA error message
     */
    public static function getErrorMessage() {
        return __('captcha_verification_failed');
    }
    
    /**
     * Check if form should have CAPTCHA
     */
    public static function shouldProtectForm($form_type) {
        $protected_forms = explode(',', getSystemSetting('captcha_protected_forms', 'login,register,contact,loan_application'));
        return in_array($form_type, $protected_forms);
    }
    
    /**
     * Get available providers
     */
    public static function getProviders() {
        return self::$providers;
    }
    
    /**
     * Test CAPTCHA configuration
     */
    public static function testConfiguration() {
        $provider = self::getProvider();
        $site_key = self::getSiteKey();
        $secret_key = self::getSecretKey();
        
        $results = [
            'provider' => $provider,
            'enabled' => self::isEnabled(),
            'configured' => false,
            'errors' => []
        ];
        
        switch ($provider) {
            case 'recaptcha':
                if (!$site_key) {
                    $results['errors'][] = 'reCAPTCHA site key not configured';
                }
                if (!$secret_key) {
                    $results['errors'][] = 'reCAPTCHA secret key not configured';
                }
                $results['configured'] = !empty($site_key) && !empty($secret_key);
                break;
                
            case 'hcaptcha':
                if (!$site_key) {
                    $results['errors'][] = 'hCaptcha site key not configured';
                }
                if (!$secret_key) {
                    $results['errors'][] = 'hCaptcha secret key not configured';
                }
                $results['configured'] = !empty($site_key) && !empty($secret_key);
                break;
                
            case 'custom':
                $results['configured'] = true; // Custom CAPTCHA doesn't need external configuration
                break;
                
            default:
                $results['errors'][] = 'Unknown CAPTCHA provider';
        }
        
        return $results;
    }
}

// Helper function for easy CAPTCHA generation
function generateCaptcha($form_id = '') {
    return CaptchaManager::generateHTML($form_id);
}

// Helper function for CAPTCHA verification
function verifyCaptcha($response = null, $action = null) {
    return CaptchaManager::verify($response, $action);
}
