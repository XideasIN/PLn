/**
 * Email Workflow Management JavaScript
 * Handles admin interface interactions for email automation
 */

class EmailWorkflowManager {
    constructor() {
        this.templates = [];
        this.campaigns = [];
        this.analytics = {};
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadAnalytics();
        this.initializeCharts();
        
        // Set default datetime for campaign scheduling
        const now = new Date();
        now.setHours(now.getHours() + 1); // Default to 1 hour from now
        const datetimeInput = document.querySelector('input[name="scheduled_at"]');
        if (datetimeInput) {
            datetimeInput.value = now.toISOString().slice(0, 16);
        }
    }
    
    bindEvents() {
        // Template form submission
        const templateForm = document.getElementById('templateForm');
        if (templateForm) {
            templateForm.addEventListener('submit', (e) => this.handleTemplateSubmit(e));
        }
        
        // Tab switching
        const tabs = document.querySelectorAll('[data-bs-toggle="tab"]');
        tabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', (e) => this.handleTabSwitch(e));
        });
        
        // Real-time preview
        const bodyTemplate = document.getElementById('bodyTemplate');
        if (bodyTemplate) {
            bodyTemplate.addEventListener('input', () => this.updatePreview());
        }
        
        const subjectTemplate = document.getElementById('subjectTemplate');
        if (subjectTemplate) {
            subjectTemplate.addEventListener('input', () => this.updatePreview());
        }
    }
    
    handleTemplateSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const templateData = {
            action: 'save_template',
            template_id: formData.get('template_id'),
            template_name: formData.get('template_name'),
            subject_template: formData.get('subject_template'),
            body_template: formData.get('body_template'),
            trigger_type: formData.get('trigger_type'),
            delay_minutes: formData.get('delay_minutes'),
            priority: formData.get('priority'),
            from_email: formData.get('from_email'),
            from_name: formData.get('from_name'),
            is_active: formData.get('is_active') ? 1 : 0
        };
        
        // Validate template
        if (!this.validateTemplate(templateData)) {
            return;
        }
        
        // Show loading state
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        submitBtn.disabled = true;
        
        // Submit form (let PHP handle the actual submission)
        e.target.submit();
    }
    
    validateTemplate(templateData) {
        const errors = [];
        
        if (!templateData.template_name || templateData.template_name.trim().length < 3) {
            errors.push('Template name must be at least 3 characters long');
        }
        
        if (!templateData.subject_template || templateData.subject_template.trim().length < 5) {
            errors.push('Subject template must be at least 5 characters long');
        }
        
        if (!templateData.body_template || templateData.body_template.trim().length < 20) {
            errors.push('Email body must be at least 20 characters long');
        }
        
        if (templateData.from_email && !this.isValidEmail(templateData.from_email)) {
            errors.push('Please enter a valid from email address');
        }
        
        if (errors.length > 0) {
            this.showAlert('error', 'Validation Error', errors.join('<br>'));
            return false;
        }
        
        return true;
    }
    
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    handleTabSwitch(e) {
        const targetTab = e.target.getAttribute('data-bs-target');
        
        switch (targetTab) {
            case '#analytics':
                this.loadAnalytics();
                break;
            case '#campaigns':
                this.loadCampaigns();
                break;
        }
    }
    
    loadAnalytics() {
        fetch('../api/email-analytics.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_analytics'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.analytics = data.analytics;
                this.updateAnalyticsDisplay();
                this.updateCharts();
            }
        })
        .catch(error => {
            console.error('Error loading analytics:', error);
        });
    }
    
    loadCampaigns() {
        fetch('../api/email-campaigns.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_campaigns'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.campaigns = data.campaigns;
                this.updateCampaignsDisplay();
            }
        })
        .catch(error => {
            console.error('Error loading campaigns:', error);
        });
    }
    
    updateAnalyticsDisplay() {
        // Update template performance
        const performanceContainer = document.getElementById('templatePerformance');
        if (performanceContainer && this.analytics.template_performance) {
            let html = '';
            this.analytics.template_performance.forEach(template => {
                const successRate = template.total_sent > 0 
                    ? ((template.delivered / template.total_sent) * 100).toFixed(1)
                    : 0;
                
                html += `
                    <div class="mb-3 p-3 border rounded">
                        <h6>${template.template_name}</h6>
                        <div class="d-flex justify-content-between">
                            <small>Sent: ${template.total_sent}</small>
                            <small>Success: ${successRate}%</small>
                        </div>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar" style="width: ${successRate}%"></div>
                        </div>
                    </div>
                `;
            });
            performanceContainer.innerHTML = html;
        }
    }
    
    updateCampaignsDisplay() {
        // This would update the campaigns table if we're loading via AJAX
        // For now, the PHP handles the display
    }
    
    initializeCharts() {
        const ctx = document.getElementById('emailChart');
        if (ctx) {
            this.emailChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Emails Sent',
                        data: [],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Delivered',
                        data: [],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Failed',
                        data: [],
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        }
    }
    
    updateCharts() {
        if (this.emailChart && this.analytics.daily_stats) {
            const labels = this.analytics.daily_stats.map(stat => stat.date);
            const sentData = this.analytics.daily_stats.map(stat => stat.sent);
            const deliveredData = this.analytics.daily_stats.map(stat => stat.delivered);
            const failedData = this.analytics.daily_stats.map(stat => stat.failed);
            
            this.emailChart.data.labels = labels;
            this.emailChart.data.datasets[0].data = sentData;
            this.emailChart.data.datasets[1].data = deliveredData;
            this.emailChart.data.datasets[2].data = failedData;
            
            this.emailChart.update();
        }
    }
    
    updatePreview() {
        const subjectTemplate = document.getElementById('subjectTemplate')?.value || '';
        const bodyTemplate = document.getElementById('bodyTemplate')?.value || '';
        
        // Sample data for preview
        const sampleData = {
            '{{client_name}}': 'John Smith',
            '{{first_name}}': 'John',
            '{{last_name}}': 'Smith',
            '{{email}}': 'john.smith@example.com',
            '{{loan_amount}}': '$25,000',
            '{{reference_number}}': 'LF-2024-001',
            '{{company_name}}': 'LoanFlow Financial',
            '{{login_url}}': 'https://loanflow.com/login'
        };
        
        // Replace variables with sample data
        let previewSubject = subjectTemplate;
        let previewBody = bodyTemplate;
        
        Object.keys(sampleData).forEach(variable => {
            const regex = new RegExp(variable.replace(/[{}]/g, '\\$&'), 'g');
            previewSubject = previewSubject.replace(regex, sampleData[variable]);
            previewBody = previewBody.replace(regex, sampleData[variable]);
        });
        
        // Update preview (if preview container exists)
        const previewContainer = document.getElementById('emailPreview');
        if (previewContainer) {
            previewContainer.innerHTML = `
                <div class="email-preview">
                    <div class="email-header">
                        <strong>Subject:</strong> ${previewSubject}
                    </div>
                    <div class="email-body">
                        ${previewBody.replace(/\n/g, '<br>')}
                    </div>
                </div>
            `;
        }
    }
    
    showAlert(type, title, message) {
        const alertClass = type === 'error' ? 'alert-danger' : 'alert-success';
        const icon = type === 'error' ? 'fas fa-exclamation-triangle' : 'fas fa-check-circle';
        
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="${icon} me-2"></i>
                <strong>${title}:</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Insert alert at the top of the container
        const container = document.querySelector('.workflow-container');
        if (container) {
            const alertDiv = document.createElement('div');
            alertDiv.innerHTML = alertHtml;
            container.insertBefore(alertDiv.firstElementChild, container.firstElementChild);
        }
    }
}

