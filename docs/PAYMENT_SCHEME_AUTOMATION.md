# Payment Scheme Automation System

## Overview

The Payment Scheme Automation System allows administrators to automatically schedule and execute payment scheme switches with advanced features including impact analysis, preset configurations, and comprehensive logging.

## Features

### 1. Automated Scheduling
- **Future Scheduling**: Schedule payment scheme switches for specific dates and times
- **Automatic Execution**: System automatically executes scheduled switches at the specified time
- **Cancellation Support**: Cancel scheduled switches before execution
- **Email Notifications**: Automatic notifications for scheduled, executed, and cancelled switches

### 2. Preset Configurations
- **Standard Presets**: Pre-configured schemes for common scenarios
- **Custom Presets**: Create and manage custom preset configurations
- **Quick Application**: Apply presets with one-click execution

### 3. Impact Analysis
- **Pre-switch Validation**: Analyze the impact before executing switches
- **Warning System**: Identify potential issues and conflicts
- **Revenue Impact**: Assess financial implications of scheme changes
- **Active Payment Detection**: Identify existing payments that may be affected

### 4. Comprehensive Logging
- **Switch History**: Complete audit trail of all scheme changes
- **Automation Logs**: Detailed logs of automated operations
- **Admin Tracking**: Track which admin performed each action
- **Result Tracking**: Record success/failure of each operation

## System Architecture

### Backend Components

#### 1. PaymentSchemeAutomation Service
```python
# Core automation service
from backend.services.payment_scheme_automation import PaymentSchemeAutomation

# Schedule a switch
result = PaymentSchemeAutomation.schedule_scheme_switch(
    scheme_data, switch_date, admin_user_id, reason
)

# Execute scheduled switches
result = PaymentSchemeAutomation.execute_scheduled_switch()

# Cancel scheduled switch
result = PaymentSchemeAutomation.cancel_scheduled_switch(admin_user_id, reason)
```

#### 2. Database Models
- `PaymentSchemeSettings`: Core payment scheme configuration
- `PaymentSchemeAutomationLogs`: Automation operation logs
- `PaymentSchemePresets`: Preset configurations

#### 3. API Endpoints
```javascript
// Schedule a switch
POST /api/admin/payment-scheme-automation/schedule

// Cancel scheduled switch
POST /api/admin/payment-scheme-automation/cancel

// Get scheduled switch
GET /api/admin/payment-scheme-automation/scheduled

// Get presets
GET /api/admin/payment-scheme-automation/presets

// Validate impact
POST /api/admin/payment-scheme-automation/validate-impact

// Execute immediately
POST /api/admin/payment-scheme-automation/execute-now

// Get switch history
GET /api/admin/payment-scheme-automation/history
```

### Frontend Components

#### 1. PaymentSchemeAutomation Component
- **Dashboard View**: Overview of scheduled switches and quick actions
- **Schedule Dialog**: Configure and schedule new switches
- **Preset Dialog**: Select and apply preset configurations
- **Impact Analysis**: View detailed impact analysis
- **History Table**: Complete switch history with filtering

#### 2. Integration with Admin Dashboard
- Seamless integration with existing admin interface
- Priority placement for payment scheme management
- Consistent UI/UX with other admin features

## Usage Guide

### 1. Scheduling a Payment Scheme Switch

#### Step 1: Access Automation Panel
1. Navigate to Admin Dashboard
2. Click on "Payment Scheme Automation" section
3. Click "Schedule New Switch" button

#### Step 2: Configure Switch
1. Select scheme type (Subscription or Percentage)
2. Configure scheme parameters:
   - **Subscription**: Fee amount, max months, refund policy
   - **Percentage**: Percentage rate, refund policy
3. Set switch date and time
4. Add optional reason for the switch

#### Step 3: Review and Schedule
1. Review configuration
2. Click "Schedule Switch" to confirm
3. System will show confirmation and scheduled switch details

### 2. Using Preset Configurations

#### Available Presets
- **Standard Subscription**: $99.99/month for 6 months
- **Premium Subscription**: $149.99/month for 6 months
- **Standard Percentage**: 15% of loan amount
- **Premium Percentage**: 20% of loan amount

#### Applying Presets
1. Click "Use Preset Configuration"
2. Select desired preset from the list
3. Review preset details
4. Choose to execute immediately or schedule for later

### 3. Impact Analysis

#### Running Analysis
1. Click "Analyze Switch Impact"
2. Select or configure scheme data
3. Review analysis results:
   - Current vs new scheme comparison
   - Warnings and potential issues
   - Switch feasibility assessment

#### Understanding Results
- **Green Status**: Safe to switch
- **Yellow Status**: Warnings present, review carefully
- **Red Status**: Significant impact, manual review required

### 4. Managing Scheduled Switches

