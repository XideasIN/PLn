# Email Template System & Admin Interface Documentation

**LoanFlow Personal Loan Management System**  
**Document Version:** 1.0  
**Created:** January 2025  
**Last Updated:** January 2025

---

## Table of Contents

1. [Overview](#overview)
2. [Email Template System](#email-template-system)
3. [Enhanced Contact Form Processing](#enhanced-contact-form-processing)
4. [Admin Management Interface](#admin-management-interface)
5. [Technical Implementation](#technical-implementation)
6. [Security Features](#security-features)
7. [Usage Guide](#usage-guide)
8. [API Endpoints](#api-endpoints)
9. [Database Schema](#database-schema)
10. [Troubleshooting](#troubleshooting)

---

## Overview

This document outlines the comprehensive email template system, enhanced contact form processing, and admin management interface implemented for the LoanFlow system. These features provide a professional, scalable solution for managing email communications with responsive design capabilities and visual editing tools.

### Key Features Implemented

- **Standardized Email Template System** with header/footer components
- **Visual Email Editor** with responsive design capabilities
- **Enhanced Contact Form Processing** with immediate acknowledgments
- **Comprehensive Admin Interface** for template management
- **Real-time Preview System** with device-specific rendering
- **Test Email Functionality** with sample data replacement

---

## Email Template System

### Core Components

#### 1. EmailTemplateSystem Class
**File:** `includes/email_template_system.php`

**Key Features:**
- Database-driven template management
- Variable replacement system
- Responsive email construction
- Component-based architecture (header, footer, acknowledgment)
- Preview functionality with sample data

**Core Methods:**
```php
// Retrieve components by type
getComponentsByType($type, $default_only = false)

// Get template variables with admin overrides
getTemplateVariables()

// Replace variables in content
replaceVariables($content, $custom_variables = [])

// Assemble complete email with responsive styling
assembleEmail($content, $subject = '', $custom_variables = [])

// Send acknowledgment emails
sendContactAcknowledgment($contact_data)

// Generate preview with sample data
getPreviewContent($component_id = null, $device = 'desktop')
```

#### 2. Database Structure
**Table:** `email_components`

```sql
CREATE TABLE email_components (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('header', 'footer', 'acknowledgment') NOT NULL,
    name VARCHAR(255) NOT NULL,
    html_content TEXT NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Table:** `email_template_variables`

```sql
CREATE TABLE email_template_variables (
    id INT PRIMARY KEY AUTO_INCREMENT,
    variable_key VARCHAR(100) NOT NULL UNIQUE,
    variable_name VARCHAR(255) NOT NULL,
    default_value TEXT,
    is_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### 3. Template Variables System

**System Variables:**
- `{company_name}` - Company name from admin settings
- `{company_phone}` - Company phone number
- `{company_email}` - Company email address
- `{company_address}` - Company physical address
- `{support_email}` - Support contact email
- `{website_url}` - Company website URL
- `{login_url}` - Client login portal URL
- `{current_year}` - Current year
- `{current_date}` - Current date

**Dynamic Variables:**
- `{first_name}` - Customer first name
- `{last_name}` - Customer last name
- `{email}` - Customer email address
- `{phone}` - Customer phone number
- `{reference_number}` - Application reference number
- `{inquiry_id}` - Contact inquiry ID
- `{message}` - Customer message content

---

## Enhanced Contact Form Processing

### Implementation Details

#### 1. Contact Form Handler
**File:** `api/submit-contact.php`

**Enhanced Features:**
- Immediate acknowledgment email sending
- Inquiry ID generation and tracking
- Integration with email template system
- Comprehensive validation and sanitization
- Audit logging for all submissions

**Processing Flow:**
1. **Form Validation** - Server-side validation of all fields
2. **Data Sanitization** - Clean and secure input data
3. **Database Storage** - Store inquiry with unique ID
4. **Immediate Acknowledgment** - Send confirmation email using templates
5. **Admin Notification** - Optional admin notification system
6. **Audit Logging** - Log all contact form submissions

#### 2. Acknowledgment Email System

**Template Structure:**
```html
<!-- Responsive Email Container -->
<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
    <!-- Header Component -->
    {header_content}
    
    <!-- Main Content -->
    <div style="padding: 20px; background-color: #ffffff;">
        <h2>Thank you for contacting us!</h2>
        <p>Dear {first_name},</p>
        <p>We have received your inquiry and will respond within 24 hours.</p>
        <p><strong>Inquiry ID:</strong> {inquiry_id}</p>
        <p><strong>Your Message:</strong></p>
        <div style="background-color: #f8f9fa; padding: 15px; border-left: 4px solid #007bff;">
            {message}
        </div>
    </div>
    
    <!-- Footer Component -->
    {footer_content}
</div>
```

**Responsive Design Features:**
- Mobile-optimized layout
- Scalable typography
- Touch-friendly buttons
- Cross-client compatibility

---

## Admin Management Interface

### 1. Email Templates Management
**File:** `admin/email-templates.php`

#### Features Overview

**Component Management:**
- Create, edit, and delete email components
- Set default components for each type
- Activate/deactivate components
- Bulk operations support

**Visual Editor:**
- Fullscreen modal editor
- Visual/Code editor toggle with real-time sync
- Rich text formatting toolbar
- Element insertion tools (buttons, images, dividers)
- Template quick-start options

**Responsive Preview System:**
- Desktop preview (600px width)
- Tablet preview (480px width)
- Mobile preview (320px width)
- Device-specific CSS overrides
- Real-time preview updates

**Design Controls:**
- Typography settings (font family, size, weight, color)
- Color picker for backgrounds and text
- Spacing controls (padding, margins)
- Layout templates (single/multi-column)
- Style reset functionality

#### Interface Components

**Main Dashboard:**
```html
<!-- Component Cards -->
<div class="component-card">
    <div class="component-header">
        <h5>Header Components</h5>
        <button class="btn btn-primary" onclick="openComponentModal('header')">
            <i class="fas fa-plus"></i> Add Header
        </button>
    </div>
    <div class="component-list">
        <!-- Dynamic component list -->
    </div>
</div>
```

**Fullscreen Editor Modal:**
```html
<div class="modal-fullscreen">
    <!-- Left Panel: Design Controls -->
    <div class="editor-panel-left">
        <div class="design-controls">
            <!-- Typography, Colors, Spacing Controls -->
        </div>
    </div>
    
    <!-- Center Panel: Editor -->
    <div class="editor-panel-center">
        <div class="editor-toolbar">
            <!-- Formatting buttons -->
        </div>
        <div class="editor-content">
            <!-- Visual/Code editor -->
        </div>
    </div>
    
    <!-- Right Panel: Preview -->
    <div class="editor-panel-right">
        <div class="preview-controls">
            <!-- Device switcher -->
        </div>
        <div class="preview-container">
            <!-- Responsive preview iframe -->
        </div>
    </div>
</div>
```

### 2. Variable Management System

**Variable Panel:**
- List all available template variables
- Copy-to-clipboard functionality
- Variable descriptions and usage examples
- System vs. custom variable indicators

**Variable Insertion:**
- Quick insertion into editor
- Variable prompts for custom values
- Real-time variable replacement in preview

### 3. Test Email Functionality
**File:** `admin/ajax/send-test-email.php`

**Features:**
- Send test emails with sample data
- Email address validation
- Complete email assembly with styling
- Success/failure notifications
- Error handling and logging

**Test Email Process:**
1. **Input Validation** - Validate email address format
2. **Sample Data Generation** - Replace variables with test data
3. **Email Assembly** - Build complete responsive email
4. **Email Sending** - Send via configured SMTP
5. **Response Handling** - Return JSON success/error response

---

## Technical Implementation

### 1. File Structure

```
admin/
├── email-templates.php          # Main admin interface
├── ajax/
│   ├── preview-component.php    # Component preview endpoint
│   ├── preview-content.php      # Enhanced preview with device support
│   └── send-test-email.php      # Test email functionality

includes/
├── email_template_system.php    # Core template system class
├── functions.php                # Helper functions
└── auth.php                     # Authentication system

database/
├── migrations/
│   └── add_email_components.sql # Database schema
```

### 2. JavaScript Architecture

**Core Functions:**
```javascript
// Editor management
toggleEditor(mode)              // Switch between visual/code editor
syncEditorContent()             // Sync content between editors

// Preview system
switchDevice(device)            // Change preview device
updatePreview()                 // Refresh preview content

// Design controls
applyStyle(property, value)     // Apply CSS styles
resetStyles()                   // Reset to default styles
parseExistingStyles(content)    // Extract styles from content

// Template insertion
insertTemplate(type)            // Insert layout templates
insertElement(element)          // Insert UI elements
insertVariable(variable)        // Insert template variables

// Test functionality
sendTestEmail(email, content)   // Send test email
```

### 3. CSS Architecture

**Responsive Design Classes:**
```css
/* Editor panels */
.editor-panel-left { /* Design controls */ }
.editor-panel-center { /* Main editor */ }
.editor-panel-right { /* Preview panel */ }

/* Device previews */
.preview-desktop { width: 600px; }
.preview-tablet { width: 480px; }
.preview-mobile { width: 320px; }

/* Visual editor */
.visual-editor { /* WYSIWYG editor styles */ }
.code-editor { /* Code editor styles */ }

/* Design controls */
.design-controls { /* Control panel styles */ }
.color-picker { /* Color selection */ }
.style-input { /* Style input fields */ }
```

---

## Security Features

### 1. Authentication & Authorization
- **Session-based authentication** for admin access
- **Role-based access control** (admin role required)
- **CSRF protection** on all forms
- **Input validation** and sanitization

### 2. Data Security
- **SQL injection prevention** using prepared statements
- **XSS protection** with content sanitization
- **File upload restrictions** (if applicable)
- **Audit logging** for all admin actions

### 3. Email Security
- **SMTP authentication** for email sending
- **Rate limiting** on test email functionality
- **Content validation** before email assembly
- **Error handling** without information disclosure

---

## Usage Guide

### 1. Accessing the Admin Interface

1. **Login to Admin Panel:**
   - URL: `https://yourdomain.com/admin/`
   - Use admin credentials

2. **Navigate to Email Templates:**
   - Click "Email Templates" in admin menu
   - URL: `https://yourdomain.com/admin/email-templates.php`

### 2. Managing Email Components

**Creating a New Component:**
1. Click "Add [Type]" button (Header/Footer/Acknowledgment)
2. Enter component name
3. Use visual editor or code editor to create content
4. Preview across different devices
5. Test with sample email
6. Save and activate

**Editing Existing Components:**
1. Click "Edit" on component card
2. Modify content in fullscreen editor
3. Use design controls for styling
4. Preview changes in real-time
5. Save changes

**Setting Default Components:**
1. Click "Set as Default" on desired component
2. Confirm the change
3. Default components are used automatically in emails

### 3. Using the Visual Editor

**Editor Modes:**
- **Visual Mode:** WYSIWYG editor with formatting toolbar
- **Code Mode:** HTML/CSS code editor with syntax highlighting
- **Sync:** Content automatically syncs between modes

**Design Controls:**
- **Typography:** Font family, size, weight, color
- **Colors:** Background colors, text colors, accent colors
- **Spacing:** Padding, margins, line height
- **Layout:** Single column, multi-column templates

**Element Insertion:**
- **Buttons:** Call-to-action buttons with styling
- **Images:** Responsive image containers
- **Dividers:** Horizontal rules and spacers
- **Variables:** Template variables for dynamic content

### 4. Testing Email Templates

**Test Email Process:**
1. Enter test email address
2. Click "Send Test Email"
3. Check email delivery and rendering
4. Verify responsive design on different devices
5. Test variable replacement

---

## API Endpoints

### 1. Preview Endpoints

**Component Preview:**
```
GET /admin/ajax/preview-component.php?id={component_id}
Response: HTML content of component
```

**Enhanced Preview:**
```
GET /admin/ajax/preview-content.php?id={component_id}&device={device}
Parameters:
- id: Component ID (optional)
- device: desktop|tablet|mobile
Response: Complete HTML document with device-specific styling
```

### 2. Test Email Endpoint

**Send Test Email:**
```
POST /admin/ajax/send-test-email.php
Parameters:
- email: Test email address
- content: Email content to test
Response: JSON {success: boolean, message: string}
```

### 3. Component Management

**CRUD Operations:**
- Create: POST to email-templates.php with component data
- Read: GET component list from database
- Update: POST with component ID and updated data
- Delete: POST with delete action and component ID

---

## Database Schema

### Email Components Table
```sql
CREATE TABLE `email_components` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('header','footer','acknowledgment') NOT NULL,
  `name` varchar(255) NOT NULL,
  `html_content` text NOT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type_default` (`type`,`is_default`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Template Variables Table
```sql
CREATE TABLE `email_template_variables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `variable_key` varchar(100) NOT NULL,
  `variable_name` varchar(255) NOT NULL,
  `default_value` text,
  `is_system` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `variable_key` (`variable_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Sample Data
```sql
-- Default Components
INSERT INTO email_components (type, name, html_content, is_default, is_active) VALUES
('header', 'Default Header', '<div style="background-color: #007bff; color: white; padding: 20px; text-align: center;"><h1>{company_name}</h1></div>', 1, 1),
('footer', 'Default Footer', '<div style="background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px;"><p>&copy; {current_year} {company_name}. All rights reserved.</p></div>', 1, 1),
('acknowledgment', 'Contact Acknowledgment', '<div style="padding: 20px;"><h2>Thank you for contacting us!</h2><p>Dear {first_name},</p><p>We have received your inquiry (ID: {inquiry_id}) and will respond within 24 hours.</p></div>', 1, 1);

-- System Variables
INSERT INTO email_template_variables (variable_key, variable_name, default_value, is_system) VALUES
('{company_name}', 'Company Name', 'LoanFlow', TRUE),
('{company_phone}', 'Company Phone', '+1-555-123-4567', TRUE),
('{company_email}', 'Company Email', 'info@loanflow.com', TRUE),
('{support_email}', 'Support Email', 'support@loanflow.com', TRUE),
('{website_url}', 'Website URL', 'https://loanflow.com', TRUE),
('{login_url}', 'Client Login URL', '/client/login.php', TRUE),
('{current_year}', 'Current Year', '2025', TRUE),
('{current_date}', 'Current Date', 'January 2025', TRUE);
```

---

## Troubleshooting

### Common Issues

#### 1. Email Templates Not Loading
**Symptoms:** Empty template list or error messages
**Solutions:**
- Check database connection
- Verify email_components table exists
- Run database migrations
- Check admin authentication

#### 2. Preview Not Working
**Symptoms:** Preview shows blank or error
**Solutions:**
- Check preview endpoint accessibility
- Verify component ID exists
- Check for JavaScript errors in browser console
- Ensure proper file permissions

#### 3. Test Emails Not Sending
**Symptoms:** Test email fails or not received
**Solutions:**
- Verify SMTP configuration in config/email.php
- Check email credentials and authentication
- Review email logs for errors
- Test SMTP connection independently

#### 4. Visual Editor Issues
**Symptoms:** Editor not loading or syncing
**Solutions:**
- Check JavaScript console for errors
- Verify all CSS/JS files are loaded
- Clear browser cache
- Check for conflicting scripts

#### 5. Responsive Preview Problems
**Symptoms:** Preview not showing correctly for different devices
**Solutions:**
- Check CSS media queries
- Verify iframe dimensions
- Test with actual devices/browsers
- Review responsive CSS rules

### Debug Mode

Enable debug mode by adding to config:
```php
// Enable debug mode
define('DEBUG_MODE', true);
define('LOG_LEVEL', 'DEBUG');
```

### Log Files
- **Email logs:** `logs/email.log`
- **Error logs:** `logs/error.log`
- **Admin logs:** `logs/admin.log`
- **Debug logs:** `logs/debug.log`

---

## Conclusion

The Email Template System provides a comprehensive solution for managing email communications in the LoanFlow system. With its visual editor, responsive design capabilities, and robust admin interface, it enables professional email template management comparable to modern email marketing platforms.

### Key Benefits

1. **Professional Email Design** - Responsive templates with visual editing
2. **Streamlined Management** - Centralized admin interface
3. **Enhanced User Experience** - Immediate acknowledgments and professional communications
4. **Scalable Architecture** - Component-based system for easy maintenance
5. **Security & Reliability** - Comprehensive security measures and error handling

### Future Enhancements

- **A/B Testing** - Template performance comparison
- **Analytics Integration** - Email open/click tracking
- **Advanced Personalization** - Dynamic content based on user data
- **Template Library** - Pre-built template collection
- **Multi-language Support** - Localized email templates

---

**Document Status:** Complete  
**Implementation Status:** Production Ready  
**Last Review:** January 2025  
**Next Review:** As needed for updates

---

*This documentation covers the complete implementation of the Email Template System, Enhanced Contact Form Processing, and Admin Management Interface for the LoanFlow Personal Loan Management System.*