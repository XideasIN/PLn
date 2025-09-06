<?php
/**
 * Comprehensive Backup Management System
 * LoanFlow Personal Loan Management System
 */

class BackupManager {
    
    private static $backup_dir;
    private static $max_backups;
    private static $email_notifications;
    private static $weekly_schedule;
    
    /**
     * Initialize backup system
     */
    public static function init() {
        self::$backup_dir = dirname(__DIR__) . '/backups';
        self::$max_backups = intval(getSystemSetting('backup_max_retention', '4'));
        self::$email_notifications = getSystemSetting('backup_email_notifications', '1') === '1';
        self::$weekly_schedule = getSystemSetting('backup_weekly_schedule', '1') === '1';
        
        // Ensure backup directory exists
        if (!is_dir(self::$backup_dir)) {
            mkdir(self::$backup_dir, 0755, true);
        }
    }
    
    /**
     * Create complete project backup (files + database)
     */
    public static function createCompleteBackup($manual = false) {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backup_name = 'loanflow_complete_' . $timestamp;
            $backup_path = self::$backup_dir . '/' . $backup_name;
            
            // Create backup directory
            if (!mkdir($backup_path, 0755, true)) {
                throw new Exception('Failed to create backup directory');
            }
            
            $backup_info = [
                'name' => $backup_name,
                'timestamp' => $timestamp,
                'type' => $manual ? 'manual' : 'automatic',
                'files' => [],
                'database' => null,
                'size' => 0,
                'status' => 'in_progress'
            ];
            
            // 1. Backup Database
            $db_result = self::backupDatabase($backup_path);
            if (!$db_result['success']) {
                throw new Exception('Database backup failed: ' . $db_result['error']);
            }
            $backup_info['database'] = $db_result;
            $backup_info['files'][] = $db_result['filename'];
            
            // 2. Backup Project Files
            $files_result = self::backupProjectFiles($backup_path);
            if (!$files_result['success']) {
                throw new Exception('Files backup failed: ' . $files_result['error']);
            }
            $backup_info['files'] = array_merge($backup_info['files'], $files_result['files']);
            
            // 3. Create backup manifest
            $manifest_result = self::createBackupManifest($backup_path, $backup_info);
            if ($manifest_result['success']) {
                $backup_info['files'][] = $manifest_result['filename'];
            }
            
            // 4. Calculate total size
            $backup_info['size'] = self::calculateBackupSize($backup_path);
            $backup_info['status'] = 'completed';
            
            // 5. Compress backup (optional)
            $compression_result = self::compressBackup($backup_path);
            if ($compression_result['success']) {
                $backup_info['compressed'] = true;
                $backup_info['compressed_file'] = $compression_result['filename'];
                $backup_info['compressed_size'] = $compression_result['size'];
            }
            
            // 6. Clean old backups
            self::cleanOldBackups();
            
            // 7. Log backup
            self::logBackup($backup_info);
            
            // 8. Send email notification
            if (self::$email_notifications) {
                self::sendBackupNotification($backup_info);
            }
            
            return [
                'success' => true,
                'backup_info' => $backup_info,
                'message' => 'Complete backup created successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Complete backup failed: " . $e->getMessage());
            
            // Clean up failed backup
            if (isset($backup_path) && is_dir($backup_path)) {
                self::removeDirectory($backup_path);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Backup database only
     */
    private static function backupDatabase($backup_path) {
        try {
            $filename = 'database_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $backup_path . '/' . $filename;
            
            // Get database connection info
            require_once dirname(__DIR__) . '/config/database.php';
            
            $command = sprintf(
                'mysqldump -h%s -u%s -p%s %s > %s 2>&1',
                DB_HOST,
                DB_USER,
                DB_PASS,
                DB_NAME,
                escapeshellarg($filepath)
            );
            
            exec($command, $output, $return_code);
            
            if ($return_code === 0 && file_exists($filepath) && filesize($filepath) > 0) {
                return [
                    'success' => true,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'size' => filesize($filepath)
                ];
            } else {
                throw new Exception('MySQL dump failed. Return code: ' . $return_code . '. Output: ' . implode('\n', $output));
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Backup all project files
     */
    private static function backupProjectFiles($backup_path) {
        try {
            $project_root = dirname(__DIR__);
            $files_backup_path = $backup_path . '/files';
            
            if (!mkdir($files_backup_path, 0755, true)) {
                throw new Exception('Failed to create files backup directory');
            }
            
            $exclude_patterns = [
                '/backups/',
                '/temp/',
                '/.git/',
                '/node_modules/',
                '/vendor/',
                '.log',
                '.tmp'
            ];
            
            $backed_up_files = [];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($project_root, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                $relative_path = str_replace($project_root, '', $file->getPathname());
                
                // Skip excluded patterns
                $skip = false;
                foreach ($exclude_patterns as $pattern) {
                    if (strpos($relative_path, $pattern) !== false) {
                        $skip = true;
                        break;
                    }
                }
                
                if ($skip) continue;
                
                $dest_path = $files_backup_path . $relative_path;
                
                if ($file->isDir()) {
                    if (!is_dir($dest_path)) {
                        mkdir($dest_path, 0755, true);
                    }
                } else {
                    $dest_dir = dirname($dest_path);
                    if (!is_dir($dest_dir)) {
                        mkdir($dest_dir, 0755, true);
                    }
                    
                    if (copy($file->getPathname(), $dest_path)) {
                        $backed_up_files[] = $relative_path;
                    }
                }
            }
            
            return [
                'success' => true,
                'files' => $backed_up_files,
                'count' => count($backed_up_files)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create backup manifest file
     */
    private static function createBackupManifest($backup_path, $backup_info) {
        try {
            $manifest_file = $backup_path . '/backup_manifest.json';
            
            $manifest = [
                'backup_name' => $backup_info['name'],
                'created_at' => date('Y-m-d H:i:s'),
                'type' => $backup_info['type'],
                'version' => '1.0',
                'system_info' => [
                    'php_version' => PHP_VERSION,
                    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'loanflow_version' => getSystemSetting('system_version', '1.0')
                ],
                'database_info' => $backup_info['database'] ?? null,
                'files_count' => count($backup_info['files']),
                'total_size' => $backup_info['size']
            ];
            
            if (file_put_contents($manifest_file, json_encode($manifest, JSON_PRETTY_PRINT))) {
                return [
                    'success' => true,
                    'filename' => 'backup_manifest.json'
                ];
            } else {
                throw new Exception('Failed to create manifest file');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Compress backup directory
     */
    private static function compressBackup($backup_path) {
        try {
            $zip_file = $backup_path . '.zip';
            $zip = new ZipArchive();
            
            if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
                throw new Exception('Cannot create zip file');
            }
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($backup_path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    $zip->addEmptyDir(str_replace($backup_path . '/', '', $file . '/'));
                } else {
                    $zip->addFile($file, str_replace($backup_path . '/', '', $file));
                }
            }
            
            $zip->close();
            
            if (file_exists($zip_file)) {
                // Remove uncompressed directory
                self::removeDirectory($backup_path);
                
                return [
                    'success' => true,
                    'filename' => basename($zip_file),
                    'size' => filesize($zip_file)
                ];
            } else {
                throw new Exception('Zip file was not created');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate backup size
     */
    private static function calculateBackupSize($backup_path) {
        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backup_path, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    /**
     * Clean old backups based on retention settings
     */
    public static function cleanOldBackups() {
        try {
            $max_backups = self::$max_backups;
            
            if ($max_backups === 0) {
                // Special handling for 0: delete backups older than 24 hours
                self::deleteOldBackups(24);
                return true;
            }
            
            // Get all backup files/directories
            $backups = [];
            $items = glob(self::$backup_dir . '/loanflow_complete_*');
            
            foreach ($items as $item) {
                $backups[] = [
                    'path' => $item,
                    'name' => basename($item),
                    'created' => filectime($item)
                ];
            }
            
            // Sort by creation time (newest first)
            usort($backups, function($a, $b) {
                return $b['created'] - $a['created'];
            });
            
            // Remove excess backups
            if (count($backups) > $max_backups) {
                $to_remove = array_slice($backups, $max_backups);
                
                foreach ($to_remove as $backup) {
                    if (is_dir($backup['path'])) {
                        self::removeDirectory($backup['path']);
                    } elseif (is_file($backup['path'])) {
                        unlink($backup['path']);
                    }
                    
                    // Log removal
                    error_log("Removed old backup: " . $backup['name']);
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Clean old backups error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete backups older than specified hours
     */
    private static function deleteOldBackups($hours) {
        try {
            $cutoff_time = time() - ($hours * 3600);
            $items = glob(self::$backup_dir . '/loanflow_complete_*');
            
            foreach ($items as $item) {
                if (filectime($item) < $cutoff_time) {
                    if (is_dir($item)) {
                        self::removeDirectory($item);
                    } elseif (is_file($item)) {
                        unlink($item);
                    }
                    
                    error_log("Removed old backup (24h rule): " . basename($item));
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Delete old backups error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log backup to database
     */
    private static function logBackup($backup_info) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO backup_logs 
                (backup_name, backup_type, files_count, total_size, compressed_size, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $backup_info['name'],
                $backup_info['type'],
                count($backup_info['files']),
                $backup_info['size'],
                $backup_info['compressed_size'] ?? null,
                $backup_info['status']
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Backup logging error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send backup notification email
     */
    private static function sendBackupNotification($backup_info) {
        try {
            $admin_email = getSystemSetting('admin_email', '');
            if (empty($admin_email)) {
                return false;
            }
            
            $subject = 'LoanFlow Backup Completed - ' . $backup_info['name'];
            
            $message = self::generateBackupEmailTemplate($backup_info);
            
            return sendEmail($admin_email, $subject, $message, true);
            
        } catch (Exception $e) {
            error_log("Backup notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate backup notification email template
     */
    private static function generateBackupEmailTemplate($backup_info) {
        $company_settings = getCompanySettings();
        $formatted_size = self::formatBytes($backup_info['size']);
        $compressed_size = isset($backup_info['compressed_size']) ? self::formatBytes($backup_info['compressed_size']) : 'N/A';
        
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #28a745; color: white; padding: 20px; border-radius: 5px 5px 0 0;">
                <h2 style="margin: 0;">✅ Backup Completed Successfully</h2>
            </div>
            <div style="background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 5px 5px;">
                <h3>Backup Information</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">Backup Name:</td>
                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . htmlspecialchars($backup_info['name']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">Type:</td>
                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . ucfirst($backup_info['type']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">Created:</td>
                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . date('Y-m-d H:i:s') . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">Files Count:</td>
                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . number_format(count($backup_info['files'])) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">Total Size:</td>
                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . $formatted_size . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">Compressed Size:</td>
                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . $compressed_size . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">Status:</td>
                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6;">
                            <span style="background: #28a745; color: white; padding: 4px 8px; border-radius: 3px;">
                                ' . ucfirst($backup_info['status']) . '
                            </span>
                        </td>
                    </tr>
                </table>
                
                <div style="margin-top: 20px; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;">
                    <h4 style="margin: 0 0 10px 0; color: #155724;">Backup Contents</h4>
                    <ul style="margin: 0; color: #155724;">
                        <li>Complete database dump</li>
                        <li>All project files and directories</li>
                        <li>Configuration files</li>
                        <li>User uploads and documents</li>
                        <li>System logs and backups manifest</li>
                    </ul>
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <a href="' . getBaseUrl() . '/admin/system-settings.php" 
                       style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                        View Backup Settings
                    </a>
                </div>
                
                <hr style="margin: 20px 0;">
                <p style="text-align: center; color: #6c757d; font-size: 14px;">
                    This is an automated backup notification from ' . htmlspecialchars($company_settings['name']) . '.<br>
                    Maximum backups to keep: ' . (self::$max_backups === 0 ? 'Delete after 24 hours' : self::$max_backups) . '
                </p>
            </div>
        </div>';
    }
    
    /**
     * Get backup statistics
     */
    public static function getBackupStatistics() {
        try {
            $db = getDB();
            
            // Get backup count
            $stmt = $db->query("SELECT COUNT(*) as total_backups FROM backup_logs WHERE status = 'completed'");
            $total_backups = $stmt->fetchColumn();
            
            // Get latest backup
            $stmt = $db->query("SELECT * FROM backup_logs WHERE status = 'completed' ORDER BY created_at DESC LIMIT 1");
            $latest_backup = $stmt->fetch();
            
            // Get total backup size
            $stmt = $db->query("SELECT SUM(total_size) as total_size FROM backup_logs WHERE status = 'completed'");
            $total_size = $stmt->fetchColumn();
            
            // Get current backup files count
            $current_backups = count(glob(self::$backup_dir . '/loanflow_complete_*'));
            
            return [
                'total_backups' => $total_backups,
                'current_backups' => $current_backups,
                'latest_backup' => $latest_backup,
                'total_size' => $total_size,
                'max_retention' => self::$max_backups,
                'email_notifications' => self::$email_notifications,
                'weekly_schedule' => self::$weekly_schedule
            ];
            
        } catch (Exception $e) {
            error_log("Backup statistics error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get list of current backups
     */
    public static function getCurrentBackups() {
        try {
            $backups = [];
            $items = glob(self::$backup_dir . '/loanflow_complete_*');
            
            foreach ($items as $item) {
                $backups[] = [
                    'name' => basename($item),
                    'path' => $item,
                    'size' => is_dir($item) ? self::calculateBackupSize($item) : filesize($item),
                    'created' => filectime($item),
                    'type' => is_dir($item) ? 'directory' : 'zip'
                ];
            }
            
            // Sort by creation time (newest first)
            usort($backups, function($a, $b) {
                return $b['created'] - $a['created'];
            });
            
            return $backups;
            
        } catch (Exception $e) {
            error_log("Get current backups error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update backup settings
     */
    public static function updateBackupSettings($settings) {
        try {
            $valid_settings = [
                'backup_max_retention' => intval($settings['max_retention'] ?? 4),
                'backup_email_notifications' => isset($settings['email_notifications']) ? '1' : '0',
                'backup_weekly_schedule' => isset($settings['weekly_schedule']) ? '1' : '0',
                'backup_schedule_day' => sanitizeInput($settings['schedule_day'] ?? 'sunday'),
                'backup_schedule_time' => sanitizeInput($settings['schedule_time'] ?? '02:00')
            ];
            
            foreach ($valid_settings as $key => $value) {
                updateSystemSetting($key, $value);
            }
            
            // Reinitialize settings
            self::init();
            
            return true;
            
        } catch (Exception $e) {
            error_log("Update backup settings error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Schedule weekly backup (called by cron)
     */
    public static function weeklyBackupCheck() {
        if (!self::$weekly_schedule) {
            return false;
        }
        
        $schedule_day = getSystemSetting('backup_schedule_day', 'sunday');
        $schedule_time = getSystemSetting('backup_schedule_time', '02:00');
        
        $current_day = strtolower(date('l'));
        $current_time = date('H:i');
        
        if ($current_day === $schedule_day && $current_time === $schedule_time) {
            return self::createCompleteBackup(false);
        }
        
        return false;
    }
    
    /**
     * Helper function to format bytes
     */
    private static function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Helper function to remove directory recursively
     */
    private static function removeDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $items = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? self::removeDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
}

// Create backup logs table if it doesn't exist
function createBackupLogsTable() {
    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS backup_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                backup_name VARCHAR(255) NOT NULL,
                backup_type ENUM('manual', 'automatic') NOT NULL,
                files_count INT NOT NULL DEFAULT 0,
                total_size BIGINT NOT NULL DEFAULT 0,
                compressed_size BIGINT NULL,
                status ENUM('in_progress', 'completed', 'failed') NOT NULL DEFAULT 'in_progress',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_backup_name (backup_name),
                INDEX idx_created_at (created_at),
                INDEX idx_status (status)
            )
        ");
    } catch (Exception $e) {
        error_log("Create backup logs table error: " . $e->getMessage());
    }
}

// Initialize backup system
BackupManager::init();
createBackupLogsTable();
