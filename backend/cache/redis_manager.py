#!/usr/bin/env python3
"""
Redis Manager
LoanFlow Personal Loan Management System

This module manages Redis operations including:
- Connection management and pooling
- Caching operations
- Queue management for background tasks
- Session storage
- Real-time data storage
- Pub/Sub messaging
- Rate limiting
- Distributed locking
"""

import logging
import redis
import json
import pickle
import hashlib
from typing import Dict, List, Optional, Any, Union
from datetime import datetime, timedelta
import os
import threading
import time
from contextlib import contextmanager

class RedisManager:
    def __init__(self):
        self.logger = logging.getLogger(__name__)
        self.redis_client = None
        self.connection_pool = None
        self.status = 'initializing'
        self.lock = threading.Lock()
        
        # Redis configuration
        self.config = {
            'host': os.getenv('REDIS_HOST', 'localhost'),
            'port': int(os.getenv('REDIS_PORT', '6379')),
            'password': os.getenv('REDIS_PASSWORD'),
            'db': int(os.getenv('REDIS_DB', '0')),
            'decode_responses': True,
            'socket_timeout': 30,
            'socket_connect_timeout': 30,
            'retry_on_timeout': True,
            'health_check_interval': 30,
            'max_connections': 50
        }
        
        # Cache configuration
        self.cache_config = {
            'default_ttl': 3600,  # 1 hour
            'max_key_length': 250,
            'compression_threshold': 1024,  # Compress values larger than 1KB
            'key_prefix': 'loanflow:',
            'version': '1.0'
        }
        
        # Queue configuration
        self.queue_config = {
            'default_queue': 'default',
            'priority_queues': ['urgent', 'high', 'normal', 'low'],
            'max_retries': 3,
            'retry_delay': 60,  # seconds
            'dead_letter_queue': 'failed_jobs'
        }
        
        # Performance metrics
        self.metrics = {
            'cache_hits': 0,
            'cache_misses': 0,
            'cache_sets': 0,
            'cache_deletes': 0,
            'queue_pushes': 0,
            'queue_pops': 0,
            'connection_errors': 0,
            'operations_total': 0
        }
    
    def initialize(self):
        """Initialize Redis connection and setup"""
        try:
            self.logger.info("Initializing Redis Manager...")
            
            # Create connection pool
            self._create_connection_pool()
            
            # Create Redis client
            self._create_redis_client()
            
            # Verify connection
            self._verify_connection()
            
            # Setup monitoring
            self._initialize_monitoring()
            
            # Initialize queues
            self._initialize_queues()
            
            self.status = 'healthy'
            self.logger.info("Redis Manager initialized successfully")
            
        except Exception as e:
            self.logger.error(f"Redis Manager initialization failed: {str(e)}")
            self.status = 'error'
            raise
    
    def shutdown(self):
        """Shutdown Redis connections"""
        try:
            self.logger.info("Shutting down Redis Manager...")
            
            if self.redis_client:
                self.redis_client.close()
            
            if self.connection_pool:
                self.connection_pool.disconnect()
            
            self.status = 'stopped'
            self.logger.info("Redis Manager shutdown complete")
            
        except Exception as e:
            self.logger.error(f"Redis shutdown error: {str(e)}")
    
    def get_status(self) -> str:
        """Get Redis manager status"""
        return self.status
    
    def get_metrics(self) -> Dict:
        """Get Redis performance metrics"""
        try:
            # Calculate cache hit ratio
            total_cache_ops = self.metrics['cache_hits'] + self.metrics['cache_misses']
            hit_ratio = (self.metrics['cache_hits'] / total_cache_ops * 100) if total_cache_ops > 0 else 0
            
            # Get Redis info
            redis_info = self.redis_client.info() if self.redis_client else {}
            
            return {
                **self.metrics,
                'cache_hit_ratio': round(hit_ratio, 2),
                'redis_memory_used': redis_info.get('used_memory_human', 'N/A'),
                'redis_connected_clients': redis_info.get('connected_clients', 0),
                'redis_uptime': redis_info.get('uptime_in_seconds', 0),
                'status': self.status,
                'last_updated': datetime.now().isoformat()
            }
            
        except Exception as e:
            self.logger.error(f"Metrics retrieval error: {str(e)}")
            return self.metrics
    
    # Connection Management
    def _create_connection_pool(self):
        """Create Redis connection pool"""
        try:
            pool_kwargs = {
                'host': self.config['host'],
                'port': self.config['port'],
                'db': self.config['db'],
                'decode_responses': self.config['decode_responses'],
                'socket_timeout': self.config['socket_timeout'],
                'socket_connect_timeout': self.config['socket_connect_timeout'],
                'retry_on_timeout': self.config['retry_on_timeout'],
                'health_check_interval': self.config['health_check_interval'],
                'max_connections': self.config['max_connections']
            }
            
            if self.config['password']:
                pool_kwargs['password'] = self.config['password']
            
            self.connection_pool = redis.ConnectionPool(**pool_kwargs)
            
            self.logger.info(f"Redis connection pool created with {self.config['max_connections']} max connections")
            
        except Exception as e:
            self.logger.error(f"Connection pool creation failed: {str(e)}")
            raise
    
    def _create_redis_client(self):
        """Create Redis client"""
        try:
            self.redis_client = redis.Redis(connection_pool=self.connection_pool)
            
        except Exception as e:
            self.logger.error(f"Redis client creation failed: {str(e)}")
            raise
    
    def _verify_connection(self):
        """Verify Redis connection"""
        try:
            response = self.redis_client.ping()
            if not response:
                raise Exception("Redis ping failed")
                
            self.logger.info("Redis connection verified successfully")
            
        except Exception as e:
            self.logger.error(f"Redis connection verification failed: {str(e)}")
            raise
    
    # Caching Operations
    def get(self, key: str, default: Any = None) -> Any:
        """Get value from cache"""
        try:
            full_key = self._build_key(key)
            
            with self.lock:
                self.metrics['operations_total'] += 1
            
            value = self.redis_client.get(full_key)
            
            if value is not None:
                with self.lock:
                    self.metrics['cache_hits'] += 1
                
                # Deserialize value
                return self._deserialize_value(value)
            else:
                with self.lock:
                    self.metrics['cache_misses'] += 1
                
                return default
                
        except Exception as e:
            self.logger.error(f"Cache get error for key '{key}': {str(e)}")
            with self.lock:
                self.metrics['cache_misses'] += 1
            return default
    
    def set(self, key: str, value: Any, ttl: Optional[int] = None) -> bool:
        """Set value in cache"""
        try:
            full_key = self._build_key(key)
            ttl = ttl or self.cache_config['default_ttl']
            
            # Serialize value
            serialized_value = self._serialize_value(value)
            
            # Set value with TTL
            result = self.redis_client.setex(full_key, ttl, serialized_value)
            
            with self.lock:
                self.metrics['cache_sets'] += 1
                self.metrics['operations_total'] += 1
            
            return result
            
        except Exception as e:
            self.logger.error(f"Cache set error for key '{key}': {str(e)}")
            return False
    
    def delete(self, key: str) -> bool:
        """Delete key from cache"""
        try:
            full_key = self._build_key(key)
            result = self.redis_client.delete(full_key)
            
            with self.lock:
                self.metrics['cache_deletes'] += 1
                self.metrics['operations_total'] += 1
            
            return bool(result)
            
        except Exception as e:
            self.logger.error(f"Cache delete error for key '{key}': {str(e)}")
            return False
    
    def exists(self, key: str) -> bool:
        """Check if key exists in cache"""
        try:
            full_key = self._build_key(key)
            return bool(self.redis_client.exists(full_key))
            
        except Exception as e:
            self.logger.error(f"Cache exists error for key '{key}': {str(e)}")
            return False
    
    def expire(self, key: str, ttl: int) -> bool:
        """Set expiration time for key"""
        try:
            full_key = self._build_key(key)
            return bool(self.redis_client.expire(full_key, ttl))
            
        except Exception as e:
            self.logger.error(f"Cache expire error for key '{key}': {str(e)}")
            return False
    
    def get_ttl(self, key: str) -> int:
        """Get time to live for key"""
        try:
            full_key = self._build_key(key)
            return self.redis_client.ttl(full_key)
            
        except Exception as e:
            self.logger.error(f"Cache TTL error for key '{key}': {str(e)}")
            return -1
    
    def increment(self, key: str, amount: int = 1) -> int:
        """Increment numeric value"""
        try:
            full_key = self._build_key(key)
            return self.redis_client.incr(full_key, amount)
            
        except Exception as e:
            self.logger.error(f"Cache increment error for key '{key}': {str(e)}")
            return 0
    
    def decrement(self, key: str, amount: int = 1) -> int:
        """Decrement numeric value"""
        try:
            full_key = self._build_key(key)
            return self.redis_client.decr(full_key, amount)
            
        except Exception as e:
            self.logger.error(f"Cache decrement error for key '{key}': {str(e)}")
            return 0
    
    # Hash Operations
    def hget(self, name: str, key: str) -> Any:
        """Get field from hash"""
        try:
            full_name = self._build_key(name)
            value = self.redis_client.hget(full_name, key)
            return self._deserialize_value(value) if value else None
            
        except Exception as e:
            self.logger.error(f"Hash get error for '{name}.{key}': {str(e)}")
            return None
    
    def hset(self, name: str, key: str, value: Any) -> bool:
        """Set field in hash"""
        try:
            full_name = self._build_key(name)
            serialized_value = self._serialize_value(value)
            return bool(self.redis_client.hset(full_name, key, serialized_value))
            
        except Exception as e:
            self.logger.error(f"Hash set error for '{name}.{key}': {str(e)}")
            return False
    
    def hgetall(self, name: str) -> Dict:
        """Get all fields from hash"""
        try:
            full_name = self._build_key(name)
            hash_data = self.redis_client.hgetall(full_name)
            
            # Deserialize all values
            result = {}
            for key, value in hash_data.items():
                result[key] = self._deserialize_value(value)
            
            return result
            
        except Exception as e:
            self.logger.error(f"Hash getall error for '{name}': {str(e)}")
            return {}
    
    def hdel(self, name: str, *keys: str) -> int:
        """Delete fields from hash"""
        try:
            full_name = self._build_key(name)
            return self.redis_client.hdel(full_name, *keys)
            
        except Exception as e:
            self.logger.error(f"Hash delete error for '{name}': {str(e)}")
            return 0
    
    # List Operations (Queues)
    def lpush(self, name: str, *values: Any) -> int:
        """Push values to left of list"""
        try:
            full_name = self._build_key(name)
            serialized_values = [self._serialize_value(v) for v in values]
            result = self.redis_client.lpush(full_name, *serialized_values)
            
            with self.lock:
                self.metrics['queue_pushes'] += len(values)
                self.metrics['operations_total'] += 1
            
            return result
            
        except Exception as e:
            self.logger.error(f"List push error for '{name}': {str(e)}")
            return 0
    
    def rpush(self, name: str, *values: Any) -> int:
        """Push values to right of list"""
        try:
            full_name = self._build_key(name)
            serialized_values = [self._serialize_value(v) for v in values]
            result = self.redis_client.rpush(full_name, *serialized_values)
            
            with self.lock:
                self.metrics['queue_pushes'] += len(values)
                self.metrics['operations_total'] += 1
            
            return result
            
        except Exception as e:
            self.logger.error(f"List push error for '{name}': {str(e)}")
            return 0
    
    def lpop(self, name: str) -> Any:
        """Pop value from left of list"""
        try:
            full_name = self._build_key(name)
            value = self.redis_client.lpop(full_name)
            
            with self.lock:
                self.metrics['queue_pops'] += 1
                self.metrics['operations_total'] += 1
            
            return self._deserialize_value(value) if value else None
            
        except Exception as e:
            self.logger.error(f"List pop error for '{name}': {str(e)}")
            return None
    
    def rpop(self, name: str) -> Any:
        """Pop value from right of list"""
        try:
            full_name = self._build_key(name)
            value = self.redis_client.rpop(full_name)
            
            with self.lock:
                self.metrics['queue_pops'] += 1
                self.metrics['operations_total'] += 1
            
            return self._deserialize_value(value) if value else None
            
        except Exception as e:
            self.logger.error(f"List pop error for '{name}': {str(e)}")
            return None
    
    def blpop(self, names: List[str], timeout: int = 0) -> Optional[tuple]:
        """Blocking pop from left of lists"""
        try:
            full_names = [self._build_key(name) for name in names]
            result = self.redis_client.blpop(full_names, timeout)
            
            if result:
                name, value = result
                # Remove prefix from name
                original_name = name.replace(self.cache_config['key_prefix'], '')
                deserialized_value = self._deserialize_value(value)
                
                with self.lock:
                    self.metrics['queue_pops'] += 1
                    self.metrics['operations_total'] += 1
                
                return (original_name, deserialized_value)
            
            return None
            
        except Exception as e:
            self.logger.error(f"Blocking list pop error: {str(e)}")
            return None
    
    def llen(self, name: str) -> int:
        """Get length of list"""
        try:
            full_name = self._build_key(name)
            return self.redis_client.llen(full_name)
            
        except Exception as e:
            self.logger.error(f"List length error for '{name}': {str(e)}")
            return 0
    
    # Set Operations
    def sadd(self, name: str, *values: Any) -> int:
        """Add values to set"""
        try:
            full_name = self._build_key(name)
            serialized_values = [self._serialize_value(v) for v in values]
            return self.redis_client.sadd(full_name, *serialized_values)
            
        except Exception as e:
            self.logger.error(f"Set add error for '{name}': {str(e)}")
            return 0
    
    def srem(self, name: str, *values: Any) -> int:
        """Remove values from set"""
        try:
            full_name = self._build_key(name)
            serialized_values = [self._serialize_value(v) for v in values]
            return self.redis_client.srem(full_name, *serialized_values)
            
        except Exception as e:
            self.logger.error(f"Set remove error for '{name}': {str(e)}")
            return 0
    
    def smembers(self, name: str) -> set:
        """Get all members of set"""
        try:
            full_name = self._build_key(name)
            members = self.redis_client.smembers(full_name)
            return {self._deserialize_value(m) for m in members}
            
        except Exception as e:
            self.logger.error(f"Set members error for '{name}': {str(e)}")
            return set()
    
    def sismember(self, name: str, value: Any) -> bool:
        """Check if value is member of set"""
        try:
            full_name = self._build_key(name)
            serialized_value = self._serialize_value(value)
            return bool(self.redis_client.sismember(full_name, serialized_value))
            
        except Exception as e:
            self.logger.error(f"Set ismember error for '{name}': {str(e)}")
            return False
    
    # Pub/Sub Operations
    def publish(self, channel: str, message: Any) -> int:
        """Publish message to channel"""
        try:
            full_channel = self._build_key(channel)
            serialized_message = self._serialize_value(message)
            return self.redis_client.publish(full_channel, serialized_message)
            
        except Exception as e:
            self.logger.error(f"Publish error for channel '{channel}': {str(e)}")
            return 0
    
    def subscribe(self, *channels: str):
        """Subscribe to channels"""
        try:
            full_channels = [self._build_key(channel) for channel in channels]
            pubsub = self.redis_client.pubsub()
            pubsub.subscribe(*full_channels)
            return pubsub
            
        except Exception as e:
            self.logger.error(f"Subscribe error: {str(e)}")
            return None
    
    # Queue Management
    def enqueue_job(self, queue_name: str, job_data: Dict, priority: str = 'normal') -> bool:
        """Enqueue job for background processing"""
        try:
            job = {
                'id': self._generate_job_id(),
                'queue': queue_name,
                'priority': priority,
                'data': job_data,
                'created_at': datetime.now().isoformat(),
                'attempts': 0,
                'max_retries': self.queue_config['max_retries']
            }
            
            # Add to priority queue
            queue_key = f"queue:{priority}:{queue_name}"
            return bool(self.lpush(queue_key, job))
            
        except Exception as e:
            self.logger.error(f"Job enqueue error: {str(e)}")
            return False
    
    def dequeue_job(self, queue_names: List[str], timeout: int = 10) -> Optional[Dict]:
        """Dequeue job from queues (priority order)"""
        try:
            # Build queue keys in priority order
            queue_keys = []
            for priority in self.queue_config['priority_queues']:
                for queue_name in queue_names:
                    queue_keys.append(f"queue:{priority}:{queue_name}")
            
            # Blocking pop from queues
            result = self.blpop(queue_keys, timeout)
            
            if result:
                queue_key, job_data = result
                return job_data
            
            return None
            
        except Exception as e:
            self.logger.error(f"Job dequeue error: {str(e)}")
            return None
    
    def requeue_job(self, job: Dict, delay: int = None) -> bool:
        """Requeue failed job with retry logic"""
        try:
            job['attempts'] += 1
            
            if job['attempts'] >= job['max_retries']:
                # Move to dead letter queue
                return self._move_to_dead_letter_queue(job)
            
            # Calculate retry delay
            retry_delay = delay or (self.queue_config['retry_delay'] * job['attempts'])
            
            # Schedule for retry
            retry_time = datetime.now() + timedelta(seconds=retry_delay)
            job['retry_at'] = retry_time.isoformat()
            
            # Add to delayed queue
            delayed_queue_key = f"queue:delayed:{job['queue']}"
            return bool(self.lpush(delayed_queue_key, job))
            
        except Exception as e:
            self.logger.error(f"Job requeue error: {str(e)}")
            return False
    
    def get_queue_stats(self, queue_name: str) -> Dict:
        """Get queue statistics"""
        try:
            stats = {
                'queue_name': queue_name,
                'total_jobs': 0,
                'priority_breakdown': {},
                'delayed_jobs': 0,
                'failed_jobs': 0
            }
            
            # Count jobs by priority
            for priority in self.queue_config['priority_queues']:
                queue_key = f"queue:{priority}:{queue_name}"
                count = self.llen(queue_key)
                stats['priority_breakdown'][priority] = count
                stats['total_jobs'] += count
            
            # Count delayed jobs
            delayed_queue_key = f"queue:delayed:{queue_name}"
            stats['delayed_jobs'] = self.llen(delayed_queue_key)
            
            # Count failed jobs
            failed_queue_key = f"queue:failed:{queue_name}"
            stats['failed_jobs'] = self.llen(failed_queue_key)
            
            return stats
            
        except Exception as e:
            self.logger.error(f"Queue stats error: {str(e)}")
            return {'error': str(e)}
    
    # Rate Limiting
    def is_rate_limited(self, key: str, limit: int, window: int) -> bool:
        """Check if key is rate limited"""
        try:
            rate_key = f"rate_limit:{key}"
            full_key = self._build_key(rate_key)
            
            # Use sliding window rate limiting
            current_time = int(time.time())
            window_start = current_time - window
            
            # Remove old entries
            self.redis_client.zremrangebyscore(full_key, 0, window_start)
            
            # Count current requests
            current_count = self.redis_client.zcard(full_key)
            
            if current_count >= limit:
                return True
            
            # Add current request
            self.redis_client.zadd(full_key, {str(current_time): current_time})
            self.redis_client.expire(full_key, window)
            
            return False
            
        except Exception as e:
            self.logger.error(f"Rate limiting error for key '{key}': {str(e)}")
            return False
    
    # Distributed Locking
    @contextmanager
    def lock(self, name: str, timeout: int = 10, blocking_timeout: int = 10):
        """Distributed lock context manager"""
        lock_key = f"lock:{name}"
        full_key = self._build_key(lock_key)
        lock_value = str(time.time())
        
        acquired = False
        try:
            # Try to acquire lock
            end_time = time.time() + blocking_timeout
            while time.time() < end_time:
                if self.redis_client.set(full_key, lock_value, nx=True, ex=timeout):
                    acquired = True
                    break
                time.sleep(0.1)
            
            if not acquired:
                raise Exception(f"Could not acquire lock '{name}' within {blocking_timeout} seconds")
            
            yield
            
        finally:
            if acquired:
                # Release lock only if we own it
                lua_script = """
                if redis.call('get', KEYS[1]) == ARGV[1] then
                    return redis.call('del', KEYS[1])
                else
                    return 0
                end
                """
                self.redis_client.eval(lua_script, 1, full_key, lock_value)
    
    # Session Management
    def create_session(self, session_id: str, user_data: Dict, ttl: int = 3600) -> bool:
        """Create user session"""
        try:
            session_key = f"session:{session_id}"
            session_data = {
                'user_data': user_data,
                'created_at': datetime.now().isoformat(),
                'last_accessed': datetime.now().isoformat()
            }
            
            return self.set(session_key, session_data, ttl)
            
        except Exception as e:
            self.logger.error(f"Session creation error: {str(e)}")
            return False
    
    def get_session(self, session_id: str) -> Optional[Dict]:
        """Get session data"""
        try:
            session_key = f"session:{session_id}"
            session_data = self.get(session_key)
            
            if session_data:
                # Update last accessed time
                session_data['last_accessed'] = datetime.now().isoformat()
                self.set(session_key, session_data)
            
            return session_data
            
        except Exception as e:
            self.logger.error(f"Session retrieval error: {str(e)}")
            return None
    
    def delete_session(self, session_id: str) -> bool:
        """Delete session"""
        try:
            session_key = f"session:{session_id}"
            return self.delete(session_key)
            
        except Exception as e:
            self.logger.error(f"Session deletion error: {str(e)}")
            return False
    
    # Helper Methods
    def _build_key(self, key: str) -> str:
        """Build full cache key with prefix"""
        # Validate key length
        if len(key) > self.cache_config['max_key_length']:
            # Hash long keys
            key = hashlib.md5(key.encode()).hexdigest()
        
        return f"{self.cache_config['key_prefix']}{key}"
    
    def _serialize_value(self, value: Any) -> str:
        """Serialize value for storage"""
        try:
            if isinstance(value, (str, int, float, bool)):
                return json.dumps(value)
            else:
                # Use pickle for complex objects
                pickled = pickle.dumps(value)
                
                # Compress if large
                if len(pickled) > self.cache_config['compression_threshold']:
                    import gzip
                    pickled = gzip.compress(pickled)
                    return f"compressed:{pickled.hex()}"
                else:
                    return f"pickled:{pickled.hex()}"
                    
        except Exception as e:
            self.logger.error(f"Value serialization error: {str(e)}")
            return json.dumps(str(value))
    
    def _deserialize_value(self, value: str) -> Any:
        """Deserialize value from storage"""
        try:
            if value.startswith('compressed:'):
                import gzip
                hex_data = value[11:]  # Remove 'compressed:' prefix
                pickled = gzip.decompress(bytes.fromhex(hex_data))
                return pickle.loads(pickled)
            elif value.startswith('pickled:'):
                hex_data = value[8:]  # Remove 'pickled:' prefix
                pickled = bytes.fromhex(hex_data)
                return pickle.loads(pickled)
            else:
                return json.loads(value)
                
        except Exception as e:
            self.logger.error(f"Value deserialization error: {str(e)}")
            return value
    
    def _generate_job_id(self) -> str:
        """Generate unique job ID"""
        import uuid
        return f"job_{int(time.time())}_{str(uuid.uuid4())[:8]}"
    
    def _move_to_dead_letter_queue(self, job: Dict) -> bool:
        """Move job to dead letter queue"""
        try:
            job['failed_at'] = datetime.now().isoformat()
            job['status'] = 'failed'
            
            dead_letter_key = f"queue:failed:{job['queue']}"
            return bool(self.lpush(dead_letter_key, job))
            
        except Exception as e:
            self.logger.error(f"Dead letter queue error: {str(e)}")
            return False
    
    def _initialize_queues(self):
        """Initialize queue system"""
        try:
            # Create queue monitoring keys
            for priority in self.queue_config['priority_queues']:
                queue_info_key = f"queue_info:{priority}"
                queue_info = {
                    'priority': priority,
                    'created_at': datetime.now().isoformat(),
                    'status': 'active'
                }
                self.set(queue_info_key, queue_info)
            
            self.logger.info("Queue system initialized")
            
        except Exception as e:
            self.logger.error(f"Queue initialization error: {str(e)}")
    
    def _initialize_monitoring(self):
        """Initialize Redis monitoring"""
        try:
            # Start monitoring thread
            monitoring_thread = threading.Thread(target=self._monitor_performance, daemon=True)
            monitoring_thread.start()
            
            self.logger.info("Redis monitoring initialized")
            
        except Exception as e:
            self.logger.error(f"Monitoring initialization error: {str(e)}")
    
    def _monitor_performance(self):
        """Monitor Redis performance"""
        while self.status == 'healthy':
            try:
                # Check Redis connection
                self.redis_client.ping()
                
                # Monitor memory usage
                info = self.redis_client.info('memory')
                used_memory = info.get('used_memory', 0)
                max_memory = info.get('maxmemory', 0)
                
                if max_memory > 0 and used_memory / max_memory > 0.9:
                    self.logger.warning(f"Redis memory usage high: {used_memory/max_memory*100:.1f}%")
                
                # Monitor slow operations
                if self.metrics['operations_total'] > 0:
                    hit_ratio = self.metrics['cache_hits'] / (self.metrics['cache_hits'] + self.metrics['cache_misses']) * 100
                    if hit_ratio < 50:
                        self.logger.warning(f"Low cache hit ratio: {hit_ratio:.1f}%")
                
                # Sleep for monitoring interval
                time.sleep(30)
                
            except Exception as e:
                self.logger.error(f"Performance monitoring error: {str(e)}")
                self.metrics['connection_errors'] += 1
                time.sleep(30)
    
    # Utility Methods
    def flush_all(self) -> bool:
        """Flush all data (use with caution)"""
        try:
            self.redis_client.flushdb()
            self.logger.warning("Redis database flushed")
            return True
            
        except Exception as e:
            self.logger.error(f"Flush error: {str(e)}")
            return False
    
    def get_info(self) -> Dict:
        """Get Redis server information"""
        try:
            return self.redis_client.info()
            
        except Exception as e:
            self.logger.error(f"Info retrieval error: {str(e)}")
            return {}
    
    def health_check(self) -> Dict:
        """Perform comprehensive health check"""
        try:
            health_status = {
                'status': self.status,
                'connection': 'healthy',
                'memory_usage': 'normal',
                'performance': 'good',
                'metrics': self.get_metrics(),
                'timestamp': datetime.now().isoformat()
            }
            
            # Test connection
            try:
                self.redis_client.ping()
            except:
                health_status['connection'] = 'unhealthy'
                health_status['status'] = 'unhealthy'
            
            # Check memory usage
            try:
                info = self.redis_client.info('memory')
                used_memory = info.get('used_memory', 0)
                max_memory = info.get('maxmemory', 0)
                
                if max_memory > 0:
                    memory_ratio = used_memory / max_memory
                    if memory_ratio > 0.9:
                        health_status['memory_usage'] = 'critical'
                        health_status['status'] = 'degraded'
                    elif memory_ratio > 0.7:
                        health_status['memory_usage'] = 'high'
            except:
                health_status['memory_usage'] = 'unknown'
            
            # Check performance
            total_ops = self.metrics['cache_hits'] + self.metrics['cache_misses']
            if total_ops > 0:
                hit_ratio = self.metrics['cache_hits'] / total_ops * 100
                if hit_ratio < 30:
                    health_status['performance'] = 'poor'
                elif hit_ratio < 60:
                    health_status['performance'] = 'fair'
            
            return health_status
            
        except Exception as e:
            self.logger.error(f"Health check error: {str(e)}")
            return {
                'status': 'error',
                'error': str(e),
                'timestamp': datetime.now().isoformat()
            }

if __name__ == "__main__":
    # Example usage and testing
    import sys
    
    # Setup logging
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    
    # Initialize Redis manager
    redis_manager = RedisManager()
    
    try:
        # Initialize Redis
        redis_manager.initialize()
        
        # Test basic operations
        redis_manager.set('test_key', 'test_value')
        value = redis_manager.get('test_key')
        print(f"Test value: {value}")
        
        # Test queue operations
        redis_manager.enqueue_job('test_queue', {'task': 'test_task'}, 'high')
        job = redis_manager.dequeue_job(['test_queue'], 1)
        print(f"Dequeued job: {job}")
        
        # Get metrics
        metrics = redis_manager.get_metrics()
        print(f"Redis Metrics: {metrics}")
        
        # Perform health check
        health = redis_manager.health_check()
        print(f"Redis Health: {health}")
        
    except Exception as e:
        print(f"Redis manager test failed: {str(e)}")
        sys.exit(1)
    
    finally:
        # Shutdown
        redis_manager.shutdown()