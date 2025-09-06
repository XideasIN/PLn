<?php
/**
 * Multi-Language System
 * LoanFlow Personal Loan Management System
 * Supports English, Spanish, French with auto-translation
 */

class LanguageManager {
    
    private static $current_language = 'en';
    private static $translations = [];
    private static $supported_languages = [
        'en' => ['name' => 'English', 'flag' => '🇺🇸', 'code' => 'en-US'],
        'es' => ['name' => 'Español', 'flag' => '🇪🇸', 'code' => 'es-ES'], 
        'fr' => ['name' => 'Français', 'flag' => '🇫🇷', 'code' => 'fr-FR']
    ];
    
    public static function init() {
        // Get language from session, cookie, or browser
        self::$current_language = self::detectLanguage();
        self::loadTranslations();
    }
    
    private static function detectLanguage() {
        // 1. Check session
        if (isset($_SESSION['language']) && array_key_exists($_SESSION['language'], self::$supported_languages)) {
            return $_SESSION['language'];
        }
        
        // 2. Check cookie
        if (isset($_COOKIE['language']) && array_key_exists($_COOKIE['language'], self::$supported_languages)) {
            $_SESSION['language'] = $_COOKIE['language'];
            return $_COOKIE['language'];
        }
        
        // 3. Check user preference from database
        if (isset($_SESSION['user_id'])) {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT language FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                if ($user && array_key_exists($user['language'], self::$supported_languages)) {
                    $_SESSION['language'] = $user['language'];
                    return $user['language'];
                }
            } catch (Exception $e) {
                error_log("Language detection error: " . $e->getMessage());
            }
        }
        
        // 4. Detect from browser
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browser_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            if (array_key_exists($browser_lang, self::$supported_languages)) {
                return $browser_lang;
            }
        }
        
        // 5. Default to English
        return 'en';
    }
    
    private static function loadTranslations() {
        $lang_file = __DIR__ . '/../languages/' . self::$current_language . '.php';
        if (file_exists($lang_file)) {
            self::$translations = include $lang_file;
        } else {
            // Load English as fallback
            $en_file = __DIR__ . '/../languages/en.php';
            if (file_exists($en_file)) {
                self::$translations = include $en_file;
            }
        }
    }
    
    public static function setLanguage($language) {
        if (array_key_exists($language, self::$supported_languages)) {
            self::$current_language = $language;
            $_SESSION['language'] = $language;
            
            // Set cookie for 1 year
            setcookie('language', $language, time() + (365 * 24 * 60 * 60), '/');
            
            // Update user preference in database
            if (isset($_SESSION['user_id'])) {
                try {
                    $db = getDB();
                    $stmt = $db->prepare("UPDATE users SET language = ? WHERE id = ?");
                    $stmt->execute([$language, $_SESSION['user_id']]);
                } catch (Exception $e) {
                    error_log("Language update error: " . $e->getMessage());
                }
            }
            
            self::loadTranslations();
            return true;
        }
        return false;
    }
    
    public static function getCurrentLanguage() {
        return self::$current_language;
    }
    
    public static function getSupportedLanguages() {
        return self::$supported_languages;
    }
    
    public static function translate($key, $params = []) {
        $translation = self::$translations[$key] ?? $key;
        
        // Replace parameters
        if (!empty($params)) {
            foreach ($params as $param_key => $param_value) {
                $translation = str_replace('{' . $param_key . '}', $param_value, $translation);
            }
        }
        
        return $translation;
    }
    
    public static function autoTranslateEmail($text, $from_lang = 'auto', $to_lang = null) {
        if ($to_lang === null) {
            $to_lang = self::$current_language;
        }
        
        // If same language, return as is
        if ($from_lang === $to_lang) {
            return $text;
        }
        
        // Use Google Translate API or similar service
        return self::translateText($text, $from_lang, $to_lang);
    }
    
    private static function translateText($text, $from_lang, $to_lang) {
        // This would integrate with Google Translate API
        // For now, return original text with a note
        try {
            // Placeholder for actual translation API integration
            $api_key = getSystemSetting('google_translate_api_key');
            if (!$api_key) {
                return $text; // Return original if no API key
            }
            
            $url = 'https://translation.googleapis.com/language/translate/v2?key=' . $api_key;
            $data = [
                'q' => $text,
                'source' => $from_lang === 'auto' ? '' : $from_lang,
                'target' => $to_lang,
                'format' => 'text'
            ];
            
            $options = [
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($data)
                ]
            ];
            
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            
            if ($result !== false) {
                $json = json_decode($result, true);
                if (isset($json['data']['translations'][0]['translatedText'])) {
                    return $json['data']['translations'][0]['translatedText'];
                }
            }
            
            return $text; // Return original on error
            
        } catch (Exception $e) {
            error_log("Translation error: " . $e->getMessage());
            return $text;
        }
    }
    
    public static function formatCurrency($amount, $currency = null) {
        if ($currency === null) {
            $currency = self::getCurrencyByLanguage();
        }
        
        $locale = self::$supported_languages[self::$current_language]['code'];
        
        if (function_exists('numfmt_create')) {
            $formatter = numfmt_create($locale, NumberFormatter::CURRENCY);
            return numfmt_format_currency($formatter, $amount, $currency);
        } else {
            // Fallback formatting
            $symbols = [
                'USD' => '$',
                'EUR' => '€',
                'GBP' => '£'
            ];
            $symbol = $symbols[$currency] ?? $currency;
            return $symbol . number_format($amount, 2);
        }
    }
    
    private static function getCurrencyByLanguage() {
        $currencies = [
            'en' => 'USD',
            'es' => 'USD', // or EUR depending on region
            'fr' => 'EUR'
        ];
        return $currencies[self::$current_language] ?? 'USD';
    }
    
    public static function formatDate($date, $format = null) {
        if ($format === null) {
            $formats = [
                'en' => 'm/d/Y',
                'es' => 'd/m/Y', 
                'fr' => 'd/m/Y'
            ];
            $format = $formats[self::$current_language] ?? 'm/d/Y';
        }
        
        if (is_string($date)) {
            $date = new DateTime($date);
        }
        
        return $date->format($format);
    }
    
    public static function getLanguageSelector() {
        $html = '<div class="language-selector dropdown">';
        $html .= '<button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">';
        $current = self::$supported_languages[self::$current_language];
        $html .= '<span class="flag">' . $current['flag'] . '</span> ' . $current['name'];
        $html .= '</button>';
        $html .= '<ul class="dropdown-menu">';
        
        foreach (self::$supported_languages as $code => $lang) {
            $active = $code === self::$current_language ? ' active' : '';
            $html .= '<li><a class="dropdown-item' . $active . '" href="#" onclick="changeLanguage(\'' . $code . '\')">';
            $html .= '<span class="flag">' . $lang['flag'] . '</span> ' . $lang['name'];
            $html .= '</a></li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }
}

// Helper function for easy translation
function __($key, $params = []) {
    return LanguageManager::translate($key, $params);
}

// Helper function for easy currency formatting
function formatMoney($amount, $currency = null) {
    return LanguageManager::formatCurrency($amount, $currency);
}

// Helper function for easy date formatting
function formatLocalDate($date, $format = null) {
    return LanguageManager::formatDate($date, $format);
}
