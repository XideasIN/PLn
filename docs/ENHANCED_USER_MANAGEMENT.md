# Enhanced User Management System

## Overview

The Enhanced User Management System provides comprehensive control over user accounts, passwords, and security settings from the admin control panel. This system allows administrators to manage all aspects of user authentication and authorization with full audit logging and security controls.

## Features

### 🔐 User Account Management
- **Create Users**: Add new users with detailed profile information
- **Edit Users**: Update user information, roles, and status
- **Delete Users**: Remove users with safety checks
- **Bulk Operations**: Perform actions on multiple users simultaneously
- **User Export**: Export user data for reporting and analysis

### 🔑 Password Management
- **Password Reset**: Admin-initiated password changes
- **Password History**: Track all password changes with timestamps
- **Force Password Change**: Require users to change passwords on next login
- **Password Validation**: Enforce strong password policies
- **Password Expiry**: Monitor and enforce password expiration

### 🛡️ Security Controls
- **Account Locking**: Lock/unlock user accounts
- **Two-Factor Authentication**: Enable/disable 2FA for users
- **Failed Login Tracking**: Monitor and manage failed login attempts
- **Session Management**: Track user login sessions
- **Security Settings**: Configure user-specific security policies

### 🔧 System Password Management
- **Database Passwords**: Manage MySQL database credentials
- **Redis Passwords**: Configure Redis authentication
- **Grafana Passwords**: Update monitoring dashboard credentials
- **Email Passwords**: Manage SMTP server credentials

### 📊 Audit & Monitoring
- **Audit Logging**: Complete audit trail of all user management actions
- **Password History**: Track all password changes with admin attribution
- **Security Events**: Monitor security-related activities
- **Change Notifications**: Email notifications for critical changes

## Admin Panel Interface

### Main User Management Dashboard

The enhanced user management interface provides:

1. **System Passwords Section**
   - Expandable accordion with all system password configurations
   - Real-time editing of database, Redis, Grafana, and email passwords
   - Secure password fields with visibility toggles

2. **Users Table**
   - Comprehensive user listing with status indicators
   - Role-based color coding (Admin, Manager, User, Reviewer)
   - Account status chips (Active, Locked, Expired, Inactive)
   - Last login timestamps
   - Action buttons for each user

3. **User Actions**
   - **Edit User**: Modify user information and roles
   - **Change Password**: Reset user passwords with options
   - **Security Settings**: Configure 2FA and account locks
   - **Password History**: View password change history
   - **Unlock Account**: Unlock locked accounts
   - **Delete User**: Remove users with safety checks

### Password Management Dialog

When changing user passwords, admins can:

- Set new passwords with strength validation
- Toggle password visibility
- Force password change on next login
- Send email notifications
- View password requirements

### Security Settings Dialog

Security configuration options include:

- Enable/disable two-factor authentication
- Lock/unlock user accounts
- View failed login attempts
- Monitor last login times
- Track password change dates

## API Endpoints

### User Management

```
GET    /api/admin/user-management/users              # Get all users
POST   /api/admin/user-management/users              # Create new user
GET    /api/admin/user-management/users/{id}         # Get specific user
PUT    /api/admin/user-management/users/{id}         # Update user
DELETE /api/admin/user-management/users/{id}         # Delete user
```

### Password Management

```
POST   /api/admin/user-management/users/{id}/reset-password    # Reset password
GET    /api/admin/user-management/users/{id}/password-history  # Get password history
```

### Security Management

```
PUT    /api/admin/user-management/users/{id}/security          # Update security settings
POST   /api/admin/user-management/users/{id}/unlock            # Unlock account
```

### System Passwords

```
GET    /api/admin/user-management/system-passwords             # Get system passwords
PUT    /api/admin/user-management/system-passwords/{service}   # Update system password
```

### Bulk Operations

```
POST   /api/admin/user-management/users/bulk-actions           # Bulk user actions
GET    /api/admin/user-management/users/export                 # Export users
```

### Audit & Monitoring

```
GET    /api/admin/user-management/users/{id}/audit-logs        # Get user audit logs
```

## Database Schema

### Enhanced User Model

```python
class User(db.Model):
    id = Column(Integer, primary_key=True)
    email = Column(String(120), unique=True, nullable=False)
    password_hash = Column(String(255), nullable=False)
    name = Column(String(100), nullable=False)
    first_name = Column(String(50), nullable=True)
    last_name = Column(String(50), nullable=True)
    role = Column(String(20), default='user')
    is_active = Column(Boolean, default=True)
    email_verified = Column(Boolean, default=False)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    last_login = Column(DateTime, nullable=True)
    failed_login_attempts = Column(Integer, default=0)
    locked_until = Column(DateTime, nullable=True)
    account_locked_until = Column(DateTime, nullable=True)
    two_factor_enabled = Column(Boolean, default=False)
    two_factor_secret = Column(String(255), nullable=True)
    backup_codes = Column(Text, nullable=True)
    password_changed_at = Column(DateTime, nullable=True)
    force_password_change = Column(Boolean, default=False)
```

