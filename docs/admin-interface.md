# LoanFlow Admin Interface Documentation

## Overview
The LoanFlow admin interface provides a comprehensive set of tools for managing and monitoring the application. This documentation covers the main components and features available to administrators.

## Components

### 1. Process Manager
The Process Manager allows administrators to monitor and control application processes.

#### Features
- View running processes and their status
- Start/stop/restart processes
- Monitor CPU and memory usage
- View process uptime and restart history
- Add new processes
- Configure process settings

#### Usage
1. **Viewing Processes**
   - All running processes are displayed in a table
   - Status is indicated by color-coded chips
   - Real-time metrics are updated every 5 seconds

2. **Process Control**
   - Start: Click the play button to start a stopped process
   - Stop: Click the stop button to halt a running process
   - Restart: Click the refresh button to restart a process
   - Delete: Click the delete button to remove a process

3. **Adding New Processes**
   - Click "Add Process" button
   - Fill in the required fields:
     - Process Name
     - Script Path
     - Number of Instances
     - Memory Limit

### 2. Log Viewer
The Log Viewer provides access to application logs with advanced filtering and search capabilities.

#### Features
- View different types of logs (application, error, security, audit)
- Search and filter logs
- Download log files
- Clear log files
- Real-time log updates

#### Usage
1. **Selecting Log Type**
   - Choose from available log types in the dropdown
   - Each log type shows different information:
     - Application Log: General application events
     - Error Log: Error and exception details
     - Security Log: Security-related events
     - Audit Log: User actions and system changes

2. **Searching Logs**
   - Use the search bar to find specific entries
   - Search across all log fields
   - Results update in real-time

3. **Managing Logs**
   - Download: Click the download button to save logs
   - Clear: Click the clear button to empty the log file
   - Refresh: Click the refresh button to update the view

### 3. System Settings
The System Settings component allows configuration of application-wide settings.

#### Features
- Configure application parameters
- Set security settings
- Manage email templates
- Configure notification settings
- Set up backup schedules

#### Usage
1. **General Settings**
   - Application name and description
   - Contact information
   - System timezone
   - Default language

2. **Security Settings**
   - Password policies
   - Session timeout
   - IP restrictions
   - Two-factor authentication

3. **Email Settings**
   - SMTP configuration
   - Email templates
   - Notification preferences

## Comprehensive Settings Management

### Overview
The Comprehensive Settings Management system provides a centralized interface for managing all system configuration settings. It offers advanced features including validation, testing, backup/restore, and version control.

### Accessing Comprehensive Settings
1. Navigate to the admin dashboard
2. Click on "Comprehensive Settings" in the left navigation menu
3. The settings are organized by categories with tabbed navigation

### Setting Categories

#### System Settings
- **APP_NAME**: Application name displayed throughout the system
- **APP_VERSION**: Current application version
- **NODE_ENV**: Environment (development, production, testing)
- **PORT**: Application port number
- **API_PORT**: API server port
- **TIMEZONE**: System timezone
- **LOCALE**: System locale/language
- **CURRENCY**: Default currency

#### Database Settings
- **DB_HOST**: Database host address
- **DB_PORT**: Database port number
- **DB_NAME**: Database name
- **DB_USER**: Database username
- **DB_PASSWORD**: Database password (encrypted)

#### Email Settings
- **SMTP_HOST**: SMTP server host
- **SMTP_PORT**: SMTP server port
- **SMTP_USER**: SMTP username
- **SMTP_PASSWORD**: SMTP password (encrypted)
- **SMTP_USE_TLS**: Enable TLS encryption
- **MAIL_FROM**: Default sender email
- **MAIL_FROM_NAME**: Default sender name

#### Security Settings
- **JWT_SECRET**: JWT secret key (encrypted)
- **JWT_EXPIRY**: JWT token expiry time
- **PASSWORD_MIN_LENGTH**: Minimum password length
- **SESSION_TIMEOUT**: Session timeout duration
- **MAX_LOGIN_ATTEMPTS**: Maximum login attempts

#### Backup Settings
- **BACKUP_AUTO_ENABLED**: Enable automatic backups
- **BACKUP_FREQUENCY**: Backup frequency (hourly, daily, weekly, monthly)
- **BACKUP_TIME**: Scheduled backup time
- **BACKUP_RETENTION_DAYS**: Backup retention period
- **BACKUP_PATH**: Backup storage location

#### Logging Settings
- **LOG_LEVEL**: Logging level (DEBUG, INFO, WARNING, ERROR, CRITICAL)
- **LOG_FILE**: Log file path
- **MAX_LOG_SIZE**: Maximum log file size
- **LOG_BACKUP_COUNT**: Number of log backup files

#### Performance Settings
- **WORKER_THREADS**: Number of worker threads
- **MAX_MEMORY**: Maximum memory usage
- **TIMEOUT**: Request timeout

#### Cache Settings
- **CACHE_TYPE**: Cache type (redis, memory, file)
- **REDIS_URL**: Redis connection URL
- **CACHE_TTL**: Cache time-to-live

#### Storage Settings
- **STORAGE_TYPE**: Storage type (local, s3, azure)
- **AWS_ACCESS_KEY_ID**: AWS access key
- **AWS_SECRET_ACCESS_KEY**: AWS secret key (encrypted)
- **AWS_REGION**: AWS region
- **AWS_S3_BUCKET**: S3 bucket name

#### Monitoring Settings
- **MONITORING_ENABLED**: Enable system monitoring
- **SENTRY_DSN**: Sentry DSN for error tracking
- **NEW_RELIC_LICENSE_KEY**: New Relic license key (encrypted)

