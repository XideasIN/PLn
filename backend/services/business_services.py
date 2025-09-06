#!/usr/bin/env python3
"""
Business Services Manager
LoanFlow Personal Loan Management System

This module manages core business operations including:
- Loan processing and management
- Customer relationship management
- Document generation and management
- Payment processing integration
- Communication and notifications
- Reporting and analytics
"""

import logging
import json
import requests
from typing import Dict, List, Optional, Any
from datetime import datetime, timedelta
import smtplib
from email.mime.text import MimeText
from email.mime.multipart import MimeMultipart
from email.mime.base import MimeBase
from email import encoders
import os
import uuid
from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import letter
import io

class BusinessServiceManager:
    def __init__(self):
        self.logger = logging.getLogger(__name__)
        self.db_manager = None
        self.redis_manager = None
        self.status = 'initializing'
        
        # Business configuration
        self.config = {
            'smtp_server': os.getenv('SMTP_SERVER', 'smtp.gmail.com'),
            'smtp_port': int(os.getenv('SMTP_PORT', '587')),
            'smtp_username': os.getenv('SMTP_USERNAME'),
            'smtp_password': os.getenv('SMTP_PASSWORD'),
            'company_name': 'LoanFlow',
            'company_email': 'noreply@loanflow.com',
            'support_email': 'support@loanflow.com',
            'max_loan_amount': 50000,
            'min_loan_amount': 1000,
            'default_interest_rate': 0.125,
            'document_storage_path': 'storage/documents/',
            'template_path': 'templates/'
        }
        
        # Business metrics
        self.metrics = {
            'loans_processed': 0,
            'documents_generated': 0,
            'emails_sent': 0,
            'payments_processed': 0,
            'customers_onboarded': 0
        }
    
    def initialize(self, db_manager, redis_manager):
        """Initialize business services"""
        try:
            self.logger.info("Initializing Business Services...")
            
            self.db_manager = db_manager
            self.redis_manager = redis_manager
            
            # Initialize email service
            self._initialize_email_service()
            
            # Initialize document templates
            self._initialize_document_templates()
            
            # Initialize payment processing
            self._initialize_payment_processing()
            
            # Initialize notification system
            self._initialize_notification_system()
            
            self.status = 'healthy'
            self.logger.info("Business Services initialized successfully")
            
        except Exception as e:
            self.logger.error(f"Business Services initialization failed: {str(e)}")
            self.status = 'error'
    
    def shutdown(self):
        """Shutdown business services"""
        self.logger.info("Shutting down Business Services...")
        self.status = 'stopped'
    
    def get_status(self) -> str:
        """Get business services status"""
        return self.status
    
    # Loan Processing Services
    def process_loan_application(self, application_data: Dict) -> Dict:
        """Process a new loan application"""
        try:
            self.logger.info(f"Processing loan application for {application_data.get('email')}")
            
            # Validate application data
            validation_result = self._validate_application_data(application_data)
            if not validation_result['valid']:
                return {
                    'success': False,
                    'error': 'Invalid application data',
                    'details': validation_result['errors']
                }
            
            # Generate application ID
            application_id = self._generate_application_id()
            
            # Store application in database
            application_record = {
                'id': application_id,
                'status': 'pending',
                'created_at': datetime.now(),
                'updated_at': datetime.now(),
                **application_data
            }
            
            self._store_application(application_record)
            
            # Send confirmation email
            self._send_application_confirmation(application_record)
            
            # Queue for AI processing
            self._queue_for_ai_processing(application_id)
            
            self.metrics['loans_processed'] += 1
            
            return {
                'success': True,
                'application_id': application_id,
                'status': 'pending',
                'message': 'Application submitted successfully'
            }
            
        except Exception as e:
            self.logger.error(f"Loan application processing error: {str(e)}")
            return {
                'success': False,
                'error': 'Processing failed',
                'details': str(e)
            }
    
    def approve_loan(self, application_id: str, approval_data: Dict) -> Dict:
        """Approve a loan application"""
        try:
            self.logger.info(f"Approving loan application {application_id}")
            
            # Get application data
            application = self._get_application(application_id)
            if not application:
                return {'success': False, 'error': 'Application not found'}
            
            # Update application status
            self._update_application_status(application_id, 'approved', approval_data)
            
            # Generate loan documents
            documents = self.generate_loan_documents(application, approval_data)
            
            # Create loan record
            loan_record = self._create_loan_record(application, approval_data)
            
            # Send approval notification
            self._send_approval_notification(application, approval_data, documents)
            
            # Setup payment schedule
            self._setup_payment_schedule(loan_record)
            
            return {
                'success': True,
                'loan_id': loan_record['id'],
                'documents': documents,
                'message': 'Loan approved successfully'
            }
            
        except Exception as e:
            self.logger.error(f"Loan approval error: {str(e)}")
            return {'success': False, 'error': str(e)}
    
    def reject_loan(self, application_id: str, rejection_data: Dict) -> Dict:
        """Reject a loan application"""
        try:
            self.logger.info(f"Rejecting loan application {application_id}")
            
            # Get application data
            application = self._get_application(application_id)
            if not application:
                return {'success': False, 'error': 'Application not found'}
            
            # Update application status
            self._update_application_status(application_id, 'rejected', rejection_data)
            
            # Send rejection notification
            self._send_rejection_notification(application, rejection_data)
            
            return {
                'success': True,
                'message': 'Application rejected'
            }
            
        except Exception as e:
            self.logger.error(f"Loan rejection error: {str(e)}")
            return {'success': False, 'error': str(e)}
    
    # Document Generation Services
    def generate_loan_documents(self, application: Dict, approval_data: Dict) -> List[Dict]:
        """Generate loan documents"""
        try:
            self.logger.info(f"Generating documents for application {application['id']}")
            
            documents = []
            
            # Generate loan agreement
            loan_agreement = self._generate_loan_agreement(application, approval_data)
            documents.append(loan_agreement)
            
            # Generate promissory note
            promissory_note = self._generate_promissory_note(application, approval_data)
            documents.append(promissory_note)
            
            # Generate payment schedule
            payment_schedule = self._generate_payment_schedule(application, approval_data)
            documents.append(payment_schedule)
            
            # Generate disclosure documents
            disclosures = self._generate_disclosure_documents(application, approval_data)
            documents.extend(disclosures)
            
            self.metrics['documents_generated'] += len(documents)
            
            return documents
            
        except Exception as e:
            self.logger.error(f"Document generation error: {str(e)}")
            return []
    
    def generate_monthly_statements(self) -> List[Dict]:
        """Generate monthly statements for all active loans"""
        try:
            self.logger.info("Generating monthly statements")
            
            # Get active loans
            active_loans = self._get_active_loans()
            
            statements = []
            for loan in active_loans:
                statement = self._generate_loan_statement(loan)
                statements.append(statement)
                
                # Send statement to customer
                self._send_monthly_statement(loan, statement)
            
            return statements
            
        except Exception as e:
            self.logger.error(f"Monthly statement generation error: {str(e)}")
            return []
    
    # Customer Management Services
    def onboard_customer(self, customer_data: Dict) -> Dict:
        """Onboard a new customer"""
        try:
            self.logger.info(f"Onboarding customer {customer_data.get('email')}")
            
            # Validate customer data
            if not self._validate_customer_data(customer_data):
                return {'success': False, 'error': 'Invalid customer data'}
            
            # Create customer record
            customer_id = self._create_customer_record(customer_data)
            
            # Setup customer account
            account_data = self._setup_customer_account(customer_id, customer_data)
            
            # Send welcome email
            self._send_welcome_email(customer_data, account_data)
            
            # Create customer profile
            self._create_customer_profile(customer_id, customer_data)
            
            self.metrics['customers_onboarded'] += 1
            
            return {
                'success': True,
                'customer_id': customer_id,
                'account_data': account_data
            }
            
        except Exception as e:
            self.logger.error(f"Customer onboarding error: {str(e)}")
            return {'success': False, 'error': str(e)}
    
    def update_customer_profile(self, customer_id: str, update_data: Dict) -> Dict:
        """Update customer profile"""
        try:
            self.logger.info(f"Updating customer profile {customer_id}")
            
            # Validate update data
            if not self._validate_update_data(update_data):
                return {'success': False, 'error': 'Invalid update data'}
            
            # Update customer record
            self._update_customer_record(customer_id, update_data)
            
            # Log profile change
            self._log_profile_change(customer_id, update_data)
            
            return {'success': True, 'message': 'Profile updated successfully'}
            
        except Exception as e:
            self.logger.error(f"Customer profile update error: {str(e)}")
            return {'success': False, 'error': str(e)}
    
    # Payment Processing Services
    def process_payment(self, payment_data: Dict) -> Dict:
        """Process a loan payment"""
        try:
            self.logger.info(f"Processing payment for loan {payment_data.get('loan_id')}")
            
            # Validate payment data
            validation_result = self._validate_payment_data(payment_data)
            if not validation_result['valid']:
                return {
                    'success': False,
                    'error': 'Invalid payment data',
                    'details': validation_result['errors']
                }
            
            # Process payment through gateway
            gateway_result = self._process_payment_gateway(payment_data)
            if not gateway_result['success']:
                return gateway_result
            
            # Update loan balance
            self._update_loan_balance(payment_data['loan_id'], payment_data['amount'])
            
            # Record payment
            payment_record = self._record_payment(payment_data, gateway_result)
            
            # Send payment confirmation
            self._send_payment_confirmation(payment_data, payment_record)
            
            # Check if loan is paid off
            self._check_loan_payoff(payment_data['loan_id'])
            
            self.metrics['payments_processed'] += 1
            
            return {
                'success': True,
                'payment_id': payment_record['id'],
                'transaction_id': gateway_result['transaction_id'],
                'message': 'Payment processed successfully'
            }
            
        except Exception as e:
            self.logger.error(f"Payment processing error: {str(e)}")
            return {'success': False, 'error': str(e)}
    
    def setup_autopay(self, loan_id: str, autopay_data: Dict) -> Dict:
        """Setup automatic payments for a loan"""
        try:
            self.logger.info(f"Setting up autopay for loan {loan_id}")
            
            # Validate autopay data
            if not self._validate_autopay_data(autopay_data):
                return {'success': False, 'error': 'Invalid autopay data'}
            
            # Create autopay record
            autopay_record = self._create_autopay_record(loan_id, autopay_data)
            
            # Schedule recurring payments
            self._schedule_recurring_payments(autopay_record)
            
            # Send autopay confirmation
            self._send_autopay_confirmation(loan_id, autopay_data)
            
            return {
                'success': True,
                'autopay_id': autopay_record['id'],
                'message': 'Autopay setup successfully'
            }
            
        except Exception as e:
            self.logger.error(f"Autopay setup error: {str(e)}")
            return {'success': False, 'error': str(e)}
    
    # Communication Services
    def send_notification(self, notification_data: Dict) -> Dict:
        """Send notification to customer"""
        try:
            notification_type = notification_data.get('type')
            recipient = notification_data.get('recipient')
            
            self.logger.info(f"Sending {notification_type} notification to {recipient}")
            
            # Choose notification method
            if notification_data.get('method') == 'sms':
                result = self._send_sms_notification(notification_data)
            else:
                result = self._send_email_notification(notification_data)
            
            # Log notification
            self._log_notification(notification_data, result)
            
            if result['success']:
                self.metrics['emails_sent'] += 1
            
            return result
            
        except Exception as e:
            self.logger.error(f"Notification sending error: {str(e)}")
            return {'success': False, 'error': str(e)}
    
    def send_bulk_notifications(self, notifications: List[Dict]) -> Dict:
        """Send bulk notifications"""
        try:
            self.logger.info(f"Sending {len(notifications)} bulk notifications")
            
            results = []
            for notification in notifications:
                result = self.send_notification(notification)
                results.append(result)
            
            successful = sum(1 for r in results if r['success'])
            
            return {
                'success': True,
                'total_sent': len(notifications),
                'successful': successful,
                'failed': len(notifications) - successful,
                'results': results
            }
            
        except Exception as e:
            self.logger.error(f"Bulk notification error: {str(e)}")
            return {'success': False, 'error': str(e)}
    
    # Reporting and Analytics Services
    def generate_business_report(self, report_type: str, date_range: Dict) -> Dict:
        """Generate business reports"""
        try:
            self.logger.info(f"Generating {report_type} report")
            
            if report_type == 'loan_performance':
                return self._generate_loan_performance_report(date_range)
            elif report_type == 'customer_analytics':
                return self._generate_customer_analytics_report(date_range)
            elif report_type == 'financial_summary':
                return self._generate_financial_summary_report(date_range)
            elif report_type == 'risk_analysis':
                return self._generate_risk_analysis_report(date_range)
            else:
                return {'success': False, 'error': 'Unknown report type'}
                
        except Exception as e:
            self.logger.error(f"Report generation error: {str(e)}")
            return {'success': False, 'error': str(e)}
    
    def get_business_metrics(self) -> Dict:
        """Get current business metrics"""
        try:
            # Get real-time metrics from database
            db_metrics = self._get_database_metrics()
            
            # Combine with service metrics
            combined_metrics = {
                **self.metrics,
                **db_metrics,
                'last_updated': datetime.now().isoformat()
            }
            
            return combined_metrics
            
        except Exception as e:
            self.logger.error(f"Metrics retrieval error: {str(e)}")
            return self.metrics
    
    # Helper Methods
    def _initialize_email_service(self):
        """Initialize email service"""
        try:
            # Test SMTP connection
            if self.config['smtp_username'] and self.config['smtp_password']:
                server = smtplib.SMTP(self.config['smtp_server'], self.config['smtp_port'])
                server.starttls()
                server.login(self.config['smtp_username'], self.config['smtp_password'])
                server.quit()
                self.logger.info("Email service initialized successfully")
            else:
                self.logger.warning("Email credentials not configured")
        except Exception as e:
            self.logger.error(f"Email service initialization error: {str(e)}")
    
    def _initialize_document_templates(self):
        """Initialize document templates"""
        self.document_templates = {
            'loan_agreement': {
                'template_path': 'templates/loan_agreement.html',
                'required_fields': ['borrower_name', 'loan_amount', 'interest_rate', 'term']
            },
            'promissory_note': {
                'template_path': 'templates/promissory_note.html',
                'required_fields': ['borrower_name', 'loan_amount', 'due_date']
            },
            'payment_schedule': {
                'template_path': 'templates/payment_schedule.html',
                'required_fields': ['loan_amount', 'interest_rate', 'term', 'payment_amount']
            }
        }
    
    def _initialize_payment_processing(self):
        """Initialize payment processing"""
        self.payment_gateways = {
            'stripe': {
                'api_key': os.getenv('STRIPE_API_KEY'),
                'webhook_secret': os.getenv('STRIPE_WEBHOOK_SECRET')
            },
            'paypal': {
                'client_id': os.getenv('PAYPAL_CLIENT_ID'),
                'client_secret': os.getenv('PAYPAL_CLIENT_SECRET')
            }
        }
    
    def _initialize_notification_system(self):
        """Initialize notification system"""
        self.notification_templates = {
            'application_confirmation': {
                'subject': 'Application Received - {application_id}',
                'template': 'templates/emails/application_confirmation.html'
            },
            'loan_approval': {
                'subject': 'Loan Approved - Congratulations!',
                'template': 'templates/emails/loan_approval.html'
            },
            'payment_reminder': {
                'subject': 'Payment Due Reminder',
                'template': 'templates/emails/payment_reminder.html'
            },
            'payment_confirmation': {
                'subject': 'Payment Received - Thank You',
                'template': 'templates/emails/payment_confirmation.html'
            }
        }
    
    def _validate_application_data(self, data: Dict) -> Dict:
        """Validate loan application data"""
        errors = []
        required_fields = ['first_name', 'last_name', 'email', 'phone', 'income', 'loan_amount']
        
        for field in required_fields:
            if not data.get(field):
                errors.append(f"Missing required field: {field}")
        
        # Validate loan amount
        loan_amount = data.get('loan_amount', 0)
        if loan_amount < self.config['min_loan_amount'] or loan_amount > self.config['max_loan_amount']:
            errors.append(f"Loan amount must be between ${self.config['min_loan_amount']} and ${self.config['max_loan_amount']}")
        
        # Validate email format
        email = data.get('email', '')
        if email and '@' not in email:
            errors.append("Invalid email format")
        
        return {
            'valid': len(errors) == 0,
            'errors': errors
        }
    
    def _generate_application_id(self) -> str:
        """Generate unique application ID"""
        return f"APP{datetime.now().strftime('%Y%m%d')}{str(uuid.uuid4())[:8].upper()}"
    
    def _store_application(self, application: Dict):
        """Store application in database"""
        try:
            query = """
                INSERT INTO loan_applications 
                (id, first_name, last_name, email, phone, income, loan_amount, 
                 status, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            
            values = (
                application['id'],
                application['first_name'],
                application['last_name'],
                application['email'],
                application['phone'],
                application['income'],
                application['loan_amount'],
                application['status'],
                application['created_at'],
                application['updated_at']
            )
            
            self.db_manager.execute_query(query, values)
            
        except Exception as e:
            self.logger.error(f"Application storage error: {str(e)}")
            raise
    
    def _send_application_confirmation(self, application: Dict):
        """Send application confirmation email"""
        try:
            notification_data = {
                'type': 'application_confirmation',
                'recipient': application['email'],
                'data': {
                    'application_id': application['id'],
                    'customer_name': f"{application['first_name']} {application['last_name']}",
                    'loan_amount': application['loan_amount']
                }
            }
            
            self.send_notification(notification_data)
            
        except Exception as e:
            self.logger.error(f"Application confirmation error: {str(e)}")
    
    def _queue_for_ai_processing(self, application_id: str):
        """Queue application for AI processing"""
        try:
            task_data = {
                'type': 'loan_processing',
                'application_id': application_id,
                'priority': 'normal',
                'created_at': datetime.now().isoformat()
            }
            
            # Add to Redis queue
            self.redis_manager.lpush('ai_processing_queue', json.dumps(task_data))
            
        except Exception as e:
            self.logger.error(f"AI queue error: {str(e)}")
    
    def _get_application(self, application_id: str) -> Optional[Dict]:
        """Get application from database"""
        try:
            query = "SELECT * FROM loan_applications WHERE id = %s"
            result = self.db_manager.execute_query(query, (application_id,))
            return result[0] if result else None
        except Exception as e:
            self.logger.error(f"Application retrieval error: {str(e)}")
            return None
    
    def _update_application_status(self, application_id: str, status: str, data: Dict):
        """Update application status"""
        try:
            query = """
                UPDATE loan_applications 
                SET status = %s, updated_at = %s, decision_data = %s
                WHERE id = %s
            """
            
            values = (status, datetime.now(), json.dumps(data), application_id)
            self.db_manager.execute_query(query, values)
            
        except Exception as e:
            self.logger.error(f"Application status update error: {str(e)}")
            raise
    
    def _create_loan_record(self, application: Dict, approval_data: Dict) -> Dict:
        """Create loan record"""
        try:
            loan_id = f"LOAN{datetime.now().strftime('%Y%m%d')}{str(uuid.uuid4())[:8].upper()}"
            
            loan_record = {
                'id': loan_id,
                'application_id': application['id'],
                'customer_email': application['email'],
                'principal_amount': approval_data['approved_amount'],
                'interest_rate': approval_data['interest_rate'],
                'term_months': approval_data['term_months'],
                'monthly_payment': approval_data['monthly_payment'],
                'status': 'active',
                'created_at': datetime.now(),
                'updated_at': datetime.now()
            }
            
            # Store in database
            query = """
                INSERT INTO loans 
                (id, application_id, customer_email, principal_amount, interest_rate, 
                 term_months, monthly_payment, status, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            
            values = (
                loan_record['id'],
                loan_record['application_id'],
                loan_record['customer_email'],
                loan_record['principal_amount'],
                loan_record['interest_rate'],
                loan_record['term_months'],
                loan_record['monthly_payment'],
                loan_record['status'],
                loan_record['created_at'],
                loan_record['updated_at']
            )
            
            self.db_manager.execute_query(query, values)
            
            return loan_record
            
        except Exception as e:
            self.logger.error(f"Loan record creation error: {str(e)}")
            raise
    
    def _generate_loan_agreement(self, application: Dict, approval_data: Dict) -> Dict:
        """Generate loan agreement document"""
        try:
            # Create PDF document
            buffer = io.BytesIO()
            p = canvas.Canvas(buffer, pagesize=letter)
            
            # Document content
            p.drawString(100, 750, f"LOAN AGREEMENT")
            p.drawString(100, 720, f"Loan ID: {approval_data.get('loan_id', 'TBD')}")
            p.drawString(100, 700, f"Borrower: {application['first_name']} {application['last_name']}")
            p.drawString(100, 680, f"Loan Amount: ${approval_data['approved_amount']:,.2f}")
            p.drawString(100, 660, f"Interest Rate: {approval_data['interest_rate']*100:.2f}%")
            p.drawString(100, 640, f"Term: {approval_data['term_months']} months")
            p.drawString(100, 620, f"Monthly Payment: ${approval_data['monthly_payment']:,.2f}")
            
            p.save()
            
            # Save document
            document_id = str(uuid.uuid4())
            file_path = f"{self.config['document_storage_path']}/loan_agreement_{document_id}.pdf"
            
            os.makedirs(os.path.dirname(file_path), exist_ok=True)
            with open(file_path, 'wb') as f:
                f.write(buffer.getvalue())
            
            return {
                'id': document_id,
                'type': 'loan_agreement',
                'file_path': file_path,
                'created_at': datetime.now().isoformat()
            }
            
        except Exception as e:
            self.logger.error(f"Loan agreement generation error: {str(e)}")
            return {}
    
    def _generate_promissory_note(self, application: Dict, approval_data: Dict) -> Dict:
        """Generate promissory note"""
        try:
            # Similar to loan agreement but simpler format
            buffer = io.BytesIO()
            p = canvas.Canvas(buffer, pagesize=letter)
            
            p.drawString(100, 750, f"PROMISSORY NOTE")
            p.drawString(100, 720, f"Amount: ${approval_data['approved_amount']:,.2f}")
            p.drawString(100, 700, f"Borrower: {application['first_name']} {application['last_name']}")
            
            p.save()
            
            document_id = str(uuid.uuid4())
            file_path = f"{self.config['document_storage_path']}/promissory_note_{document_id}.pdf"
            
            os.makedirs(os.path.dirname(file_path), exist_ok=True)
            with open(file_path, 'wb') as f:
                f.write(buffer.getvalue())
            
            return {
                'id': document_id,
                'type': 'promissory_note',
                'file_path': file_path,
                'created_at': datetime.now().isoformat()
            }
            
        except Exception as e:
            self.logger.error(f"Promissory note generation error: {str(e)}")
            return {}
    
    def _generate_payment_schedule(self, application: Dict, approval_data: Dict) -> Dict:
        """Generate payment schedule"""
        try:
            buffer = io.BytesIO()
            p = canvas.Canvas(buffer, pagesize=letter)
            
            p.drawString(100, 750, f"PAYMENT SCHEDULE")
            p.drawString(100, 720, f"Loan Amount: ${approval_data['approved_amount']:,.2f}")
            p.drawString(100, 700, f"Monthly Payment: ${approval_data['monthly_payment']:,.2f}")
            p.drawString(100, 680, f"Number of Payments: {approval_data['term_months']}")
            
            # Add payment schedule table
            y_position = 650
            for i in range(min(12, approval_data['term_months'])):  # Show first 12 payments
                payment_date = datetime.now() + timedelta(days=30*(i+1))
                p.drawString(100, y_position, f"Payment {i+1}: {payment_date.strftime('%Y-%m-%d')} - ${approval_data['monthly_payment']:,.2f}")
                y_position -= 20
            
            p.save()
            
            document_id = str(uuid.uuid4())
            file_path = f"{self.config['document_storage_path']}/payment_schedule_{document_id}.pdf"
            
            os.makedirs(os.path.dirname(file_path), exist_ok=True)
            with open(file_path, 'wb') as f:
                f.write(buffer.getvalue())
            
            return {
                'id': document_id,
                'type': 'payment_schedule',
                'file_path': file_path,
                'created_at': datetime.now().isoformat()
            }
            
        except Exception as e:
            self.logger.error(f"Payment schedule generation error: {str(e)}")
            return {}
    
    def _generate_disclosure_documents(self, application: Dict, approval_data: Dict) -> List[Dict]:
        """Generate required disclosure documents"""
        try:
            disclosures = []
            
            # Truth in Lending disclosure
            til_disclosure = self._generate_til_disclosure(application, approval_data)
            disclosures.append(til_disclosure)
            
            # Privacy notice
            privacy_notice = self._generate_privacy_notice(application)
            disclosures.append(privacy_notice)
            
            return disclosures
            
        except Exception as e:
            self.logger.error(f"Disclosure generation error: {str(e)}")
            return []
    
    def _generate_til_disclosure(self, application: Dict, approval_data: Dict) -> Dict:
        """Generate Truth in Lending disclosure"""
        try:
            buffer = io.BytesIO()
            p = canvas.Canvas(buffer, pagesize=letter)
            
            p.drawString(100, 750, f"TRUTH IN LENDING DISCLOSURE")
            p.drawString(100, 720, f"Annual Percentage Rate (APR): {approval_data['interest_rate']*100:.2f}%")
            p.drawString(100, 700, f"Finance Charge: ${approval_data.get('finance_charge', 0):,.2f}")
            p.drawString(100, 680, f"Amount Financed: ${approval_data['approved_amount']:,.2f}")
            p.drawString(100, 660, f"Total of Payments: ${approval_data['monthly_payment'] * approval_data['term_months']:,.2f}")
            
            p.save()
            
            document_id = str(uuid.uuid4())
            file_path = f"{self.config['document_storage_path']}/til_disclosure_{document_id}.pdf"
            
            os.makedirs(os.path.dirname(file_path), exist_ok=True)
            with open(file_path, 'wb') as f:
                f.write(buffer.getvalue())
            
            return {
                'id': document_id,
                'type': 'til_disclosure',
                'file_path': file_path,
                'created_at': datetime.now().isoformat()
            }
            
        except Exception as e:
            self.logger.error(f"TIL disclosure generation error: {str(e)}")
            return {}
    
    def _generate_privacy_notice(self, application: Dict) -> Dict:
        """Generate privacy notice"""
        try:
            buffer = io.BytesIO()
            p = canvas.Canvas(buffer, pagesize=letter)
            
            p.drawString(100, 750, f"PRIVACY NOTICE")
            p.drawString(100, 720, f"We protect your personal information in accordance with applicable laws.")
            
            p.save()
            
            document_id = str(uuid.uuid4())
            file_path = f"{self.config['document_storage_path']}/privacy_notice_{document_id}.pdf"
            
            os.makedirs(os.path.dirname(file_path), exist_ok=True)
            with open(file_path, 'wb') as f:
                f.write(buffer.getvalue())
            
            return {
                'id': document_id,
                'type': 'privacy_notice',
                'file_path': file_path,
                'created_at': datetime.now().isoformat()
            }
            
        except Exception as e:
            self.logger.error(f"Privacy notice generation error: {str(e)}")
            return {}
    
    def _send_approval_notification(self, application: Dict, approval_data: Dict, documents: List[Dict]):
        """Send loan approval notification"""
        try:
            notification_data = {
                'type': 'loan_approval',
                'recipient': application['email'],
                'data': {
                    'customer_name': f"{application['first_name']} {application['last_name']}",
                    'loan_amount': approval_data['approved_amount'],
                    'interest_rate': approval_data['interest_rate'],
                    'monthly_payment': approval_data['monthly_payment'],
                    'documents': documents
                }
            }
            
            self.send_notification(notification_data)
            
        except Exception as e:
            self.logger.error(f"Approval notification error: {str(e)}")
    
    def _send_rejection_notification(self, application: Dict, rejection_data: Dict):
        """Send loan rejection notification"""
        try:
            notification_data = {
                'type': 'loan_rejection',
                'recipient': application['email'],
                'data': {
                    'customer_name': f"{application['first_name']} {application['last_name']}",
                    'reason': rejection_data.get('reason', 'Application did not meet our current lending criteria')
                }
            }
            
            self.send_notification(notification_data)
            
        except Exception as e:
            self.logger.error(f"Rejection notification error: {str(e)}")
    
    def _setup_payment_schedule(self, loan_record: Dict):
        """Setup payment schedule for loan"""
        try:
            # Create payment schedule records
            for month in range(1, loan_record['term_months'] + 1):
                due_date = datetime.now() + timedelta(days=30*month)
                
                payment_record = {
                    'loan_id': loan_record['id'],
                    'payment_number': month,
                    'due_date': due_date,
                    'amount_due': loan_record['monthly_payment'],
                    'status': 'pending'
                }
                
                self._create_payment_record(payment_record)
            
        except Exception as e:
            self.logger.error(f"Payment schedule setup error: {str(e)}")
    
    def _create_payment_record(self, payment_record: Dict):
        """Create payment record in database"""
        try:
            query = """
                INSERT INTO loan_payments 
                (loan_id, payment_number, due_date, amount_due, status)
                VALUES (%s, %s, %s, %s, %s)
            """
            
            values = (
                payment_record['loan_id'],
                payment_record['payment_number'],
                payment_record['due_date'],
                payment_record['amount_due'],
                payment_record['status']
            )
            
            self.db_manager.execute_query(query, values)
            
        except Exception as e:
            self.logger.error(f"Payment record creation error: {str(e)}")
            raise
    
    def _send_email_notification(self, notification_data: Dict) -> Dict:
        """Send email notification"""
        try:
            if not self.config['smtp_username'] or not self.config['smtp_password']:
                return {'success': False, 'error': 'Email not configured'}
            
            # Create email message
            msg = MimeMultipart()
            msg['From'] = self.config['company_email']
            msg['To'] = notification_data['recipient']
            
            # Get template
            template_info = self.notification_templates.get(notification_data['type'])
            if template_info:
                msg['Subject'] = template_info['subject'].format(**notification_data.get('data', {}))
                
                # Load and format template
                email_body = self._format_email_template(template_info['template'], notification_data.get('data', {}))
                msg.attach(MimeText(email_body, 'html'))
            else:
                msg['Subject'] = 'Notification from LoanFlow'
                msg.attach(MimeText('You have a new notification.', 'plain'))
            
            # Send email
            server = smtplib.SMTP(self.config['smtp_server'], self.config['smtp_port'])
            server.starttls()
            server.login(self.config['smtp_username'], self.config['smtp_password'])
            server.send_message(msg)
            server.quit()
            
            return {'success': True, 'message': 'Email sent successfully'}
            
        except Exception as e:
            self.logger.error(f"Email sending error: {str(e)}")
            return {'success': False, 'error': str(e)}
    
    def _format_email_template(self, template_path: str, data: Dict) -> str:
        """Format email template with data"""
        try:
            # Simple template formatting (in production, use proper template engine)
            template_content = f"""
            <html>
            <body>
                <h2>LoanFlow Notification</h2>
                <p>Dear {data.get('customer_name', 'Customer')},</p>
                <p>This is an automated notification from LoanFlow.</p>
                <p>Best regards,<br>The LoanFlow Team</p>
            </body>
            </html>
            """
            
            return template_content
            
        except Exception as e:
            self.logger.error(f"Template formatting error: {str(e)}")
            return "<html><body><p>Notification from LoanFlow</p></body></html>"
    
    def _validate_payment_data(self, data: Dict) -> Dict:
        """Validate payment data"""
        errors = []
        required_fields = ['loan_id', 'amount', 'payment_method']
        
        for field in required_fields:
            if not data.get(field):
                errors.append(f"Missing required field: {field}")
        
        # Validate amount
        amount = data.get('amount', 0)
        if amount <= 0:
            errors.append("Payment amount must be greater than 0")
        
        return {
            'valid': len(errors) == 0,
            'errors': errors
        }
    
    def _process_payment_gateway(self, payment_data: Dict) -> Dict:
        """Process payment through gateway"""
        try:
            # Simulate payment processing
            transaction_id = f"TXN{datetime.now().strftime('%Y%m%d%H%M%S')}{str(uuid.uuid4())[:6].upper()}"
            
            # In production, integrate with actual payment gateway
            return {
                'success': True,
                'transaction_id': transaction_id,
                'status': 'completed',
                'gateway_response': 'Payment processed successfully'
            }
            
        except Exception as e:
            self.logger.error(f"Payment gateway error: {str(e)}")
            return {'success': False, 'error': str(e)}
    
    def _update_loan_balance(self, loan_id: str, payment_amount: float):
        """Update loan balance after payment"""
        try:
            query = """
                UPDATE loans 
                SET current_balance = current_balance - %s,
                    updated_at = %s
                WHERE id = %s
            """
            
            values = (payment_amount, datetime.now(), loan_id)
            self.db_manager.execute_query(query, values)
            
        except Exception as e:
            self.logger.error(f"Loan balance update error: {str(e)}")
            raise
    
    def _record_payment(self, payment_data: Dict, gateway_result: Dict) -> Dict:
        """Record payment in database"""
        try:
            payment_id = str(uuid.uuid4())
            
            payment_record = {
                'id': payment_id,
                'loan_id': payment_data['loan_id'],
                'amount': payment_data['amount'],
                'payment_method': payment_data['payment_method'],
                'transaction_id': gateway_result['transaction_id'],
                'status': 'completed',
                'processed_at': datetime.now()
            }
            
            query = """
                INSERT INTO payments 
                (id, loan_id, amount, payment_method, transaction_id, status, processed_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
            """
            
            values = (
                payment_record['id'],
                payment_record['loan_id'],
                payment_record['amount'],
                payment_record['payment_method'],
                payment_record['transaction_id'],
                payment_record['status'],
                payment_record['processed_at']
            )
            
            self.db_manager.execute_query(query, values)
            
            return payment_record
            
        except Exception as e:
            self.logger.error(f"Payment recording error: {str(e)}")
            raise
    
    def _send_payment_confirmation(self, payment_data: Dict, payment_record: Dict):
        """Send payment confirmation"""
        try:
            # Get customer email from loan
            loan = self._get_loan(payment_data['loan_id'])
            if not loan:
                return
            
            notification_data = {
                'type': 'payment_confirmation',
                'recipient': loan['customer_email'],
                'data': {
                    'payment_amount': payment_data['amount'],
                    'transaction_id': payment_record['transaction_id'],
                    'loan_id': payment_data['loan_id']
                }
            }
            
            self.send_notification(notification_data)
            
        except Exception as e:
            self.logger.error(f"Payment confirmation error: {str(e)}")
    
    def _check_loan_payoff(self, loan_id: str):
        """Check if loan is paid off"""
        try:
            # Get current loan balance
            loan = self._get_loan(loan_id)
            if loan and loan.get('current_balance', 0) <= 0:
                # Mark loan as paid off
                self._mark_loan_paid_off(loan_id)
                
                # Send payoff notification
                self._send_payoff_notification(loan)
            
        except Exception as e:
            self.logger.error(f"Loan payoff check error: {str(e)}")
    
    def _get_loan(self, loan_id: str) -> Optional[Dict]:
        """Get loan from database"""
        try:
            query = "SELECT * FROM loans WHERE id = %s"
            result = self.db_manager.execute_query(query, (loan_id,))
            return result[0] if result else None
        except Exception as e:
            self.logger.error(f"Loan retrieval error: {str(e)}")
            return None
    
    def _mark_loan_paid_off(self, loan_id: str):
        """Mark loan as paid off"""
        try:
            query = """
                UPDATE loans 
                SET status = 'paid_off', paid_off_date = %s, updated_at = %s
                WHERE id = %s
            """
            
            values = (datetime.now(), datetime.now(), loan_id)
            self.db_manager.execute_query(query, values)
            
        except Exception as e:
            self.logger.error(f"Loan payoff marking error: {str(e)}")
            raise
    
    def _send_payoff_notification(self, loan: Dict):
        """Send loan payoff notification"""
        try:
            notification_data = {
                'type': 'loan_payoff',
                'recipient': loan['customer_email'],
                'data': {
                    'loan_id': loan['id'],
                    'original_amount': loan['principal_amount']
                }
            }
            
            self.send_notification(notification_data)
            
        except Exception as e:
            self.logger.error(f"Payoff notification error: {str(e)}")
    
    def _get_database_metrics(self) -> Dict:
        """Get metrics from database"""
        try:
            metrics = {}
            
            # Total loans
            query = "SELECT COUNT(*) as total_loans FROM loans"
            result = self.db_manager.execute_query(query)
            metrics['total_loans'] = result[0]['total_loans'] if result else 0
            
            # Active loans
            query = "SELECT COUNT(*) as active_loans FROM loans WHERE status = 'active'"
            result = self.db_manager.execute_query(query)
            metrics['active_loans'] = result[0]['active_loans'] if result else 0
            
            # Total loan amount
            query = "SELECT SUM(principal_amount) as total_amount FROM loans"
            result = self.db_manager.execute_query(query)
            metrics['total_loan_amount'] = result[0]['total_amount'] if result and result[0]['total_amount'] else 0
            
            # Pending applications
            query = "SELECT COUNT(*) as pending_applications FROM loan_applications WHERE status = 'pending'"
            result = self.db_manager.execute_query(query)
            metrics['pending_applications'] = result[0]['pending_applications'] if result else 0
            
            return metrics
            
        except Exception as e:
            self.logger.error(f"Database metrics error: {str(e)}")
            return {}
    
    def _log_notification(self, notification_data: Dict, result: Dict):
        """Log notification attempt"""
        try:
            log_entry = {
                'type': notification_data['type'],
                'recipient': notification_data['recipient'],
                'success': result['success'],
                'timestamp': datetime.now().isoformat(),
                'error': result.get('error')
            }
            
            # Store in Redis for monitoring
            self.redis_manager.lpush('notification_log', json.dumps(log_entry))
            
        except Exception as e:
            self.logger.error(f"Notification logging error: {str(e)}")
    
    # Additional helper methods would continue here...
    # This includes methods for customer management, reporting, etc.
    # Due to length constraints, I'm showing the core structure and key methods