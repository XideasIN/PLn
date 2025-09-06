#!/usr/bin/env python3
"""
Configuration Manager
LoanFlow Personal Loan Management System

This module manages all configuration settings including:
- Environment variables
- Database configuration
- Redis configuration
- API keys and secrets
- System parameters
- Feature flags
- Security settings
- Performance tuning
- Logging configuration
"""

import os
import json
import logging
from typing import Dict, Any, Optional, List
from datetime import datetime
import configparser
from pathlib import Path
import yaml
from cryptography.fernet import Fernet
import base64

class ConfigManager:
    def __init__(self, config_dir: str = None):
        self.logger = logging.getLogger(__name__)
        
        # Set configuration directory
        self.config_dir = config_dir or os.path.join(os.path.dirname(__file__), '..')
        self.config_file = os.path.join(self.config_dir, 'config', 'settings.yaml')
        self.secrets_file = os.path.join(self.config_dir, 'config', 'secrets.enc')
        
        # Configuration data
        self.config = {}
        self.secrets = {}
        self.environment = os.getenv('ENVIRONMENT', 'development')
        
        # Encryption key for secrets
        self.encryption_key = self._get_or_create_encryption_key()
        
        # Default configuration
        self.default_config = self._get_default_config()
        
        # Load configuration
        self._load_configuration()
    
    def _get_default_config(self) -> Dict:
        """Get default configuration settings"""
        return {
            'system': {
                'name': 'LoanFlow Autonomous Business System',
                'version': '1.0.0',
                'environment': self.environment,
                'debug': self.environment == 'development',
                'timezone': 'UTC',
                'language': 'en',
                'max_workers': 4,
                'health_check_interval': 30,
                'monitoring_enabled': True
            },
            'database': {
                'host': os.getenv('DB_HOST', 'localhost'),
                'port': int(os.getenv('DB_PORT', '3306')),
                'name': os.getenv('DB_NAME', 'loanflow'),
                'username': os.getenv('DB_USERNAME', 'root'),
                'password': os.getenv('DB_PASSWORD', ''),
                'charset': 'utf8mb4',
                'pool_size': 10,
                'pool_timeout': 30,
                'pool_recycle': 3600,
                'echo': False,
                'ssl_disabled': True
            },
            'redis': {
                'host': os.getenv('REDIS_HOST', 'localhost'),
                'port': int(os.getenv('REDIS_PORT', '6379')),
                'password': os.getenv('REDIS_PASSWORD'),
                'db': int(os.getenv('REDIS_DB', '0')),
                'socket_timeout': 30,
                'socket_connect_timeout': 30,
                'max_connections': 50,
                'health_check_interval': 30,
                'default_ttl': 3600
            },
            'security': {
                'secret_key': os.getenv('SECRET_KEY', 'your-secret-key-here'),
                'jwt_secret': os.getenv('JWT_SECRET', 'jwt-secret-key'),
                'jwt_expiration': 3600,
                'password_min_length': 8,
                'password_require_special': True,
                'max_login_attempts': 5,
                'lockout_duration': 900,
                'session_timeout': 1800,
                'csrf_protection': True,
                'rate_limiting': True,
                'rate_limit_requests': 100,
                'rate_limit_window': 3600
            },
            'email': {
                'smtp_host': os.getenv('SMTP_HOST', 'localhost'),
                'smtp_port': int(os.getenv('SMTP_PORT', '587')),
                'smtp_username': os.getenv('SMTP_USERNAME', ''),
                'smtp_password': os.getenv('SMTP_PASSWORD', ''),
                'smtp_use_tls': True,
                'from_email': os.getenv('FROM_EMAIL', 'noreply@loanflow.com'),
                'from_name': 'LoanFlow System',
                'template_dir': 'templates/email',
                'max_retries': 3,
                'retry_delay': 60
            },
            'ai_services': {
                'openai_api_key': os.getenv('OPENAI_API_KEY', ''),
                'openai_model': 'gpt-3.5-turbo',
                'openai_max_tokens': 1000,
                'openai_temperature': 0.7,
                'content_generation_enabled': True,
                'risk_assessment_enabled': True,
                'fraud_detection_enabled': True,
                'seo_automation_enabled': True,
                'customer_service_enabled': True,
                'business_intelligence_enabled': True
            },
            'autonomous_business': {
                'enabled': True,
                'auto_start': False,
                'customer_acquisition_enabled': True,
                'loan_processing_enabled': True,
                'content_generation_enabled': True,
                'seo_optimization_enabled': True,
                'customer_service_enabled': True,
                'risk_management_enabled': True,
                'daily_operations_time': '02:00',
                'monitoring_interval': 300,
                'alert_threshold': 0.8,
                'max_concurrent_tasks': 10
            },
            'payment_gateways': {
                'stripe_enabled': True,
                'stripe_public_key': os.getenv('STRIPE_PUBLIC_KEY', ''),
                'stripe_secret_key': os.getenv('STRIPE_SECRET_KEY', ''),
                'paypal_enabled': True,
                'paypal_client_id': os.getenv('PAYPAL_CLIENT_ID', ''),
                'paypal_client_secret': os.getenv('PAYPAL_CLIENT_SECRET', ''),
                'razorpay_enabled': False,
                'razorpay_key_id': os.getenv('RAZORPAY_KEY_ID', ''),
                'razorpay_key_secret': os.getenv('RAZORPAY_KEY_SECRET', ''),
                'default_gateway': 'stripe',
                'webhook_timeout': 30
            },
            'sms': {
                'twilio_enabled': True,
                'twilio_account_sid': os.getenv('TWILIO_ACCOUNT_SID', ''),
                'twilio_auth_token': os.getenv('TWILIO_AUTH_TOKEN', ''),
                'twilio_phone_number': os.getenv('TWILIO_PHONE_NUMBER', ''),
                'max_retries': 3,
                'retry_delay': 30
            },
            'file_storage': {
                'storage_type': 'local',  # local, s3, gcs
                'upload_dir': 'uploads',
                'max_file_size': 10485760,  # 10MB
                'allowed_extensions': ['.pdf', '.doc', '.docx', '.jpg', '.jpeg', '.png'],
                'aws_access_key': os.getenv('AWS_ACCESS_KEY_ID', ''),
                'aws_secret_key': os.getenv('AWS_SECRET_ACCESS_KEY', ''),
                'aws_bucket': os.getenv('AWS_S3_BUCKET', ''),
                'aws_region': os.getenv('AWS_REGION', 'us-east-1')
            },
            'logging': {
                'level': os.getenv('LOG_LEVEL', 'INFO'),
                'format': '%(asctime)s - %(name)s - %(levelname)s - %(message)s',
                'file_enabled': True,
                'file_path': 'logs/autonomous_business.log',
                'file_max_size': 10485760,  # 10MB
                'file_backup_count': 5,
                'console_enabled': True,
                'syslog_enabled': False,
                'syslog_host': 'localhost',
                'syslog_port': 514
            },
            'monitoring': {
                'metrics_enabled': True,
                'metrics_interval': 60,
                'health_check_enabled': True,
                'performance_monitoring': True,
                'error_tracking': True,
                'alert_email': os.getenv('ALERT_EMAIL', ''),
                'alert_webhook': os.getenv('ALERT_WEBHOOK', ''),
                'prometheus_enabled': False,
                'prometheus_port': 8000
            },
            'api': {
                'host': '0.0.0.0',
                'port': int(os.getenv('API_PORT', '8080')),
                'cors_enabled': True,
                'cors_origins': ['*'],
                'request_timeout': 30,
                'max_request_size': 16777216,  # 16MB
                'rate_limiting': True,
                'api_key_required': False,
                'documentation_enabled': True
            },
            'business_rules': {
                'min_loan_amount': 1000,
                'max_loan_amount': 100000,
                'min_credit_score': 600,
                'max_loan_term_months': 60,
                'interest_rate_min': 5.0,
                'interest_rate_max': 25.0,
                'processing_fee_percentage': 2.0,
                'auto_approval_threshold': 750,
                'manual_review_threshold': 650,
                'rejection_threshold': 600,
                'income_verification_required': True,
                'employment_verification_required': True
            },
            'feature_flags': {
                'autonomous_business_enabled': True,
                'ai_learning_enabled': True,
                'enhanced_user_management': True,
                'seo_automation': True,
                'edge_network': False,
                'advanced_analytics': True,
                'mobile_api': False,
                'chatbot_enabled': True,
                'document_management': True,
                'automated_workflows': True
            }
        }
    
    def _get_or_create_encryption_key(self) -> bytes:
        """Get or create encryption key for secrets"""
        key_file = os.path.join(self.config_dir, 'config', '.encryption_key')
        
        try:
            if os.path.exists(key_file):
                with open(key_file, 'rb') as f:
                    return f.read()
            else:
                # Create new key
                key = Fernet.generate_key()
                
                # Ensure config directory exists
                os.makedirs(os.path.dirname(key_file), exist_ok=True)
                
                with open(key_file, 'wb') as f:
                    f.write(key)
                
                # Set restrictive permissions
                os.chmod(key_file, 0o600)
                
                return key
                
        except Exception as e:
            self.logger.error(f"Encryption key error: {str(e)}")
            # Fallback to environment variable or default
            env_key = os.getenv('ENCRYPTION_KEY')
            if env_key:
                return base64.b64decode(env_key)
            else:
                # Generate temporary key (not persistent)
                return Fernet.generate_key()
    
    def _load_configuration(self):
        """Load configuration from files and environment"""
        try:
            # Start with default configuration
            self.config = self.default_config.copy()
            
            # Load from YAML file if exists
            if os.path.exists(self.config_file):
                with open(self.config_file, 'r') as f:
                    file_config = yaml.safe_load(f) or {}
                    self._merge_config(self.config, file_config)
            
            # Load encrypted secrets
            self._load_secrets()
            
            # Override with environment variables
            self._load_environment_overrides()
            
            # Validate configuration
            self._validate_configuration()
            
            self.logger.info(f"Configuration loaded for environment: {self.environment}")
            
        except Exception as e:
            self.logger.error(f"Configuration loading failed: {str(e)}")
            raise
    
    def _merge_config(self, base_config: Dict, override_config: Dict):
        """Recursively merge configuration dictionaries"""
        for key, value in override_config.items():
            if key in base_config and isinstance(base_config[key], dict) and isinstance(value, dict):
                self._merge_config(base_config[key], value)
            else:
                base_config[key] = value
    
    def _load_secrets(self):
        """Load encrypted secrets"""
        try:
            if os.path.exists(self.secrets_file):
                with open(self.secrets_file, 'rb') as f:
                    encrypted_data = f.read()
                
                # Decrypt secrets
                fernet = Fernet(self.encryption_key)
                decrypted_data = fernet.decrypt(encrypted_data)
                self.secrets = json.loads(decrypted_data.decode())
                
                # Merge secrets into config
                self._merge_secrets_into_config()
                
        except Exception as e:
            self.logger.warning(f"Could not load secrets file: {str(e)}")
            self.secrets = {}
    
    def _merge_secrets_into_config(self):
        """Merge secrets into configuration"""
        for section, secrets in self.secrets.items():
            if section in self.config:
                for key, value in secrets.items():
                    if key in self.config[section]:
                        self.config[section][key] = value
    
    def _load_environment_overrides(self):
        """Load configuration overrides from environment variables"""
        # Environment variable mapping
        env_mappings = {
            'DB_HOST': ('database', 'host'),
            'DB_PORT': ('database', 'port'),
            'DB_NAME': ('database', 'name'),
            'DB_USERNAME': ('database', 'username'),
            'DB_PASSWORD': ('database', 'password'),
            'REDIS_HOST': ('redis', 'host'),
            'REDIS_PORT': ('redis', 'port'),
            'REDIS_PASSWORD': ('redis', 'password'),
            'REDIS_DB': ('redis', 'db'),
            'SECRET_KEY': ('security', 'secret_key'),
            'JWT_SECRET': ('security', 'jwt_secret'),
            'OPENAI_API_KEY': ('ai_services', 'openai_api_key'),
            'STRIPE_PUBLIC_KEY': ('payment_gateways', 'stripe_public_key'),
            'STRIPE_SECRET_KEY': ('payment_gateways', 'stripe_secret_key'),
            'PAYPAL_CLIENT_ID': ('payment_gateways', 'paypal_client_id'),
            'PAYPAL_CLIENT_SECRET': ('payment_gateways', 'paypal_client_secret'),
            'SMTP_HOST': ('email', 'smtp_host'),
            'SMTP_PORT': ('email', 'smtp_port'),
            'SMTP_USERNAME': ('email', 'smtp_username'),
            'SMTP_PASSWORD': ('email', 'smtp_password'),
            'FROM_EMAIL': ('email', 'from_email'),
            'TWILIO_ACCOUNT_SID': ('sms', 'twilio_account_sid'),
            'TWILIO_AUTH_TOKEN': ('sms', 'twilio_auth_token'),
            'TWILIO_PHONE_NUMBER': ('sms', 'twilio_phone_number'),
            'AWS_ACCESS_KEY_ID': ('file_storage', 'aws_access_key'),
            'AWS_SECRET_ACCESS_KEY': ('file_storage', 'aws_secret_key'),
            'AWS_S3_BUCKET': ('file_storage', 'aws_bucket'),
            'AWS_REGION': ('file_storage', 'aws_region'),
            'LOG_LEVEL': ('logging', 'level'),
            'API_PORT': ('api', 'port'),
            'ALERT_EMAIL': ('monitoring', 'alert_email'),
            'ALERT_WEBHOOK': ('monitoring', 'alert_webhook')
        }
        
        for env_var, (section, key) in env_mappings.items():
            value = os.getenv(env_var)
            if value is not None:
                # Type conversion
                if key in ['port', 'db', 'jwt_expiration', 'max_file_size', 'file_max_size', 'file_backup_count']:
                    try:
                        value = int(value)
                    except ValueError:
                        continue
                elif key in ['smtp_use_tls', 'debug', 'echo', 'ssl_disabled']:
                    value = value.lower() in ('true', '1', 'yes', 'on')
                elif key in ['interest_rate_min', 'interest_rate_max', 'processing_fee_percentage']:
                    try:
                        value = float(value)
                    except ValueError:
                        continue
                
                if section in self.config:
                    self.config[section][key] = value
    
    def _validate_configuration(self):
        """Validate configuration settings"""
        errors = []
        
        # Validate required settings
        required_settings = [
            ('database', 'host'),
            ('database', 'name'),
            ('database', 'username'),
            ('redis', 'host'),
            ('security', 'secret_key'),
            ('email', 'smtp_host'),
            ('email', 'from_email')
        ]
        
        for section, key in required_settings:
            if not self.get(f"{section}.{key}"):
                errors.append(f"Missing required setting: {section}.{key}")
        
        # Validate numeric ranges
        numeric_validations = [
            ('database', 'port', 1, 65535),
            ('redis', 'port', 1, 65535),
            ('api', 'port', 1, 65535),
            ('business_rules', 'min_loan_amount', 1, 1000000),
            ('business_rules', 'max_loan_amount', 1000, 10000000),
            ('business_rules', 'interest_rate_min', 0.1, 50.0),
            ('business_rules', 'interest_rate_max', 0.1, 50.0)
        ]
        
        for section, key, min_val, max_val in numeric_validations:
            value = self.get(f"{section}.{key}")
            if value is not None and not (min_val <= value <= max_val):
                errors.append(f"Invalid value for {section}.{key}: {value} (must be between {min_val} and {max_val})")
        
        # Validate business rules consistency
        min_loan = self.get('business_rules.min_loan_amount')
        max_loan = self.get('business_rules.max_loan_amount')
        if min_loan and max_loan and min_loan >= max_loan:
            errors.append("min_loan_amount must be less than max_loan_amount")
        
        min_rate = self.get('business_rules.interest_rate_min')
        max_rate = self.get('business_rules.interest_rate_max')
        if min_rate and max_rate and min_rate >= max_rate:
            errors.append("interest_rate_min must be less than interest_rate_max")
        
        if errors:
            error_msg = "Configuration validation failed:\n" + "\n".join(errors)
            self.logger.error(error_msg)
            raise ValueError(error_msg)
    
    # Public Methods
    def get(self, key: str, default: Any = None) -> Any:
        """Get configuration value using dot notation"""
        try:
            keys = key.split('.')
            value = self.config
            
            for k in keys:
                if isinstance(value, dict) and k in value:
                    value = value[k]
                else:
                    return default
            
            return value
            
        except Exception as e:
            self.logger.error(f"Configuration get error for key '{key}': {str(e)}")
            return default
    
    def set(self, key: str, value: Any, persist: bool = False) -> bool:
        """Set configuration value using dot notation"""
        try:
            keys = key.split('.')
            config_ref = self.config
            
            # Navigate to the parent dictionary
            for k in keys[:-1]:
                if k not in config_ref:
                    config_ref[k] = {}
                config_ref = config_ref[k]
            
            # Set the value
            config_ref[keys[-1]] = value
            
            # Persist to file if requested
            if persist:
                self.save_configuration()
            
            return True
            
        except Exception as e:
            self.logger.error(f"Configuration set error for key '{key}': {str(e)}")
            return False
    
    def get_section(self, section: str) -> Dict:
        """Get entire configuration section"""
        return self.config.get(section, {})
    
    def set_section(self, section: str, config: Dict, persist: bool = False) -> bool:
        """Set entire configuration section"""
        try:
            self.config[section] = config
            
            if persist:
                self.save_configuration()
            
            return True
            
        except Exception as e:
            self.logger.error(f"Configuration section set error for '{section}': {str(e)}")
            return False
    
    def save_configuration(self) -> bool:
        """Save configuration to file"""
        try:
            # Ensure config directory exists
            os.makedirs(os.path.dirname(self.config_file), exist_ok=True)
            
            # Save configuration (excluding secrets)
            config_to_save = self._filter_secrets_from_config()
            
            with open(self.config_file, 'w') as f:
                yaml.dump(config_to_save, f, default_flow_style=False, indent=2)
            
            self.logger.info("Configuration saved successfully")
            return True
            
        except Exception as e:
            self.logger.error(f"Configuration save error: {str(e)}")
            return False
    
    def save_secret(self, section: str, key: str, value: str) -> bool:
        """Save encrypted secret"""
        try:
            if section not in self.secrets:
                self.secrets[section] = {}
            
            self.secrets[section][key] = value
            
            # Update config
            if section not in self.config:
                self.config[section] = {}
            self.config[section][key] = value
            
            # Encrypt and save secrets
            return self._save_encrypted_secrets()
            
        except Exception as e:
            self.logger.error(f"Secret save error: {str(e)}")
            return False
    
    def delete_secret(self, section: str, key: str) -> bool:
        """Delete encrypted secret"""
        try:
            if section in self.secrets and key in self.secrets[section]:
                del self.secrets[section][key]
                
                # Remove from config
                if section in self.config and key in self.config[section]:
                    del self.config[section][key]
                
                # Save updated secrets
                return self._save_encrypted_secrets()
            
            return True
            
        except Exception as e:
            self.logger.error(f"Secret delete error: {str(e)}")
            return False
    
    def _filter_secrets_from_config(self) -> Dict:
        """Filter out secrets from configuration for saving"""
        config_copy = json.loads(json.dumps(self.config))  # Deep copy
        
        # List of secret keys to exclude
        secret_keys = [
            'password', 'secret', 'key', 'token', 'auth_token',
            'api_key', 'client_secret', 'private_key'
        ]
        
        def filter_dict(d):
            if isinstance(d, dict):
                filtered = {}
                for k, v in d.items():
                    if any(secret_key in k.lower() for secret_key in secret_keys):
                        filtered[k] = '[REDACTED]'
                    else:
                        filtered[k] = filter_dict(v)
                return filtered
            else:
                return d
        
        return filter_dict(config_copy)
    
    def _save_encrypted_secrets(self) -> bool:
        """Save encrypted secrets to file"""
        try:
            if not self.secrets:
                return True
            
            # Ensure config directory exists
            os.makedirs(os.path.dirname(self.secrets_file), exist_ok=True)
            
            # Encrypt secrets
            fernet = Fernet(self.encryption_key)
            secrets_json = json.dumps(self.secrets)
            encrypted_data = fernet.encrypt(secrets_json.encode())
            
            # Save encrypted secrets
            with open(self.secrets_file, 'wb') as f:
                f.write(encrypted_data)
            
            # Set restrictive permissions
            os.chmod(self.secrets_file, 0o600)
            
            return True
            
        except Exception as e:
            self.logger.error(f"Encrypted secrets save error: {str(e)}")
            return False
    
    def reload_configuration(self) -> bool:
        """Reload configuration from files"""
        try:
            self._load_configuration()
            self.logger.info("Configuration reloaded successfully")
            return True
            
        except Exception as e:
            self.logger.error(f"Configuration reload error: {str(e)}")
            return False
    
    def get_database_url(self) -> str:
        """Get database connection URL"""
        db_config = self.get_section('database')
        
        username = db_config.get('username')
        password = db_config.get('password')
        host = db_config.get('host')
        port = db_config.get('port')
        name = db_config.get('name')
        
        if password:
            return f"mysql://{username}:{password}@{host}:{port}/{name}"
        else:
            return f"mysql://{username}@{host}:{port}/{name}"
    
    def get_redis_url(self) -> str:
        """Get Redis connection URL"""
        redis_config = self.get_section('redis')
        
        host = redis_config.get('host')
        port = redis_config.get('port')
        password = redis_config.get('password')
        db = redis_config.get('db')
        
        if password:
            return f"redis://:{password}@{host}:{port}/{db}"
        else:
            return f"redis://{host}:{port}/{db}"
    
    def is_feature_enabled(self, feature: str) -> bool:
        """Check if feature flag is enabled"""
        return self.get(f'feature_flags.{feature}', False)
    
    def enable_feature(self, feature: str, persist: bool = False) -> bool:
        """Enable feature flag"""
        return self.set(f'feature_flags.{feature}', True, persist)
    
    def disable_feature(self, feature: str, persist: bool = False) -> bool:
        """Disable feature flag"""
        return self.set(f'feature_flags.{feature}', False, persist)
    
    def get_environment(self) -> str:
        """Get current environment"""
        return self.environment
    
    def is_development(self) -> bool:
        """Check if running in development environment"""
        return self.environment == 'development'
    
    def is_production(self) -> bool:
        """Check if running in production environment"""
        return self.environment == 'production'
    
    def get_log_config(self) -> Dict:
        """Get logging configuration"""
        log_config = self.get_section('logging')
        
        return {
            'version': 1,
            'disable_existing_loggers': False,
            'formatters': {
                'standard': {
                    'format': log_config.get('format')
                }
            },
            'handlers': {
                'console': {
                    'class': 'logging.StreamHandler',
                    'level': log_config.get('level'),
                    'formatter': 'standard',
                    'stream': 'ext://sys.stdout'
                },
                'file': {
                    'class': 'logging.handlers.RotatingFileHandler',
                    'level': log_config.get('level'),
                    'formatter': 'standard',
                    'filename': log_config.get('file_path'),
                    'maxBytes': log_config.get('file_max_size'),
                    'backupCount': log_config.get('file_backup_count')
                }
            },
            'loggers': {
                '': {
                    'handlers': ['console', 'file'] if log_config.get('file_enabled') else ['console'],
                    'level': log_config.get('level'),
                    'propagate': False
                }
            }
        }
    
    def validate_api_keys(self) -> Dict[str, bool]:
        """Validate API keys and external service credentials"""
        validation_results = {}
        
        # OpenAI API Key
        openai_key = self.get('ai_services.openai_api_key')
        validation_results['openai'] = bool(openai_key and len(openai_key) > 20)
        
        # Stripe Keys
        stripe_public = self.get('payment_gateways.stripe_public_key')
        stripe_secret = self.get('payment_gateways.stripe_secret_key')
        validation_results['stripe'] = bool(stripe_public and stripe_secret)
        
        # PayPal Keys
        paypal_client_id = self.get('payment_gateways.paypal_client_id')
        paypal_secret = self.get('payment_gateways.paypal_client_secret')
        validation_results['paypal'] = bool(paypal_client_id and paypal_secret)
        
        # Twilio Keys
        twilio_sid = self.get('sms.twilio_account_sid')
        twilio_token = self.get('sms.twilio_auth_token')
        validation_results['twilio'] = bool(twilio_sid and twilio_token)
        
        # AWS Keys
        aws_access = self.get('file_storage.aws_access_key')
        aws_secret = self.get('file_storage.aws_secret_key')
        validation_results['aws'] = bool(aws_access and aws_secret)
        
        return validation_results
    
    def get_config_summary(self) -> Dict:
        """Get configuration summary for monitoring"""
        return {
            'environment': self.environment,
            'system_name': self.get('system.name'),
            'version': self.get('system.version'),
            'debug_mode': self.get('system.debug'),
            'database_host': self.get('database.host'),
            'redis_host': self.get('redis.host'),
            'api_port': self.get('api.port'),
            'features_enabled': {
                feature: self.is_feature_enabled(feature)
                for feature in self.get_section('feature_flags').keys()
            },
            'api_keys_configured': self.validate_api_keys(),
            'last_loaded': datetime.now().isoformat()
        }

if __name__ == "__main__":
    # Example usage and testing
    import sys
    
    # Setup logging
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    
    # Initialize configuration manager
    config_manager = ConfigManager()
    
    try:
        # Test configuration access
        print(f"Environment: {config_manager.get_environment()}")
        print(f"Database Host: {config_manager.get('database.host')}")
        print(f"Redis Host: {config_manager.get('redis.host')}")
        print(f"API Port: {config_manager.get('api.port')}")
        
        # Test feature flags
        print(f"Autonomous Business Enabled: {config_manager.is_feature_enabled('autonomous_business_enabled')}")
        print(f"AI Learning Enabled: {config_manager.is_feature_enabled('ai_learning_enabled')}")
        
        # Test API key validation
        api_validation = config_manager.validate_api_keys()
        print(f"API Keys Validation: {api_validation}")
        
        # Get configuration summary
        summary = config_manager.get_config_summary()
        print(f"Configuration Summary: {json.dumps(summary, indent=2)}")
        
        print("Configuration manager test completed successfully")
        
    except Exception as e:
        print(f"Configuration manager test failed: {str(e)}")
        sys.exit(1)