#### Analytics Settings
- **ANALYTICS_ENABLED**: Enable analytics
- **GOOGLE_ANALYTICS_ID**: Google Analytics ID
- **MIXPANEL_TOKEN**: Mixpanel token

### Features

#### Validation
- Click the validation icon (✓) next to any setting category
- The system validates all settings in the category
- Validation results show success/failure for each setting
- Invalid settings are highlighted with error messages

#### Testing
- Click the test icon (▶) next to any setting category
- The system tests the functionality of settings (e.g., database connection, email sending)
- Test results show passed/failed tests with detailed messages
- Failed tests provide troubleshooting guidance

#### Backup and Restore
- **Create Backup**: Click the backup icon to create a complete settings backup
- **Download Backup**: Download backup files for external storage
- **Restore Backup**: Restore settings from a previous backup
- **Delete Backup**: Remove old backup files to free space

#### Import/Export
- **Export Settings**: Export settings as JSON for backup or migration
- **Import Settings**: Import settings from JSON file
- **Bulk Operations**: Select multiple settings for bulk operations

#### Version Control
- **Change History**: View history of all setting changes
- **Rollback**: Rollback individual settings to previous versions
- **Audit Trail**: Track who changed what and when

### Best Practices

#### Security
1. **Encrypt Sensitive Data**: Always encrypt passwords, API keys, and secrets
2. **Regular Backups**: Create regular backups before making changes
3. **Test Changes**: Always test settings in a development environment first
4. **Document Changes**: Document significant configuration changes

#### Performance
1. **Optimize Cache Settings**: Configure appropriate cache TTL values
2. **Monitor Resource Usage**: Set appropriate memory and timeout limits
3. **Database Optimization**: Configure database connection pools appropriately

#### Maintenance
1. **Regular Validation**: Run validation tests regularly
2. **Clean Up Logs**: Configure log rotation and cleanup
3. **Update Monitoring**: Keep monitoring and analytics settings current

### Troubleshooting

#### Common Issues

**Settings Not Saving**
- Check user permissions
- Verify database connection
- Check for validation errors

**Validation Failures**
- Review error messages for specific issues
- Check data type requirements
- Verify format requirements (email, URL, etc.)

**Test Failures**
- Check network connectivity
- Verify credentials
- Review service-specific error messages

**Backup/Restore Issues**
- Ensure sufficient disk space
- Check file permissions
- Verify backup file integrity

#### Getting Help
1. Use the help icon (?) next to each setting for context-specific help
2. Check the validation and test results for error details
3. Review the audit logs for recent changes
4. Contact system administrator for persistent issues

### Advanced Features

#### Role-Based Access
- Different user roles have access to different setting categories
- Super admins have full access to all settings
- Regular admins have limited access to non-critical settings

#### Change Notifications
- Receive notifications when critical settings change
- Email alerts for security-related changes
- Dashboard notifications for system updates

#### API Access
- All settings can be managed via REST API
- API endpoints support CRUD operations
- Bulk operations available for efficiency

### Migration Guide

#### From Environment Variables
1. Export current environment variables
2. Use the import feature to load settings
3. Validate all imported settings
4. Test system functionality

#### From Configuration Files
1. Convert configuration files to JSON format
2. Import using the bulk import feature
3. Review and validate all settings
4. Update any file-based references

#### To New Environment
1. Export settings from current environment
2. Import to new environment
3. Update environment-specific settings (URLs, paths, etc.)
4. Test all functionality in new environment

## Security Considerations

### Access Control
- Admin interface is protected by role-based access control
- All actions are logged for audit purposes
- Sensitive operations require confirmation
- Session timeout after inactivity

### Best Practices
1. **Process Management**
   - Monitor process resource usage
   - Set appropriate memory limits
   - Use multiple instances for critical processes
   - Regular health checks

2. **Log Management**
   - Regular log rotation
   - Secure log storage
   - Regular log analysis
   - Backup important logs

3. **System Settings**
   - Regular security audits
   - Keep settings up to date
   - Document all changes
   - Test changes in staging

## Troubleshooting

### Common Issues
1. **Process Issues**
   - Process fails to start
   - High resource usage
   - Unexpected restarts
   - Configuration errors

2. **Log Issues**
   - Missing log entries
   - Log file size issues
   - Search not working
   - Download failures

3. **Settings Issues**
   - Changes not applying
   - Validation errors
   - Permission issues
   - Configuration conflicts

### Solutions
1. **Process Problems**
   - Check process logs
   - Verify resource limits
   - Review configuration
   - Check system resources

2. **Log Problems**
   - Verify log permissions
   - Check disk space
   - Review log rotation
   - Clear browser cache

3. **Settings Problems**
   - Check user permissions
   - Verify input values
   - Review validation rules
   - Check for conflicts

## API Reference

### Process Management
```javascript
// Get all processes
GET /api/admin/processes

// Start a process
POST /api/admin/processes/{id}/start

// Stop a process
POST /api/admin/processes/{id}/stop

// Restart a process
POST /api/admin/processes/{id}/restart

// Delete a process
DELETE /api/admin/processes/{id}
```

### Log Management
```javascript
// Get logs
GET /api/admin/logs

// Download logs
GET /api/admin/logs/download/{filename}

// Clear logs
DELETE /api/admin/logs/{filename}
```

### System Settings
```javascript
// Get settings
GET /api/admin/settings

// Update settings
PUT /api/admin/settings

// Reset settings
POST /api/admin/settings/reset
```

## Support
For additional support:
- Check the troubleshooting guide
- Review the FAQ section
- Contact system administrator
- Submit a support ticket 