### Password History Model

```python
class PasswordHistory(db.Model):
    id = Column(Integer, primary_key=True)
    user_id = Column(Integer, ForeignKey('users.id'), nullable=False)
    password_hash = Column(String(255), nullable=False)
    created_at = Column(DateTime, nullable=False, default=datetime.utcnow)
    created_by = Column(Integer, ForeignKey('users.id'), nullable=True)
```

## Security Features

### Password Policies

The system enforces strong password policies:

- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character
- No common passwords
- Password history check (prevents reuse)

### Access Control

- **Admin-only access**: All user management features require admin role
- **Audit logging**: All actions are logged with admin attribution
- **Safety checks**: Prevents deletion of last admin user
- **Input validation**: Comprehensive validation of all inputs
- **Rate limiting**: Prevents abuse of management endpoints

### Data Protection

- **Password hashing**: All passwords are securely hashed
- **Encrypted storage**: Sensitive data is encrypted at rest
- **Secure transmission**: All API communications use HTTPS
- **Session management**: Secure session handling with JWT tokens

## Usage Examples

### Creating a New User

```javascript
// Frontend API call
const newUser = {
  email: 'newuser@company.com',
  name: 'John Doe',
  first_name: 'John',
  last_name: 'Doe',
  role: 'manager',
  password: 'SecurePass123!',
  is_active: true
};

const response = await axios.post('/api/admin/user-management/users', newUser);
```

### Resetting User Password

```javascript
// Frontend API call
const passwordData = {
  new_password: 'NewSecurePass123!',
  force_change: true,
  send_email: true
};

const response = await axios.post(
  `/api/admin/user-management/users/${userId}/reset-password`,
  passwordData
);
```

### Updating Security Settings

```javascript
// Frontend API call
const securityData = {
  two_factor_enabled: true,
  account_locked: false
};

const response = await axios.put(
  `/api/admin/user-management/users/${userId}/security`,
  securityData
);
```

### Bulk User Operations

```javascript
// Frontend API call
const bulkData = {
  action: 'activate',
  user_ids: [1, 2, 3, 4, 5]
};

const response = await axios.post('/api/admin/user-management/users/bulk-actions', bulkData);
```

## Deployment Considerations

### Database Migration

Run the migration to add new fields:

```bash
flask db upgrade
```

### Environment Variables

Ensure these environment variables are set:

```bash
# Database
MYSQL_ROOT_PASSWORD=secure_root_password
MYSQL_USER=loanflow_user
MYSQL_PASSWORD=secure_user_password

# Redis
REDIS_PASSWORD=secure_redis_password

# Email
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=secure_email_password

# JWT
JWT_SECRET_KEY=your-secure-jwt-secret
```

### Security Best Practices

1. **Change Default Passwords**: Immediately change all default passwords after deployment
2. **Regular Password Rotation**: Implement regular password change policies
3. **Monitor Audit Logs**: Regularly review audit logs for suspicious activity
4. **Access Control**: Limit admin access to trusted personnel only
5. **Backup Security**: Ensure backup files are encrypted and secure

## Troubleshooting

### Common Issues

1. **Password Reset Fails**
   - Check password validation rules
   - Verify user account is active
   - Check audit logs for errors

2. **User Cannot Login**
   - Check if account is locked
   - Verify password hasn't expired
   - Check failed login attempts

3. **System Password Update Fails**
   - Verify admin permissions
   - Check environment variable access
   - Review audit logs for details

4. **Bulk Operations Fail**
   - Check if trying to delete last admin
   - Verify user IDs exist
   - Check database constraints

### Monitoring

Monitor these key metrics:

- Failed login attempts
- Password reset frequency
- Account lockouts
- Admin action frequency
- System password changes

## Support

For issues with the Enhanced User Management System:

1. Check the audit logs for detailed error information
2. Review the troubleshooting section above
3. Verify all environment variables are correctly set
4. Ensure database migrations have been applied
5. Check user permissions and role assignments

## Future Enhancements

Planned improvements include:

- **Multi-factor authentication management**
- **Advanced role-based permissions**
- **User activity analytics**
- **Automated security scanning**
- **Integration with external identity providers**
- **Advanced password policies**
- **User session management**
- **Real-time security alerts** 