// Global functions for template management
function editTemplate(templateId) {
    fetch('../api/email-templates.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_template&template_id=${templateId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const template = data.template;
            
            // Populate form fields
            document.getElementById('templateId').value = template.id;
            document.getElementById('templateName').value = template.template_name;
            document.getElementById('subjectTemplate').value = template.subject_template;
            document.getElementById('bodyTemplate').value = template.body_template;
            document.getElementById('triggerType').value = template.trigger_type;
            document.getElementById('delayMinutes').value = template.delay_minutes;
            document.getElementById('priority').value = template.priority;
            document.getElementById('fromEmail').value = template.from_email || '';
            document.getElementById('fromName').value = template.from_name || '';
            document.getElementById('isActive').checked = template.is_active == 1;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('templateModal'));
            modal.show();
        } else {
            alert('Error loading template: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading template');
    });
}

function previewTemplate(templateId) {
    fetch('../api/email-templates.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=preview_template&template_id=${templateId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Create preview modal
            const previewModal = document.createElement('div');
            previewModal.className = 'modal fade';
            previewModal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Email Preview</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="email-preview-container">
                                <div class="mb-3">
                                    <strong>Subject:</strong> ${data.preview.subject}
                                </div>
                                <div class="email-body-preview">
                                    ${data.preview.body.replace(/\n/g, '<br>')}
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(previewModal);
            const modal = new bootstrap.Modal(previewModal);
            modal.show();
            
            // Remove modal from DOM when hidden
            previewModal.addEventListener('hidden.bs.modal', () => {
                document.body.removeChild(previewModal);
            });
        } else {
            alert('Error loading preview: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading preview');
    });
}

