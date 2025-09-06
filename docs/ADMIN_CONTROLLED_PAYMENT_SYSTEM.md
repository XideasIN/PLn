# Admin-Controlled Payment System Documentation

## Overview

The PersonalLoan project features a comprehensive **Admin-Controlled Payment System** where all payment processing features, workflows, and user experiences are managed through the Admin area. This ensures complete administrative control over payment methods, user workflows, and business processes.

## Table of Contents

1. [Admin Control Center](#admin-control-center)
2. [Payment Method Controls](#payment-method-controls)
3. [Workflow Management](#workflow-management)
4. [Security & Compliance](#security--compliance)
5. [User Experience](#user-experience)
6. [Implementation Details](#implementation-details)
7. [Admin Benefits](#admin-benefits)

---

## Admin Control Center

### Navigation
- **Location**: Admin Dashboard → Configuration → Payment Methods
- **File**: `admin/payment-methods.php`
- **Access**: Admin role required

### Interface Features
- **Tabbed Navigation**: Separate configuration for each payment method
- **Visual Indicators**: Clear enable/disable toggles and status indicators  
- **Real-time Validation**: Prevents invalid configurations
- **Help Text**: Comprehensive guidance for each setting
- **Statistics Dashboard**: Payment method usage analytics

---

## Payment Method Controls

### 🏦 Wire Transfer Configuration

#### Required Information
- **Bank Name**: Institution name
- **Account Name**: Account holder name
- **Account Number**: Bank account number
- **Routing Number**: Bank routing code (optional)
- **SWIFT Code**: International transfer code (optional)
- **Bank Address**: Physical bank address (optional)

#### Workflow Controls
| Setting | Description | Default |
|---------|-------------|---------|
| **Auto-email Instructions** | Send payment details via email | Enabled |
| **Require Payment Confirmation** | Users must submit proof of payment | Enabled |
| **Require Image Upload** | Force users to upload payment receipt | Enabled |
| **Maximum File Size** | Upload limit in MB (1-50) | 10MB |
| **Allowed File Types** | Permitted file extensions | jpg,jpeg,png,pdf |
| **Confirmation Timeout** | Hours to submit confirmation (1-168) | 72 hours |

#### Customization Options
- **Custom Instructions**: Admin-written payment guidance
- **Email Template**: Custom email content with variables
- **Country Restrictions**: Select allowed countries

### ₿ Cryptocurrency Configuration

#### Required Information
- **Wallet Address**: Receiving cryptocurrency address
- **Currency Type**: Bitcoin, Ethereum, USDT, Litecoin
- **Network**: Blockchain network (e.g., ERC-20, TRC-20)
- **QR Code URL**: Optional QR code image

#### Workflow Controls
| Setting | Description | Default |
|---------|-------------|---------|
| **Enable Auto-completion** | Automatic payment verification | Enabled |
| **Auto-email Instructions** | Send wallet details via email | Enabled |
| **Show QR Code** | Display QR for mobile wallets | Enabled |
| **Required Confirmations** | Blockchain confirmations needed (1-12) | 3 |
| **Payment Timeout** | Minutes before expiration (5-120) | 30 minutes |
| **Manual Verification** | Require admin approval | Disabled |

#### Advanced Features
- **Blockchain Integration**: Automatic transaction verification
- **Custom Instructions**: Admin-defined payment guidance
- **Email Templates**: Personalized communication

### 📧 e-Transfer Configuration (Canada Only)

#### Required Information
- **Email Address**: Recipient email for e-Transfer
- **Recipient Name**: Account holder name
- **Security Question**: Optional security question
- **Security Answer**: Answer to security question

#### Workflow Controls
| Setting | Description | Default |
|---------|-------------|---------|
| **Auto-email Instructions** | Send recipient details via email | Enabled |
| **Require Payment Confirmation** | Users must confirm transfer | Enabled |
| **Allow Manual Details** | Permit manual entry vs image upload | Enabled |
| **Require Image Upload** | Force screenshot upload | Disabled |
| **Auto-accept Known Users** | Skip verification for trusted users | Disabled |
| **Confirmation Timeout** | Hours for confirmation (1-168) | 24 hours |

#### Special Features
- **Canada-Only Restriction**: Automatically enforced
- **Flexible Confirmation**: Image upload OR manual details
- **User Trust System**: Auto-approval for established users

### 💳 Credit Card Configuration

#### Required Information
- **Stripe Keys**: Publishable and secret keys
- **PayPal Credentials**: Client ID and secret
- **Gateway Settings**: Payment processor configuration

#### Workflow Controls
- **Auto-email Instructions**: Payment confirmations
- **3D Secure**: Enhanced security requirements
- **Save Card Option**: Allow stored payment methods
- **Payment Timeout**: Transaction expiration

---

## Workflow Management

### Email Management

#### Template Variables
Available in all email templates:
- `{payment_id}` - Payment reference number
- `{amount}` - Payment amount formatted
- `{user_name}` - Customer full name
- `{payment_method}` - Selected payment method
- `{login_url}` - Link to customer portal

#### Method-Specific Variables
**Wire Transfer:**
- `{bank_name}`, `{account_name}`, `{account_number}`
- `{routing_number}`, `{swift_code}`, `{bank_address}`

**Cryptocurrency:**
- `{wallet_address}`, `{currency_type}`, `{network}`

**e-Transfer:**
- `{email_address}`, `{recipient_name}`
- `{security_question}`, `{security_answer}`

### File Upload Controls

#### Admin Configuration
- **File Size Limits**: 1MB to 50MB per upload
- **File Type Restrictions**: Configurable extensions
- **Upload Requirements**: Optional or mandatory
- **Security Validation**: MIME type checking

#### Supported File Types
- **Images**: JPG, JPEG, PNG, GIF
- **Documents**: PDF
- **Custom**: Admin-defined extensions

### Confirmation Management

#### Confirmation Types
1. **Image Upload**: Receipt or screenshot
2. **Manual Entry**: Transaction details form
3. **Automatic**: Blockchain verification (crypto)
4. **Hybrid**: Image OR manual (admin choice)

#### Timeout Controls
- **Flexible Deadlines**: 1 hour to 7 days
- **Method-Specific**: Different timeouts per payment type
- **Automatic Expiration**: System handles overdue confirmations

---

## Security & Compliance

### Access Controls

#### Method Availability
- **Configuration Required**: Methods hidden until admin setup complete
- **Enable/Disable Toggle**: Instant activation control
- **Validation Checks**: Required fields enforcement

#### User Restrictions
- **Country-Based**: Geographic payment method filtering
- **Scheme Assignment**: Admin assigns subscription vs percentage
- **Manual Overrides**: Admin can bypass automatic processes

### Data Protection

#### File Security
- **Secure Upload**: Random filename generation
- **Type Validation**: MIME type and extension checking
- **Size Limits**: Admin-controlled upload restrictions
- **Access Control**: Protected storage directory

#### Payment Security
- **2FA Integration**: Double authentication for subscriptions
- **Audit Logging**: Complete activity tracking
- **CSRF Protection**: Form security tokens
- **Data Encryption**: Sensitive information protection

### Compliance Features

#### Documentation
- **Payment Proof**: Required upload confirmations
- **Audit Trail**: Complete transaction logging
- **Admin Oversight**: Manual verification options
- **Retention Policies**: Configurable data retention

#### Geographic Compliance
- **Country Restrictions**: Method availability by region
- **Regulatory Compliance**: e-Transfer Canada-only enforcement
- **Currency Controls**: Regional payment processing

---

## User Experience

### Dynamic Interface

#### Method Visibility
- **Smart Filtering**: Only shows admin-enabled methods
- **Country-Based**: Automatic geographic filtering
- **Configuration-Dependent**: Methods appear when properly set up

#### Instructions Display
- **Admin-Written**: Custom guidance and help text
- **Method-Specific**: Tailored instructions per payment type
- **Multi-Language**: Localization support

### Form Behavior

#### Responsive Design
- **Mobile-Optimized**: Touch-friendly interface
- **Progressive Enhancement**: Features activate based on admin config
- **Cross-Platform**: Consistent experience across devices

#### Validation
- **Real-Time**: Immediate feedback on form inputs
- **Admin-Controlled**: Validation rules follow admin settings
- **Clear Messaging**: Helpful error messages and guidance

### Payment Workflows

#### Wire Transfer Flow
1. **Selection**: User chooses wire transfer
2. **Instructions**: Display admin-configured bank details
3. **Email**: Automatic instruction delivery (if enabled)
4. **Confirmation**: User uploads receipt (if required)
5. **Processing**: Admin verification and approval

#### Cryptocurrency Flow
1. **Selection**: User chooses cryptocurrency
2. **Wallet Display**: Show admin-configured wallet address
3. **QR Code**: Display for mobile scanning (if enabled)
4. **Auto-Completion**: Blockchain verification (if enabled)
5. **Manual Override**: Admin verification (if required)

#### e-Transfer Flow
1. **Selection**: User chooses e-Transfer (Canada only)
2. **Instructions**: Display admin email and recipient details
3. **Transfer**: User sends e-Transfer independently
4. **Confirmation**: Upload receipt OR enter details manually
5. **Processing**: Admin or automatic verification

---

## Implementation Details

### Core Files

#### Admin Interface
- **`admin/payment-methods.php`**: Main configuration interface
- **`admin/index.php`**: Navigation integration
- **Enhanced with**: Tabbed interface, workflow controls, validation

#### Backend Logic
- **`includes/enhanced_payment.php`**: Core payment processing
- **Enhanced with**: Admin setting respect, workflow management
- **Features**: Configuration validation, dynamic processing

#### Database Schema
- **`database/schema.sql`**: Enhanced payment tables
- **New Tables**: 
  - `payment_method_config`: Admin configurations
  - `user_payment_schemes`: Individual user assignments
- **Enhanced Tables**: 
  - `payments`: Confirmation tracking, 2FA fields

#### Language Support
- **`languages/en.php`**: 60+ new admin control strings
- **Categories**: Workflow controls, help text, validation messages

### Key Classes & Functions

#### EnhancedPaymentManager
```php
// Check admin configuration
EnhancedPaymentManager::isMethodConfigured($method, $config)

// Respect admin workflow settings
EnhancedPaymentManager::sendPaymentInstructions($user_id, $payment_id, $method)

// Handle admin-controlled file uploads
EnhancedPaymentManager::handleConfirmationFileUpload($file, $payment_id, $config)

// Process admin-controlled confirmations
EnhancedPaymentManager::submitPaymentConfirmation($payment_id, $user_id, $data, $file)
```

#### Admin Configuration Processing
```php
// Save workflow settings
$config_data = [
    'auto_email_instructions' => isset($_POST['auto_email_instructions']),
    'require_confirmation' => isset($_POST['require_confirmation']),
    'max_file_size' => intval($_POST['max_file_size'] ?? 10),
    // ... other admin-controlled settings
];
```

### Database Structure

#### payment_method_config Table
```sql
CREATE TABLE `payment_method_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `method_name` enum('wire_transfer','crypto','e_transfer','credit_card') NOT NULL,
  `is_enabled` boolean NOT NULL DEFAULT FALSE,
  `allowed_countries` json DEFAULT NULL,
  `config_data` json DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `email_template` text DEFAULT NULL,
  -- Additional fields for workflow control
);
```

#### Enhanced payments Table
```sql
ALTER TABLE `payments` ADD COLUMN `confirmation_image` varchar(255) DEFAULT NULL;
ALTER TABLE `payments` ADD COLUMN `confirmation_details` text DEFAULT NULL;
ALTER TABLE `payments` ADD COLUMN `requires_2fa` boolean NOT NULL DEFAULT FALSE;
ALTER TABLE `payments` ADD COLUMN `2fa_verified` boolean NOT NULL DEFAULT FALSE;
```

---

## Admin Benefits

### Complete Control

#### Method Management
- **Instant Toggle**: Enable/disable any payment method immediately
- **Configuration Required**: Methods only appear when properly set up
- **Validation Enforcement**: System ensures complete admin setup

#### Workflow Customization
- **Email Control**: Custom templates and auto-send toggles
- **Confirmation Rules**: Flexible requirements per method
- **File Restrictions**: Admin-defined upload limits and types
- **Timeout Management**: Configurable deadlines and expiration

#### User Experience
- **Geographic Control**: Country-specific method availability
- **Instruction Customization**: Admin-written guidance and help
- **Security Settings**: 2FA requirements and manual verification
- **Compliance Features**: Documentation and audit requirements

### Business Flexibility

#### Regional Customization
- **Country Restrictions**: Different methods for different regions
- **Compliance Enforcement**: Automatic regulatory compliance
- **Currency Controls**: Regional payment processing rules

#### Risk Management
- **Manual Verification**: Override automatic processes when needed
- **User Assignment**: Individual subscription scheme management
- **Audit Controls**: Complete transaction logging and oversight
- **Security Enforcement**: 2FA and confirmation requirements

#### Customer Support
- **Clear Instructions**: Reduce support tickets with custom guidance
- **Consistent Experience**: Standardized workflows across all users
- **Error Prevention**: Validation and helpful messaging
- **Self-Service**: Users can complete processes independently

### Operational Efficiency

#### Automated Processing
- **Smart Defaults**: Sensible fallback settings
- **Progressive Enhancement**: Features activate as configured
- **Error Prevention**: Invalid configuration protection
- **Bulk Management**: Configure multiple settings simultaneously

#### Monitoring & Analytics
- **Usage Statistics**: Payment method popularity and success rates
- **Configuration Tracking**: See which settings are most effective
- **User Behavior**: Monitor confirmation rates and completion times
- **Performance Metrics**: Track processing efficiency and error rates

---

## Conclusion

The Admin-Controlled Payment System provides complete administrative oversight and control over all payment processing aspects. This ensures:

✅ **Flexibility**: Adapt to business needs and regional requirements  
✅ **Security**: Comprehensive controls and validation  
✅ **Compliance**: Automatic enforcement of regulations  
✅ **User Experience**: Consistent, guided workflows  
✅ **Efficiency**: Streamlined processing and reduced support burden  

All payment features are now **completely controlled by the Admin through the Admin area**, ensuring that users only see and can use payment methods that have been properly configured and enabled by administrators.

---

*Last Updated: December 2024*  
*Version: 1.0*  
*System: PersonalLoan Project*
