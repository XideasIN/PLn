# Payment Gateway Disabling Method Documentation

## Overview

This document outlines the implementation method used to ensure that Stripe and PayPal payment gateways are automatically disabled when administrators have not provided the required credentials. This prevents customers from encountering broken payment options and ensures a seamless user experience.

## Table of Contents

1. [Problem Statement](#problem-statement)
2. [Solution Architecture](#solution-architecture)
3. [Implementation Details](#implementation-details)
4. [Code Structure](#code-structure)
5. [Validation Logic](#validation-logic)
6. [Admin Interface Integration](#admin-interface-integration)
7. [Customer Experience](#customer-experience)
8. [Testing and Verification](#testing-and-verification)
9. [Security Considerations](#security-considerations)
10. [Troubleshooting](#troubleshooting)

---

## Problem Statement

### Challenge
Payment systems often expose non-functional payment methods to customers when:
- Admin enables a gateway but doesn't provide API credentials
- API credentials are invalid or incomplete
- Gateway configuration is partially completed

### Requirements
- Payment gateways must be **both enabled AND configured** to be available
- Customers should never see broken payment options
- Admins should receive clear feedback about configuration status
- System should gracefully degrade when gateways are unavailable

---

## Solution Architecture

### Core Principle
**Double Validation**: Every payment method must pass both administrative enablement and technical configuration checks before becoming available to customers.

### Implementation Strategy
1. **Centralized Validation**: Single source of truth for gateway availability
2. **Dynamic Filtering**: Real-time filtering of payment options
3. **Admin Feedback**: Clear status reporting for administrators
4. **Graceful Degradation**: System continues functioning with available methods

### System Flow
```
Admin Settings → Validation Check → Gateway Availability → Customer Options
     ↓               ↓                    ↓                   ↓
Enable Toggle → API Credentials → Available Gateways → Payment Methods
```

---

## Implementation Details

### File Modifications

#### 1. Enhanced Payment Manager (`includes/enhanced_payment.php`)

**Primary Changes:**
- Modified `isMethodConfigured()` function to include administrative settings
- Added gateway status checking functions
- Implemented missing field detection

#### 2. Admin Interface (`admin/payment-gateway-status.php`)
- Real-time gateway status monitoring
- Configuration requirement display
- Administrative action guidance

#### 3. Customer Test Interface (`test-payment-options.php`)
- Demonstrates payment option filtering
- Shows customer impact of gateway configuration

---

## Code Structure

### Core Validation Function

```php
/**
 * Check if payment method is properly configured by admin
 */
private static function isMethodConfigured($method_name, $config) {
    // First check if the method is enabled by admin
    $payment_settings = getPaymentSettings();
    
    switch ($method_name) {
        case 'credit_card':
            // Check if Stripe is enabled and configured
            $stripe_enabled = ($payment_settings['stripe_enabled'] ?? '0') === '1' &&
                             !empty($payment_settings['stripe_publishable_key']) && 
                             !empty($payment_settings['stripe_secret_key']);
            
            // Check if PayPal is enabled and configured  
            $paypal_enabled = ($payment_settings['paypal_enabled'] ?? '0') === '1' &&
                             !empty($payment_settings['paypal_client_id']) && 
                             !empty($payment_settings['paypal_client_secret']);
            
            // At least one gateway must be enabled and configured
            return $stripe_enabled || $paypal_enabled;
            
        case 'stripe':
            // Direct Stripe method check
            return ($payment_settings['stripe_enabled'] ?? '0') === '1' &&
                   !empty($payment_settings['stripe_publishable_key']) && 
                   !empty($payment_settings['stripe_secret_key']);
                   
        case 'paypal':
            // Direct PayPal method check
            return ($payment_settings['paypal_enabled'] ?? '0') === '1' &&
                   !empty($payment_settings['paypal_client_id']) && 
                   !empty($payment_settings['paypal_client_secret']);
                   
        default:
            return false;
    }
}
```

### Gateway Status Functions

```php
/**
 * Get available payment gateways based on admin configuration
 */
public static function getAvailablePaymentGateways() {
    $payment_settings = getPaymentSettings();
    $available_gateways = [];
    
    // Check Stripe configuration
    if (($payment_settings['stripe_enabled'] ?? '0') === '1' && 
        !empty($payment_settings['stripe_publishable_key']) && 
        !empty($payment_settings['stripe_secret_key'])) {
        $available_gateways['stripe'] = [
            'name' => 'Stripe',
            'enabled' => true,
            'publishable_key' => $payment_settings['stripe_publishable_key'],
            'webhook_secret' => $payment_settings['stripe_webhook_secret'] ?? ''
        ];
    }
    
    // Check PayPal configuration
    if (($payment_settings['paypal_enabled'] ?? '0') === '1' && 
        !empty($payment_settings['paypal_client_id']) && 
        !empty($payment_settings['paypal_client_secret'])) {
        $available_gateways['paypal'] = [
            'name' => 'PayPal',
            'enabled' => true,
            'client_id' => $payment_settings['paypal_client_id'],
            'sandbox' => ($payment_settings['paypal_sandbox'] ?? '1') === '1'
        ];
    }
    
    return $available_gateways;
}
```

---

## Validation Logic

### Stripe Gateway Validation

**Required Conditions:**
1. `stripe_enabled` setting must be `'1'`
2. `stripe_publishable_key` must be non-empty
3. `stripe_secret_key` must be non-empty

**Validation Code:**
```php
$stripe_enabled = ($payment_settings['stripe_enabled'] ?? '0') === '1' &&
                 !empty($payment_settings['stripe_publishable_key']) && 
                 !empty($payment_settings['stripe_secret_key']);
```

### PayPal Gateway Validation

**Required Conditions:**
1. `paypal_enabled` setting must be `'1'`
2. `paypal_client_id` must be non-empty
3. `paypal_client_secret` must be non-empty

**Validation Code:**
```php
$paypal_enabled = ($payment_settings['paypal_enabled'] ?? '0') === '1' &&
                 !empty($payment_settings['paypal_client_id']) && 
                 !empty($payment_settings['paypal_client_secret']);
```

### Credit Card Method Availability

**Logic:**
- Credit card processing is available if **at least one** gateway is properly configured
- Both Stripe AND PayPal can be enabled simultaneously
- If neither gateway is configured, credit card option is hidden

**Implementation:**
```php
case 'credit_card':
    return $stripe_enabled || $paypal_enabled;
```

---

## Admin Interface Integration

### Gateway Status Dashboard

**File:** `admin/payment-gateway-status.php`

**Features:**
- **Real-time Status Display**: Shows current configuration state
- **Missing Field Detection**: Identifies specific missing credentials
- **Action Guidance**: Directs admins to configuration pages
- **Visual Indicators**: Color-coded status badges

**Status Categories:**
1. **Enabled**: Admin has turned on the gateway
2. **Configured**: Required API credentials are present
3. **Available**: Gateway is both enabled and configured

### Configuration Requirements Display

**Stripe Requirements:**
- Publishable Key (starts with `pk_`)
- Secret Key (starts with `sk_`)
- Webhook Secret (optional but recommended)

**PayPal Requirements:**
- Client ID
- Client Secret
- Sandbox Mode Setting

### Admin Alerts

**Warning Messages:**
- "Action Required: No payment gateways configured"
- "Stripe Disabled: Missing API credentials"
- "PayPal Disabled: Incomplete configuration"

---

## Customer Experience

### Payment Method Filtering

**Automatic Behavior:**
- Only configured gateways appear in payment options
- Credit card option is hidden if no gateways are available
- Customers never see broken payment methods
- Alternative payment methods (wire transfer, e-transfer) remain available

### User Interface Impact

**When Gateways Available:**
```html
<div class="payment-option">
    <input type="radio" name="payment_method" value="credit_card">
    <label>Credit Card (Visa, MasterCard, American Express)</label>
</div>
```

**When Gateways Unavailable:**
```html
<!-- Credit card option is automatically excluded -->
<div class="payment-option">
    <input type="radio" name="payment_method" value="wire_transfer">
    <label>Wire Transfer</label>
</div>
```

### Customer-Facing Testing

**Test Page:** `test-payment-options.php`

**Demonstrates:**
- Current gateway configuration status
- Available payment methods for customers
- Impact of admin configuration on customer options
- Real-time filtering based on admin settings

---

## Testing and Verification

### Test Scenarios

#### Scenario 1: No Gateways Configured
```php
// Admin Settings:
$payment_settings = [
    'stripe_enabled' => '0',
    'stripe_publishable_key' => '',
    'stripe_secret_key' => '',
    'paypal_enabled' => '0',
    'paypal_client_id' => '',
    'paypal_client_secret' => ''
];

// Expected Result: Credit card option hidden
```

#### Scenario 2: Stripe Enabled, Not Configured
```php
// Admin Settings:
$payment_settings = [
    'stripe_enabled' => '1',
    'stripe_publishable_key' => '',
    'stripe_secret_key' => '',
    'paypal_enabled' => '0',
    'paypal_client_id' => '',
    'paypal_client_secret' => ''
];

// Expected Result: Credit card option hidden (missing credentials)
```

#### Scenario 3: Stripe Fully Configured
```php
// Admin Settings:
$payment_settings = [
    'stripe_enabled' => '1',
    'stripe_publishable_key' => 'pk_test_xxxxx',
    'stripe_secret_key' => 'sk_test_xxxxx',
    'paypal_enabled' => '0',
    'paypal_client_id' => '',
    'paypal_client_secret' => ''
];

// Expected Result: Credit card option available via Stripe
```

### Verification Methods

1. **Admin Dashboard Check**: Visit `admin/payment-gateway-status.php`
2. **Customer Test Page**: Visit `test-payment-options.php`
3. **Payment Method API**: Call `getAvailablePaymentMethods()`
4. **Gateway Status API**: Call `getGatewayStatus()`

### Expected Behaviors

✅ **Correct Behaviors:**
- Gateways disabled by default
- Payment options filter dynamically
- Admin receives clear status feedback
- No broken payment methods exposed

❌ **Incorrect Behaviors:**
- Credit card option shown without credentials
- Customers encounter payment errors
- Admin unaware of configuration issues
- Partial configurations treated as complete

---

## Security Considerations

### Credential Protection

**Implementation:**
- API credentials stored in encrypted system settings
- Sensitive keys never exposed in client-side code
- Admin-only access to credential configuration

**Code Example:**
```php
// Safe: Only returns non-sensitive configuration
public static function getAvailablePaymentGateways() {
    // Returns publishable keys only, never secret keys
    $available_gateways['stripe'] = [
        'publishable_key' => $payment_settings['stripe_publishable_key'],
        // secret_key is NOT included in response
    ];
}
```

### Access Control

**Admin Restrictions:**
- Only admin users can modify payment settings
- CSRF protection on configuration forms
- Audit logging for setting changes

**Customer Protection:**
- Customers cannot access gateway configuration
- Failed payment attempts logged for security
- Rate limiting on payment processing

### Error Handling

**Graceful Degradation:**
- System continues functioning without gateways
- Clear error messages for customers
- Alternative payment methods remain available
- Admin notifications for configuration issues

---

## Troubleshooting

### Common Issues

#### Issue 1: Gateway Enabled But Not Working
**Symptoms:**
- Admin has enabled gateway
- Credit card option not appearing

**Diagnosis:**
```php
$status = EnhancedPaymentManager::getGatewayStatus();
print_r($status['stripe']['missing_fields']);
```

**Solution:**
- Check for missing API credentials
- Verify field names match requirements
- Test API key validity

#### Issue 2: Credentials Provided But Gateway Unavailable
**Symptoms:**
- API credentials entered
- Gateway still shows as unavailable

**Diagnosis:**
```php
$settings = getPaymentSettings();
var_dump([
    'enabled' => $settings['stripe_enabled'],
    'has_pk' => !empty($settings['stripe_publishable_key']),
    'has_sk' => !empty($settings['stripe_secret_key'])
]);
```

**Solution:**
- Verify enable toggle is checked
- Check for whitespace in credentials
- Confirm database settings are saved

#### Issue 3: Customer Sees No Payment Options
**Symptoms:**
- No payment methods available to customers
- All gateways disabled

**Diagnosis:**
```php
$methods = EnhancedPaymentManager::getAvailablePaymentMethods('USA');
print_r($methods);
```

**Solution:**
- Configure at least one payment method
- Check country restrictions
- Verify payment method database entries

### Debug Functions

#### Gateway Status Check
```php
function debugGatewayStatus() {
    $status = EnhancedPaymentManager::getGatewayStatus();
    $gateways = EnhancedPaymentManager::getAvailablePaymentGateways();
    
    echo "Gateway Status:\n";
    print_r($status);
    
    echo "\nAvailable Gateways:\n";
    print_r($gateways);
}
```

#### Payment Method Availability
```php
function debugPaymentMethods($country = 'USA') {
    $methods = EnhancedPaymentManager::getAvailablePaymentMethods($country);
    
    echo "Available Payment Methods for {$country}:\n";
    print_r($methods);
}
```

### Logging

**Key Log Points:**
- Gateway configuration changes
- Payment method availability checks
- Failed payment processing attempts
- Admin setting modifications

**Log Format:**
```
[2024-12-XX] Payment Gateway Status: Stripe disabled (missing secret key)
[2024-12-XX] Payment Method Check: credit_card unavailable (no gateways)
[2024-12-XX] Admin Action: Payment settings updated by user ID 1
```

---

## Conclusion

The payment gateway disabling method ensures robust protection against exposing non-functional payment options to customers. By implementing double validation (administrative enablement + technical configuration), the system provides:

1. **Seamless Customer Experience**: No broken payment methods
2. **Clear Admin Feedback**: Obvious configuration requirements
3. **Automatic Failsafe**: Gateways disabled by default
4. **Graceful Degradation**: Alternative payment methods remain available

This implementation follows security best practices and provides comprehensive monitoring and debugging capabilities for ongoing maintenance.

---

**Last Updated:** December 2024  
**Version:** 1.0  
**Author:** LoanFlow Development Team
