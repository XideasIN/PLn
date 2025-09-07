// Consumer Loan Admin Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    initCharts();
    initAuditScore();
    initAnimations();
});

// Sidebar functionality
function initSidebar() {
    // Set active navigation item
    setActiveNavItem();
    
    // Mobile sidebar toggle
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    // Navigation expand/collapse
    const expandButtons = document.querySelectorAll('.nav-expand');
    expandButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const section = this.closest('.nav-section');
            const items = section.querySelectorAll('.nav-item');
            
            items.forEach((item, index) => {
                if (index > 0) { // Skip the main section item
                    item.style.display = item.style.display === 'none' ? 'flex' : 'none';
                }
            });
            
            this.style.transform = this.style.transform === 'rotate(180deg)' ? 'rotate(0deg)' : 'rotate(180deg)';
        });
    });
}

function setActiveNavItem() {
    const currentPath = window.location.pathname;
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href && (currentPath.includes(href) || (href === 'index.php' && currentPath.endsWith('/LBackEnd-2/')))) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}

// Initialize audit score circle
function initAuditScore() {
    const circle = document.querySelector('.circle-progress');
    if (!circle) return;
    
    const score = 75; // Overall audit score
    const circumference = 2 * Math.PI * 52; // radius is 52
    const progress = (score / 100) * circumference;
    
    circle.style.strokeDasharray = `${circumference}`;
    circle.style.strokeDashoffset = `${circumference - progress}`;
    
    // Animate the circle
    setTimeout(() => {
        circle.style.transition = 'stroke-dashoffset 1s ease-in-out';
        circle.style.strokeDashoffset = `${circumference - progress}`;
    }, 500);
}

// Initialize charts
function initCharts() {
    initActivityCharts();
    initProgressBars();
}

function initActivityCharts() {
    const activities = [
        {
            selector: '.check-in-chart',
            data: [15, 25, 20, 30, 18, 22, 16],
            color: '#ef4444',
            maxValue: 30
        },
        {
            selector: '.key-results-chart',
            data: [20, 35, 25, 40, 30, 38, 28],
            color: '#8b5cf6',
            maxValue: 40
        },
        {
            selector: '.tasks-chart',
            data: [18, 28, 22, 32, 26, 30, 24],
            color: '#3b82f6',
            maxValue: 35
        }
    ];
    
    activities.forEach(activity => {
        const container = document.querySelector(activity.selector);
        if (!container) return;
        
        const chartContainer = container.querySelector('.chart-container');
        if (!chartContainer) return;
        
        // Clear existing bars
        chartContainer.innerHTML = '';
        
        activity.data.forEach((value, index) => {
            const bar = document.createElement('div');
            bar.className = 'chart-bar';
            bar.style.backgroundColor = activity.color;
            bar.style.height = `${(value / activity.maxValue) * 100}%`;
            bar.style.animationDelay = `${index * 0.1}s`;
            
            // Add hover effect
            bar.addEventListener('mouseenter', function() {
                this.style.transform = 'scaleY(1.1)';
            });
            
            bar.addEventListener('mouseleave', function() {
                this.style.transform = 'scaleY(1)';
            });
            
            chartContainer.appendChild(bar);
        });
    });
}

function initProgressBars() {
    const progressBars = document.querySelectorAll('.progress-fill');
    
    progressBars.forEach((bar, index) => {
        const percentage = bar.getAttribute('data-percentage') || Math.random() * 100;
        
        setTimeout(() => {
            bar.style.width = `${percentage}%`;
        }, index * 200);
    });
}

// Initialize animations
function initAnimations() {
    // Intersection Observer for fade-in animations
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    // Observe all metric cards and sections
    document.querySelectorAll('.metric-card, .audit-overview, .audit-details, .activities-section').forEach(el => {
        observer.observe(el);
    });
}

// Search functionality
function initSearch() {
    const searchInput = document.querySelector('.search-input');
    if (!searchInput) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            performSearch(e.target.value);
        }, 300);
    });
}

function performSearch(query) {
    console.log('Searching for:', query);
    // Implement search functionality here
}

// Utility functions
function formatNumber(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
}

function formatPercentage(value, total) {
    return Math.round((value / total) * 100);
}

// Toast notifications
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <span>${message}</span>
            <button class="toast-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 5000);
}

// Filter functionality
function initFilters() {
    const filterSelects = document.querySelectorAll('.filter-select');
    
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            const filterType = this.getAttribute('data-filter');
            const filterValue = this.value;
            
            applyFilter(filterType, filterValue);
        });
    });
}

function applyFilter(type, value) {
    console.log(`Applying ${type} filter:`, value);
    // Implement filtering logic here
}

// Dynamic data updates
function updateMetrics(newData) {
    const metricCards = document.querySelectorAll('.metric-card');
    
    metricCards.forEach((card, index) => {
        if (newData[index]) {
            const numberEl = card.querySelector('.metric-number');
            const unitEl = card.querySelector('.metric-unit');
            
            if (numberEl) numberEl.textContent = newData[index].value;
            if (unitEl) unitEl.textContent = newData[index].unit;
            
            // Animate the change
            card.style.transform = 'scale(1.05)';
            setTimeout(() => {
                card.style.transform = 'scale(1)';
            }, 200);
        }
    });
}

// Export functions for external use
window.LoanAdmin = {
    updateMetrics,
    showToast,
    formatNumber,
    formatPercentage
};

// Add CSS for toast notifications
const toastStyles = `
.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    padding: 16px;
    z-index: 1000;
    transform: translateX(400px);
    transition: transform 0.3s ease;
    max-width: 300px;
}

.toast.show {
    transform: translateX(0);
}

.toast-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.toast-close {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: #64748b;
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.toast-info { border-left: 4px solid #3b82f6; }
.toast-success { border-left: 4px solid #10b981; }
.toast-warning { border-left: 4px solid #f59e0b; }
.toast-error { border-left: 4px solid #ef4444; }
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = toastStyles;
document.head.appendChild(styleSheet);