#!/usr/bin/env python3
"""
Database Manager
LoanFlow Personal Loan Management System

This module manages all database operations including:
- Connection management and pooling
- Query execution and transaction handling
- Database schema management
- Data validation and sanitization
- Backup and recovery operations
- Performance monitoring and optimization
"""

import logging
import mysql.connector
from mysql.connector import pooling, Error
import json
import hashlib
from typing import Dict, List, Optional, Any, Tuple
from datetime import datetime, timedelta
import os
import threading
import time
from contextlib import contextmanager

class DatabaseManager:
    def __init__(self):
        self.logger = logging.getLogger(__name__)
        self.connection_pool = None
        self.status = 'initializing'
        self.lock = threading.Lock()
        
        # Database configuration
        self.config = {
            'host': os.getenv('DB_HOST', 'localhost'),
            'port': int(os.getenv('DB_PORT', '3306')),
            'database': os.getenv('DB_NAME', 'loanflow'),
            'user': os.getenv('DB_USER', 'root'),
            'password': os.getenv('DB_PASSWORD', ''),
            'charset': 'utf8mb4',
            'collation': 'utf8mb4_unicode_ci',
            'autocommit': True,
            'pool_name': 'loanflow_pool',
            'pool_size': 10,
            'pool_reset_session': True,
            'connect_timeout': 30,
            'sql_mode': 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'
        }
        
        # Performance metrics
        self.metrics = {
            'queries_executed': 0,
            'transactions_completed': 0,
            'connection_errors': 0,
            'query_errors': 0,
            'avg_query_time': 0,
            'active_connections': 0
        }
        
        # Query cache
        self.query_cache = {}
        self.cache_ttl = 300  # 5 minutes
    
    def initialize(self):
        """Initialize database connection and setup"""
        try:
            self.logger.info("Initializing Database Manager...")
            
            # Create connection pool
            self._create_connection_pool()
            
            # Verify database connection
            self._verify_connection()
            
            # Setup database schema
            self._setup_database_schema()
            
            # Initialize monitoring
            self._initialize_monitoring()
            
            self.status = 'healthy'
            self.logger.info("Database Manager initialized successfully")
            
        except Exception as e:
            self.logger.error(f"Database Manager initialization failed: {str(e)}")
            self.status = 'error'
            raise
    
    def shutdown(self):
        """Shutdown database connections"""
        try:
            self.logger.info("Shutting down Database Manager...")
            
            if self.connection_pool:
                # Close all connections in pool
                for _ in range(self.config['pool_size']):
                    try:
                        conn = self.connection_pool.get_connection()
                        conn.close()
                    except:
                        pass
            
            self.status = 'stopped'
            self.logger.info("Database Manager shutdown complete")
            
        except Exception as e:
            self.logger.error(f"Database shutdown error: {str(e)}")
    
    def get_status(self) -> str:
        """Get database manager status"""
        return self.status
    
    def get_metrics(self) -> Dict:
        """Get database performance metrics"""
        return {
            **self.metrics,
            'pool_size': self.config['pool_size'],
            'status': self.status,
            'last_updated': datetime.now().isoformat()
        }
    
    # Connection Management
    def _create_connection_pool(self):
        """Create database connection pool"""
        try:
            self.connection_pool = pooling.MySQLConnectionPool(
                pool_name=self.config['pool_name'],
                pool_size=self.config['pool_size'],
                pool_reset_session=self.config['pool_reset_session'],
                host=self.config['host'],
                port=self.config['port'],
                database=self.config['database'],
                user=self.config['user'],
                password=self.config['password'],
                charset=self.config['charset'],
                collation=self.config['collation'],
                autocommit=self.config['autocommit'],
                connect_timeout=self.config['connect_timeout'],
                sql_mode=self.config['sql_mode']
            )
            
            self.logger.info(f"Database connection pool created with {self.config['pool_size']} connections")
            
        except Error as e:
            self.logger.error(f"Connection pool creation failed: {str(e)}")
            raise
    
    @contextmanager
    def get_connection(self):
        """Get database connection from pool"""
        connection = None
        try:
            connection = self.connection_pool.get_connection()
            self.metrics['active_connections'] += 1
            yield connection
            
        except Error as e:
            self.metrics['connection_errors'] += 1
            self.logger.error(f"Database connection error: {str(e)}")
            raise
            
        finally:
            if connection and connection.is_connected():
                connection.close()
                self.metrics['active_connections'] -= 1
    
    def _verify_connection(self):
        """Verify database connection"""
        try:
            with self.get_connection() as conn:
                cursor = conn.cursor()
                cursor.execute("SELECT 1")
                result = cursor.fetchone()
                cursor.close()
                
                if result[0] != 1:
                    raise Exception("Database connection verification failed")
                    
                self.logger.info("Database connection verified successfully")
                
        except Exception as e:
            self.logger.error(f"Database connection verification failed: {str(e)}")
            raise
    
    # Query Execution
    def execute_query(self, query: str, params: Tuple = None, fetch: bool = True) -> Optional[List[Dict]]:
        """Execute database query"""
        start_time = time.time()
        
        try:
            with self.lock:
                self.metrics['queries_executed'] += 1
            
            # Check cache first for SELECT queries
            if query.strip().upper().startswith('SELECT') and params:
                cache_key = self._generate_cache_key(query, params)
                cached_result = self._get_cached_result(cache_key)
                if cached_result is not None:
                    return cached_result
            
            with self.get_connection() as conn:
                cursor = conn.cursor(dictionary=True)
                
                if params:
                    cursor.execute(query, params)
                else:
                    cursor.execute(query)
                
                result = None
                if fetch:
                    result = cursor.fetchall()
                    
                    # Cache SELECT results
                    if query.strip().upper().startswith('SELECT') and params:
                        self._cache_result(cache_key, result)
                
                # Commit if not autocommit
                if not self.config['autocommit']:
                    conn.commit()
                
                cursor.close()
                
                # Update metrics
                query_time = time.time() - start_time
                self._update_query_metrics(query_time)
                
                return result
                
        except Error as e:
            self.metrics['query_errors'] += 1
            self.logger.error(f"Query execution error: {str(e)}")
            self.logger.error(f"Query: {query}")
            self.logger.error(f"Params: {params}")
            raise
    
    def execute_many(self, query: str, params_list: List[Tuple]) -> bool:
        """Execute query with multiple parameter sets"""
        try:
            with self.get_connection() as conn:
                cursor = conn.cursor()
                cursor.executemany(query, params_list)
                
                if not self.config['autocommit']:
                    conn.commit()
                
                cursor.close()
                
                self.metrics['queries_executed'] += len(params_list)
                return True
                
        except Error as e:
            self.metrics['query_errors'] += 1
            self.logger.error(f"Batch query execution error: {str(e)}")
            raise
    
    @contextmanager
    def transaction(self):
        """Database transaction context manager"""
        connection = None
        try:
            connection = self.connection_pool.get_connection()
            connection.autocommit = False
            
            yield connection
            
            connection.commit()
            self.metrics['transactions_completed'] += 1
            
        except Exception as e:
            if connection:
                connection.rollback()
            self.logger.error(f"Transaction error: {str(e)}")
            raise
            
        finally:
            if connection and connection.is_connected():
                connection.autocommit = True
                connection.close()
    
    # Schema Management
    def _setup_database_schema(self):
        """Setup database schema and tables"""
        try:
            self.logger.info("Setting up database schema...")
            
            # Create tables
            self._create_users_table()
            self._create_loan_applications_table()
            self._create_loans_table()
            self._create_payments_table()
            self._create_documents_table()
            self._create_notifications_table()
            self._create_audit_log_table()
            self._create_system_settings_table()
            self._create_ai_learning_table()
            self._create_business_metrics_table()
            
            # Create indexes
            self._create_indexes()
            
            # Insert default data
            self._insert_default_data()
            
            self.logger.info("Database schema setup complete")
            
        except Exception as e:
            self.logger.error(f"Schema setup error: {str(e)}")
            raise
    
    def _create_users_table(self):
        """Create users table"""
        query = """
            CREATE TABLE IF NOT EXISTS users (
                id VARCHAR(36) PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                phone VARCHAR(20),
                role ENUM('customer', 'admin', 'agent') DEFAULT 'customer',
                status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                email_verified BOOLEAN DEFAULT FALSE,
                phone_verified BOOLEAN DEFAULT FALSE,
                two_factor_enabled BOOLEAN DEFAULT FALSE,
                two_factor_secret VARCHAR(32),
                last_login DATETIME,
                login_attempts INT DEFAULT 0,
                locked_until DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_email (email),
                INDEX idx_role (role),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """
        
        self.execute_query(query, fetch=False)
    
    def _create_loan_applications_table(self):
        """Create loan applications table"""
        query = """
            CREATE TABLE IF NOT EXISTS loan_applications (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36),
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                date_of_birth DATE,
                ssn_hash VARCHAR(64),
                address TEXT,
                city VARCHAR(100),
                state VARCHAR(50),
                zip_code VARCHAR(10),
                employment_status VARCHAR(50),
                employer_name VARCHAR(200),
                job_title VARCHAR(100),
                employment_duration INT,
                monthly_income DECIMAL(12,2),
                additional_income DECIMAL(12,2),
                loan_amount DECIMAL(12,2) NOT NULL,
                loan_purpose VARCHAR(100),
                credit_score INT,
                existing_debts DECIMAL(12,2),
                bank_account_verified BOOLEAN DEFAULT FALSE,
                identity_verified BOOLEAN DEFAULT FALSE,
                income_verified BOOLEAN DEFAULT FALSE,
                status ENUM('pending', 'under_review', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
                decision_data JSON,
                ai_risk_score DECIMAL(5,2),
                ai_recommendation TEXT,
                assigned_agent VARCHAR(36),
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_status (status),
                INDEX idx_email (email),
                INDEX idx_created_at (created_at),
                INDEX idx_loan_amount (loan_amount)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """
        
        self.execute_query(query, fetch=False)
    
    def _create_loans_table(self):
        """Create loans table"""
        query = """
            CREATE TABLE IF NOT EXISTS loans (
                id VARCHAR(36) PRIMARY KEY,
                application_id VARCHAR(36) NOT NULL,
                user_id VARCHAR(36),
                customer_email VARCHAR(255) NOT NULL,
                loan_number VARCHAR(50) UNIQUE,
                principal_amount DECIMAL(12,2) NOT NULL,
                interest_rate DECIMAL(5,4) NOT NULL,
                term_months INT NOT NULL,
                monthly_payment DECIMAL(10,2) NOT NULL,
                current_balance DECIMAL(12,2),
                total_paid DECIMAL(12,2) DEFAULT 0,
                next_payment_date DATE,
                last_payment_date DATE,
                status ENUM('active', 'paid_off', 'defaulted', 'charged_off') DEFAULT 'active',
                origination_fee DECIMAL(8,2) DEFAULT 0,
                late_fee_rate DECIMAL(5,4) DEFAULT 0,
                grace_period_days INT DEFAULT 10,
                autopay_enabled BOOLEAN DEFAULT FALSE,
                autopay_account_id VARCHAR(36),
                risk_grade CHAR(1),
                servicing_notes TEXT,
                paid_off_date DATETIME,
                charged_off_date DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (application_id) REFERENCES loan_applications(id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_status (status),
                INDEX idx_customer_email (customer_email),
                INDEX idx_next_payment_date (next_payment_date),
                INDEX idx_loan_number (loan_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """
        
        self.execute_query(query, fetch=False)
    
    def _create_payments_table(self):
        """Create payments table"""
        query = """
            CREATE TABLE IF NOT EXISTS payments (
                id VARCHAR(36) PRIMARY KEY,
                loan_id VARCHAR(36) NOT NULL,
                payment_number INT,
                amount DECIMAL(10,2) NOT NULL,
                principal_amount DECIMAL(10,2),
                interest_amount DECIMAL(10,2),
                fee_amount DECIMAL(10,2) DEFAULT 0,
                payment_method ENUM('ach', 'card', 'check', 'wire', 'cash') NOT NULL,
                payment_type ENUM('regular', 'extra', 'payoff', 'late_fee') DEFAULT 'regular',
                transaction_id VARCHAR(100),
                gateway_response JSON,
                status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
                scheduled_date DATE,
                processed_date DATETIME,
                due_date DATE,
                late_fee_assessed DECIMAL(8,2) DEFAULT 0,
                is_autopay BOOLEAN DEFAULT FALSE,
                failure_reason TEXT,
                refund_amount DECIMAL(10,2),
                refund_date DATETIME,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
                INDEX idx_loan_id (loan_id),
                INDEX idx_status (status),
                INDEX idx_scheduled_date (scheduled_date),
                INDEX idx_processed_date (processed_date),
                INDEX idx_transaction_id (transaction_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """
        
        self.execute_query(query, fetch=False)
    
    def _create_documents_table(self):
        """Create documents table"""
        query = """
            CREATE TABLE IF NOT EXISTS documents (
                id VARCHAR(36) PRIMARY KEY,
                related_id VARCHAR(36),
                related_type ENUM('application', 'loan', 'user', 'payment') NOT NULL,
                document_type VARCHAR(50) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size INT,
                mime_type VARCHAR(100),
                file_hash VARCHAR(64),
                is_signed BOOLEAN DEFAULT FALSE,
                signature_data JSON,
                is_encrypted BOOLEAN DEFAULT FALSE,
                encryption_key_id VARCHAR(36),
                access_level ENUM('public', 'customer', 'internal', 'admin') DEFAULT 'customer',
                retention_date DATE,
                uploaded_by VARCHAR(36),
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_related (related_id, related_type),
                INDEX idx_document_type (document_type),
                INDEX idx_access_level (access_level),
                INDEX idx_file_hash (file_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """
        
        self.execute_query(query, fetch=False)
    
    def _create_notifications_table(self):
        """Create notifications table"""
        query = """
            CREATE TABLE IF NOT EXISTS notifications (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36),
                recipient_email VARCHAR(255),
                recipient_phone VARCHAR(20),
                type VARCHAR(50) NOT NULL,
                channel ENUM('email', 'sms', 'push', 'in_app') NOT NULL,
                subject VARCHAR(255),
                message TEXT NOT NULL,
                template_id VARCHAR(50),
                template_data JSON,
                status ENUM('pending', 'sent', 'delivered', 'failed', 'bounced') DEFAULT 'pending',
                priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
                scheduled_at DATETIME,
                sent_at DATETIME,
                delivered_at DATETIME,
                opened_at DATETIME,
                clicked_at DATETIME,
                error_message TEXT,
                retry_count INT DEFAULT 0,
                max_retries INT DEFAULT 3,
                external_id VARCHAR(100),
                metadata JSON,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_status (status),
                INDEX idx_type (type),
                INDEX idx_scheduled_at (scheduled_at),
                INDEX idx_recipient_email (recipient_email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """
        
        self.execute_query(query, fetch=False)
    
    def _create_audit_log_table(self):
        """Create audit log table"""
        query = """
            CREATE TABLE IF NOT EXISTS audit_log (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(36),
                action VARCHAR(100) NOT NULL,
                resource_type VARCHAR(50) NOT NULL,
                resource_id VARCHAR(36),
                old_values JSON,
                new_values JSON,
                ip_address VARCHAR(45),
                user_agent TEXT,
                session_id VARCHAR(100),
                request_id VARCHAR(100),
                severity ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
                description TEXT,
                metadata JSON,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_resource (resource_type, resource_id),
                INDEX idx_created_at (created_at),
                INDEX idx_severity (severity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """
        
        self.execute_query(query, fetch=False)
    
    def _create_system_settings_table(self):
        """Create system settings table"""
        query = """
            CREATE TABLE IF NOT EXISTS system_settings (
                id VARCHAR(100) PRIMARY KEY,
                category VARCHAR(50) NOT NULL,
                name VARCHAR(100) NOT NULL,
                value TEXT,
                data_type ENUM('string', 'integer', 'float', 'boolean', 'json') DEFAULT 'string',
                description TEXT,
                is_encrypted BOOLEAN DEFAULT FALSE,
                is_public BOOLEAN DEFAULT FALSE,
                validation_rules JSON,
                updated_by VARCHAR(36),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_category (category),
                INDEX idx_name (name),
                INDEX idx_is_public (is_public)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """
        
        self.execute_query(query, fetch=False)
    
    def _create_ai_learning_table(self):
        """Create AI learning table"""
        query = """
            CREATE TABLE IF NOT EXISTS ai_learning_requests (
                id VARCHAR(36) PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                content_type ENUM('document', 'email_template', 'faq', 'policy') NOT NULL,
                content_data JSON NOT NULL,
                file_path VARCHAR(500),
                priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
                processing_progress INT DEFAULT 0,
                ai_analysis JSON,
                knowledge_base_entries JSON,
                error_message TEXT,
                requested_by VARCHAR(36) NOT NULL,
                processed_by VARCHAR(36),
                estimated_completion DATETIME,
                completed_at DATETIME,
                metadata JSON,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (requested_by) REFERENCES users(id),
                FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_status (status),
                INDEX idx_priority (priority),
                INDEX idx_content_type (content_type),
                INDEX idx_requested_by (requested_by),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """
        
        self.execute_query(query, fetch=False)
    
    def _create_business_metrics_table(self):
        """Create business metrics table"""
        query = """
            CREATE TABLE IF NOT EXISTS business_metrics (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                metric_name VARCHAR(100) NOT NULL,
                metric_category VARCHAR(50) NOT NULL,
                metric_value DECIMAL(15,4),
                metric_data JSON,
                period_type ENUM('hourly', 'daily', 'weekly', 'monthly', 'yearly') NOT NULL,
                period_start DATETIME NOT NULL,
                period_end DATETIME NOT NULL,
                calculated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                metadata JSON,
                INDEX idx_metric_name (metric_name),
                INDEX idx_category (metric_category),
                INDEX idx_period (period_type, period_start),
                UNIQUE KEY unique_metric_period (metric_name, period_type, period_start)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """
        
        self.execute_query(query, fetch=False)
    
    def _create_indexes(self):
        """Create additional database indexes for performance"""
        indexes = [
            "CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at)",
            "CREATE INDEX IF NOT EXISTS idx_applications_ai_score ON loan_applications(ai_risk_score)",
            "CREATE INDEX IF NOT EXISTS idx_loans_balance ON loans(current_balance)",
            "CREATE INDEX IF NOT EXISTS idx_payments_amount ON payments(amount)",
            "CREATE INDEX IF NOT EXISTS idx_notifications_sent_at ON notifications(sent_at)",
            "CREATE INDEX IF NOT EXISTS idx_audit_log_ip ON audit_log(ip_address)"
        ]
        
        for index_query in indexes:
            try:
                self.execute_query(index_query, fetch=False)
            except Exception as e:
                self.logger.warning(f"Index creation warning: {str(e)}")
    
    def _insert_default_data(self):
        """Insert default system data"""
        try:
            # Default system settings
            default_settings = [
                ('system.name', 'general', 'LoanFlow', 'string', 'System name'),
                ('system.version', 'general', '1.0.0', 'string', 'System version'),
                ('loan.min_amount', 'lending', '1000', 'integer', 'Minimum loan amount'),
                ('loan.max_amount', 'lending', '50000', 'integer', 'Maximum loan amount'),
                ('loan.default_rate', 'lending', '0.125', 'float', 'Default interest rate'),
                ('security.session_timeout', 'security', '3600', 'integer', 'Session timeout in seconds'),
                ('security.max_login_attempts', 'security', '5', 'integer', 'Maximum login attempts'),
                ('notification.email_enabled', 'notifications', 'true', 'boolean', 'Enable email notifications'),
                ('ai.risk_threshold', 'ai', '0.7', 'float', 'AI risk assessment threshold')
            ]
            
            for setting_id, category, value, data_type, description in default_settings:
                query = """
                    INSERT IGNORE INTO system_settings 
                    (id, category, name, value, data_type, description, is_public)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                """
                
                name = setting_id.split('.')[1]
                is_public = category in ['general']
                
                self.execute_query(query, (
                    setting_id, category, name, value, data_type, description, is_public
                ), fetch=False)
            
            self.logger.info("Default system data inserted")
            
        except Exception as e:
            self.logger.error(f"Default data insertion error: {str(e)}")
    
    # Cache Management
    def _generate_cache_key(self, query: str, params: Tuple) -> str:
        """Generate cache key for query"""
        cache_string = f"{query}:{str(params)}"
        return hashlib.md5(cache_string.encode()).hexdigest()
    
    def _get_cached_result(self, cache_key: str) -> Optional[List[Dict]]:
        """Get cached query result"""
        try:
            if cache_key in self.query_cache:
                cached_data = self.query_cache[cache_key]
                if datetime.now() - cached_data['timestamp'] < timedelta(seconds=self.cache_ttl):
                    return cached_data['result']
                else:
                    # Remove expired cache
                    del self.query_cache[cache_key]
            
            return None
            
        except Exception as e:
            self.logger.error(f"Cache retrieval error: {str(e)}")
            return None
    
    def _cache_result(self, cache_key: str, result: List[Dict]):
        """Cache query result"""
        try:
            self.query_cache[cache_key] = {
                'result': result,
                'timestamp': datetime.now()
            }
            
            # Limit cache size
            if len(self.query_cache) > 1000:
                # Remove oldest entries
                oldest_keys = sorted(
                    self.query_cache.keys(),
                    key=lambda k: self.query_cache[k]['timestamp']
                )[:100]
                
                for key in oldest_keys:
                    del self.query_cache[key]
                    
        except Exception as e:
            self.logger.error(f"Cache storage error: {str(e)}")
    
    def clear_cache(self):
        """Clear query cache"""
        self.query_cache.clear()
        self.logger.info("Query cache cleared")
    
    # Monitoring and Metrics
    def _initialize_monitoring(self):
        """Initialize database monitoring"""
        try:
            # Start monitoring thread
            monitoring_thread = threading.Thread(target=self._monitor_performance, daemon=True)
            monitoring_thread.start()
            
            self.logger.info("Database monitoring initialized")
            
        except Exception as e:
            self.logger.error(f"Monitoring initialization error: {str(e)}")
    
    def _monitor_performance(self):
        """Monitor database performance"""
        while self.status == 'healthy':
            try:
                # Monitor connection pool
                self._check_connection_pool_health()
                
                # Monitor query performance
                self._analyze_slow_queries()
                
                # Clean up old cache entries
                self._cleanup_cache()
                
                # Sleep for monitoring interval
                time.sleep(60)  # Monitor every minute
                
            except Exception as e:
                self.logger.error(f"Performance monitoring error: {str(e)}")
                time.sleep(60)
    
    def _check_connection_pool_health(self):
        """Check connection pool health"""
        try:
            # Test connection
            with self.get_connection() as conn:
                cursor = conn.cursor()
                cursor.execute("SELECT CONNECTION_ID()")
                cursor.fetchone()
                cursor.close()
                
        except Exception as e:
            self.logger.error(f"Connection pool health check failed: {str(e)}")
            self.status = 'unhealthy'
    
    def _analyze_slow_queries(self):
        """Analyze slow queries"""
        try:
            # Check for slow queries (simplified)
            if self.metrics['avg_query_time'] > 1.0:  # 1 second threshold
                self.logger.warning(f"Slow query detected: avg time {self.metrics['avg_query_time']:.2f}s")
                
        except Exception as e:
            self.logger.error(f"Slow query analysis error: {str(e)}")
    
    def _cleanup_cache(self):
        """Clean up expired cache entries"""
        try:
            current_time = datetime.now()
            expired_keys = []
            
            for key, data in self.query_cache.items():
                if current_time - data['timestamp'] > timedelta(seconds=self.cache_ttl):
                    expired_keys.append(key)
            
            for key in expired_keys:
                del self.query_cache[key]
                
        except Exception as e:
            self.logger.error(f"Cache cleanup error: {str(e)}")
    
    def _update_query_metrics(self, query_time: float):
        """Update query performance metrics"""
        try:
            # Update average query time (simple moving average)
            if self.metrics['avg_query_time'] == 0:
                self.metrics['avg_query_time'] = query_time
            else:
                self.metrics['avg_query_time'] = (
                    self.metrics['avg_query_time'] * 0.9 + query_time * 0.1
                )
                
        except Exception as e:
            self.logger.error(f"Metrics update error: {str(e)}")
    
    # Backup and Recovery
    def create_backup(self, backup_path: str) -> bool:
        """Create database backup"""
        try:
            self.logger.info(f"Creating database backup to {backup_path}")
            
            # Use mysqldump for backup (simplified)
            import subprocess
            
            cmd = [
                'mysqldump',
                f'--host={self.config["host"]}',
                f'--port={self.config["port"]}',
                f'--user={self.config["user"]}',
                f'--password={self.config["password"]}',
                '--single-transaction',
                '--routines',
                '--triggers',
                self.config['database']
            ]
            
            with open(backup_path, 'w') as backup_file:
                result = subprocess.run(cmd, stdout=backup_file, stderr=subprocess.PIPE, text=True)
                
                if result.returncode == 0:
                    self.logger.info("Database backup completed successfully")
                    return True
                else:
                    self.logger.error(f"Backup failed: {result.stderr}")
                    return False
                    
        except Exception as e:
            self.logger.error(f"Backup creation error: {str(e)}")
            return False
    
    def restore_backup(self, backup_path: str) -> bool:
        """Restore database from backup"""
        try:
            self.logger.info(f"Restoring database from {backup_path}")
            
            # Use mysql client for restore (simplified)
            import subprocess
            
            cmd = [
                'mysql',
                f'--host={self.config["host"]}',
                f'--port={self.config["port"]}',
                f'--user={self.config["user"]}',
                f'--password={self.config["password"]}',
                self.config['database']
            ]
            
            with open(backup_path, 'r') as backup_file:
                result = subprocess.run(cmd, stdin=backup_file, stderr=subprocess.PIPE, text=True)
                
                if result.returncode == 0:
                    self.logger.info("Database restore completed successfully")
                    return True
                else:
                    self.logger.error(f"Restore failed: {result.stderr}")
                    return False
                    
        except Exception as e:
            self.logger.error(f"Backup restore error: {str(e)}")
            return False
    
    # Utility Methods
    def health_check(self) -> Dict:
        """Perform comprehensive health check"""
        try:
            health_status = {
                'status': self.status,
                'connection_pool': 'healthy',
                'query_performance': 'good',
                'cache_size': len(self.query_cache),
                'metrics': self.get_metrics(),
                'timestamp': datetime.now().isoformat()
            }
            
            # Test database connection
            try:
                with self.get_connection() as conn:
                    cursor = conn.cursor()
                    cursor.execute("SELECT 1")
                    cursor.fetchone()
                    cursor.close()
            except:
                health_status['connection_pool'] = 'unhealthy'
                health_status['status'] = 'unhealthy'
            
            # Check query performance
            if self.metrics['avg_query_time'] > 2.0:
                health_status['query_performance'] = 'slow'
            elif self.metrics['avg_query_time'] > 5.0:
                health_status['query_performance'] = 'poor'
                health_status['status'] = 'degraded'
            
            return health_status
            
        except Exception as e:
            self.logger.error(f"Health check error: {str(e)}")
            return {
                'status': 'error',
                'error': str(e),
                'timestamp': datetime.now().isoformat()
            }
    
    def optimize_tables(self) -> bool:
        """Optimize database tables"""
        try:
            self.logger.info("Optimizing database tables...")
            
            # Get all tables
            tables_query = "SHOW TABLES"
            tables = self.execute_query(tables_query)
            
            for table in tables:
                table_name = list(table.values())[0]
                optimize_query = f"OPTIMIZE TABLE {table_name}"
                self.execute_query(optimize_query, fetch=False)
            
            self.logger.info("Database optimization completed")
            return True
            
        except Exception as e:
            self.logger.error(f"Table optimization error: {str(e)}")
            return False
    
    def get_table_stats(self) -> Dict:
        """Get database table statistics"""
        try:
            stats_query = """
                SELECT 
                    table_name,
                    table_rows,
                    data_length,
                    index_length,
                    (data_length + index_length) as total_size
                FROM information_schema.tables 
                WHERE table_schema = %s
                ORDER BY total_size DESC
            """
            
            results = self.execute_query(stats_query, (self.config['database'],))
            
            return {
                'tables': results,
                'total_tables': len(results),
                'timestamp': datetime.now().isoformat()
            }
            
        except Exception as e:
            self.logger.error(f"Table stats error: {str(e)}")
            return {'error': str(e)}

if __name__ == "__main__":
    # Example usage and testing
    import os
    import sys
    
    # Setup logging
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    
    # Initialize database manager
    db_manager = DatabaseManager()
    
    try:
        # Initialize database
        db_manager.initialize()
        
        # Perform health check
        health = db_manager.health_check()
        print(f"Database Health: {health}")
        
        # Get metrics
        metrics = db_manager.get_metrics()
        print(f"Database Metrics: {metrics}")
        
        # Test query
        result = db_manager.execute_query("SELECT COUNT(*) as user_count FROM users")
        print(f"User count: {result}")
        
    except Exception as e:
        print(f"Database manager test failed: {str(e)}")
        sys.exit(1)
    
    finally:
        # Shutdown
        db_manager.shutdown()