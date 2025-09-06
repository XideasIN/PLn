#!/usr/bin/env python3
"""
Logger Manager
LoanFlow Personal Loan Management System

This module provides comprehensive logging management including:
- Centralized logging configuration
- Multiple log handlers (file, console, database, remote)
- Log rotation and archival
- Structured logging with JSON format
- Performance logging
- Security audit logging
- Error tracking and alerting
- Log analysis and reporting
"""

import logging
import logging.handlers
import json
import os
import sys
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Any
from pathlib import Path
import threading
import queue
import time
from dataclasses import dataclass, asdict
import traceback
import hashlib
import gzip
import shutil

@dataclass
class LogEntry:
    """Structured log entry"""
    timestamp: datetime
    level: str
    logger_name: str
    message: str
    module: str
    function: str
    line_number: int
    thread_id: int
    process_id: int
    user_id: Optional[str] = None
    session_id: Optional[str] = None
    request_id: Optional[str] = None
    ip_address: Optional[str] = None
    user_agent: Optional[str] = None
    extra_data: Optional[Dict] = None
    exception_info: Optional[str] = None
    stack_trace: Optional[str] = None

class DatabaseLogHandler(logging.Handler):
    """Custom log handler for database storage"""
    
    def __init__(self, database_manager, table_name='system_logs'):
        super().__init__()
        self.database_manager = database_manager
        self.table_name = table_name
        self.log_queue = queue.Queue(maxsize=1000)
        self.worker_thread = None
        self.shutdown_event = threading.Event()
        self.start_worker()
    
    def start_worker(self):
        """Start background worker thread"""
        self.worker_thread = threading.Thread(target=self._worker_loop, daemon=True)
        self.worker_thread.start()
    
    def stop_worker(self):
        """Stop background worker thread"""
        self.shutdown_event.set()
        if self.worker_thread and self.worker_thread.is_alive():
            self.worker_thread.join(timeout=5)
    
    def emit(self, record):
        """Emit log record to queue"""
        try:
            if not self.log_queue.full():
                self.log_queue.put_nowait(record)
        except queue.Full:
            # Drop log if queue is full to prevent blocking
            pass
    
    def _worker_loop(self):
        """Background worker to process log queue"""
        while not self.shutdown_event.is_set():
            try:
                # Get log record with timeout
                try:
                    record = self.log_queue.get(timeout=1)
                except queue.Empty:
                    continue
                
                # Process the log record
                self._write_to_database(record)
                self.log_queue.task_done()
                
            except Exception as e:
                # Avoid infinite recursion by not logging this error
                print(f"Database log handler error: {e}", file=sys.stderr)
    
    def _write_to_database(self, record):
        """Write log record to database"""
        try:
            if not self.database_manager:
                return
            
            # Format log entry
            log_data = {
                'timestamp': datetime.fromtimestamp(record.created),
                'level': record.levelname,
                'logger_name': record.name,
                'message': record.getMessage(),
                'module': record.module if hasattr(record, 'module') else '',
                'function': record.funcName,
                'line_number': record.lineno,
                'thread_id': record.thread,
                'process_id': record.process,
                'pathname': record.pathname,
                'exception_info': self.format(record) if record.exc_info else None
            }
            
            # Add extra fields if available
            if hasattr(record, 'user_id'):
                log_data['user_id'] = record.user_id
            if hasattr(record, 'session_id'):
                log_data['session_id'] = record.session_id
            if hasattr(record, 'request_id'):
                log_data['request_id'] = record.request_id
            if hasattr(record, 'ip_address'):
                log_data['ip_address'] = record.ip_address
            if hasattr(record, 'extra_data'):
                log_data['extra_data'] = json.dumps(record.extra_data)
            
            # Insert into database
            query = f"""
                INSERT INTO {self.table_name} 
                (timestamp, level, logger_name, message, module, function, line_number, 
                 thread_id, process_id, pathname, user_id, session_id, request_id, 
                 ip_address, extra_data, exception_info)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            
            values = (
                log_data['timestamp'], log_data['level'], log_data['logger_name'],
                log_data['message'], log_data['module'], log_data['function'],
                log_data['line_number'], log_data['thread_id'], log_data['process_id'],
                log_data['pathname'], log_data.get('user_id'), log_data.get('session_id'),
                log_data.get('request_id'), log_data.get('ip_address'),
                log_data.get('extra_data'), log_data.get('exception_info')
            )
            
            self.database_manager.execute_query(query, values)
            
        except Exception as e:
            print(f"Database log write error: {e}", file=sys.stderr)

class JSONFormatter(logging.Formatter):
    """JSON log formatter"""
    
    def format(self, record):
        """Format log record as JSON"""
        try:
            # Create log entry
            log_entry = {
                'timestamp': datetime.fromtimestamp(record.created).isoformat(),
                'level': record.levelname,
                'logger': record.name,
                'message': record.getMessage(),
                'module': getattr(record, 'module', ''),
                'function': record.funcName,
                'line': record.lineno,
                'thread': record.thread,
                'process': record.process,
                'pathname': record.pathname
            }
            
            # Add exception info if present
            if record.exc_info:
                log_entry['exception'] = self.formatException(record.exc_info)
            
            # Add extra fields
            for key, value in record.__dict__.items():
                if key not in ['name', 'msg', 'args', 'levelname', 'levelno', 'pathname',
                              'filename', 'module', 'lineno', 'funcName', 'created',
                              'msecs', 'relativeCreated', 'thread', 'threadName',
                              'processName', 'process', 'exc_info', 'exc_text', 'stack_info']:
                    log_entry[key] = value
            
            return json.dumps(log_entry, default=str)
            
        except Exception as e:
            # Fallback to standard formatting
            return super().format(record)

class SecurityAuditLogger:
    """Security audit logging"""
    
    def __init__(self, logger_manager):
        self.logger_manager = logger_manager
        self.logger = logging.getLogger('security_audit')
        
        # Security event types
        self.event_types = {
            'LOGIN_SUCCESS': 'User login successful',
            'LOGIN_FAILURE': 'User login failed',
            'LOGOUT': 'User logout',
            'PASSWORD_CHANGE': 'Password changed',
            'ACCOUNT_LOCKED': 'Account locked due to failed attempts',
            'PERMISSION_DENIED': 'Access denied',
            'ADMIN_ACTION': 'Administrative action performed',
            'DATA_ACCESS': 'Sensitive data accessed',
            'DATA_MODIFICATION': 'Data modified',
            'SUSPICIOUS_ACTIVITY': 'Suspicious activity detected',
            'SECURITY_VIOLATION': 'Security policy violation'
        }
    
    def log_security_event(self, event_type: str, user_id: str = None, 
                          ip_address: str = None, details: Dict = None,
                          session_id: str = None, request_id: str = None):
        """Log security event"""
        try:
            message = self.event_types.get(event_type, f'Security event: {event_type}')
            
            extra_data = {
                'event_type': event_type,
                'user_id': user_id,
                'ip_address': ip_address,
                'session_id': session_id,
                'request_id': request_id,
                'details': details or {},
                'timestamp': datetime.now().isoformat()
            }
            
            self.logger.warning(message, extra={
                'user_id': user_id,
                'session_id': session_id,
                'request_id': request_id,
                'ip_address': ip_address,
                'extra_data': extra_data
            })
            
        except Exception as e:
            self.logger.error(f"Security audit logging error: {str(e)}")

class PerformanceLogger:
    """Performance logging and monitoring"""
    
    def __init__(self, logger_manager):
        self.logger_manager = logger_manager
        self.logger = logging.getLogger('performance')
        self.active_timers = {}
    
    def start_timer(self, operation_id: str, operation_name: str, 
                   user_id: str = None, session_id: str = None):
        """Start performance timer"""
        try:
            timer_data = {
                'operation_name': operation_name,
                'start_time': time.time(),
                'user_id': user_id,
                'session_id': session_id
            }
            
            self.active_timers[operation_id] = timer_data
            
        except Exception as e:
            self.logger.error(f"Performance timer start error: {str(e)}")
    
    def end_timer(self, operation_id: str, success: bool = True, 
                 error_message: str = None, additional_data: Dict = None):
        """End performance timer and log results"""
        try:
            if operation_id not in self.active_timers:
                self.logger.warning(f"Timer not found: {operation_id}")
                return
            
            timer_data = self.active_timers.pop(operation_id)
            end_time = time.time()
            duration = end_time - timer_data['start_time']
            
            log_data = {
                'operation_id': operation_id,
                'operation_name': timer_data['operation_name'],
                'duration_seconds': duration,
                'duration_ms': duration * 1000,
                'success': success,
                'error_message': error_message,
                'user_id': timer_data.get('user_id'),
                'session_id': timer_data.get('session_id'),
                'additional_data': additional_data or {}
            }
            
            # Log based on duration and success
            if not success:
                level = logging.ERROR
                message = f"Operation failed: {timer_data['operation_name']} ({duration:.3f}s)"
            elif duration > 5.0:  # Slow operation threshold
                level = logging.WARNING
                message = f"Slow operation: {timer_data['operation_name']} ({duration:.3f}s)"
            else:
                level = logging.INFO
                message = f"Operation completed: {timer_data['operation_name']} ({duration:.3f}s)"
            
            self.logger.log(level, message, extra={
                'user_id': timer_data.get('user_id'),
                'session_id': timer_data.get('session_id'),
                'extra_data': log_data
            })
            
        except Exception as e:
            self.logger.error(f"Performance timer end error: {str(e)}")
    
    def log_performance_metric(self, metric_name: str, value: float, 
                              unit: str = 'ms', tags: Dict = None):
        """Log performance metric"""
        try:
            log_data = {
                'metric_name': metric_name,
                'value': value,
                'unit': unit,
                'tags': tags or {},
                'timestamp': datetime.now().isoformat()
            }
            
            self.logger.info(f"Performance metric: {metric_name} = {value} {unit}", 
                           extra={'extra_data': log_data})
            
        except Exception as e:
            self.logger.error(f"Performance metric logging error: {str(e)}")

class LoggerManager:
    """Central logger management system"""
    
    def __init__(self, config_manager=None, database_manager=None):
        self.config_manager = config_manager
        self.database_manager = database_manager
        
        # Configuration
        self.log_config = {
            'level': 'INFO',
            'log_dir': 'logs',
            'max_file_size': 10 * 1024 * 1024,  # 10MB
            'backup_count': 5,
            'console_logging': True,
            'file_logging': True,
            'database_logging': True,
            'json_format': True,
            'compression': True,
            'retention_days': 30
        }
        
        # Handlers
        self.handlers = {}
        self.formatters = {}
        self.loggers = {}
        
        # Special loggers
        self.security_logger = None
        self.performance_logger = None
        
        # Log rotation and cleanup
        self.cleanup_thread = None
        self.cleanup_interval = 3600  # 1 hour
        self.shutdown_event = threading.Event()
        
        # Statistics
        self.log_stats = {
            'total_logs': 0,
            'logs_by_level': {'DEBUG': 0, 'INFO': 0, 'WARNING': 0, 'ERROR': 0, 'CRITICAL': 0},
            'logs_by_logger': {},
            'start_time': datetime.now()
        }
    
    def initialize(self):
        """Initialize logging system"""
        try:
            # Load configuration
            self._load_configuration()
            
            # Create log directory
            self._create_log_directory()
            
            # Setup formatters
            self._setup_formatters()
            
            # Setup handlers
            self._setup_handlers()
            
            # Configure root logger
            self._configure_root_logger()
            
            # Setup special loggers
            self._setup_special_loggers()
            
            # Start cleanup thread
            self._start_cleanup_thread()
            
            # Setup database schema
            self._setup_database_schema()
            
            # Log initialization
            logger = self.get_logger('logger_manager')
            logger.info("Logging system initialized successfully")
            
        except Exception as e:
            print(f"Logger initialization failed: {str(e)}", file=sys.stderr)
            raise
    
    def shutdown(self):
        """Shutdown logging system"""
        try:
            logger = self.get_logger('logger_manager')
            logger.info("Shutting down logging system")
            
            # Stop cleanup thread
            self.shutdown_event.set()
            if self.cleanup_thread and self.cleanup_thread.is_alive():
                self.cleanup_thread.join(timeout=5)
            
            # Close database handler
            if 'database' in self.handlers:
                self.handlers['database'].stop_worker()
            
            # Close all handlers
            for handler in self.handlers.values():
                handler.close()
            
            # Shutdown logging
            logging.shutdown()
            
        except Exception as e:
            print(f"Logger shutdown error: {str(e)}", file=sys.stderr)
    
    def get_logger(self, name: str) -> logging.Logger:
        """Get or create logger"""
        try:
            if name not in self.loggers:
                logger = logging.getLogger(name)
                
                # Add custom methods
                logger.log_security = lambda event_type, **kwargs: self._log_security_event(name, event_type, **kwargs)
                logger.log_performance = lambda operation, duration, **kwargs: self._log_performance(name, operation, duration, **kwargs)
                
                self.loggers[name] = logger
                
                # Update statistics
                self.log_stats['logs_by_logger'][name] = 0
            
            return self.loggers[name]
            
        except Exception as e:
            print(f"Get logger error: {str(e)}", file=sys.stderr)
            return logging.getLogger(name)  # Fallback
    
    def get_security_logger(self) -> SecurityAuditLogger:
        """Get security audit logger"""
        return self.security_logger
    
    def get_performance_logger(self) -> PerformanceLogger:
        """Get performance logger"""
        return self.performance_logger
    
    def get_log_stats(self) -> Dict:
        """Get logging statistics"""
        try:
            uptime = (datetime.now() - self.log_stats['start_time']).total_seconds()
            
            stats = self.log_stats.copy()
            stats['uptime_seconds'] = uptime
            stats['logs_per_second'] = stats['total_logs'] / uptime if uptime > 0 else 0
            stats['timestamp'] = datetime.now().isoformat()
            
            return stats
            
        except Exception as e:
            return {'error': str(e)}
    
    def search_logs(self, query: str = None, level: str = None, 
                   logger_name: str = None, start_time: datetime = None,
                   end_time: datetime = None, limit: int = 100) -> List[Dict]:
        """Search logs in database"""
        try:
            if not self.database_manager:
                return []
            
            # Build query
            conditions = []
            params = []
            
            if query:
                conditions.append("message LIKE %s")
                params.append(f"%{query}%")
            
            if level:
                conditions.append("level = %s")
                params.append(level)
            
            if logger_name:
                conditions.append("logger_name = %s")
                params.append(logger_name)
            
            if start_time:
                conditions.append("timestamp >= %s")
                params.append(start_time)
            
            if end_time:
                conditions.append("timestamp <= %s")
                params.append(end_time)
            
            where_clause = " AND ".join(conditions) if conditions else "1=1"
            
            query_sql = f"""
                SELECT timestamp, level, logger_name, message, module, function,
                       line_number, user_id, session_id, request_id, ip_address,
                       extra_data, exception_info
                FROM system_logs
                WHERE {where_clause}
                ORDER BY timestamp DESC
                LIMIT %s
            """
            
            params.append(limit)
            
            results = self.database_manager.execute_query(query_sql, params)
            
            # Format results
            logs = []
            for row in results:
                log_entry = {
                    'timestamp': row[0].isoformat() if row[0] else None,
                    'level': row[1],
                    'logger_name': row[2],
                    'message': row[3],
                    'module': row[4],
                    'function': row[5],
                    'line_number': row[6],
                    'user_id': row[7],
                    'session_id': row[8],
                    'request_id': row[9],
                    'ip_address': row[10],
                    'extra_data': json.loads(row[11]) if row[11] else None,
                    'exception_info': row[12]
                }
                logs.append(log_entry)
            
            return logs
            
        except Exception as e:
            logger = self.get_logger('logger_manager')
            logger.error(f"Log search error: {str(e)}")
            return []
    
    def export_logs(self, format: str = 'json', **search_params) -> str:
        """Export logs in specified format"""
        try:
            logs = self.search_logs(**search_params)
            
            if format.lower() == 'json':
                return json.dumps(logs, indent=2, default=str)
            elif format.lower() == 'csv':
                # TODO: Implement CSV export
                return "CSV export not implemented yet"
            else:
                raise ValueError(f"Unsupported export format: {format}")
                
        except Exception as e:
            return f"Export error: {str(e)}"
    
    def _load_configuration(self):
        """Load logging configuration"""
        try:
            if self.config_manager:
                logging_config = self.config_manager.get_section('logging')
                if logging_config:
                    self.log_config.update(logging_config)
            
        except Exception as e:
            print(f"Configuration load error: {str(e)}", file=sys.stderr)
    
    def _create_log_directory(self):
        """Create log directory"""
        try:
            log_dir = Path(self.log_config['log_dir'])
            log_dir.mkdir(parents=True, exist_ok=True)
            
        except Exception as e:
            print(f"Log directory creation error: {str(e)}", file=sys.stderr)
    
    def _setup_formatters(self):
        """Setup log formatters"""
        try:
            # Standard formatter
            self.formatters['standard'] = logging.Formatter(
                '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
            )
            
            # Detailed formatter
            self.formatters['detailed'] = logging.Formatter(
                '%(asctime)s - %(name)s - %(levelname)s - %(module)s:%(funcName)s:%(lineno)d - %(message)s'
            )
            
            # JSON formatter
            if self.log_config['json_format']:
                self.formatters['json'] = JSONFormatter()
            
        except Exception as e:
            print(f"Formatter setup error: {str(e)}", file=sys.stderr)
    
    def _setup_handlers(self):
        """Setup log handlers"""
        try:
            # Console handler
            if self.log_config['console_logging']:
                console_handler = logging.StreamHandler(sys.stdout)
                console_handler.setLevel(getattr(logging, self.log_config['level']))
                
                if self.log_config['json_format']:
                    console_handler.setFormatter(self.formatters['json'])
                else:
                    console_handler.setFormatter(self.formatters['standard'])
                
                self.handlers['console'] = console_handler
            
            # File handler with rotation
            if self.log_config['file_logging']:
                log_file = Path(self.log_config['log_dir']) / 'application.log'
                
                file_handler = logging.handlers.RotatingFileHandler(
                    log_file,
                    maxBytes=self.log_config['max_file_size'],
                    backupCount=self.log_config['backup_count']
                )
                file_handler.setLevel(getattr(logging, self.log_config['level']))
                
                if self.log_config['json_format']:
                    file_handler.setFormatter(self.formatters['json'])
                else:
                    file_handler.setFormatter(self.formatters['detailed'])
                
                self.handlers['file'] = file_handler
            
            # Database handler
            if self.log_config['database_logging'] and self.database_manager:
                db_handler = DatabaseLogHandler(self.database_manager)
                db_handler.setLevel(logging.INFO)  # Only log INFO and above to database
                self.handlers['database'] = db_handler
            
        except Exception as e:
            print(f"Handler setup error: {str(e)}", file=sys.stderr)
    
    def _configure_root_logger(self):
        """Configure root logger"""
        try:
            root_logger = logging.getLogger()
            root_logger.setLevel(getattr(logging, self.log_config['level']))
            
            # Clear existing handlers
            root_logger.handlers.clear()
            
            # Add configured handlers
            for handler in self.handlers.values():
                root_logger.addHandler(handler)
            
        except Exception as e:
            print(f"Root logger configuration error: {str(e)}", file=sys.stderr)
    
    def _setup_special_loggers(self):
        """Setup special purpose loggers"""
        try:
            # Security audit logger
            self.security_logger = SecurityAuditLogger(self)
            
            # Performance logger
            self.performance_logger = PerformanceLogger(self)
            
        except Exception as e:
            print(f"Special logger setup error: {str(e)}", file=sys.stderr)
    
    def _setup_database_schema(self):
        """Setup database schema for logging"""
        try:
            if not self.database_manager:
                return
            
            # Create system_logs table
            create_table_query = """
                CREATE TABLE IF NOT EXISTS system_logs (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    timestamp DATETIME NOT NULL,
                    level VARCHAR(20) NOT NULL,
                    logger_name VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    module VARCHAR(255),
                    function VARCHAR(255),
                    line_number INT,
                    thread_id BIGINT,
                    process_id INT,
                    pathname TEXT,
                    user_id VARCHAR(255),
                    session_id VARCHAR(255),
                    request_id VARCHAR(255),
                    ip_address VARCHAR(45),
                    extra_data JSON,
                    exception_info TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_timestamp (timestamp),
                    INDEX idx_level (level),
                    INDEX idx_logger_name (logger_name),
                    INDEX idx_user_id (user_id),
                    INDEX idx_session_id (session_id)
                )
            """
            
            self.database_manager.execute_query(create_table_query)
            
        except Exception as e:
            print(f"Database schema setup error: {str(e)}", file=sys.stderr)
    
    def _start_cleanup_thread(self):
        """Start log cleanup thread"""
        try:
            self.cleanup_thread = threading.Thread(target=self._cleanup_loop, daemon=True)
            self.cleanup_thread.start()
            
        except Exception as e:
            print(f"Cleanup thread start error: {str(e)}", file=sys.stderr)
    
    def _cleanup_loop(self):
        """Log cleanup loop"""
        while not self.shutdown_event.is_set():
            try:
                # Clean old log files
                self._cleanup_old_files()
                
                # Clean old database logs
                self._cleanup_old_database_logs()
                
                # Compress old log files
                if self.log_config['compression']:
                    self._compress_old_files()
                
                # Wait for next cleanup cycle
                self.shutdown_event.wait(self.cleanup_interval)
                
            except Exception as e:
                print(f"Cleanup loop error: {str(e)}", file=sys.stderr)
                time.sleep(60)  # Wait before retrying
    
    def _cleanup_old_files(self):
        """Clean up old log files"""
        try:
            log_dir = Path(self.log_config['log_dir'])
            if not log_dir.exists():
                return
            
            cutoff_date = datetime.now() - timedelta(days=self.log_config['retention_days'])
            
            for log_file in log_dir.glob('*.log*'):
                if log_file.stat().st_mtime < cutoff_date.timestamp():
                    log_file.unlink()
            
        except Exception as e:
            print(f"File cleanup error: {str(e)}", file=sys.stderr)
    
    def _cleanup_old_database_logs(self):
        """Clean up old database logs"""
        try:
            if not self.database_manager:
                return
            
            cutoff_date = datetime.now() - timedelta(days=self.log_config['retention_days'])
            
            query = "DELETE FROM system_logs WHERE timestamp < %s"
            self.database_manager.execute_query(query, (cutoff_date,))
            
        except Exception as e:
            print(f"Database cleanup error: {str(e)}", file=sys.stderr)
    
    def _compress_old_files(self):
        """Compress old log files"""
        try:
            log_dir = Path(self.log_config['log_dir'])
            if not log_dir.exists():
                return
            
            # Compress files older than 1 day
            cutoff_date = datetime.now() - timedelta(days=1)
            
            for log_file in log_dir.glob('*.log.*'):
                if (log_file.suffix != '.gz' and 
                    log_file.stat().st_mtime < cutoff_date.timestamp()):
                    
                    # Compress file
                    compressed_file = log_file.with_suffix(log_file.suffix + '.gz')
                    
                    with open(log_file, 'rb') as f_in:
                        with gzip.open(compressed_file, 'wb') as f_out:
                            shutil.copyfileobj(f_in, f_out)
                    
                    # Remove original file
                    log_file.unlink()
            
        except Exception as e:
            print(f"File compression error: {str(e)}", file=sys.stderr)
    
    def _log_security_event(self, logger_name: str, event_type: str, **kwargs):
        """Log security event from logger"""
        if self.security_logger:
            self.security_logger.log_security_event(event_type, **kwargs)
    
    def _log_performance(self, logger_name: str, operation: str, duration: float, **kwargs):
        """Log performance metric from logger"""
        if self.performance_logger:
            self.performance_logger.log_performance_metric(operation, duration, **kwargs)

if __name__ == "__main__":
    # Example usage and testing
    import sys
    
    # Initialize logger manager
    logger_manager = LoggerManager()
    
    try:
        # Initialize logging system
        logger_manager.initialize()
        
        # Get test logger
        logger = logger_manager.get_logger('test')
        
        # Test different log levels
        logger.debug("Debug message")
        logger.info("Info message")
        logger.warning("Warning message")
        logger.error("Error message")
        logger.critical("Critical message")
        
        # Test structured logging
        logger.info("User action", extra={
            'user_id': 'user123',
            'session_id': 'session456',
            'action': 'login',
            'ip_address': '192.168.1.1'
        })
        
        # Test security logging
        security_logger = logger_manager.get_security_logger()
        security_logger.log_security_event(
            'LOGIN_SUCCESS',
            user_id='user123',
            ip_address='192.168.1.1',
            details={'method': 'password'}
        )
        
        # Test performance logging
        perf_logger = logger_manager.get_performance_logger()
        perf_logger.start_timer('test_op', 'Test Operation')
        time.sleep(0.1)  # Simulate work
        perf_logger.end_timer('test_op', success=True)
        
        # Get statistics
        stats = logger_manager.get_log_stats()
        print(f"Log Statistics: {json.dumps(stats, indent=2, default=str)}")
        
        print("Logger manager test completed successfully")
        
    except Exception as e:
        print(f"Logger manager test failed: {str(e)}")
        sys.exit(1)
    
    finally:
        # Shutdown
        logger_manager.shutdown()