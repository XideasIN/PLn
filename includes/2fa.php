<?php
/**
 * Two-Factor Authentication System
 * LoanFlow Personal Loan Management System
 */

class TwoFactorAuth {
    
    private static $secret_length = 32;
    
    /**
     * Generate a new secret key for 2FA
     */
    public static function generateSecret() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < self::$secret_length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }
    
    /**
     * Generate QR code URL for Google Authenticator
     */
    public static function getQRCodeUrl($secret, $email, $issuer = 'LoanFlow') {
        $url = 'otpauth://totp/' . urlencode($issuer . ':' . $email) . '?secret=' . $secret . '&issuer=' . urlencode($issuer);
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($url);
    }
    
    /**
     * Verify TOTP code
     */
    public static function verifyCode($secret, $code, $window = 1) {
        $timestamp = floor(time() / 30);
        
        // Check current time slot and adjacent slots for clock drift
        for ($i = -$window; $i <= $window; $i++) {
            $calculatedCode = self::calculateCode($secret, $timestamp + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Calculate TOTP code for given timestamp
     */
    private static function calculateCode($secret, $timestamp) {
        // Convert secret from base32
        $secret = self::base32Decode($secret);
        
        // Pack timestamp
        $time = pack('N*', 0) . pack('N*', $timestamp);
        
        // Generate HMAC
        $hash = hash_hmac('sha1', $time, $secret, true);
        
        // Extract dynamic binary code
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % pow(10, 6);
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Base32 decode
     */
    private static function base32Decode($secret) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $decoded = '';
        
        for ($i = 0; $i < strlen($secret); $i += 8) {
            $chunk = substr($secret, $i, 8);
            $chunk = str_pad($chunk, 8, '=');
            
            $binaryString = '';
            for ($j = 0; $j < 8; $j++) {
                if ($chunk[$j] !== '=') {
                    $binaryString .= sprintf('%05b', strpos($alphabet, $chunk[$j]));
                }
            }
            
            for ($j = 0; $j < strlen($binaryString); $j += 8) {
                $byte = substr($binaryString, $j, 8);
                if (strlen($byte) === 8) {
                    $decoded .= chr(bindec($byte));
                }
            }
        }
        
        return $decoded;
    }
    
    /**
     * Generate backup codes
     */
    public static function generateBackupCodes($count = 10) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }
    
    /**
     * Setup 2FA for user
     */
    public static function setupUser($user_id) {
        try {
            $db = getDB();
            
            // Generate secret
            $secret = self::generateSecret();
            
            // Store temporary secret
            $stmt = $db->prepare("UPDATE users SET temp_2fa_secret = ? WHERE id = ?");
            $stmt->execute([$secret, $user_id]);
            
            // Get user email
            $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return false;
            }
            
            return [
                'secret' => $secret,
                'qr_code_url' => self::getQRCodeUrl($secret, $user['email']),
                'backup_codes' => self::generateBackupCodes()
            ];
            
        } catch (Exception $e) {
            error_log("2FA setup error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enable 2FA for user after verification
     */
    public static function enableUser($user_id, $verification_code) {
        try {
            $db = getDB();
            
            // Get temporary secret
            $stmt = $db->prepare("SELECT temp_2fa_secret FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user || !$user['temp_2fa_secret']) {
                return false;
            }
            
            // Verify code
            if (!self::verifyCode($user['temp_2fa_secret'], $verification_code)) {
                return false;
            }
            
            // Generate backup codes
            $backup_codes = self::generateBackupCodes();
            $backup_codes_json = json_encode($backup_codes);
            
            // Enable 2FA
            $stmt = $db->prepare("
                UPDATE users 
                SET two_factor_secret = temp_2fa_secret, 
                    temp_2fa_secret = NULL, 
                    two_factor_enabled = 1,
                    backup_codes = ? 
                WHERE id = ?
            ");
            $stmt->execute([$backup_codes_json, $user_id]);
            
            // Log the action
            logAudit('2fa_enabled', 'users', $user_id, $user_id);
            
            return $backup_codes;
            
        } catch (Exception $e) {
            error_log("2FA enable error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Disable 2FA for user
     */
    public static function disableUser($user_id, $verification_code = null) {
        try {
            $db = getDB();
            
            // Get user's 2FA secret
            $stmt = $db->prepare("SELECT two_factor_secret, two_factor_enabled FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user || !$user['two_factor_enabled']) {
                return false;
            }
            
            // Verify code if provided
            if ($verification_code && !self::verifyCode($user['two_factor_secret'], $verification_code)) {
                return false;
            }
            
            // Disable 2FA
            $stmt = $db->prepare("
                UPDATE users 
                SET two_factor_secret = NULL, 
                    two_factor_enabled = 0,
                    backup_codes = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$user_id]);
            
            // Log the action
            logAudit('2fa_disabled', 'users', $user_id, $user_id);
            
            return true;
            
        } catch (Exception $e) {
            error_log("2FA disable error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify user's 2FA code
     */
    public static function verifyUser($user_id, $code) {
        try {
            $db = getDB();
            
            // Get user's 2FA secret
            $stmt = $db->prepare("SELECT two_factor_secret, backup_codes FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user || !$user['two_factor_secret']) {
                return false;
            }
            
            // Try TOTP code first
            if (self::verifyCode($user['two_factor_secret'], $code)) {
                return true;
            }
            
            // Try backup codes
            if ($user['backup_codes']) {
                $backup_codes = json_decode($user['backup_codes'], true);
                if (in_array(strtoupper($code), $backup_codes)) {
                    // Remove used backup code
                    $backup_codes = array_diff($backup_codes, [strtoupper($code)]);
                    $stmt = $db->prepare("UPDATE users SET backup_codes = ? WHERE id = ?");
                    $stmt->execute([json_encode(array_values($backup_codes)), $user_id]);
                    
                    // Log backup code usage
                    logAudit('2fa_backup_code_used', 'users', $user_id, $user_id);
                    
                    return true;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("2FA verification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user has 2FA enabled
     */
    public static function isEnabled($user_id) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            return $user && $user['two_factor_enabled'];
            
        } catch (Exception $e) {
            error_log("2FA status check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's backup codes
     */
    public static function getBackupCodes($user_id) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT backup_codes FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user && $user['backup_codes']) {
                return json_decode($user['backup_codes'], true);
            }
            
            return [];
            
        } catch (Exception $e) {
            error_log("2FA backup codes error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Regenerate backup codes
     */
    public static function regenerateBackupCodes($user_id) {
        try {
            $db = getDB();
            
            // Check if 2FA is enabled
            if (!self::isEnabled($user_id)) {
                return false;
            }
            
            // Generate new backup codes
            $backup_codes = self::generateBackupCodes();
            $backup_codes_json = json_encode($backup_codes);
            
            // Update database
            $stmt = $db->prepare("UPDATE users SET backup_codes = ? WHERE id = ?");
            $stmt->execute([$backup_codes_json, $user_id]);
            
            // Log the action
            logAudit('2fa_backup_codes_regenerated', 'users', $user_id, $user_id);
            
            return $backup_codes;
            
        } catch (Exception $e) {
            error_log("2FA backup codes regeneration error: " . $e->getMessage());
            return false;
        }
    }
}

// Add 2FA fields to users table if they don't exist
function add2FAFields() {
    try {
        $db = getDB();
        
        // Check if fields exist
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'two_factor_enabled'");
        if ($stmt->rowCount() == 0) {
            $db->exec("
                ALTER TABLE users 
                ADD COLUMN two_factor_enabled BOOLEAN NOT NULL DEFAULT FALSE,
                ADD COLUMN two_factor_secret VARCHAR(255) NULL,
                ADD COLUMN temp_2fa_secret VARCHAR(255) NULL,
                ADD COLUMN backup_codes TEXT NULL
            ");
        }
        
    } catch (Exception $e) {
        error_log("2FA fields addition error: " . $e->getMessage());
    }
}

// Initialize 2FA fields
add2FAFields();
