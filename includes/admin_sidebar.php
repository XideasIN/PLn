<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="sidebar-header mb-4">
            <div class="d-flex align-items-center">
                <div class="logo-icon me-2">
                    <i class="fas fa-tachometer-alt text-primary"></i>
                </div>
                <h5 class="mb-0 text-dark">Admin Panel</h5>
            </div>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'index') ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-home me-2"></i>
                    Dashboard
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'applications') ? 'active' : ''; ?>" href="applications.php">
                    <i class="fas fa-file-alt me-2"></i>
                    Applications
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'users') ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-users me-2"></i>
                    User Management
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'funding_management') ? 'active' : ''; ?>" href="funding_management.php">
                    <i class="fas fa-money-check-alt me-2"></i>
                    Funding Management
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'document-management') ? 'active' : ''; ?>" href="document-management.php">
                    <i class="fas fa-folder me-2"></i>
                    Document Management
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'email-management') ? 'active' : ''; ?>" href="email-management.php">
                    <i class="fas fa-envelope me-2"></i>
                    Email Management
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'content-management') ? 'active' : ''; ?>" href="content-management.php">
                    <i class="fas fa-file-text me-2"></i>
                    Content Management
                </a>
            </li>
            
            <!-- AI & Automation Section -->
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                    <span>AI & Automation</span>
                </h6>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'chatbot-management') ? 'active' : ''; ?>" href="chatbot-management.php">
                    <i class="fas fa-robot me-2"></i>
                    Chatbot Management
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'ai-learning') ? 'active' : ''; ?>" href="ai-learning.php">
                    <i class="fas fa-brain me-2"></i>
                    AI Learning System
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'autonomous-dashboard') ? 'active' : ''; ?>" href="autonomous-dashboard.php">
                    <i class="fas fa-cogs me-2"></i>
                    Autonomous Business
                </a>
            </li>
            
            <!-- System Section -->
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                    <span>System</span>
                </h6>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'system-settings') ? 'active' : ''; ?>" href="system-settings.php">
                    <i class="fas fa-cog me-2"></i>
                    System Settings
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'holidays') ? 'active' : ''; ?>" href="holidays.php">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Holiday Management
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'audit-logs') ? 'active' : ''; ?>" href="audit-logs.php">
                    <i class="fas fa-history me-2"></i>
                    Audit Logs
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'system-health') ? 'active' : ''; ?>" href="system-health.php">
                    <i class="fas fa-heartbeat me-2"></i>
                    System Health
                </a>
            </li>
        </ul>
        
        <!-- User Info & Logout -->
        <div class="mt-auto pt-3 border-top">
            <div class="px-3 py-2">
                <div class="d-flex align-items-center mb-2">
                    <div class="avatar-sm me-2">
                        <i class="fas fa-user-shield text-primary"></i>
                    </div>
                    <div>
                        <small class="text-muted">Logged in as</small><br>
                        <strong class="text-dark"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator'); ?></strong>
                    </div>
                </div>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm w-100">
                    <i class="fas fa-sign-out-alt me-1"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<style>
.sidebar {
    min-height: 100vh;
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
}

.sidebar .nav-link {
    color: #333;
    padding: 0.75rem 1rem;
    border-radius: 0.375rem;
    margin: 0.125rem 0.5rem;
    transition: all 0.2s ease-in-out;
}

.sidebar .nav-link:hover {
    color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.1);
}

.sidebar .nav-link.active {
    color: #fff;
    background-color: #0d6efd;
}

.sidebar .nav-link i {
    width: 16px;
    text-align: center;
}

.sidebar-heading {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(13, 110, 253, 0.1);
    border-radius: 50%;
}

@media (max-width: 767.98px) {
    .sidebar {
        position: fixed;
        top: 0;
        left: -100%;
        z-index: 1000;
        width: 280px;
        height: 100vh;
        transition: left 0.3s ease-in-out;
        background-color: #fff;
    }
    
    .sidebar.show {
        left: 0;
    }
}
</style>