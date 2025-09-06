#!/usr/bin/env python3
"""
System Monitor
LoanFlow Personal Loan Management System

This module provides comprehensive system monitoring including:
- Performance metrics collection
- Health checks
- Alert management
- Resource monitoring
- Error tracking
- Business metrics
- Real-time dashboards
- Automated reporting
"""

import logging
import psutil
import time
import threading
from typing import Dict, List, Optional, Any
from datetime import datetime, timedelta
import json
import os
import requests
from dataclasses import dataclass
from collections import defaultdict, deque
import statistics

@dataclass
class MetricPoint:
    """Single metric data point"""
    timestamp: datetime
    value: float
    tags: Dict[str, str] = None

@dataclass
class Alert:
    """System alert"""
    id: str
    level: str  # info, warning, error, critical
    message: str
    timestamp: datetime
    source: str
    resolved: bool = False
    resolved_at: Optional[datetime] = None

class SystemMonitor:
    def __init__(self, config_manager=None, redis_manager=None, database_manager=None):
        self.logger = logging.getLogger(__name__)
        self.config_manager = config_manager
        self.redis_manager = redis_manager
        self.database_manager = database_manager
        
        # Monitoring state
        self.status = 'initializing'
        self.monitoring_active = False
        self.monitoring_thread = None
        
        # Metrics storage
        self.metrics = defaultdict(lambda: deque(maxlen=1000))  # Keep last 1000 points
        self.alerts = deque(maxlen=500)  # Keep last 500 alerts
        self.alert_rules = {}
        
        # Performance tracking
        self.start_time = datetime.now()
        self.last_health_check = None
        self.health_status = {}
        
        # Configuration
        self.monitoring_config = {
            'interval': 60,  # seconds
            'retention_hours': 24,
            'alert_cooldown': 300,  # 5 minutes
            'cpu_threshold': 80.0,
            'memory_threshold': 85.0,
            'disk_threshold': 90.0,
            'response_time_threshold': 5.0,
            'error_rate_threshold': 5.0,
            'queue_size_threshold': 1000
        }
        
        # Alert tracking
        self.alert_history = defaultdict(list)
        self.last_alert_time = defaultdict(float)
        
        # Business metrics
        self.business_metrics = {
            'loan_applications_today': 0,
            'loan_approvals_today': 0,
            'loan_rejections_today': 0,
            'revenue_today': 0.0,
            'active_users': 0,
            'system_uptime': 0.0,
            'avg_response_time': 0.0,
            'error_rate': 0.0
        }
    
    def initialize(self):
        """Initialize system monitor"""
        try:
            self.logger.info("Initializing System Monitor...")
            
            # Load configuration
            self._load_configuration()
            
            # Setup alert rules
            self._setup_alert_rules()
            
            # Initialize metrics collection
            self._initialize_metrics()
            
            # Start monitoring
            self.start_monitoring()
            
            self.status = 'healthy'
            self.logger.info("System Monitor initialized successfully")
            
        except Exception as e:
            self.logger.error(f"System Monitor initialization failed: {str(e)}")
            self.status = 'error'
            raise
    
    def shutdown(self):
        """Shutdown system monitor"""
        try:
            self.logger.info("Shutting down System Monitor...")
            
            # Stop monitoring
            self.stop_monitoring()
            
            # Save final metrics
            self._save_metrics_to_storage()
            
            self.status = 'stopped'
            self.logger.info("System Monitor shutdown complete")
            
        except Exception as e:
            self.logger.error(f"System Monitor shutdown error: {str(e)}")
    
    def start_monitoring(self):
        """Start monitoring thread"""
        try:
            if not self.monitoring_active:
                self.monitoring_active = True
                self.monitoring_thread = threading.Thread(target=self._monitoring_loop, daemon=True)
                self.monitoring_thread.start()
                self.logger.info("System monitoring started")
            
        except Exception as e:
            self.logger.error(f"Failed to start monitoring: {str(e)}")
    
    def stop_monitoring(self):
        """Stop monitoring thread"""
        try:
            self.monitoring_active = False
            if self.monitoring_thread and self.monitoring_thread.is_alive():
                self.monitoring_thread.join(timeout=5)
            self.logger.info("System monitoring stopped")
            
        except Exception as e:
            self.logger.error(f"Failed to stop monitoring: {str(e)}")
    
    def get_status(self) -> str:
        """Get monitor status"""
        return self.status
    
    def get_health_status(self) -> Dict:
        """Get comprehensive health status"""
        try:
            health = {
                'status': self.status,
                'monitoring_active': self.monitoring_active,
                'uptime_seconds': (datetime.now() - self.start_time).total_seconds(),
                'last_health_check': self.last_health_check.isoformat() if self.last_health_check else None,
                'system_health': self._get_system_health(),
                'service_health': self._get_service_health(),
                'business_metrics': self.business_metrics.copy(),
                'active_alerts': self._get_active_alerts(),
                'timestamp': datetime.now().isoformat()
            }
            
            return health
            
        except Exception as e:
            self.logger.error(f"Health status error: {str(e)}")
            return {'status': 'error', 'error': str(e)}
    
    def get_metrics(self, metric_name: str = None, hours: int = 1) -> Dict:
        """Get metrics data"""
        try:
            cutoff_time = datetime.now() - timedelta(hours=hours)
            
            if metric_name:
                # Get specific metric
                points = [p for p in self.metrics[metric_name] if p.timestamp >= cutoff_time]
                return {
                    'metric': metric_name,
                    'points': [{'timestamp': p.timestamp.isoformat(), 'value': p.value, 'tags': p.tags} for p in points],
                    'count': len(points),
                    'latest_value': points[-1].value if points else None
                }
            else:
                # Get all metrics summary
                summary = {}
                for name, points in self.metrics.items():
                    recent_points = [p for p in points if p.timestamp >= cutoff_time]
                    if recent_points:
                        values = [p.value for p in recent_points]
                        summary[name] = {
                            'count': len(recent_points),
                            'latest': values[-1],
                            'average': statistics.mean(values),
                            'min': min(values),
                            'max': max(values)
                        }
                
                return summary
                
        except Exception as e:
            self.logger.error(f"Metrics retrieval error: {str(e)}")
            return {}
    
    def add_metric(self, name: str, value: float, tags: Dict[str, str] = None):
        """Add metric point"""
        try:
            point = MetricPoint(
                timestamp=datetime.now(),
                value=value,
                tags=tags or {}
            )
            
            self.metrics[name].append(point)
            
            # Check alert rules
            self._check_alert_rules(name, value)
            
        except Exception as e:
            self.logger.error(f"Add metric error: {str(e)}")
    
    def create_alert(self, level: str, message: str, source: str = 'system') -> str:
        """Create system alert"""
        try:
            alert_id = f"alert_{int(time.time())}_{len(self.alerts)}"
            
            alert = Alert(
                id=alert_id,
                level=level,
                message=message,
                timestamp=datetime.now(),
                source=source
            )
            
            self.alerts.append(alert)
            
            # Log alert
            log_level = getattr(logging, level.upper(), logging.INFO)
            self.logger.log(log_level, f"Alert [{level}] from {source}: {message}")
            
            # Send notifications if configured
            self._send_alert_notification(alert)
            
            return alert_id
            
        except Exception as e:
            self.logger.error(f"Create alert error: {str(e)}")
            return None
    
    def resolve_alert(self, alert_id: str) -> bool:
        """Resolve alert"""
        try:
            for alert in self.alerts:
                if alert.id == alert_id and not alert.resolved:
                    alert.resolved = True
                    alert.resolved_at = datetime.now()
                    self.logger.info(f"Alert {alert_id} resolved")
                    return True
            
            return False
            
        except Exception as e:
            self.logger.error(f"Resolve alert error: {str(e)}")
            return False
    
    def get_alerts(self, level: str = None, resolved: bool = None, hours: int = 24) -> List[Dict]:
        """Get alerts"""
        try:
            cutoff_time = datetime.now() - timedelta(hours=hours)
            
            filtered_alerts = []
            for alert in self.alerts:
                if alert.timestamp < cutoff_time:
                    continue
                
                if level and alert.level != level:
                    continue
                
                if resolved is not None and alert.resolved != resolved:
                    continue
                
                filtered_alerts.append({
                    'id': alert.id,
                    'level': alert.level,
                    'message': alert.message,
                    'timestamp': alert.timestamp.isoformat(),
                    'source': alert.source,
                    'resolved': alert.resolved,
                    'resolved_at': alert.resolved_at.isoformat() if alert.resolved_at else None
                })
            
            return sorted(filtered_alerts, key=lambda x: x['timestamp'], reverse=True)
            
        except Exception as e:
            self.logger.error(f"Get alerts error: {str(e)}")
            return []
    
    def _monitoring_loop(self):
        """Main monitoring loop"""
        while self.monitoring_active:
            try:
                # Collect system metrics
                self._collect_system_metrics()
                
                # Collect service metrics
                self._collect_service_metrics()
                
                # Collect business metrics
                self._collect_business_metrics()
                
                # Perform health checks
                self._perform_health_checks()
                
                # Clean old metrics
                self._cleanup_old_metrics()
                
                # Update last check time
                self.last_health_check = datetime.now()
                
                # Sleep until next interval
                time.sleep(self.monitoring_config['interval'])
                
            except Exception as e:
                self.logger.error(f"Monitoring loop error: {str(e)}")
                time.sleep(30)  # Wait before retrying
    
    def _collect_system_metrics(self):
        """Collect system performance metrics"""
        try:
            # CPU usage
            cpu_percent = psutil.cpu_percent(interval=1)
            self.add_metric('system.cpu_percent', cpu_percent)
            
            # Memory usage
            memory = psutil.virtual_memory()
            self.add_metric('system.memory_percent', memory.percent)
            self.add_metric('system.memory_used_gb', memory.used / (1024**3))
            self.add_metric('system.memory_available_gb', memory.available / (1024**3))
            
            # Disk usage
            disk = psutil.disk_usage('/')
            disk_percent = (disk.used / disk.total) * 100
            self.add_metric('system.disk_percent', disk_percent)
            self.add_metric('system.disk_used_gb', disk.used / (1024**3))
            self.add_metric('system.disk_free_gb', disk.free / (1024**3))
            
            # Network I/O
            network = psutil.net_io_counters()
            self.add_metric('system.network_bytes_sent', network.bytes_sent)
            self.add_metric('system.network_bytes_recv', network.bytes_recv)
            
            # Process count
            process_count = len(psutil.pids())
            self.add_metric('system.process_count', process_count)
            
            # Load average (Unix-like systems)
            try:
                load_avg = os.getloadavg()
                self.add_metric('system.load_avg_1min', load_avg[0])
                self.add_metric('system.load_avg_5min', load_avg[1])
                self.add_metric('system.load_avg_15min', load_avg[2])
            except (OSError, AttributeError):
                # Not available on Windows
                pass
            
        except Exception as e:
            self.logger.error(f"System metrics collection error: {str(e)}")
    
    def _collect_service_metrics(self):
        """Collect service-specific metrics"""
        try:
            # Database metrics
            if self.database_manager:
                db_metrics = self.database_manager.get_metrics()
                for key, value in db_metrics.items():
                    if isinstance(value, (int, float)):
                        self.add_metric(f'database.{key}', value)
            
            # Redis metrics
            if self.redis_manager:
                redis_metrics = self.redis_manager.get_metrics()
                for key, value in redis_metrics.items():
                    if isinstance(value, (int, float)):
                        self.add_metric(f'redis.{key}', value)
            
            # Application uptime
            uptime_seconds = (datetime.now() - self.start_time).total_seconds()
            self.add_metric('application.uptime_seconds', uptime_seconds)
            
        except Exception as e:
            self.logger.error(f"Service metrics collection error: {str(e)}")
    
    def _collect_business_metrics(self):
        """Collect business-specific metrics"""
        try:
            if not self.database_manager:
                return
            
            # Today's date for filtering
            today = datetime.now().date()
            
            # Loan applications today
            query = "SELECT COUNT(*) FROM loan_applications WHERE DATE(created_at) = %s"
            result = self.database_manager.execute_query(query, (today,))
            if result:
                self.business_metrics['loan_applications_today'] = result[0][0]
                self.add_metric('business.loan_applications_today', result[0][0])
            
            # Loan approvals today
            query = "SELECT COUNT(*) FROM loans WHERE DATE(created_at) = %s AND status = 'approved'"
            result = self.database_manager.execute_query(query, (today,))
            if result:
                self.business_metrics['loan_approvals_today'] = result[0][0]
                self.add_metric('business.loan_approvals_today', result[0][0])
            
            # Loan rejections today
            query = "SELECT COUNT(*) FROM loan_applications WHERE DATE(updated_at) = %s AND status = 'rejected'"
            result = self.database_manager.execute_query(query, (today,))
            if result:
                self.business_metrics['loan_rejections_today'] = result[0][0]
                self.add_metric('business.loan_rejections_today', result[0][0])
            
            # Revenue today (from payments)
            query = "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(created_at) = %s AND status = 'completed'"
            result = self.database_manager.execute_query(query, (today,))
            if result:
                self.business_metrics['revenue_today'] = float(result[0][0])
                self.add_metric('business.revenue_today', float(result[0][0]))
            
            # Active users (logged in within last 24 hours)
            query = "SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE last_activity >= %s"
            yesterday = datetime.now() - timedelta(days=1)
            result = self.database_manager.execute_query(query, (yesterday,))
            if result:
                self.business_metrics['active_users'] = result[0][0]
                self.add_metric('business.active_users', result[0][0])
            
        except Exception as e:
            self.logger.error(f"Business metrics collection error: {str(e)}")
    
    def _perform_health_checks(self):
        """Perform comprehensive health checks"""
        try:
            health_results = {}
            
            # Database health check
            if self.database_manager:
                try:
                    db_health = self.database_manager.health_check()
                    health_results['database'] = db_health
                except Exception as e:
                    health_results['database'] = {'status': 'unhealthy', 'error': str(e)}
                    self.create_alert('error', f'Database health check failed: {str(e)}', 'health_check')
            
            # Redis health check
            if self.redis_manager:
                try:
                    redis_health = self.redis_manager.health_check()
                    health_results['redis'] = redis_health
                except Exception as e:
                    health_results['redis'] = {'status': 'unhealthy', 'error': str(e)}
                    self.create_alert('error', f'Redis health check failed: {str(e)}', 'health_check')
            
            # File system health check
            try:
                disk_usage = psutil.disk_usage('/')
                disk_percent = (disk_usage.used / disk_usage.total) * 100
                
                if disk_percent > self.monitoring_config['disk_threshold']:
                    health_results['filesystem'] = {'status': 'warning', 'disk_usage_percent': disk_percent}
                    self.create_alert('warning', f'High disk usage: {disk_percent:.1f}%', 'health_check')
                else:
                    health_results['filesystem'] = {'status': 'healthy', 'disk_usage_percent': disk_percent}
            except Exception as e:
                health_results['filesystem'] = {'status': 'unhealthy', 'error': str(e)}
            
            # Memory health check
            try:
                memory = psutil.virtual_memory()
                if memory.percent > self.monitoring_config['memory_threshold']:
                    health_results['memory'] = {'status': 'warning', 'usage_percent': memory.percent}
                    self.create_alert('warning', f'High memory usage: {memory.percent:.1f}%', 'health_check')
                else:
                    health_results['memory'] = {'status': 'healthy', 'usage_percent': memory.percent}
            except Exception as e:
                health_results['memory'] = {'status': 'unhealthy', 'error': str(e)}
            
            self.health_status = health_results
            
        except Exception as e:
            self.logger.error(f"Health checks error: {str(e)}")
    
    def _get_system_health(self) -> Dict:
        """Get system health summary"""
        try:
            # Get latest system metrics
            cpu_metrics = list(self.metrics.get('system.cpu_percent', []))
            memory_metrics = list(self.metrics.get('system.memory_percent', []))
            disk_metrics = list(self.metrics.get('system.disk_percent', []))
            
            health = {
                'overall_status': 'healthy',
                'cpu': {
                    'current': cpu_metrics[-1].value if cpu_metrics else 0,
                    'status': 'healthy'
                },
                'memory': {
                    'current': memory_metrics[-1].value if memory_metrics else 0,
                    'status': 'healthy'
                },
                'disk': {
                    'current': disk_metrics[-1].value if disk_metrics else 0,
                    'status': 'healthy'
                }
            }
            
            # Check thresholds
            if health['cpu']['current'] > self.monitoring_config['cpu_threshold']:
                health['cpu']['status'] = 'warning'
                health['overall_status'] = 'degraded'
            
            if health['memory']['current'] > self.monitoring_config['memory_threshold']:
                health['memory']['status'] = 'warning'
                health['overall_status'] = 'degraded'
            
            if health['disk']['current'] > self.monitoring_config['disk_threshold']:
                health['disk']['status'] = 'critical'
                health['overall_status'] = 'critical'
            
            return health
            
        except Exception as e:
            self.logger.error(f"System health error: {str(e)}")
            return {'overall_status': 'unknown', 'error': str(e)}
    
    def _get_service_health(self) -> Dict:
        """Get service health summary"""
        try:
            services = {}
            
            # Database service
            if self.database_manager:
                db_status = self.database_manager.get_status()
                services['database'] = {
                    'status': db_status,
                    'healthy': db_status == 'healthy'
                }
            
            # Redis service
            if self.redis_manager:
                redis_status = self.redis_manager.get_status()
                services['redis'] = {
                    'status': redis_status,
                    'healthy': redis_status == 'healthy'
                }
            
            # Overall service health
            all_healthy = all(service.get('healthy', False) for service in services.values())
            
            return {
                'overall_healthy': all_healthy,
                'services': services
            }
            
        except Exception as e:
            self.logger.error(f"Service health error: {str(e)}")
            return {'overall_healthy': False, 'error': str(e)}
    
    def _get_active_alerts(self) -> List[Dict]:
        """Get active (unresolved) alerts"""
        try:
            active_alerts = []
            for alert in self.alerts:
                if not alert.resolved:
                    active_alerts.append({
                        'id': alert.id,
                        'level': alert.level,
                        'message': alert.message,
                        'timestamp': alert.timestamp.isoformat(),
                        'source': alert.source
                    })
            
            return sorted(active_alerts, key=lambda x: x['timestamp'], reverse=True)
            
        except Exception as e:
            self.logger.error(f"Active alerts error: {str(e)}")
            return []
    
    def _setup_alert_rules(self):
        """Setup alert rules"""
        self.alert_rules = {
            'system.cpu_percent': {
                'threshold': self.monitoring_config['cpu_threshold'],
                'operator': '>',
                'level': 'warning',
                'message': 'High CPU usage detected'
            },
            'system.memory_percent': {
                'threshold': self.monitoring_config['memory_threshold'],
                'operator': '>',
                'level': 'warning',
                'message': 'High memory usage detected'
            },
            'system.disk_percent': {
                'threshold': self.monitoring_config['disk_threshold'],
                'operator': '>',
                'level': 'critical',
                'message': 'High disk usage detected'
            },
            'database.connection_errors': {
                'threshold': 5,
                'operator': '>',
                'level': 'error',
                'message': 'Database connection errors detected'
            },
            'redis.connection_errors': {
                'threshold': 5,
                'operator': '>',
                'level': 'error',
                'message': 'Redis connection errors detected'
            }
        }
    
    def _check_alert_rules(self, metric_name: str, value: float):
        """Check if metric value triggers any alert rules"""
        try:
            if metric_name not in self.alert_rules:
                return
            
            rule = self.alert_rules[metric_name]
            threshold = rule['threshold']
            operator = rule['operator']
            
            # Check if alert should be triggered
            should_alert = False
            if operator == '>' and value > threshold:
                should_alert = True
            elif operator == '<' and value < threshold:
                should_alert = True
            elif operator == '==' and value == threshold:
                should_alert = True
            
            if should_alert:
                # Check cooldown period
                current_time = time.time()
                last_alert = self.last_alert_time.get(metric_name, 0)
                
                if current_time - last_alert > self.monitoring_config['alert_cooldown']:
                    message = f"{rule['message']}: {metric_name} = {value} (threshold: {threshold})"
                    self.create_alert(rule['level'], message, 'alert_rule')
                    self.last_alert_time[metric_name] = current_time
            
        except Exception as e:
            self.logger.error(f"Alert rule check error: {str(e)}")
    
    def _send_alert_notification(self, alert: Alert):
        """Send alert notification"""
        try:
            if not self.config_manager:
                return
            
            # Email notification
            alert_email = self.config_manager.get('monitoring.alert_email')
            if alert_email:
                # TODO: Implement email notification
                pass
            
            # Webhook notification
            alert_webhook = self.config_manager.get('monitoring.alert_webhook')
            if alert_webhook:
                payload = {
                    'alert_id': alert.id,
                    'level': alert.level,
                    'message': alert.message,
                    'timestamp': alert.timestamp.isoformat(),
                    'source': alert.source
                }
                
                try:
                    response = requests.post(
                        alert_webhook,
                        json=payload,
                        timeout=10
                    )
                    if response.status_code == 200:
                        self.logger.info(f"Alert notification sent for {alert.id}")
                    else:
                        self.logger.warning(f"Alert notification failed: {response.status_code}")
                except requests.RequestException as e:
                    self.logger.error(f"Alert notification error: {str(e)}")
            
        except Exception as e:
            self.logger.error(f"Alert notification error: {str(e)}")
    
    def _cleanup_old_metrics(self):
        """Clean up old metrics data"""
        try:
            cutoff_time = datetime.now() - timedelta(hours=self.monitoring_config['retention_hours'])
            
            for metric_name in list(self.metrics.keys()):
                points = self.metrics[metric_name]
                # Remove old points
                while points and points[0].timestamp < cutoff_time:
                    points.popleft()
            
        except Exception as e:
            self.logger.error(f"Metrics cleanup error: {str(e)}")
    
    def _save_metrics_to_storage(self):
        """Save metrics to persistent storage"""
        try:
            if not self.database_manager:
                return
            
            # Save recent metrics to database
            cutoff_time = datetime.now() - timedelta(hours=1)  # Save last hour
            
            for metric_name, points in self.metrics.items():
                recent_points = [p for p in points if p.timestamp >= cutoff_time]
                
                for point in recent_points:
                    query = """
                        INSERT INTO system_metrics (metric_name, value, tags, timestamp)
                        VALUES (%s, %s, %s, %s)
                        ON DUPLICATE KEY UPDATE value = VALUES(value)
                    """
                    
                    tags_json = json.dumps(point.tags) if point.tags else None
                    
                    self.database_manager.execute_query(
                        query,
                        (metric_name, point.value, tags_json, point.timestamp)
                    )
            
            self.logger.info("Metrics saved to storage")
            
        except Exception as e:
            self.logger.error(f"Metrics save error: {str(e)}")
    
    def _load_configuration(self):
        """Load monitoring configuration"""
        try:
            if self.config_manager:
                monitoring_config = self.config_manager.get_section('monitoring')
                if monitoring_config:
                    self.monitoring_config.update(monitoring_config)
            
        except Exception as e:
            self.logger.error(f"Configuration load error: {str(e)}")
    
    def _initialize_metrics(self):
        """Initialize metrics collection"""
        try:
            # Create initial metric points
            self.add_metric('application.startup_time', time.time())
            self.add_metric('application.version', 1.0, {'version': '1.0.0'})
            
            self.logger.info("Metrics collection initialized")
            
        except Exception as e:
            self.logger.error(f"Metrics initialization error: {str(e)}")
    
    # Public API Methods
    def get_dashboard_data(self) -> Dict:
        """Get data for monitoring dashboard"""
        try:
            return {
                'health_status': self.get_health_status(),
                'system_metrics': self.get_metrics(hours=1),
                'business_metrics': self.business_metrics.copy(),
                'recent_alerts': self.get_alerts(hours=24),
                'active_alerts_count': len(self._get_active_alerts()),
                'uptime': (datetime.now() - self.start_time).total_seconds(),
                'last_updated': datetime.now().isoformat()
            }
            
        except Exception as e:
            self.logger.error(f"Dashboard data error: {str(e)}")
            return {'error': str(e)}
    
    def export_metrics(self, format: str = 'json', hours: int = 24) -> str:
        """Export metrics data"""
        try:
            metrics_data = self.get_metrics(hours=hours)
            
            if format.lower() == 'json':
                return json.dumps(metrics_data, indent=2, default=str)
            elif format.lower() == 'csv':
                # TODO: Implement CSV export
                return "CSV export not implemented yet"
            else:
                raise ValueError(f"Unsupported export format: {format}")
                
        except Exception as e:
            self.logger.error(f"Metrics export error: {str(e)}")
            return f"Export error: {str(e)}"

if __name__ == "__main__":
    # Example usage and testing
    import sys
    
    # Setup logging
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    
    # Initialize system monitor
    monitor = SystemMonitor()
    
    try:
        # Initialize monitor
        monitor.initialize()
        
        # Add some test metrics
        monitor.add_metric('test.cpu_usage', 45.5)
        monitor.add_metric('test.memory_usage', 67.2)
        monitor.add_metric('test.response_time', 1.23)
        
        # Create test alert
        alert_id = monitor.create_alert('warning', 'Test alert message', 'test')
        print(f"Created alert: {alert_id}")
        
        # Get health status
        health = monitor.get_health_status()
        print(f"Health Status: {json.dumps(health, indent=2, default=str)}")
        
        # Get metrics
        metrics = monitor.get_metrics(hours=1)
        print(f"Metrics: {json.dumps(metrics, indent=2, default=str)}")
        
        # Get dashboard data
        dashboard = monitor.get_dashboard_data()
        print(f"Dashboard Data: {json.dumps(dashboard, indent=2, default=str)}")
        
        print("System monitor test completed successfully")
        
    except Exception as e:
        print(f"System monitor test failed: {str(e)}")
        sys.exit(1)
    
    finally:
        # Shutdown
        monitor.shutdown()