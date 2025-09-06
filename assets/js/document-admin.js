/**
 * Document Management Admin JavaScript
 * LoanFlow Personal Loan Management System
 * 
 * Handles document editing, permissions management, and analytics
 */

class DocumentManager {
    constructor() {
        this.currentDocument = null;
        this.previewMode = false;
        this.charts = {};
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeTabs();
        this.loadAnalytics();
    }

    bindEvents() {
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.switchTab(e.target.dataset.tab));
        });

        // Document actions
        document.querySelectorAll('.edit-document').forEach(btn => {
            btn.addEventListener('click', (e) => this.editDocument(e.target.dataset.id));
        });

        document.querySelectorAll('.preview-document').forEach(btn => {
            btn.addEventListener('click', (e) => this.previewDocument(e.target.dataset.id));
        });

        document.querySelectorAll('.document-stats').forEach(btn => {
            btn.addEventListener('click', (e) => this.showDocumentStats(e.target.dataset.id));
        });

        // Permission management
        document.querySelectorAll('.manage-permissions').forEach(btn => {
            btn.addEventListener('click', (e) => this.managePermissions(e.target.dataset.userId));
        });

        // Editor events
        const editorForm = document.getElementById('document-editor-form');
        if (editorForm) {
            editorForm.addEventListener('submit', (e) => this.saveDocument(e));
        }

        const previewToggle = document.getElementById('preview-toggle');
        if (previewToggle) {
            previewToggle.addEventListener('click', () => this.togglePreview());
        }

        const variablesHelp = document.getElementById('variables-help');
        if (variablesHelp) {
            variablesHelp.addEventListener('click', () => this.showVariablesHelp());
        }

        // Toolbar actions
        document.querySelectorAll('.toolbar-btn[data-action]').forEach(btn => {
            btn.addEventListener('click', (e) => this.handleToolbarAction(e.target.dataset.action));
        });

        // Modal events
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', (e) => this.closeModal(e.target.closest('.modal')));
        });

        // Content change for live preview
        const contentTextarea = document.getElementById('document-content');
        if (contentTextarea) {
            contentTextarea.addEventListener('input', () => this.updatePreview());
        }

        // Bulk permissions
        const bulkPermissionsBtn = document.getElementById('bulk-permissions-btn');
        if (bulkPermissionsBtn) {
            bulkPermissionsBtn.addEventListener('click', () => this.showBulkPermissions());
        }
    }

    switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

        // Update tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(`${tabName}-tab`).classList.add('active');

        // Load tab-specific data
        if (tabName === 'analytics') {
            this.loadAnalytics();
        }
    }

    async editDocument(documentId) {
        try {
            const response = await fetch('../api/documents.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_document&document_id=${documentId}`
            });

            const result = await response.json();
            if (result.success) {
                this.currentDocument = result.document;
                this.showDocumentEditor();
            } else {
                this.showAlert('Error loading document: ' + result.message, 'error');
            }
        } catch (error) {
            this.showAlert('Error loading document: ' + error.message, 'error');
        }
    }

    showDocumentEditor() {
        const modal = document.getElementById('document-editor-modal');
        const doc = this.currentDocument;

        // Populate form
        document.getElementById('edit-document-id').value = doc.id;
        document.getElementById('document-content').value = doc.content;
        document.getElementById('is-client-visible').checked = doc.is_client_visible == 1;
        document.getElementById('requires-download-permission').checked = doc.requires_download_permission == 1;

        // Update modal title
        modal.querySelector('.modal-header h2').textContent = `Edit: ${doc.title}`;

        this.showModal(modal);
        this.updatePreview();
    }

    async saveDocument(e) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('action', 'save_document');
        formData.append('document_id', document.getElementById('edit-document-id').value);
        formData.append('content', document.getElementById('document-content').value);
        
        if (document.getElementById('is-client-visible').checked) {
            formData.append('is_client_visible', '1');
        }
        
        if (document.getElementById('requires-download-permission').checked) {
            formData.append('requires_download_permission', '1');
        }

        try {
            const response = await fetch('document-management.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                this.showAlert('Document saved successfully!', 'success');
                this.closeModal(document.getElementById('document-editor-modal'));
                location.reload(); // Refresh to show changes
            } else {
                this.showAlert('Error saving document: ' + result.message, 'error');
            }
        } catch (error) {
            this.showAlert('Error saving document: ' + error.message, 'error');
        }
    }

    togglePreview() {
        this.previewMode = !this.previewMode;
        const previewPane = document.getElementById('preview-pane');
        const editorPane = document.querySelector('.editor-pane');
        const toggleBtn = document.getElementById('preview-toggle');

        if (this.previewMode) {
            previewPane.style.display = 'block';
            editorPane.style.width = '50%';
            toggleBtn.innerHTML = '<i class="fas fa-edit"></i> Edit';
            this.updatePreview();
        } else {
            previewPane.style.display = 'none';
            editorPane.style.width = '100%';
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i> Preview';
        }
    }

    updatePreview() {
        if (!this.previewMode) return;

        const content = document.getElementById('document-content').value;
        const previewContent = document.getElementById('preview-content');
        
        // Convert markdown to HTML using marked.js
        if (typeof marked !== 'undefined') {
            previewContent.innerHTML = marked.parse(content);
        } else {
            // Fallback: simple text display
            previewContent.innerHTML = `<pre>${content}</pre>`;
        }
    }

    handleToolbarAction(action) {
        const textarea = document.getElementById('document-content');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end);
        let replacement = '';

        switch (action) {
            case 'bold':
                replacement = `**${selectedText || 'bold text'}**`;
                break;
            case 'italic':
                replacement = `*${selectedText || 'italic text'}*`;
                break;
            case 'heading':
                replacement = `## ${selectedText || 'Heading'}`;
                break;
            case 'list':
                replacement = `- ${selectedText || 'List item'}`;
                break;
        }

        if (replacement) {
            textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
            textarea.focus();
            textarea.setSelectionRange(start + replacement.length, start + replacement.length);
            this.updatePreview();
        }
    }

    showVariablesHelp() {
        this.showModal(document.getElementById('variables-modal'));
    }

    async previewDocument(documentId) {
        try {
            const response = await fetch('../api/documents.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=preview_document&document_id=${documentId}`
            });

            const result = await response.json();
            if (result.success) {
                // Open preview in new window
                const previewWindow = window.open('', '_blank', 'width=800,height=600');
                previewWindow.document.write(`
                    <html>
                        <head>
                            <title>Document Preview</title>
                            <style>
                                body { font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; }
                                h1, h2, h3 { color: #333; }
                                pre { background: #f4f4f4; padding: 10px; border-radius: 4px; }
                            </style>
                        </head>
                        <body>
                            ${marked.parse(result.content)}
                        </body>
                    </html>
                `);
                previewWindow.document.close();
            } else {
                this.showAlert('Error loading preview: ' + result.message, 'error');
            }
        } catch (error) {
            this.showAlert('Error loading preview: ' + error.message, 'error');
        }
    }

    async showDocumentStats(documentId) {
        try {
            const formData = new FormData();
            formData.append('action', 'get_document_stats');
            formData.append('document_id', documentId);

            const response = await fetch('document-management.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                const stats = result.stats;
                const message = `
                    Document Statistics:\n
                    • Users with access: ${stats.users_with_access || 0}\n
                    • Users can download: ${stats.users_can_download || 0}\n
                    • Users downloaded: ${stats.users_downloaded || 0}\n
                    • Total downloads: ${stats.total_downloads || 0}\n
                    • Last download: ${stats.last_download ? new Date(stats.last_download).toLocaleString() : 'Never'}
                `;
                alert(message);
            } else {
                this.showAlert('Error loading stats: ' + result.message, 'error');
            }
        } catch (error) {
            this.showAlert('Error loading stats: ' + error.message, 'error');
        }
    }

    async managePermissions(userId) {
        try {
            const response = await fetch('../api/documents.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_user_permissions&user_id=${userId}`
            });

            const result = await response.json();
            if (result.success) {
                this.showPermissionsModal(result.user, result.permissions, result.documents);
            } else {
                this.showAlert('Error loading permissions: ' + result.message, 'error');
            }
        } catch (error) {
            this.showAlert('Error loading permissions: ' + error.message, 'error');
        }
    }

    showPermissionsModal(user, permissions, documents) {
        const modal = document.getElementById('permissions-modal');
        const content = document.getElementById('permissions-content');
        
        let html = `
            <h3>Permissions for ${user.first_name} ${user.last_name}</h3>
            <p><strong>Email:</strong> ${user.email}</p>
            
            <form id="permissions-form">
                <input type="hidden" name="user_id" value="${user.id}">
                
                <div class="permissions-grid">
        `;
        
        documents.forEach(doc => {
            const perm = permissions.find(p => p.document_id == doc.id) || {};
            html += `
                <div class="permission-item">
                    <h4>${doc.title}</h4>
                    <div class="permission-controls">
                        <label>
                            <input type="checkbox" name="documents[${doc.id}][can_view]" 
                                   ${perm.can_view ? 'checked' : ''}>
                            Can View
                        </label>
                        <label>
                            <input type="checkbox" name="documents[${doc.id}][can_download]" 
                                   ${perm.can_download ? 'checked' : ''}>
                            Can Download
                        </label>
                    </div>
                    <div class="expiry-control">
                        <label>Expires:</label>
                        <input type="datetime-local" name="documents[${doc.id}][expires_at]" 
                               value="${perm.expires_at ? perm.expires_at.slice(0, 16) : ''}">
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="documentManager.closeModal(document.getElementById('permissions-modal'))">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Permissions</button>
                </div>
            </form>
        `;
        
        content.innerHTML = html;
        
        // Bind form submit
        document.getElementById('permissions-form').addEventListener('submit', (e) => {
            this.savePermissions(e);
        });
        
        this.showModal(modal);
    }

    async savePermissions(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        formData.append('action', 'save_permissions');

        try {
            const response = await fetch('../api/documents.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                this.showAlert('Permissions saved successfully!', 'success');
                this.closeModal(document.getElementById('permissions-modal'));
                location.reload();
            } else {
                this.showAlert('Error saving permissions: ' + result.message, 'error');
            }
        } catch (error) {
            this.showAlert('Error saving permissions: ' + error.message, 'error');
        }
    }

    showBulkPermissions() {
        // Implementation for bulk permission management
        this.showAlert('Bulk permissions feature coming soon!', 'info');
    }

    async loadAnalytics() {
        try {
            const response = await fetch('../api/documents.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_analytics'
            });

            const result = await response.json();
            if (result.success) {
                this.renderCharts(result.analytics);
            }
        } catch (error) {
            console.error('Error loading analytics:', error);
        }
    }

    renderCharts(analytics) {
        // Downloads by document chart
        const downloadsCtx = document.getElementById('downloadsChart');
        if (downloadsCtx && analytics.downloads_by_document) {
            if (this.charts.downloads) {
                this.charts.downloads.destroy();
            }
            
            this.charts.downloads = new Chart(downloadsCtx, {
                type: 'bar',
                data: {
                    labels: analytics.downloads_by_document.map(d => d.title),
                    datasets: [{
                        label: 'Downloads',
                        data: analytics.downloads_by_document.map(d => d.download_count),
                        backgroundColor: 'rgba(54, 162, 235, 0.8)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Access distribution chart
        const accessCtx = document.getElementById('accessChart');
        if (accessCtx && analytics.access_distribution) {
            if (this.charts.access) {
                this.charts.access.destroy();
            }
            
            this.charts.access = new Chart(accessCtx, {
                type: 'doughnut',
                data: {
                    labels: analytics.access_distribution.map(d => d.title),
                    datasets: [{
                        data: analytics.access_distribution.map(d => d.access_count),
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 205, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true
                }
            });
        }
    }

    initializeTabs() {
        // Set default active tab
        const activeTab = document.querySelector('.tab-btn.active');
        if (activeTab) {
            this.switchTab(activeTab.dataset.tab);
        }
    }

    showModal(modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    closeModal(modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    showAlert(message, type = 'info') {
        // Create alert element
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `
            <span>${message}</span>
            <button class="alert-close">&times;</button>
        `;

        // Add to page
        document.body.appendChild(alert);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 5000);

        // Manual close
        alert.querySelector('.alert-close').addEventListener('click', () => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.documentManager = new DocumentManager();
});

// Handle modal clicks outside content
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        window.documentManager.closeModal(e.target);
    }
});

// Handle escape key for modals
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal[style*="flex"]');
        if (openModal) {
            window.documentManager.closeModal(openModal);
        }
    }
});