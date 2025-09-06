<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>QuickFunds Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom Admin CSS -->
    <link href="../assets/css/admin.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar-brand {
            font-weight: 600;
            color: #fff !important;
        }
        
        .navbar-dark {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        
        .main-content {
            margin-left: 0;
            transition: margin-left 0.3s ease-in-out;
        }
        
        @media (min-width: 768px) {
            .main-content {
                margin-left: 16.66667%;
            }
        }
        
        @media (min-width: 992px) {
            .main-content {
                margin-left: 16.66667%;
            }
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
        }
        
        .page-header h1 {
            margin: 0;
            font-weight: 300;
        }
        
        .page-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: box-shadow 0.15s ease-in-out;
        }
        
        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background-color: rgba(0, 0, 0, 0.03);
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            border-radius: 0.75rem 0.75rem 0 0 !important;
        }
        
        .btn {
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-1px);
        }
        
        .table {
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }
        
        .badge {
            font-weight: 500;
            padding: 0.5em 0.75em;
        }
        
        .alert {
            border: none;
            border-radius: 0.75rem;
        }
        
        .modal-content {
            border: none;
            border-radius: 1rem;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 1rem 1rem 0 0;
        }
        
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 1px solid #ced4da;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .border-left-primary {
            border-left: 0.25rem solid #667eea !important;
        }
        
        .border-left-success {
            border-left: 0.25rem solid #28a745 !important;
        }
        
        .border-left-info {
            border-left: 0.25rem solid #17a2b8 !important;
        }
        
        .border-left-warning {
            border-left: 0.25rem solid #ffc107 !important;
        }
        
        .text-gray-800 {
            color: #5a5c69 !important;
        }
        
        .text-gray-300 {
            color: #dddfeb !important;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-tachometer-alt me-2"></i>
                QuickFunds Admin
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <!-- Notifications -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge" id="notificationCount">0</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" id="notificationsList">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-muted" href="#">No new notifications</a></li>
                        </ul>
                    </li>
                    
                    <!-- Quick Actions -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="quickActionsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-plus-circle"></i>
                            Quick Actions
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="quickActionsDropdown">
                            <li><a class="dropdown-item" href="applications.php"><i class="fas fa-file-alt me-2"></i>View Applications</a></li>
                            <li><a class="dropdown-item" href="users.php"><i class="fas fa-user-plus me-2"></i>Add User</a></li>
                            <li><a class="dropdown-item" href="email-management.php"><i class="fas fa-envelope me-2"></i>Send Email</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="system-settings.php"><i class="fas fa-cog me-2"></i>System Settings</a></li>
                        </ul>
                    </li>
                    
                    <!-- User Profile -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="system-settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Add padding to account for fixed navbar -->
    <div style="padding-top: 76px;"></div>
    
    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    
    <script>
        // Initialize notifications
        $(document).ready(function() {
            loadNotifications();
            
            // Refresh notifications every 30 seconds
            setInterval(loadNotifications, 30000);
        });
        
        function loadNotifications() {
            // This would typically fetch from an API endpoint
            // For now, we'll simulate with static data
            const notifications = [
                // Add notification logic here
            ];
            
            updateNotificationBadge(notifications.length);
            updateNotificationsList(notifications);
        }
        
        function updateNotificationBadge(count) {
            const badge = document.getElementById('notificationCount');
            if (badge) {
                badge.textContent = count;
                badge.style.display = count > 0 ? 'flex' : 'none';
            }
        }
        
        function updateNotificationsList(notifications) {
            const list = document.getElementById('notificationsList');
            if (list && notifications.length === 0) {
                list.innerHTML = `
                    <li><h6 class="dropdown-header">Notifications</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-muted" href="#">No new notifications</a></li>
                `;
            }
        }
        
        // Global utility functions
        function showAlert(message, type = 'info') {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            // Insert at the top of main content
            const mainContent = document.querySelector('.main-content, main, .container-fluid');
            if (mainContent) {
                mainContent.insertAdjacentHTML('afterbegin', alertHtml);
            }
        }
        
        function showLoading(element) {
            if (element) {
                element.innerHTML = '<div class="text-center"><div class="loading-spinner"></div> Loading...</div>';
            }
        }
        
        function hideLoading() {
            // Remove loading spinners
            document.querySelectorAll('.loading-spinner').forEach(spinner => {
                spinner.closest('.text-center')?.remove();
            });
        }
    </script>