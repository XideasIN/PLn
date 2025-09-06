#!/usr/bin/env python3
"""
Autonomous Business System Startup Script
LoanFlow Personal Loan Management System

This script initializes and starts the complete autonomous business system
with AI-powered automation for customer acquisition, loan processing,
content generation, and business operations.
"""

import sys
import os
import time
import logging
import signal
import threading
from datetime import datetime, timedelta

# Add the backend directory to Python path
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from controllers.autonomous_controller import AutonomousBusinessController
from services.ai_services import AIServiceManager
from services.business_services import BusinessServiceManager
from utils.redis_manager import RedisManager
from utils.database import DatabaseManager
from utils.logger import setup_logging

class AutonomousBusinessSystem:
    def __init__(self):
        self.controller = None
        self.ai_services = None
        self.business_services = None
        self.redis_manager = None
        self.db_manager = None
        self.running = False
        self.shutdown_event = threading.Event()
        
        # Setup logging
        setup_logging()
        self.logger = logging.getLogger(__name__)
        
    def initialize(self):
        """Initialize all system components"""
        try:
            self.logger.info("Initializing Autonomous Business System...")
            
            # Initialize Redis for state management
            self.redis_manager = RedisManager()
            if not self.redis_manager.connect():
                raise Exception("Failed to connect to Redis")
            
            # Initialize database manager
            self.db_manager = DatabaseManager()
            if not self.db_manager.connect():
                raise Exception("Failed to connect to database")
            
            # Initialize AI services
            self.ai_services = AIServiceManager()
            self.ai_services.initialize()
            
            # Initialize business services
            self.business_services = BusinessServiceManager()
            self.business_services.initialize(self.db_manager, self.redis_manager)
            
            # Initialize autonomous controller
            self.controller = AutonomousBusinessController(
                ai_services=self.ai_services,
                business_services=self.business_services,
                redis_manager=self.redis_manager,
                db_manager=self.db_manager
            )
            
            self.logger.info("System initialization completed successfully")
            return True
            
        except Exception as e:
            self.logger.error(f"System initialization failed: {str(e)}")
            return False
    
    def start(self):
        """Start the autonomous business system"""
        if not self.initialize():
            self.logger.error("Failed to initialize system")
            return False
        
        try:
            self.logger.info("Starting Autonomous Business System...")
            
            # Set system status to running
            self.redis_manager.set('autonomous_system_status', 'running')
            self.redis_manager.set('autonomous_system_start_time', datetime.now().isoformat())
            
            # Start the autonomous controller
            self.controller.start()
            
            # Start monitoring thread
            monitor_thread = threading.Thread(target=self._monitor_system)
            monitor_thread.daemon = True
            monitor_thread.start()
            
            # Start daily operations scheduler
            scheduler_thread = threading.Thread(target=self._daily_operations_scheduler)
            scheduler_thread.daemon = True
            scheduler_thread.start()
            
            self.running = True
            self.logger.info("Autonomous Business System started successfully")
            
            # Keep the main thread alive
            while self.running and not self.shutdown_event.is_set():
                time.sleep(1)
                
        except KeyboardInterrupt:
            self.logger.info("Received shutdown signal")
        except Exception as e:
            self.logger.error(f"System error: {str(e)}")
        finally:
            self.stop()
    
    def stop(self):
        """Stop the autonomous business system"""
        self.logger.info("Stopping Autonomous Business System...")
        
        self.running = False
        self.shutdown_event.set()
        
        if self.controller:
            self.controller.stop()
        
        if self.ai_services:
            self.ai_services.shutdown()
        
        if self.business_services:
            self.business_services.shutdown()
        
        # Update system status
        if self.redis_manager:
            self.redis_manager.set('autonomous_system_status', 'stopped')
            self.redis_manager.set('autonomous_system_stop_time', datetime.now().isoformat())
        
        self.logger.info("Autonomous Business System stopped")
    
    def _monitor_system(self):
        """Monitor system health and performance"""
        while self.running and not self.shutdown_event.is_set():
            try:
                # Collect system metrics
                metrics = {
                    'timestamp': datetime.now().isoformat(),
                    'system_status': 'running',
                    'active_tasks': self.controller.get_active_task_count() if self.controller else 0,
                    'queue_size': self.controller.get_queue_size() if self.controller else 0,
                    'ai_services_status': self.ai_services.get_status() if self.ai_services else 'unknown',
                    'business_services_status': self.business_services.get_status() if self.business_services else 'unknown'
                }
                
                # Store metrics in Redis
                self.redis_manager.set('system_metrics', metrics)
                
                # Check for alerts
                self._check_system_alerts(metrics)
                
                time.sleep(60)  # Monitor every minute
                
            except Exception as e:
                self.logger.error(f"Monitoring error: {str(e)}")
                time.sleep(60)
    
    def _daily_operations_scheduler(self):
        """Schedule and run daily operations at 6 AM"""
        while self.running and not self.shutdown_event.is_set():
            try:
                now = datetime.now()
                # Check if it's 6 AM and we haven't run today
                if now.hour == 6 and now.minute == 0:
                    last_run = self.redis_manager.get('last_daily_operations')
                    today = now.date().isoformat()
                    
                    if not last_run or last_run != today:
                        self.logger.info("Starting daily operations...")
                        self.controller.run_daily_operations()
                        self.redis_manager.set('last_daily_operations', today)
                
                time.sleep(60)  # Check every minute
                
            except Exception as e:
                self.logger.error(f"Daily operations scheduler error: {str(e)}")
                time.sleep(60)
    
    def _check_system_alerts(self, metrics):
        """Check for system alerts and admin intervention requirements"""
        alerts = []
        
        # Check queue size
        if metrics['queue_size'] > 100:
            alerts.append({
                'type': 'performance',
                'severity': 'warning',
                'message': f"High queue size: {metrics['queue_size']} tasks pending",
                'timestamp': datetime.now().isoformat()
            })
        
        # Check AI services status
        if metrics['ai_services_status'] != 'healthy':
            alerts.append({
                'type': 'system',
                'severity': 'critical',
                'message': f"AI services status: {metrics['ai_services_status']}",
                'timestamp': datetime.now().isoformat()
            })
        
        # Store alerts if any
        if alerts:
            existing_alerts = self.redis_manager.get('system_alerts') or []
            existing_alerts.extend(alerts)
            self.redis_manager.set('system_alerts', existing_alerts[-50:])  # Keep last 50 alerts

def signal_handler(signum, frame):
    """Handle shutdown signals"""
    print("\nReceived shutdown signal. Stopping system...")
    if hasattr(signal_handler, 'system'):
        signal_handler.system.stop()
    sys.exit(0)

def main():
    """Main entry point"""
    print("="*60)
    print("LoanFlow Autonomous Business System")
    print("AI-Powered Business Automation Platform")
    print("="*60)
    
    # Create system instance
    system = AutonomousBusinessSystem()
    
    # Setup signal handlers
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)
    signal_handler.system = system
    
    # Start the system
    try:
        system.start()
    except Exception as e:
        print(f"Failed to start system: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    main()