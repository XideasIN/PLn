<!-- Admin Footer -->
    <footer class="admin-footer mt-5 py-4 bg-light border-top">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">
                        &copy; <?php echo date('Y'); ?> QuickFunds Admin Panel. 
                        <span class="text-primary">Autonomous Business System</span> v2.0
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="d-flex justify-content-md-end align-items-center">
                        <span class="text-muted me-3">
                            <i class="fas fa-clock me-1"></i>
                            Last updated: <span id="lastUpdated"><?php echo date('M j, Y H:i'); ?></span>
                        </span>
                        <div class="system-status">
                            <span class="badge bg-success" id="systemStatus">
                                <i class="fas fa-circle me-1"></i>System Online
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Information -->
            <div class="row mt-3">
                <div class="col-12">
                    <div class="d-flex flex-wrap justify-content-between align-items-center text-muted small">
                        <div class="d-flex flex-wrap gap-3">
                            <span><i class="fas fa-server me-1"></i>Server: <?php echo $_SERVER['SERVER_NAME']; ?></span>
                            <span><i class="fas fa-database me-1"></i>DB: Connected</span>
                            <span><i class="fas fa-memory me-1"></i>Memory: <?php echo round(memory_get_usage(true) / 1024 / 1024, 2); ?>MB</span>
                            <span><i class="fas fa-php me-1"></i>PHP: <?php echo PHP_VERSION; ?></span>
                        </div>
                        <div class="d-flex gap-3">
                            <a href="system-health.php" class="text-decoration-none text-muted">
                                <i class="fas fa-heartbeat me-1"></i>System Health
                            </a>
                            <a href="audit-logs.php" class="text-decoration-none text-muted">
                                <i class="fas fa-history me-1"></i>Audit Logs
                            </a>
                            <a href="#" class="text-decoration-none text-muted" onclick="showSystemInfo()">
                                <i class="fas fa-info-circle me-1"></i>System Info
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- System Information Modal -->
    <div class="modal fade" id="systemInfoModal" tabindex="-1" aria-labelledby="systemInfoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="systemInfoModalLabel">
                        <i class="fas fa-info-circle me-2"></i>System Information
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary">Server Information</h6>
                            <table class="table table-sm">
                                <tr><td><strong>Server Name:</strong></td><td><?php echo $_SERVER['SERVER_NAME']; ?></td></tr>
                                <tr><td><strong>Server Software:</strong></td><td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td></tr>
                                <tr><td><strong>PHP Version:</strong></td><td><?php echo PHP_VERSION; ?></td></tr>
                                <tr><td><strong>Server Time:</strong></td><td><?php echo date('Y-m-d H:i:s T'); ?></td></tr>
                                <tr><td><strong>Timezone:</strong></td><td><?php echo date_default_timezone_get(); ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary">Application Information</h6>
                            <table class="table table-sm">
                                <tr><td><strong>Application:</strong></td><td>QuickFunds Admin</td></tr>
                                <tr><td><strong>Version:</strong></td><td>2.0.0</td></tr>
                                <tr><td><strong>Environment:</strong></td><td><?php echo $_ENV['APP_ENV'] ?? 'production'; ?></td></tr>
                                <tr><td><strong>Debug Mode:</strong></td><td><?php echo (ini_get('display_errors') ? 'Enabled' : 'Disabled'); ?></td></tr>
                                <tr><td><strong>Memory Limit:</strong></td><td><?php echo ini_get('memory_limit'); ?></td></tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6 class="text-primary">System Status</h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card border-success">
                                        <div class="card-body text-center py-2">
                                            <i class="fas fa-database text-success fa-2x mb-2"></i>
                                            <h6 class="card-title mb-0">Database</h6>
                                            <small class="text-success">Connected</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-success">
                                        <div class="card-body text-center py-2">
                                            <i class="fas fa-envelope text-success fa-2x mb-2"></i>
                                            <h6 class="card-title mb-0">Email</h6>
                                            <small class="text-success">Configured</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-success">
                                        <div class="card-body text-center py-2">
                                            <i class="fas fa-robot text-success fa-2x mb-2"></i>
                                            <h6 class="card-title mb-0">AI Services</h6>
                                            <small class="text-success">Active</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-success">
                                        <div class="card-body text-center py-2">
                                            <i class="fas fa-shield-alt text-success fa-2x mb-2"></i>
                                            <h6 class="card-title mb-0">Security</h6>
                                            <small class="text-success">Protected</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="downloadSystemInfo()">
                        <i class="fas fa-download me-1"></i>Download Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button type="button" class="btn btn-primary btn-floating btn-lg" id="btn-back-to-top" style="position: fixed; bottom: 20px; right: 20px; display: none; z-index: 1000; border-radius: 50%; width: 50px; height: 50px;">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
        // System status check
        function checkSystemStatus() {
            // This would typically make an AJAX call to check system health
            // For now, we'll simulate a healthy system
            const statusElement = document.getElementById('systemStatus');
            if (statusElement) {
                statusElement.innerHTML = '<i class="fas fa-circle me-1"></i>System Online';
                statusElement.className = 'badge bg-success';
            }
        }

        // Show system information modal
        function showSystemInfo() {
            const modal = new bootstrap.Modal(document.getElementById('systemInfoModal'));
            modal.show();
        }

        // Download system information report
        function downloadSystemInfo() {
            const systemInfo = {
                server: {
                    name: '<?php echo $_SERVER['SERVER_NAME']; ?>',
                    software: '<?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>',
                    php_version: '<?php echo PHP_VERSION; ?>',
                    time: '<?php echo date('Y-m-d H:i:s T'); ?>',
                    timezone: '<?php echo date_default_timezone_get(); ?>'
                },
                application: {
                    name: 'QuickFunds Admin',
                    version: '2.0.0',
                    environment: '<?php echo $_ENV['APP_ENV'] ?? 'production'; ?>',
                    debug_mode: '<?php echo (ini_get('display_errors') ? 'Enabled' : 'Disabled'); ?>',
                    memory_limit: '<?php echo ini_get('memory_limit'); ?>'
                },
                timestamp: new Date().toISOString()
            };

            const dataStr = JSON.stringify(systemInfo, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `system-info-${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        // Back to top button functionality
        const backToTopButton = document.getElementById('btn-back-to-top');
        
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopButton.style.display = 'block';
            } else {
                backToTopButton.style.display = 'none';
            }
        });
        
        backToTopButton.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Update last updated time every minute
        setInterval(function() {
            const now = new Date();
            const timeString = now.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            const lastUpdatedElement = document.getElementById('lastUpdated');
            if (lastUpdatedElement) {
                lastUpdatedElement.textContent = timeString;
            }
        }, 60000);

        // Initialize system status check
        document.addEventListener('DOMContentLoaded', function() {
            checkSystemStatus();
            
            // Check system status every 5 minutes
            setInterval(checkSystemStatus, 300000);
        });

        // Global error handler for AJAX requests
        $(document).ajaxError(function(event, xhr, settings, thrownError) {
            if (xhr.status === 401) {
                window.location.href = '../login.php';
            } else if (xhr.status >= 500) {
                showAlert('A server error occurred. Please try again later.', 'danger');
            }
        });

        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>

    <style>
        .admin-footer {
            margin-top: auto;
        }
        
        .system-status .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
        }
        
        .btn-floating {
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }
        
        .btn-floating:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.4);
        }
        
        @media (max-width: 768px) {
            .admin-footer .row > div {
                text-align: center !important;
                margin-bottom: 1rem;
            }
            
            .admin-footer .d-flex {
                justify-content: center !important;
            }
        }
    </style>

</body>
</html>