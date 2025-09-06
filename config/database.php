<?php
/**
 * Database Configuration
 * LoanFlow Personal Loan Management System
 */

// Load admin-configurable database settings or fallback to defaults
function getDatabaseSettings() {
    // Environment variables take precedence, then system_settings table, then defaults
    $default_settings = [
        'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost',
        'name' => $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'loanflow',
        'user' => $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'loanflow_user',
        'password' => $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '',
        'charset' => $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4',
        'port' => $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306,
        'socket' => $_ENV['DB_SOCKET'] ?? getenv('DB_SOCKET') ?: '',
        'ssl_key' => $_ENV['DB_SSL_KEY'] ?? getenv('DB_SSL_KEY') ?: '',
        'ssl_cert' => $_ENV['DB_SSL_CERT'] ?? getenv('DB_SSL_CERT') ?: '',
        'ssl_ca' => $_ENV['DB_SSL_CA'] ?? getenv('DB_SSL_CA') ?: '',
        'sql_mode' => 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO',
        'timezone' => '+00:00',
        'init_command' => 'SET NAMES utf8mb4'
    ];
    
    // Validate that password is set
    if (empty($default_settings['password'])) {
        error_log("CRITICAL: Database password not configured. Set DB_PASSWORD environment variable.");
        throw new Exception("Database password not configured. Please set DB_PASSWORD environment variable or configure in admin settings.");
    }
    
    // Try to load from system_settings table if it exists
    try {
        $temp_pdo = new PDO(
            "mysql:host={$default_settings['host']};dbname={$default_settings['name']};charset={$default_settings['charset']}", 
            $default_settings['user'], 
            $default_settings['password']
        );
        
        $stmt = $temp_pdo->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() > 0) {
            $stmt = $temp_pdo->prepare("
                SELECT setting_key, setting_value 
                FROM system_settings 
                WHERE setting_key LIKE 'db_%'
            ");
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            $settings = $default_settings;
            foreach ($results as $row) {
                $key = str_replace('db_', '', $row['setting_key']);
                $settings[$key] = $row['setting_value'];
            }
            return $settings;
        }
    } catch (Exception $e) {
        // Fallback to defaults if database or table doesn't exist
        error_log("Database settings fallback: " . $e->getMessage());
    }
    
    return $default_settings;
}

// Get database settings
$db_settings = getDatabaseSettings();

// Database connection settings (backward compatibility)
define('DB_HOST', $db_settings['host']);
define('DB_NAME', $db_settings['name']);
define('DB_USER', $db_settings['user']);
define('DB_PASS', $db_settings['password']);
define('DB_CHARSET', $db_settings['charset']);
define('DB_PORT', $db_settings['port']);

// Database connection class
class Database {
    private static $instance = null;
    private $connection;
    private $settings;
    
    private function __construct() {
        $this->settings = getDatabaseSettings();
        try {
            $dsn = $this->buildDSN();
            $options = $this->buildConnectionOptions();
            
            $this->connection = new PDO($dsn, $this->settings['user'], $this->settings['password'], $options);
            
            // Set additional MySQL settings if configured
            if (!empty($this->settings['sql_mode'])) {
                $this->connection->exec("SET sql_mode = '{$this->settings['sql_mode']}'");
            }
            
            if (!empty($this->settings['timezone'])) {
                $this->connection->exec("SET time_zone = '{$this->settings['timezone']}'");
            }
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check configuration in admin settings.");
        }
    }
    
    private function buildDSN() {
        $dsn = "mysql:host={$this->settings['host']};dbname={$this->settings['name']};charset={$this->settings['charset']}";
        
        if (!empty($this->settings['port']) && $this->settings['port'] != 3306) {
            $dsn .= ";port={$this->settings['port']}";
        }
        
        if (!empty($this->settings['socket'])) {
            $dsn .= ";unix_socket={$this->settings['socket']}";
        }
        
        return $dsn;
    }
    
    private function buildConnectionOptions() {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => $this->settings['init_command']
        ];
        
        // SSL configuration if provided
        if (!empty($this->settings['ssl_key']) && !empty($this->settings['ssl_cert'])) {
            $options[PDO::MYSQL_ATTR_SSL_KEY] = $this->settings['ssl_key'];
            $options[PDO::MYSQL_ATTR_SSL_CERT] = $this->settings['ssl_cert'];
            
            if (!empty($this->settings['ssl_ca'])) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $this->settings['ssl_ca'];
            }
        }
        
        return $options;
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Prevent cloning and serialization
    private function __clone() {}
    private function __wakeup() {}
}

// Global database connection function
function getDB() {
    return Database::getInstance()->getConnection();
}

// Test database connection
function testDatabaseConnection() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT 1");
        return $stmt !== false;
    } catch (Exception $e) {
        error_log("Database test failed: " . $e->getMessage());
        return false;
    }
}
?>