function deleteTemplate(templateId) {
    if (confirm('Are you sure you want to delete this email template? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_template">
            <input type="hidden" name="template_id" value="${templateId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewCampaignDetails(campaignId) {
    fetch('../api/email-campaigns.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_campaign_details&campaign_id=${campaignId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const campaign = data.campaign;
            
            // Create details modal
            const detailsModal = document.createElement('div');
            detailsModal.className = 'modal fade';
            detailsModal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Campaign Details: ${campaign.campaign_name}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Subject:</strong> ${campaign.subject}</p>
                                    <p><strong>Recipients:</strong> ${campaign.recipient_count}</p>
                                    <p><strong>Scheduled:</strong> ${campaign.scheduled_at}</p>
                                    <p><strong>Status:</strong> <span class="badge bg-info">${campaign.status}</span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Sent:</strong> ${campaign.emails_sent || 0}</p>
                                    <p><strong>Delivered:</strong> ${campaign.emails_delivered || 0}</p>
                                    <p><strong>Failed:</strong> ${campaign.emails_failed || 0}</p>
                                    <p><strong>Created:</strong> ${campaign.created_at}</p>
                                </div>
                            </div>
                            <div class="mt-3">
                                <strong>Email Body:</strong>
                                <div class="border p-3 mt-2" style="max-height: 300px; overflow-y: auto;">
                                    ${campaign.body.replace(/\n/g, '<br>')}
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(detailsModal);
            const modal = new bootstrap.Modal(detailsModal);
            modal.show();
            
            // Remove modal from DOM when hidden
            detailsModal.addEventListener('hidden.bs.modal', () => {
                document.body.removeChild(detailsModal);
            });
        } else {
            alert('Error loading campaign details: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading campaign details');
    });
}

function insertVariable(variable) {
    const bodyTemplate = document.getElementById('bodyTemplate');
    if (bodyTemplate) {
        const cursorPos = bodyTemplate.selectionStart;
        const textBefore = bodyTemplate.value.substring(0, cursorPos);
        const textAfter = bodyTemplate.value.substring(cursorPos);
        
        bodyTemplate.value = textBefore + variable + textAfter;
        bodyTemplate.focus();
        bodyTemplate.setSelectionRange(cursorPos + variable.length, cursorPos + variable.length);
        
        // Trigger input event to update preview
        bodyTemplate.dispatchEvent(new Event('input'));
    }
}

// Initialize the email workflow manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.emailWorkflowManager = new EmailWorkflowManager();
});