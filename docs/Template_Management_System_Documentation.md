# Template Management System - Complete Implementation Guide

## Table of Contents
1. [Overview](#overview)
2. [System Architecture](#system-architecture)
3. [Database Schema](#database-schema)
4. [File Structure](#file-structure)
5. [Core Components](#core-components)
6. [AI Integration](#ai-integration)
7. [Cross-Project Implementation](#cross-project-implementation)
8. [Template Sharing System](#template-sharing-system)
9. [**NEW**: AI-Powered Deployment Manager](#ai-powered-deployment-manager)
10. [**NEW**: Post-Creation Template Editing](#post-creation-template-editing)
11. [**NEW**: Subscription Management System](#subscription-management-system)
12. [**NEW**: API Cost Analysis](#api-cost-analysis)
13. [Security Considerations](#security-considerations)
14. [API Documentation](#api-documentation)
15. [Installation Guide](#installation-guide)
16. [Configuration](#configuration)
17. [Usage Examples](#usage-examples)
18. [Troubleshooting](#troubleshooting)
19. [Best Practices](#best-practices)

## Overview

The Template Management System is a comprehensive solution for managing three distinct template areas:
- **Frontend/Public Area**: Public-facing website templates
- **Client Area**: Authenticated user interface templates
- **Admin Area**: Administrative backend templates

### Key Features
- ✅ AI-powered template generation from images
- ✅ Seamless integration with existing AI Image-to-HTML/PHP conversion
- ✅ Cross-project template sharing
- ✅ Production-ready code generation
- ✅ Role-based access control
- ✅ Template validation and security
- ✅ Visual template management interface
- ✅ Export/Import functionality
- ✅ **NEW**: AI-Powered Deployment Manager
- ✅ **NEW**: Real-time Error Detection & Correction
- ✅ **NEW**: In-Browser Code Editor with Syntax Highlighting
- ✅ **NEW**: Version Control Integration
- ✅ **NEW**: One-Click Deployment System
- ✅ **NEW**: Automated Testing Framework
- ✅ **NEW**: Post-Creation Template Editing (with limits)
- ✅ **NEW**: Comprehensive Subscription Management

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Template Management System               │
├─────────────────────────────────────────────────────────────┤
│  Frontend Templates  │  Client Templates   │ Admin Templates │
│  ┌─────────────────┐ │ ┌─────────────────┐ │ ┌──────────────┐│
│  │ Public Pages    │ │ │ User Dashboard  │ │ │ Admin Panel  ││
│  │ Landing Pages   │ │ │ Account Pages   │ │ │ Management   ││
│  │ Marketing       │ │ │ Feature Access  │ │ │ System Tools ││
│  └─────────────────┘ │ └─────────────────┘ │ └──────────────┘│
├─────────────────────────────────────────────────────────────┤
│                      AI Integration Layer                   │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ Image-to-HTML/PHP Converter → Template Processor       ││
│  └─────────────────────────────────────────────────────────┘│
├─────────────────────────────────────────────────────────────┤
│                    Template Manager Core                   │
│  ┌─────────────────┐ ┌─────────────────┐ ┌──────────────────┐│
│  │ Template Engine │ │ Config Manager  │ │ Security Layer   ││
│  │ Code Validator  │ │ File Manager    │ │ Access Control   ││
│  │ Export/Import   │ │ Database Layer  │ │ Activity Logging ││
│  └─────────────────┘ └─────────────────┘ └──────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

## Database Schema

### Core Tables

```sql
-- Template configuration and registry
CREATE TABLE templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('frontend', 'client', 'admin') NOT NULL,
    version VARCHAR(50) DEFAULT '1.0.0',
    file_path VARCHAR(255) NOT NULL,
    css_path VARCHAR(255),
    js_path VARCHAR(255),
    preview_image VARCHAR(255),
    is_active BOOLEAN DEFAULT FALSE,
    is_ai_generated BOOLEAN DEFAULT FALSE,
    supports_ai_integration BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_active (is_active),
    INDEX idx_ai_generated (is_ai_generated)
);

-- AI-generated templates with source tracking
CREATE TABLE ai_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) NOT NULL UNIQUE,
    template_type ENUM('frontend', 'client', 'admin') NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    version VARCHAR(50) DEFAULT '1.0.0',
    file_path VARCHAR(255) NOT NULL,
    css_path VARCHAR(255),
    js_path VARCHAR(255),
    preview_image VARCHAR(255),
    ai_source TEXT COMMENT 'Original image URL or description',
    original_code LONGTEXT COMMENT 'Original AI-generated code',
    processed_code LONGTEXT COMMENT 'Cleaned and validated code',
    validation_status ENUM('pending', 'passed', 'failed') DEFAULT 'pending',
    validation_errors JSON,
    status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
    tags JSON COMMENT 'Template tags for categorization',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_template_type (template_type),
    INDEX idx_status (status),
    INDEX idx_validation_status (validation_status)
);

-- System settings for template management
CREATE TABLE template_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('boolean', 'string', 'integer', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key),
    INDEX idx_is_public (is_public)
);

-- Activity logging for template operations
CREATE TABLE template_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    template_key VARCHAR(100),
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_template_key (template_key),
    INDEX idx_created_at (created_at)
);

-- Template sharing and export tracking
CREATE TABLE template_exports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) NOT NULL,
    export_token VARCHAR(255) NOT NULL UNIQUE,
    exported_by INT,
    export_data LONGTEXT COMMENT 'JSON export package',
    download_count INT DEFAULT 0,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_template_key (template_key),
    INDEX idx_export_token (export_token),
    INDEX idx_expires_at (expires_at)
);
```

### Required User System Tables

```sql
-- Users table (if not already exists)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    role ENUM('user', 'admin', 'super_admin') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Settings table (if not already exists)
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## File Structure

```
project_root/
├── config/
│   ├── template_config.php          # Template configuration
│   └── app_config.php               # Application settings
├── includes/
│   ├── template_manager.php         # Core template management class
│   ├── template_functions.php       # Helper functions
│   ├── ai_integration.php           # AI integration layer
│   └── security.php                 # Security functions
├── templates/
│   ├── frontend/                    # Frontend templates
│   │   ├── base.php
│   │   ├── one-page-complete.php
│   │   └── components/
│   ├── client/                      # Client area templates
│   │   ├── dashboard.php
│   │   └── components/
│   ├── admin/                       # Admin templates
│   │   ├── dashboard.php
│   │   ├── sidebar-nav.php
│   │   └── components/
│   └── ai-generated/                # AI-generated templates
│       ├── frontend/
│       ├── client/
│       └── admin/
├── pages/
│   └── admin/
│       ├── template-manager-comprehensive.php
│       ├── template-switcher.php
│       ├── template-preview.php
│       ├── template-editor.php
│       └── template-export.php
├── assets/
│   ├── css/
│   │   ├── frontend-one-page-complete.css
│   │   ├── client-area-enhanced.css
│   │   ├── admin-enhanced.css
│   │   └── template-manager.css
│   ├── js/
│   │   ├── template-manager.js
│   │   ├── code-validator.js
│   │   └── template-preview.js
│   └── images/
│       └── templates/               # Template preview images
├── uploads/
│   └── templates/                   # User uploaded templates
├── api/
│   └── templates/                   # Template API endpoints
└── docs/
    └── Template_Management_System_Documentation.md
```

## Core Components

### 1. Template Configuration System

**File: `config/template_config.php`**

```php
<?php
/**
 * Template Configuration System
 * Manages template registry and settings
 */

$TEMPLATE_CONFIG = [
    'system' => [
        'version' => '1.0.0',
        'ai_integration_enabled' => true,
        'cross_project_sharing' => true,
        'template_validation' => true,
        'max_template_size' => 5242880, // 5MB
        'allowed_file_types' => ['php', 'html', 'css', 'js'],
        'security_scan' => true
    ],
    'frontend' => [
        'active_template' => 'one-page-complete',
        'allow_ai_generation' => true,
        'require_responsive' => true,
        'templates' => []
    ],
    'client' => [
        'mirror_frontend' => true,
        'unique_template_enabled' => false,
        'active_template' => null,
        'subscription_features' => true,
        'templates' => []
    ],
    'admin' => [
        'active_template' => 'dashboard',
        'allow_customization' => true,
        'require_navigation' => true,
        'templates' => []
    ]
];

// Load dynamic templates from database
function loadDynamicTemplates() {
    global $TEMPLATE_CONFIG, $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM templates WHERE status = 'active'");
        $stmt->execute();
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($templates as $template) {
            $config = [
                'name' => $template['name'],
                'description' => $template['description'],
                'version' => $template['version'],
                'file' => $template['file_path'],
                'css' => $template['css_path'],
                'js' => $template['js_path'],
                'preview' => $template['preview_image'],
                'is_ai_generated' => (bool)$template['is_ai_generated'],
                'supports_ai_integration' => (bool)$template['supports_ai_integration']
            ];
            
            $TEMPLATE_CONFIG[$template['type']]['templates'][$template['template_key']] = $config;
        }
    } catch (Exception $e) {
        error_log("Failed to load dynamic templates: " . $e->getMessage());
    }
}

// Initialize
loadDynamicTemplates();
?>
```

### 2. Template Manager Class

**File: `includes/template_manager.php`**

```php
<?php
/**
 * Core Template Manager Class
 * Handles all template operations
 */

class TemplateManager {
    private $db;
    private $config;
    private $securityValidator;
    
    public function __construct($database, $config) {
        $this->db = $database;
        $this->config = $config;
        $this->securityValidator = new TemplateSecurityValidator();
    }
    
    /**
     * Import AI-generated template
     */
    public function importAITemplate($data) {
        // 1. Validate input data
        $validation = $this->validateImportData($data);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }
        
        // 2. Clean and process code
        $processedCode = $this->processAICode($data['generated_code']);
        
        // 3. Security scan
        $securityCheck = $this->securityValidator->scanCode($processedCode);
        if (!$securityCheck['safe']) {
            return ['success' => false, 'errors' => $securityCheck['issues']];
        }
        
        // 4. Create template files
        $templateKey = $this->generateTemplateKey($data['name']);
        $filePaths = $this->createTemplateFiles($templateKey, $processedCode, $data);
        
        // 5. Save to database
        $templateId = $this->saveTemplate($templateKey, $data, $filePaths);
        
        // 6. Register in config system
        $this->registerTemplate($templateKey, $data['type'], $filePaths);
        
        return ['success' => true, 'template_key' => $templateKey, 'id' => $templateId];
    }
    
    /**
     * Switch active template
     */
    public function switchTemplate($type, $templateKey) {
        // Validate template exists
        if (!$this->templateExists($templateKey, $type)) {
            return ['success' => false, 'error' => 'Template not found'];
        }
        
        // Update active template setting
        $this->updateSetting("active_template_{$type}", $templateKey);
        
        // Log activity
        $this->logActivity('template_switched', [
            'type' => $type,
            'template_key' => $templateKey
        ]);
        
        return ['success' => true];
    }
    
    /**
     * Export template for sharing
     */
    public function exportTemplate($templateKey) {
        $template = $this->getTemplate($templateKey);
        if (!$template) {
            return ['success' => false, 'error' => 'Template not found'];
        }
        
        // Create export package
        $exportData = [
            'metadata' => [
                'system_version' => $this->config['system']['version'],
                'export_date' => date('c'),
                'template_version' => $template['version'],
                'compatibility' => 'universal'
            ],
            'template' => $template,
            'files' => $this->getTemplateFiles($templateKey),
            'dependencies' => $this->getTemplateDependencies($templateKey)
        ];
        
        // Generate export token
        $exportToken = $this->generateExportToken();
        
        // Save export record
        $this->saveExportRecord($templateKey, $exportToken, $exportData);
        
        return ['success' => true, 'export_token' => $exportToken, 'data' => $exportData];
    }
    
    /**
     * Import template from external package
     */
    public function importTemplatePackage($packageData, $options = []) {
        // Validate package format
        if (!$this->validatePackageFormat($packageData)) {
            return ['success' => false, 'error' => 'Invalid package format'];
        }
        
        // Check compatibility
        $compatibility = $this->checkCompatibility($packageData['metadata']);
        if (!$compatibility['compatible']) {
            return ['success' => false, 'error' => 'Incompatible package version'];
        }
        
        // Process import
        $importData = [
            'name' => $packageData['template']['name'] . ' (Imported)',
            'description' => $packageData['template']['description'],
            'type' => $packageData['template']['type'],
            'generated_code' => $packageData['files']['html'],
            'css_content' => $packageData['files']['css'],
            'js_content' => $packageData['files']['js']
        ];
        
        return $this->importAITemplate($importData);
    }
    
    // Additional methods...
    private function validateImportData($data) { /* ... */ }
    private function processAICode($code) { /* ... */ }
    private function createTemplateFiles($key, $code, $data) { /* ... */ }
    private function saveTemplate($key, $data, $files) { /* ... */ }
    private function registerTemplate($key, $type, $files) { /* ... */ }
    // ... more helper methods
}
```

### 3. AI Integration Layer

**File: `includes/ai_integration.php`**

```php
<?php
/**
 * AI Integration Layer
 * Interfaces with existing AI Image-to-HTML system
 */

class AITemplateIntegration {
    private $aiService;
    private $templateManager;
    
    public function __construct($aiService, $templateManager) {
        $this->aiService = $aiService;
        $this->templateManager = $templateManager;
    }
    
    /**
     * Process AI-generated code for template integration
     */
    public function processAIOutput($aiOutput, $templateType = 'frontend') {
        // 1. Extract components from AI output
        $components = $this->extractComponents($aiOutput);
        
        // 2. Add template integration code
        $integratedCode = $this->addTemplateIntegration($components, $templateType);
        
        // 3. Optimize for production
        $optimizedCode = $this->optimizeForProduction($integratedCode);
        
        // 4. Add security features
        $secureCode = $this->addSecurityFeatures($optimizedCode);
        
        return [
            'html' => $secureCode['html'],
            'css' => $secureCode['css'],
            'js' => $secureCode['js'],
            'metadata' => $this->generateMetadata($templateType)
        ];
    }
    
    /**
     * Add template integration hooks
     */
    private function addTemplateIntegration($components, $type) {
        $html = $components['html'];
        
        // Add PHP security check
        $securityCheck = "<?php if (!defined('APP_INIT')) die('Direct access not permitted'); ?>\n";
        
        // Add template metadata
        $metadata = $this->generateTemplateMetadata($type);
        
        // Add dynamic content placeholders
        $html = $this->addContentPlaceholders($html, $type);
        
        // Add language support
        $html = $this->addLanguageSupport($html);
        
        // Add CSRF protection for forms
        $html = $this->addCSRFProtection($html);
        
        return [
            'html' => $securityCheck . $metadata . $html,
            'css' => $this->optimizeCSS($components['css']),
            'js' => $this->addJSSecurity($components['js'])
        ];
    }
    
    /**
     * Add content placeholders based on template type
     */
    private function addContentPlaceholders($html, $type) {
        switch ($type) {
            case 'frontend':
                $html = str_replace('<title>', '<title><?= htmlspecialchars($page_title ?? "Welcome") ?> - ', $html);
                $html = str_replace('</body>', '<?= $content ?? "" ?></body>', $html);
                break;
                
            case 'client':
                $html = $this->addClientAreaFeatures($html);
                break;
                
            case 'admin':
                $html = $this->addAdminFeatures($html);
                break;
        }
        
        return $html;
    }
    
    /**
     * Add client area specific features
     */
    private function addClientAreaFeatures($html) {
        // Add subscription status widget
        $subscriptionWidget = '<?php if (isset($user_subscription)): ?>
            <div class="subscription-status">
                <span class="badge bg-<?= $user_subscription["plan"] === "enterprise" ? "primary" : "secondary" ?>">
                    <?= ucfirst($user_subscription["plan"]) ?> Plan
                </span>
            </div>
        <?php endif; ?>';
        
        // Add navigation for authenticated users
        $userNav = '<?php if (isset($_SESSION["user_id"])): ?>
            <div class="user-navigation">
                <a href="<?= APP_URL ?>/pages/client-area/"><?= t("dashboard") ?></a>
                <a href="<?= APP_URL ?>/pages/auth/logout.php"><?= t("logout") ?></a>
            </div>
        <?php endif; ?>';
        
        // Insert widgets
        $html = str_replace('</nav>', $userNav . '</nav>', $html);
        $html = str_replace('<main', $subscriptionWidget . '<main', $html);
        
        return $html;
    }
    
    /**
     * Add admin area features
     */
    private function addAdminFeatures($html) {
        // Add admin navigation
        $adminNav = '<?php include "sidebar-nav.php"; ?>';
        
        // Add admin authentication check
        $authCheck = '<?php
            if (!isset($_SESSION["user_id"]) || !isAdmin($_SESSION["user_id"])) {
                header("Location: " . APP_URL . "/pages/auth/login.php");
                exit;
            }
        ?>';
        
        $html = $authCheck . $html;
        $html = str_replace('<body', '<body class="admin-area"', $html);
        $html = str_replace('<main', $adminNav . '<main', $html);
        
        return $html;
    }
    
    // Additional helper methods...
}
```

## Cross-Project Implementation

### Implementation Steps

#### 1. Database Setup
```sql
-- Run the database schema from above
-- Adjust table names if needed for your project
-- Ensure proper foreign key relationships
```

#### 2. Directory Structure Setup
```bash
# Create required directories
mkdir -p templates/{frontend,client,admin,ai-generated}
mkdir -p includes
mkdir -p pages/admin
mkdir -p assets/{css,js}
mkdir -p uploads/templates
mkdir -p api/templates
```

#### 3. Configuration Files

**config/template_config.php**
```php
<?php
// Copy the template configuration from above
// Adjust paths and settings for your project
define('TEMPLATE_SYSTEM_VERSION', '1.0.0');
define('AI_INTEGRATION_ENABLED', true);
define('CROSS_PROJECT_SHARING', true);

// Project-specific settings
$PROJECT_CONFIG = [
    'project_name' => 'Your Project Name',
    'base_url' => 'https://yourproject.com',
    'template_prefix' => 'yourproject_',
    'ai_service_url' => 'https://ai-service.com/api'
];
?>
```

#### 4. Core Files Integration

Copy these core files to your project:
- `includes/template_manager.php`
- `includes/template_functions.php`
- `includes/ai_integration.php`
- `pages/admin/template-manager-comprehensive.php`
- `pages/admin/template-switcher.php`

#### 5. Authentication Integration

Ensure your project has these functions:
```php
function isAdmin($userId) {
    // Your admin check logic
}

function isSuperAdmin($userId) {
    // Your super admin check logic
}

function validateCSRFToken($token) {
    // Your CSRF validation logic
}

function generateCSRFToken() {
    // Your CSRF token generation logic
}
```

## Template Sharing System

### Export Template API

**api/templates/export.php**
```php
<?php
require_once '../../includes/init.php';
require_once '../../includes/template_manager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Validate authentication
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$templateKey = $input['template_key'] ?? '';

if (empty($templateKey)) {
    http_response_code(400);
    echo json_encode(['error' => 'Template key required']);
    exit;
}

$templateManager = new TemplateManager($pdo, $TEMPLATE_CONFIG);
$result = $templateManager->exportTemplate($templateKey);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'export_token' => $result['export_token'],
        'download_url' => APP_URL . '/api/templates/download.php?token=' . $result['export_token']
    ]);
} else {
    http_response_code(400);
    echo json_encode(['error' => $result['error']]);
}
?>
```

### Import Template API

**api/templates/import.php**
```php
<?php
require_once '../../includes/init.php';
require_once '../../includes/template_manager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Validate authentication
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Handle file upload or JSON data
$packageData = null;

if (isset($_FILES['template_package'])) {
    // File upload
    $file = $_FILES['template_package'];
    $content = file_get_contents($file['tmp_name']);
    $packageData = json_decode($content, true);
} else {
    // JSON data
    $packageData = json_decode(file_get_contents('php://input'), true);
}

if (!$packageData) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid package data']);
    exit;
}

$templateManager = new TemplateManager($pdo, $TEMPLATE_CONFIG);
$result = $templateManager->importTemplatePackage($packageData);

echo json_encode($result);
?>
```

### Cross-Project Template Sharing

#### Exporting Templates for Other Projects

```javascript
// JavaScript function to export template
async function exportTemplateForSharing(templateKey) {
    try {
        const response = await fetch('/api/templates/export', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                template_key: templateKey,
                export_type: 'cross_project'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Download the export file
            window.location.href = result.download_url;
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('Export failed:', error);
        alert('Export failed: ' + error.message);
    }
}
```

#### Importing Templates from Other Projects

```javascript
// JavaScript function to import template
async function importTemplateFromProject(packageFile) {
    const formData = new FormData();
    formData.append('template_package', packageFile);
    
    try {
        const response = await fetch('/api/templates/import', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Template imported successfully!');
            location.reload();
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        console.error('Import failed:', error);
        alert('Import failed: ' + error.message);
    }
}
```

## Security Considerations

### 1. Code Validation and Sanitization

```php
class TemplateSecurityValidator {
    private $dangerousFunctions = [
        'exec', 'system', 'shell_exec', 'passthru', 'eval',
        'file_get_contents', 'file_put_contents', 'fopen',
        'include', 'require', 'include_once', 'require_once'
    ];
    
    private $allowedTags = [
        'html', 'head', 'body', 'title', 'meta', 'link',
        'div', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'a', 'img', 'form', 'input', 'button',
        'table', 'tr', 'td', 'th', 'thead', 'tbody', 'script', 'style'
    ];
    
    public function scanCode($code) {
        $issues = [];
        
        // Check for dangerous PHP functions
        foreach ($this->dangerousFunctions as $func) {
            if (preg_match('/\b' . preg_quote($func) . '\s*\(/i', $code)) {
                $issues[] = "Dangerous function detected: {$func}";
            }
        }
        
        // Check for SQL injection patterns
        if (preg_match('/\$_(GET|POST|REQUEST)\s*\[\s*[\'"][^\'"]*[\'"]\s*\]/i', $code)) {
            $issues[] = "Potential SQL injection vulnerability";
        }
        
        // Check for XSS vulnerabilities
        if (preg_match('/echo\s+\$_(GET|POST|REQUEST)/i', $code)) {
            $issues[] = "Potential XSS vulnerability - unescaped output";
        }
        
        // Check for file inclusion vulnerabilities
        if (preg_match('/(include|require)(_once)?\s*\(\s*\$_(GET|POST|REQUEST)/i', $code)) {
            $issues[] = "Potential file inclusion vulnerability";
        }
        
        return [
            'safe' => empty($issues),
            'issues' => $issues
        ];
    }
    
    public function sanitizeCode($code) {
        // Remove dangerous PHP code blocks
        $code = preg_replace('/<\?php\s+(exec|system|shell_exec|passthru|eval)\s*\([^;]*;/i', 
                           '<?php // REMOVED_DANGEROUS_CODE;', $code);
        
        // Escape user inputs
        $code = preg_replace('/echo\s+\$_(GET|POST|REQUEST)\[([^\]]+)\]/', 
                           'echo htmlspecialchars($_$1[$2])', $code);
        
        return $code;
    }
}
```

### 2. Access Control

```php
// Implement role-based access control
function checkTemplateAccess($action, $templateType = null) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $userRole = getUserRole($_SESSION['user_id']);
    
    switch ($action) {
        case 'view':
            return in_array($userRole, ['user', 'admin', 'super_admin']);
            
        case 'create':
        case 'edit':
            return in_array($userRole, ['admin', 'super_admin']);
            
        case 'delete':
        case 'export':
        case 'import':
            return $userRole === 'super_admin';
            
        case 'manage_admin_templates':
            return in_array($userRole, ['admin', 'super_admin']) && 
                   $templateType !== 'admin' || $userRole === 'super_admin';
            
        default:
            return false;
    }
}
```

### 3. File System Security

```php
// Secure file handling
function secureFilePath($path) {
    // Remove directory traversal attempts
    $path = str_replace(['../', '.\\', '..\\'], '', $path);
    
    // Ensure path is within allowed directories
    $allowedDirs = [
        realpath(__DIR__ . '/../templates/'),
        realpath(__DIR__ . '/../uploads/templates/')
    ];
    
    $realPath = realpath($path);
    
    foreach ($allowedDirs as $allowedDir) {
        if (strpos($realPath, $allowedDir) === 0) {
            return $realPath;
        }
    }
    
    throw new SecurityException('Invalid file path');
}
```

## Installation Guide

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Existing user authentication system
- AI Image-to-HTML service (optional but recommended)

### Step-by-Step Installation

#### 1. Database Setup
```sql
-- Create database
CREATE DATABASE template_management;

-- Import schema
SOURCE docs/database_schema.sql;

-- Create initial admin user (if needed)
INSERT INTO users (username, email, password_hash, role) 
VALUES ('admin', 'admin@example.com', PASSWORD('secure_password'), 'super_admin');
```

#### 2. File Installation
```bash
# Copy files to your project
cp -r template_system/* /path/to/your/project/

# Set proper permissions
chmod -R 755 templates/
chmod -R 755 uploads/
chmod 644 config/*.php
```

#### 3. Configuration
```php
// config/app_config.php
define('APP_URL', 'https://yourproject.com');
define('DB_HOST', 'localhost');
define('DB_NAME', 'template_management');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Enable template management
define('TEMPLATE_MANAGEMENT_ENABLED', true);
define('AI_INTEGRATION_ENABLED', true);
```

#### 4. Initialize System
```php
// Run initialization script
include 'includes/init_template_system.php';
```

#### 5. Test Installation
1. Navigate to `/pages/admin/template-manager-comprehensive.php`
2. Verify database connection
3. Test template switching functionality
4. Import a test AI-generated template

## Configuration

### System Settings

```php
// config/template_settings.php
return [
    // Core settings
    'template_management_enabled' => true,
    'ai_integration_enabled' => true,
    'cross_project_sharing' => true,
    
    // Security settings
    'template_validation' => true,
    'security_scanning' => true,
    'safe_mode' => false,
    
    // File settings
    'max_template_size' => 5242880, // 5MB
    'allowed_extensions' => ['php', 'html', 'css', 'js'],
    'template_backup' => true,
    
    // AI settings
    'ai_service_url' => 'http://ai-service/api',
    'ai_api_key' => 'your_api_key',
    'ai_processing_timeout' => 30,
    
    // Export/Import settings
    'export_expiry_hours' => 24,
    'import_validation' => true,
    'overwrite_protection' => true
];
```

### Environment Variables

```bash
# .env file
TEMPLATE_SYSTEM_VERSION=1.0.0
AI_SERVICE_URL=https://ai-service.com/api
AI_API_KEY=your_api_key_here
TEMPLATE_STORAGE_PATH=/var/www/templates
TEMPLATE_BACKUP_ENABLED=true
CROSS_PROJECT_SHARING=true
```

## Usage Examples

### 1. Basic Template Creation

```php
// Create new template from AI output
$templateManager = new TemplateManager($pdo, $config);

$result = $templateManager->importAITemplate([
    'name' => 'Modern Landing Page',
    'description' => 'AI-generated modern landing page',
    'type' => 'frontend',
    'generated_code' => $aiGeneratedHTML,
    'ai_source' => 'Image: landing-page-design.jpg'
]);

if ($result['success']) {
    echo "Template created: " . $result['template_key'];
} else {
    echo "Error: " . implode(', ', $result['errors']);
}
```

### 2. Template Switching

```php
// Switch active frontend template
$result = $templateManager->switchTemplate('frontend', 'modern-landing-page-123456');

if ($result['success']) {
    echo "Template switched successfully";
} else {
    echo "Switch failed: " . $result['error'];
}
```

### 3. Cross-Project Export

```php
// Export template for another project
$result = $templateManager->exportTemplate('modern-landing-page-123456');

if ($result['success']) {
    // Save export file
    file_put_contents('template_export.json', json_encode($result['data']));
    echo "Template exported successfully";
} else {
    echo "Export failed: " . $result['error'];
}
```

### 4. Template Import

```php
// Import template from another project
$packageData = json_decode(file_get_contents('template_export.json'), true);

$result = $templateManager->importTemplatePackage($packageData);

if ($result['success']) {
    echo "Template imported: " . $result['template_key'];
} else {
    echo "Import failed: " . $result['error'];
}
```

## API Documentation

### Template Management API Endpoints

#### GET /api/templates
List all templates
```
Response:
{
    "success": true,
    "templates": [
        {
            "key": "template-key",
            "name": "Template Name",
            "type": "frontend",
            "is_active": true,
            "is_ai_generated": true
        }
    ]
}
```

#### POST /api/templates/import
Import AI-generated template
```
Request:
{
    "name": "Template Name",
    "type": "frontend",
    "generated_code": "<html>...</html>",
    "description": "Template description"
}

Response:
{
    "success": true,
    "template_key": "generated-key"
}
```

#### PUT /api/templates/{key}/activate
Activate template
```
Response:
{
    "success": true,
    "message": "Template activated"
}
```

#### GET /api/templates/{key}/export
Export template
```
Response:
{
    "success": true,
    "export_token": "token",
    "download_url": "/api/templates/download?token=..."
}
```

#### POST /api/templates/import-package
Import template package
```
Request: FormData with 'template_package' file

Response:
{
    "success": true,
    "template_key": "imported-key"
}
```

### JavaScript SDK

```javascript
class TemplateManagerSDK {
    constructor(baseUrl, csrfToken) {
        this.baseUrl = baseUrl;
        this.csrfToken = csrfToken;
    }
    
    async importAITemplate(templateData) {
        const response = await fetch(`${this.baseUrl}/api/templates/import`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken
            },
            body: JSON.stringify(templateData)
        });
        
        return await response.json();
    }
    
    async switchTemplate(type, templateKey) {
        const response = await fetch(`${this.baseUrl}/api/templates/${templateKey}/activate`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken
            },
            body: JSON.stringify({ type })
        });
        
        return await response.json();
    }
    
    async exportTemplate(templateKey) {
        const response = await fetch(`${this.baseUrl}/api/templates/${templateKey}/export`, {
            method: 'GET',
            headers: {
                'X-CSRF-Token': this.csrfToken
            }
        });
        
        return await response.json();
    }
}

// Usage
const sdk = new TemplateManagerSDK('/path/to/api', csrfToken);
const result = await sdk.importAITemplate(templateData);
```

## Troubleshooting

### Common Issues

#### 1. Templates Not Loading
**Problem**: Templates don't appear in the management interface
**Solutions**:
- Check database connection
- Verify table structure matches schema
- Check file permissions on template directories
- Review error logs for PHP errors

```bash
# Check permissions
ls -la templates/
chmod -R 755 templates/

# Check database
mysql -u username -p
USE your_database;
SELECT * FROM templates;
```

#### 2. AI Import Failing
**Problem**: AI-generated code import fails
**Solutions**:
- Validate HTML structure
- Check for PHP syntax errors
- Verify security scanner settings
- Review code size limits

```php
// Debug AI import
$validator = new TemplateSecurityValidator();
$result = $validator->scanCode($aiCode);
var_dump($result);
```

#### 3. Template Switching Not Working
**Problem**: Active template doesn't change
**Solutions**:
- Clear application cache
- Check template file existence
- Verify user permissions
- Review activity logs

```sql
-- Check active templates
SELECT * FROM settings WHERE setting_key LIKE 'active_template_%';

-- Check template files
SELECT template_key, file_path FROM templates WHERE status = 'active';
```

#### 4. Cross-Project Import Issues
**Problem**: Templates imported from other projects don't work
**Solutions**:
- Check compatibility versions
- Verify package format
- Review path adjustments needed
- Check dependency requirements

### Debug Mode

Enable debug mode for detailed error reporting:

```php
// config/debug.php
define('TEMPLATE_DEBUG', true);
define('TEMPLATE_LOG_LEVEL', 'DEBUG');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log template operations
function debugLog($message, $data = null) {
    if (TEMPLATE_DEBUG) {
        $logEntry = date('Y-m-d H:i:s') . " - {$message}";
        if ($data) {
            $logEntry .= " - " . json_encode($data);
        }
        error_log($logEntry, 3, 'logs/template_debug.log');
    }
}
```

### Performance Optimization

#### 1. Template Caching
```php
// Implement template caching
class TemplateCache {
    private $cacheDir;
    
    public function __construct($cacheDir = 'cache/templates/') {
        $this->cacheDir = $cacheDir;
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function get($key) {
        $file = $this->cacheDir . md5($key) . '.cache';
        if (file_exists($file) && (time() - filemtime($file)) < 3600) {
            return unserialize(file_get_contents($file));
        }
        return false;
    }
    
    public function set($key, $data) {
        $file = $this->cacheDir . md5($key) . '.cache';
        file_put_contents($file, serialize($data));
    }
}
```

#### 2. Database Optimization
```sql
-- Add indexes for better performance
CREATE INDEX idx_templates_type_active ON templates(type, is_active);
CREATE INDEX idx_ai_templates_status_type ON ai_templates(status, template_type);
CREATE INDEX idx_template_logs_date ON template_activity_logs(created_at);

-- Optimize queries
EXPLAIN SELECT * FROM templates WHERE type = 'frontend' AND is_active = 1;
```

## Best Practices

### 1. Template Development
- Always validate AI-generated code before deployment
- Include responsive design considerations
- Follow accessibility guidelines (WCAG 2.1 AA)
- Use semantic HTML structure
- Optimize for performance (minimize CSS/JS)
- Include proper error handling
- Document template features and requirements

### 2. Security Best Practices
- Never trust AI-generated code without validation
- Implement content security policies
- Use prepared statements for database queries
- Sanitize all user inputs
- Regular security audits of templates
- Keep template system updated
- Monitor for suspicious activity

### 3. Performance Best Practices
- Implement template caching
- Optimize images and assets
- Minimize HTTP requests
- Use CDN for static assets
- Enable gzip compression
- Monitor template loading times
- Regular performance audits

### 4. Maintenance Best Practices
- Regular database cleanup
- Template backup procedures
- Version control for custom templates
- Documentation updates
- User training on template management
- Regular system health checks
- Plan for template migrations

## Future Enhancements

### Planned Features
1. **Template Marketplace**: Share templates with the community
2. **Advanced AI Integration**: More sophisticated AI template generation
3. **Template Versioning**: Git-like version control for templates
4. **A/B Testing**: Built-in template testing capabilities
5. **Analytics Integration**: Template performance tracking
6. **Mobile App**: Template management mobile application
7. **Plugin System**: Extensible template functionality
8. **Cloud Storage**: Cloud-based template storage and sync

### Extensibility Points
- Custom validation rules
- Additional template types
- Third-party AI service integration
- Custom export formats
- Template preview enhancements
- Advanced security scanning
- Performance monitoring
- Custom analytics

---

## AI-Powered Deployment Manager

### Overview
The AI-Powered Deployment Manager is a comprehensive tool that enables users to upload, edit, and deploy projects with AI assistance. It integrates seamlessly with the Template Management System to provide a complete development workflow.

### Key Features
- **Secure File Upload**: Progress tracking with real-time validation
- **AI Error Detection**: Real-time code analysis and correction suggestions
- **In-Browser Editor**: Monaco-based code editor with syntax highlighting
- **Version Control**: Git integration for change tracking
- **Automated Testing**: Comprehensive test suites before deployment
- **One-Click Deployment**: Streamlined deployment to live servers
- **AI Assistant**: Context-aware help and guidance

### Workflow Process
```
Debug Project → Upload Files → Edit Code → Run Tests → Configure Server → Deploy
     ↓              ↓           ↓          ↓           ↓              ↓
[Debugger] → [File Manager] → [Editor] → [Testing] → [Server] → [Live Site]
```

### Implementation Files
- `pages/client/deployment-manager.php` - Main interface
- `includes/deployment_manager.php` - Core logic
- `includes/ai_error_detector.php` - Error detection
- `includes/automated_test_runner.php` - Testing framework
- `includes/deployment_engine.php` - Deployment logic

### Integration with Debugger
The deployment manager works in conjunction with the existing debugger tool:
1. User debugs project using the debugger tool
2. Once debugging is complete, user proceeds to deployment manager
3. System requests server details and file selection
4. Seamless handoff from debugging to deployment

---

## Post-Creation Template Editing

### Overview
After template creation, users can continue editing their templates with a limited number of edits based on their subscription tier.

### Edit Limit System
- **Free Plan**: 2 edits per template
- **Professional Plan**: 5 edits per template  
- **Enterprise Plan**: Unlimited edits
- **Admin Override**: Administrators can grant additional edits

### Editable Elements
- **Colors & Themes**: Complete color palette customization
- **Typography**: Font families, sizes, weights, and spacing
- **Images**: Replace placeholders with actual images
- **Layout**: Spacing, alignment, and structure adjustments
- **Content**: Text content and headings

### Features
- **Real-Time Preview**: See changes instantly across devices
- **Auto-Save**: Progress saved every 30 seconds
- **Version History**: Track all edits and revert if needed
- **Edit Counter**: Clear indication of remaining edits
- **Upgrade Prompts**: Seamless upgrade path for more edits

### Implementation Files
- `pages/client/template-editor.php` - Main editing interface
- `includes/template_editor.php` - Edit logic and limits
- `database/migrations/edit_limits.sql` - Database schema

---

## Subscription Management System

### Admin Controls
Comprehensive subscription management for administrators:

#### Subscription Plans Management
- **Create/Edit/Delete Plans**: Full CRUD operations
- **Feature Matrix**: Control feature access per plan
- **Pricing Configuration**: Flexible pricing and billing cycles
- **User Assignment**: Assign plans to users

#### Edit Limits Configuration
- **Global Settings**: Default edit limits
- **Plan-Specific Limits**: Custom limits per subscription tier
- **Admin Override**: Bypass limits when needed
- **Usage Tracking**: Monitor edit usage across users

#### Analytics Dashboard
- **Revenue Tracking**: Monthly and yearly revenue analysis
- **Conversion Rates**: Free to paid conversion metrics
- **Feature Usage**: Most used features and templates
- **User Activity**: Active users and engagement metrics

### User Experience
- **Clear Limit Display**: Users always know their edit count
- **Upgrade Prompts**: Contextual subscription upgrade suggestions
- **Usage History**: Track template creation and editing history
- **Billing Integration**: Seamless Stripe integration for payments

### Implementation Files
- `pages/admin/subscriptions.php` - Admin interface
- `includes/subscription_admin.php` - Management logic
- `includes/subscription_manager.php` - User-facing logic
- `database/migrations/create_subscription_tables.sql` - Database schema

---

## API Cost Analysis

### Overview
Comprehensive cost analysis for running the AI Development Suite, including all implemented features and their API dependencies.

### Major Cost Categories
1. **AI APIs (40-50% of total costs)**
   - OpenAI GPT-4: Template generation, code analysis
   - OpenAI DALL-E 3: Image generation
   - Google Vision API: Image analysis and OCR

2. **Infrastructure (25-35% of total costs)**
   - Server hosting and scaling
   - Database and storage
   - CDN and file delivery

3. **Third-Party Services (15-25% of total costs)**
   - Email and SMS services
   - Payment processing
   - Monitoring and analytics

### Cost Tiers
- **Startup (100 users)**: $577-677/month
- **Growth (300 users)**: $1,440-1,560/month  
- **Enterprise (1000+ users)**: $3,280-3,650/month

### Profit Margins
- **Startup Tier**: 92.5% profit margin
- **Growth Tier**: 91.8% profit margin
- **Enterprise Tier**: 90.9% profit margin

### Cost Optimization Strategies
- AI response caching (30% reduction)
- Token optimization (20% reduction)
- Auto-scaling infrastructure (25% reduction)
- Bulk API discounts (15% reduction)

### Full Documentation
See `docs/API_Cost_Analysis.md` for complete cost breakdown, ROI analysis, and optimization strategies.

---

## Conclusion

This Template Management System provides a comprehensive, secure, and extensible solution for managing templates across multiple projects. The integration with AI Image-to-HTML conversion and the new AI-Powered Deployment Manager makes it a complete development and deployment solution.

The enhanced system now includes:
- **AI-Powered Deployment Manager**: Complete workflow from debug to deployment
- **Post-Creation Template Editing**: Continued refinement with subscription-based limits
- **Comprehensive Subscription Management**: Full admin control over plans and features
- **Cost-Effective Operation**: Detailed cost analysis with 90%+ profit margins

The system is designed to be:
- **Secure**: With comprehensive validation and security scanning
- **Flexible**: Supporting multiple template types and customization
- **Scalable**: Built for high-performance and large-scale deployments
- **Maintainable**: With clear documentation and best practices
- **Extensible**: Ready for future enhancements and integrations
- **Profitable**: With strong profit margins and cost optimization

By following this documentation, you can implement the complete AI Development Suite system in any project and leverage the full power of AI-assisted template creation, editing, and deployment.

For support and updates, please refer to the project repository and community resources.

**Last Updated**: December 2024  
**Version**: 2.0 (Enhanced with Deployment Manager and Subscription System)
