<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/style.css'); ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">🎯</div>
                    <span><?php echo APP_NAME; ?></span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <?php foreach($navigation as $section): ?>
                <div class="nav-section">
                    <div class="nav-section-header"><?php echo $section['section']; ?></div>
                    <?php foreach($section['items'] as $index => $item): ?>
                    <a href="<?php echo base_url($item['href']); ?>" class="nav-item">
                        <i data-lucide="<?php echo $item['icon']; ?>" class="icon"></i>
                        <span><?php echo $item['name']; ?></span>
                        <?php if($index === 0 && count($section['items']) > 1): ?>
                        <button class="nav-expand">
                            <i data-lucide="chevron-down" style="width: 12px; height: 12px;"></i>
                        </button>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </nav>
        </div>
        
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-title">
                    <h1>Workspace Insights</h1>
                    <span class="changes-badge">8 Changes</span>
                </div>
                
                <div class="header-actions">
                    <div class="search-container">
                        <i data-lucide="search" class="search-icon"></i>
                        <input type="text" class="search-input" placeholder="Search something...">
                    </div>
                    
                    <div class="header-filters">
                        <div class="filter-group">
                            <span>Filter by:</span>
                            <select class="filter-select" data-filter="plan">
                                <option value="all">Plan</option>
                                <option value="active">Active Plans</option>
                                <option value="inactive">Inactive Plans</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <span>Filter by:</span>
                            <select class="filter-select" data-filter="team">
                                <option value="all">Team</option>
                                <option value="development">Development</option>
                                <option value="marketing">Marketing</option>
                                <option value="sales">Sales</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="user-menu">
                        <div class="notification-icon">
                            <i data-lucide="bell"></i>
                            <span class="notification-badge"></span>
                        </div>
                        <div class="user-avatar">A</div>
                    </div>
                </div>
            </header>