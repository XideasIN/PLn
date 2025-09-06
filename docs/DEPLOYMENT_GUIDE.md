# LoanFlow HTML/PHP Deployment Guide

**Version:** 2.0  
**Date:** January 2025  
**Project:** LoanFlow Personal Loan Management System  
**Technology:** HTML/PHP/MySQL

---

## Table of Contents

1. [Overview](#overview)
2. [System Requirements](#system-requirements)
3. [Pre-Deployment Checklist](#pre-deployment-checklist)
4. [Installation Steps](#installation-steps)
5. [Configuration](#configuration)
6. [Database Setup](#database-setup)
7. [Security Configuration](#security-configuration)
8. [Cron Jobs Setup](#cron-jobs-setup)
9. [Testing](#testing)
10. [Troubleshooting](#troubleshooting)
11. [Maintenance](#maintenance)

---

## Overview

This guide provides step-by-step instructions for deploying the LoanFlow Personal Loan Management System on any web hosting provider that supports PHP and MySQL.

### Key Features Included

- ✅ **6-digit reference number system** for client tracking
- ✅ **Multi-country support** (USA, Canada, UK, Australia)
- ✅ **Automated email system** with customizable templates
- ✅ **Document management** with secure file uploads
- ✅ **Digital signature system** for loan agreements
- ✅ **Payment processing** (subscription and percentage models)
- ✅ **Client dashboard** with step-by-step process tracking
- ✅ **Admin panel** with comprehensive management tools
- ✅ **CRM system** with call lists and client memos
- ✅ **Audit logging** for security and compliance
- ✅ **Responsive design** for mobile and desktop

---

## System Requirements

### Minimum Requirements

- **PHP:** 8.0 or higher
- **MySQL:** 8.0 or higher (or MariaDB 10.4+)
- **Web Server:** Apache 2.4+ or Nginx 1.18+
- **Storage:** 2GB free disk space minimum
- **Memory:** 512MB RAM minimum (1GB+ recommended)
- **SSL Certificate:** Required for production

### PHP Extensions Required

```bash
# Required PHP extensions
- pdo
- pdo_mysql
- mbstring
- openssl
- curl
- gd
- fileinfo
- json
- session
```

### Recommended Server Configuration

```apache
# Apache .htaccess configuration
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

---

## Pre-Deployment Checklist

### Before You Begin

- [ ] Web hosting account with PHP 8.0+ and MySQL 8.0+
- [ ] Domain name configured and pointing to your hosting
- [ ] SSL certificate installed
- [ ] FTP/SSH access credentials
- [ ] Database credentials from your hosting provider

### Files to Prepare

- [ ] Download the complete NewReBuild folder
- [ ] Verify all files are present (see File Structure below)
- [ ] Prepare configuration values (database, email, etc.)

### File Structure Verification

```
NewReBuild/
├── admin/                 # Admin panel files
│   ├── index.php         # Admin dashboard
│   ├── applications.php  # Application management
│   └── ...
├── client/               # Client area files
│   ├── dashboard.php     # Client dashboard
│   ├── documents.php     # Document upload
│   └── ...
├── config/               # Configuration files
│   ├── database.php      # Database settings
│   ├── email.php         # Email configuration
│   └── countries.php     # Country settings
├── includes/             # Core functions
│   ├── functions.php     # Main functions
│   ├── email.php         # Email system
│   └── payment.php       # Payment processing
├── assets/               # CSS, JS, images
│   ├── css/
│   ├── js/
│   └── images/
├── database/             # Database schema
│   └── schema.sql        # Database structure
├── cron/                 # Automated tasks
│   ├── daily-tasks.php   # Daily maintenance
│   └── process-emails.php # Email processing
├── uploads/              # File uploads (auto-created)
├── templates/            # Email templates
├── docs/                 # Documentation
├── index.php             # Main application form
├── login.php             # Login page
└── README.md             # Project information
```

---

## Installation Steps

### Step 1: Upload Files

#### Option A: FTP Upload

1. **Connect to your hosting via FTP:**
   ```bash
   # Example using FileZilla or WinSCP
   Host: ftp.yourdomain.com
   Username: your-ftp-username
   Password: your-ftp-password
   Port: 21
   ```

2. **Upload the NewReBuild folder contents:**
   - Upload all files to your web root directory (usually `public_html` or `www`)
   - Ensure file permissions are set correctly (755 for directories, 644 for files)

#### Option B: SSH Upload

1. **Create a deployment package:**
   ```bash
   # On your local machine
   cd NewReBuild
   tar -czf loanflow-deploy.tar.gz *
   ```

2. **Upload and extract:**
   ```bash
   # Upload to server
   scp loanflow-deploy.tar.gz user@yourserver.com:/path/to/webroot/
   
   # SSH into server and extract
   ssh user@yourserver.com
   cd /path/to/webroot/
   tar -xzf loanflow-deploy.tar.gz
   rm loanflow-deploy.tar.gz
   ```

### Step 2: Set File Permissions

```bash
# Set proper permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# Make uploads directory writable
chmod 755 uploads/
chmod 755 cron/

# Secure sensitive files
chmod 600 config/*.php
```

---

## Configuration

### Step 1: Database Configuration

Edit `config/database.php`:

```php
<?php
// Database connection settings
define('DB_HOST', 'localhost');           // Your MySQL host
define('DB_NAME', 'your_database_name');  // Your database name
define('DB_USER', 'your_db_username');    // Your database username
define('DB_PASS', 'your_secure_password'); // Your database password
define('DB_CHARSET', 'utf8mb4');
?>
```

### Step 2: Email Configuration

Edit `config/email.php`:

```php
<?php
// Email configuration
return [
    'smtp_host' => 'smtp.yourdomain.com',
    'smtp_port' => 587,
    'smtp_username' => 'noreply@yourdomain.com',
    'smtp_password' => 'your_email_password',
    'smtp_encryption' => 'tls',
    'from_name' => 'LoanFlow',
    'from_email' => 'noreply@yourdomain.com',
    'reply_to' => 'support@yourdomain.com'
];
?>
```

### Step 3: System Settings

The system will use default settings, but you can customize them through the admin panel after installation.

---

## Database Setup

### Step 1: Create Database

1. **Via cPanel/Hosting Control Panel:**
   - Log into your hosting control panel
   - Navigate to "MySQL Databases"
   - Create a new database (e.g., `loanflow`)
   - Create a database user with full privileges
   - Note down the database name, username, and password

2. **Via Command Line:**
   ```sql
   CREATE DATABASE loanflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'loanflow_user'@'localhost' IDENTIFIED BY 'secure_password';
   GRANT ALL PRIVILEGES ON loanflow.* TO 'loanflow_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

### Step 2: Import Database Schema

1. **Via phpMyAdmin:**
   - Access phpMyAdmin from your hosting control panel
   - Select your database
   - Click "Import"
   - Upload the `database/schema.sql` file
   - Click "Go" to execute

2. **Via Command Line:**
   ```bash
   mysql -u loanflow_user -p loanflow < database/schema.sql
   ```

### Step 3: Verify Database Setup

The schema includes:
- ✅ All required tables (users, loan_applications, documents, etc.)
- ✅ Default admin user (admin@loanflow.com / admin123)
- ✅ Default email templates
- ✅ Sample payment schemes
- ✅ Country configurations
- ✅ System settings

---

## Security Configuration

### Step 1: Secure File Permissions

```bash
# Protect configuration files
chmod 600 config/*.php

# Protect uploads directory from direct access
echo "Deny from all" > uploads/.htaccess

# Protect cron directory
echo "Deny from all" > cron/.htaccess

# Protect includes directory
echo "Deny from all" > includes/.htaccess
```

### Step 2: Update Default Passwords

1. **Change Admin Password:**
   - Log in to admin panel: `https://yourdomain.com/admin/`
   - Email: `admin@loanflow.com`
   - Password: `admin123`
   - **IMMEDIATELY** change this password!

2. **Update Database Passwords:**
   - Use strong, unique passwords for database users
   - Consider using environment variables for sensitive data

### Step 3: SSL Configuration

Ensure your site uses HTTPS:

```apache
# Force HTTPS redirect in .htaccess
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## Cron Jobs Setup

### Required Cron Jobs

Set up these automated tasks in your hosting control panel:

#### 1. Daily Tasks (Required)
```bash
# Run daily at 2:00 AM
0 2 * * * /usr/bin/php /path/to/your/site/cron/daily-tasks.php
```

**What it does:**
- Processes email queue
- Sends pre-approval emails
- Checks overdue payments
- Updates call list priorities
- Cleans old data
- Generates statistics

#### 2. Email Processing (Optional - for high volume)
```bash
# Process emails every 15 minutes
*/15 * * * * /usr/bin/php /path/to/your/site/cron/process-emails.php
```

### Setting Up Cron Jobs

#### Via cPanel:
1. Log into cPanel
2. Find "Cron Jobs" in Advanced section
3. Add new cron job with the command above
4. Set appropriate timing

#### Via Command Line:
```bash
# Edit crontab
crontab -e

# Add the cron job lines
0 2 * * * /usr/bin/php /path/to/your/site/cron/daily-tasks.php
*/15 * * * * /usr/bin/php /path/to/your/site/cron/process-emails.php
```

---

## Testing

### Step 1: Basic Functionality Test

1. **Homepage Test:**
   - Visit `https://yourdomain.com`
   - Verify loan application form loads
   - Test loan calculator widget

2. **Database Connection Test:**
   - Application should load without errors
   - Check error logs if issues occur

3. **Admin Access Test:**
   - Visit `https://yourdomain.com/admin/`
   - Login with default credentials
   - **Change password immediately!**

### Step 2: Application Flow Test

1. **Submit Test Application:**
   - Fill out the loan application form
   - Use test data (don't use real personal information)
   - Verify reference number is generated

2. **Client Area Test:**
   - Login to client area
   - Check dashboard displays correctly
   - Test document upload functionality

3. **Admin Review Test:**
   - View application in admin panel
   - Test status updates
   - Verify email notifications work

### Step 3: Email System Test

1. **Configure Email Settings:**
   - Update `config/email.php` with real SMTP settings
   - Test email sending from admin panel

2. **Automated Email Test:**
   - Submit test application
   - Wait for confirmation email
   - Check pre-approval email after 6 hours

### Step 4: Payment System Test

1. **Configure Payment Methods:**
   - Set up wire transfer details in admin
   - Configure crypto addresses if using
   - Test payment instruction generation

2. **Payment Flow Test:**
   - Create test payment
   - Generate payment instructions
   - Test payment status updates

---

## Troubleshooting

### Common Issues

#### 1. Database Connection Errors

**Error:** `Database connection failed`

**Solutions:**
- Verify database credentials in `config/database.php`
- Check if database exists
- Ensure database user has proper privileges
- Contact hosting provider for database server details

#### 2. File Upload Issues

**Error:** `Failed to save uploaded file`

**Solutions:**
```bash
# Check upload directory permissions
chmod 755 uploads/
chown www-data:www-data uploads/

# Check PHP configuration
php -m | grep fileinfo  # Should show fileinfo extension
```

#### 3. Email Not Sending

**Error:** `Email sending failed`

**Solutions:**
- Verify SMTP settings in `config/email.php`
- Check hosting provider's email policies
- Test with different SMTP provider
- Check server error logs

#### 4. Cron Jobs Not Running

**Error:** Automated tasks not executing

**Solutions:**
- Verify cron job syntax
- Check file permissions on cron scripts
- Ensure correct PHP path: `which php`
- Check cron logs: `/var/log/cron`

#### 5. Permission Denied Errors

**Error:** `Permission denied` when accessing files

**Solutions:**
```bash
# Fix file permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 755 uploads/ cron/
```

### Debug Mode

Enable debug mode for development:

```php
// In includes/functions.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Disable in production!
```

### Log Files

Check these log files for errors:
- Server error logs (usually in `/var/log/apache2/` or `/var/log/nginx/`)
- PHP error logs
- Application logs in `uploads/logs/` (if enabled)

---

## Maintenance

### Daily Tasks

- [ ] Check email queue status
- [ ] Review new applications
- [ ] Process pending documents
- [ ] Monitor payment status

### Weekly Tasks

- [ ] Review system performance
- [ ] Check disk space usage
- [ ] Update call lists
- [ ] Review audit logs

### Monthly Tasks

- [ ] Update system software
- [ ] Review security settings
- [ ] Backup database
- [ ] Clean old files

### Backup Strategy

#### Database Backup
```bash
# Daily database backup
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql

# Automated backup script
0 1 * * * /path/to/backup-script.sh
```

#### File Backup
```bash
# Backup uploaded files
tar -czf uploads_backup_$(date +%Y%m%d).tar.gz uploads/

# Full site backup
tar -czf site_backup_$(date +%Y%m%d).tar.gz --exclude='uploads' .
```

### Updates

1. **Before Updating:**
   - Backup database and files
   - Test updates in staging environment
   - Review changelog for breaking changes

2. **Update Process:**
   - Download new version
   - Compare configuration files
   - Update database schema if needed
   - Test functionality

3. **After Updating:**
   - Clear any caches
   - Test critical functionality
   - Monitor error logs

---

## Production Checklist

### Before Going Live

- [ ] Change all default passwords
- [ ] Configure real SMTP settings
- [ ] Set up SSL certificate
- [ ] Configure proper file permissions
- [ ] Set up automated backups
- [ ] Configure cron jobs
- [ ] Test all functionality
- [ ] Review security settings
- [ ] Set up monitoring
- [ ] Prepare support documentation

### Security Best Practices

- [ ] Use strong passwords
- [ ] Enable HTTPS everywhere
- [ ] Regular security updates
- [ ] Monitor access logs
- [ ] Implement rate limiting
- [ ] Regular security audits
- [ ] Backup encryption
- [ ] Access control reviews

### Performance Optimization

- [ ] Enable PHP OPcache
- [ ] Configure database optimization
- [ ] Implement file caching
- [ ] Optimize images
- [ ] Monitor resource usage
- [ ] Set up CDN if needed

---

## Support and Documentation

### Getting Help

1. **Check Documentation:**
   - Review this deployment guide
   - Check system requirements
   - Review troubleshooting section

2. **Log Analysis:**
   - Check server error logs
   - Review application logs
   - Monitor database performance

3. **Community Support:**
   - Check project repository
   - Review issue tracker
   - Consult hosting provider documentation

### Additional Resources

- **System Requirements:** See requirements section above
- **Configuration Examples:** Check `config/` directory
- **Database Schema:** Review `database/schema.sql`
- **API Documentation:** Available in admin panel

---

## Conclusion

This deployment guide provides comprehensive instructions for setting up the LoanFlow Personal Loan Management System. The system is designed to be:

- **Easy to Deploy:** Works on any standard PHP/MySQL hosting
- **Secure by Default:** Implements security best practices
- **Scalable:** Handles growing user bases efficiently
- **Maintainable:** Clean code structure and comprehensive logging
- **Feature-Complete:** All essential loan management features included

For additional support or custom modifications, consult the system documentation or contact your development team.

---

**Last Updated:** January 2025  
**Version:** 2.0  
**Next Review:** February 2025