#### Viewing Scheduled Switch
- Current scheduled switch displayed in dashboard
- Shows switch date, new scheme type, and reason
- Status indicators for scheduled/executed/cancelled

#### Cancelling Scheduled Switch
1. Click "Cancel Scheduled Switch" button
2. Add cancellation reason (optional)
3. Confirm cancellation
4. System will update status and send notification

## Configuration

### 1. Cron Job Setup
```python
# Automatic execution every 5 minutes
schedule = '*/5 * * * *'

# Task function
def execute_scheduled_payment_switches():
    PaymentSchemeAutomation.execute_scheduled_switch()
```

### 2. Email Notifications
```python
# Notification types
- scheduled: When switch is scheduled
- executed: When switch is executed
- cancelled: When switch is cancelled

# Recipients
- Admin email (configurable)
- Additional stakeholders (optional)
```

### 3. System Settings
```python
# Key settings
SCHEDULED_PAYMENT_SCHEME_SWITCH: JSON data of scheduled switch
PAYMENT_SCHEME_PRESETS: JSON data of available presets
ADMIN_EMAIL: Email for notifications
```

## Security Features

### 1. Admin Authentication
- All automation endpoints require admin authentication
- JWT token validation for all operations
- Admin role verification

### 2. Audit Trail
- Complete logging of all automation actions
- Admin user tracking for each operation
- Timestamp and reason tracking

### 3. Validation
- Scheme data validation before execution
- Impact analysis before critical changes
- Error handling and rollback capabilities

## Monitoring and Maintenance

### 1. Health Checks
```python
# Check automation system health
def check_automation_health():
    # Verify cron job status
    # Check scheduled switch validity
    # Validate preset configurations
    # Monitor execution logs
```

### 2. Log Analysis
- Review automation logs regularly
- Monitor execution success rates
- Track admin usage patterns
- Identify potential issues

### 3. Performance Optimization
- Database indexing for automation tables
- Efficient query patterns
- Background task processing
- Resource usage monitoring

## Troubleshooting

### Common Issues

#### 1. Scheduled Switch Not Executing
- Check cron job status
- Verify switch date is in the past
- Review system logs for errors
- Ensure automation service is running

#### 2. Impact Analysis Errors
- Verify current scheme exists
- Check database connectivity
- Review scheme data format
- Validate required fields

#### 3. Preset Loading Issues
- Check preset data in system settings
- Verify JSON format validity
- Review preset creation process
- Check database connectivity

### Debug Commands
```python
# Check scheduled switch
scheduled = PaymentSchemeAutomation.get_scheduled_switch()

# Validate impact
impact = PaymentSchemeAutomation.validate_switch_impact(scheme_data)

# Get presets
presets = PaymentSchemeAutomation.get_switch_presets()

# Check automation logs
logs = PaymentSchemeAutomation.get_switch_history()
```

## Best Practices

### 1. Planning Switches
- Always run impact analysis before scheduling
- Schedule during low-traffic periods
- Provide clear reasons for switches
- Test with small changes first

### 2. Monitoring
- Set up alerts for automation failures
- Regular review of switch history
- Monitor system performance
- Track user feedback

### 3. Documentation
- Document all major scheme changes
- Maintain preset configurations
- Update automation procedures
- Train admin users

## API Reference

### Schedule Switch
```javascript
POST /api/admin/payment-scheme-automation/schedule
{
  "scheme_data": {
    "scheme_type": "subscription",
    "subscription_fee": 99.99,
    "max_subscription_months": 6,
    "refund_policy_subscription": 100.00
  },
  "switch_date": "2024-03-22T10:00:00Z",
  "reason": "Standard pricing adjustment"
}
```

### Cancel Switch
```javascript
POST /api/admin/payment-scheme-automation/cancel
{
  "reason": "Business decision change"
}
```

### Validate Impact
```javascript
POST /api/admin/payment-scheme-automation/validate-impact
{
  "scheme_data": {
    "scheme_type": "percentage",
    "percentage_fee": 15.00,
    "refund_policy_percentage": 80.00
  }
}
```

### Execute Now
```javascript
POST /api/admin/payment-scheme-automation/execute-now
{
  "scheme_data": {
    "scheme_type": "subscription",
    "subscription_fee": 149.99,
    "max_subscription_months": 12,
    "refund_policy_subscription": 100.00
  }
}
```

## Future Enhancements

### 1. Advanced Scheduling
- Recurring switches
- Conditional scheduling
- Multi-step transitions
- A/B testing support

### 2. Enhanced Analytics
- Revenue impact forecasting
- User behavior analysis
- Performance metrics
- Predictive modeling

### 3. Integration Features
- Third-party payment processor integration
- CRM system integration
- Marketing automation integration
- Reporting dashboard integration

### 4. User Experience
- Mobile admin interface
- Real-time notifications
- Interactive dashboards
- Advanced filtering options 