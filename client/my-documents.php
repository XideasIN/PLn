<?php
/**
 * Client Document Viewer
 * LoanFlow Personal Loan Management System
 * 
 * Allows clients to view and download documents they have permission to access
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a client
if (!isLoggedIn() || $_SESSION['role'] !== 'client') {
    header('Location: ../login.php');
    exit();
}

$pageTitle = 'My Documents';
include '../includes/header.php';
?>

<div class="documents-container">
    <div class="documents-header">
        <h1><i class="fas fa-file-alt"></i> My Documents</h1>
        <p>Access your loan documents and agreements. Click to view or download documents you have permission to access.</p>
    </div>

    <!-- Document Categories -->
    <div class="document-categories" id="documentCategories">
        <div class="loading-spinner" id="loadingSpinner">
            <i class="fas fa-spinner fa-spin"></i> Loading documents...
        </div>
    </div>

    <!-- Document Viewer Modal -->
    <div class="modal" id="documentViewerModal">
        <div class="modal-content document-viewer">
            <div class="modal-header">
                <h3 id="documentTitle">Document Viewer</h3>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" id="downloadPdfBtn">
                        <i class="fas fa-download"></i> Download PDF
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeDocumentViewer()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
            <div class="modal-body">
                <div class="document-content" id="documentContent">
                    <!-- Document content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Access Denied Modal -->
    <div class="modal" id="accessDeniedModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-lock"></i> Access Restricted</h3>
                <button type="button" class="btn btn-outline" onclick="closeModal('accessDeniedModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <p><strong>Document Access Required</strong></p>
                    <p>You don't have permission to access this document yet. Please contact your loan officer if you believe this is an error.</p>
                    <div class="contact-info">
                        <p><i class="fas fa-phone"></i> <strong>Phone:</strong> (555) 123-4567</p>
                        <p><i class="fas fa-envelope"></i> <strong>Email:</strong> support@loanflow.com</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.documents-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.documents-header {
    text-align: center;
    margin-bottom: 40px;
    padding: 30px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.documents-header h1 {
    margin: 0 0 15px 0;
    font-size: 2.5rem;
    font-weight: 300;
}

.documents-header p {
    margin: 0;
    font-size: 1.1rem;
    opacity: 0.9;
}

.document-categories {
    display: grid;
    gap: 30px;
}

.document-category {
    background: white;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.document-category:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.15);
}

.category-header {
    background: #f8f9fa;
    padding: 20px;
    border-bottom: 1px solid #e9ecef;
}

.category-header h3 {
    margin: 0;
    color: #495057;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.document-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    padding: 20px;
}

.document-card {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 20px;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.document-card:hover {
    border-color: #667eea;
    background: #f8f9ff;
}

.document-card.accessible {
    border-color: #28a745;
}

.document-card.restricted {
    border-color: #dc3545;
    background: #fff5f5;
    cursor: not-allowed;
}

.document-info h4 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 1.1rem;
}

.document-info p {
    margin: 0 0 15px 0;
    color: #666;
    font-size: 0.9rem;
    line-height: 1.4;
}

.document-status {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-badge.accessible {
    background: #d4edda;
    color: #155724;
}

.status-badge.restricted {
    background: #f8d7da;
    color: #721c24;
}

.document-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-document {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-view {
    background: #667eea;
    color: white;
}

.btn-view:hover {
    background: #5a6fd8;
}

.btn-download {
    background: #28a745;
    color: white;
}

.btn-download:hover {
    background: #218838;
}

.btn-restricted {
    background: #6c757d;
    color: white;
    cursor: not-allowed;
}

.download-count {
    font-size: 0.8rem;
    color: #666;
    margin-top: 10px;
}

.loading-spinner {
    text-align: center;
    padding: 60px;
    color: #666;
    font-size: 1.1rem;
}

.loading-spinner i {
    font-size: 2rem;
    margin-bottom: 15px;
    display: block;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    overflow-y: auto;
}

.modal-content {
    background: white;
    margin: 50px auto;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    max-width: 90%;
    width: 800px;
    max-height: 90vh;
    overflow: hidden;
}

.modal-content.document-viewer {
    width: 95%;
    max-width: 1200px;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
}

.modal-header h3 {
    margin: 0;
    color: #333;
}

.modal-actions {
    display: flex;
    gap: 10px;
}

.modal-body {
    padding: 0;
    max-height: calc(90vh - 100px);
    overflow-y: auto;
}

.document-content {
    padding: 30px;
    line-height: 1.6;
    font-family: 'Georgia', serif;
}

.document-content h1,
.document-content h2,
.document-content h3 {
    color: #333;
    margin-top: 30px;
    margin-bottom: 15px;
}

.document-content h1 {
    border-bottom: 2px solid #667eea;
    padding-bottom: 10px;
}

.document-content p {
    margin-bottom: 15px;
    text-align: justify;
}

.document-content ul,
.document-content ol {
    margin-bottom: 15px;
    padding-left: 30px;
}

.alert {
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.alert-info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

.alert-danger {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.contact-info {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #ffeaa7;
}

.contact-info p {
    margin: 5px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-outline {
    background: transparent;
    color: #6c757d;
    border: 1px solid #6c757d;
}

.btn-outline:hover {
    background: #6c757d;
    color: white;
}

.no-documents {
    text-align: center;
    padding: 60px 20px;
}

.no-documents .alert {
    max-width: 600px;
    margin: 0 auto;
    text-align: left;
}

@media (max-width: 768px) {
    .documents-container {
        padding: 10px;
    }
    
    .documents-header {
        padding: 20px;
    }
    
    .documents-header h1 {
        font-size: 2rem;
    }
    
    .document-grid {
        grid-template-columns: 1fr;
        padding: 15px;
    }
    
    .modal-content {
        margin: 20px auto;
        width: 95%;
    }
    
    .modal-actions {
        flex-direction: column;
    }
    
    .document-content {
        padding: 20px;
    }
}
</style>

<script>
class ClientDocumentManager {
    constructor() {
        this.documents = [];
        this.currentDocumentId = null;
        this.init();
    }

    init() {
        this.loadDocuments();
        this.bindEvents();
    }

    bindEvents() {
        // Download PDF button
        document.getElementById('downloadPdfBtn').addEventListener('click', () => {
            this.downloadCurrentDocument();
        });

        // Close modal on outside click
        document.getElementById('documentViewerModal').addEventListener('click', (e) => {
            if (e.target.id === 'documentViewerModal') {
                this.closeDocumentViewer();
            }
        });

        document.getElementById('accessDeniedModal').addEventListener('click', (e) => {
            if (e.target.id === 'accessDeniedModal') {
                this.closeModal('accessDeniedModal');
            }
        });
    }

    async loadDocuments() {
        try {
            const response = await fetch('../api/documents.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_client_documents'
            });

            const data = await response.json();
            
            if (data.success) {
                this.documents = data.documents;
                this.renderDocuments();
            } else {
                this.showError('Failed to load documents: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading documents:', error);
            this.showError('Failed to load documents. Please try again.');
        }
    }

    renderDocuments() {
        const container = document.getElementById('documentCategories');
        
        if (this.documents.length === 0) {
            container.innerHTML = `
                <div class="no-documents">
                    <div class="alert alert-info">
                        <h3><i class="fas fa-info-circle"></i> No Documents Available</h3>
                        <p>You don't have access to any documents yet. Documents will appear here once your loan officer grants you access.</p>
                    </div>
                </div>
            `;
            return;
        }

        // Group documents by category
        const categories = {};
        this.documents.forEach(doc => {
            const categoryName = doc.category_name || 'General Documents';
            if (!categories[categoryName]) {
                categories[categoryName] = [];
            }
            categories[categoryName].push(doc);
        });

        let html = '';
        Object.keys(categories).forEach(categoryName => {
            html += `
                <div class="document-category">
                    <div class="category-header">
                        <h3><i class="fas fa-folder"></i> ${categoryName}</h3>
                    </div>
                    <div class="document-grid">
            `;

            categories[categoryName].forEach(doc => {
                const canView = doc.can_view == 1;
                const canDownload = doc.can_download == 1;
                const isAccessible = canView || canDownload;
                
                html += `
                    <div class="document-card ${isAccessible ? 'accessible' : 'restricted'}" 
                         onclick="${isAccessible ? `documentManager.viewDocument(${doc.id})` : `documentManager.showAccessDenied()`}">
                        <div class="document-info">
                            <h4>${doc.title}</h4>
                            <p>${doc.description || 'No description available'}</p>
                            
                            <div class="document-status">
                                <span class="status-badge ${isAccessible ? 'accessible' : 'restricted'}">
                                    <i class="fas ${isAccessible ? 'fa-check-circle' : 'fa-lock'}"></i>
                                    ${isAccessible ? 'Accessible' : 'Restricted'}
                                </span>
                                ${doc.expires_at ? `<span class="expires-info"><i class="fas fa-clock"></i> Expires: ${new Date(doc.expires_at).toLocaleDateString()}</span>` : ''}
                            </div>
                            
                            <div class="document-actions">
                                ${canView ? `<button class="btn-document btn-view" onclick="event.stopPropagation(); documentManager.viewDocument(${doc.id})"><i class="fas fa-eye"></i> View</button>` : ''}
                                ${canDownload ? `<button class="btn-document btn-download" onclick="event.stopPropagation(); documentManager.downloadDocument(${doc.id})"><i class="fas fa-download"></i> Download</button>` : ''}
                                ${!isAccessible ? `<button class="btn-document btn-restricted"><i class="fas fa-lock"></i> Access Required</button>` : ''}
                            </div>
                            
                            ${doc.download_count > 0 ? `<div class="download-count"><i class="fas fa-download"></i> Downloaded ${doc.download_count} time${doc.download_count !== 1 ? 's' : ''}</div>` : ''}
                        </div>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    async viewDocument(documentId) {
        try {
            // Log the view
            await this.logDocumentAccess(documentId, 'view');
            
            // Get personalized content
            const response = await fetch('../api/documents.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=personalize_document&document_id=${documentId}`
            });

            const data = await response.json();
            
            if (data.success) {
                const document = this.documents.find(doc => doc.id == documentId);
                document.getElementById('documentTitle').textContent = document.title;
                document.getElementById('documentContent').innerHTML = this.formatDocumentContent(data.content);
                
                // Store current document for download
                this.currentDocumentId = documentId;
                
                // Show/hide download button based on permissions
                const downloadBtn = document.getElementById('downloadPdfBtn');
                downloadBtn.style.display = document.can_download == 1 ? 'flex' : 'none';
                
                document.getElementById('documentViewerModal').style.display = 'block';
            } else {
                this.showError('Failed to load document: ' + data.message);
            }
        } catch (error) {
            console.error('Error viewing document:', error);
            this.showError('Failed to load document. Please try again.');
        }
    }

    async downloadDocument(documentId) {
        try {
            const response = await fetch('../api/documents.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=generate_pdf&document_id=${documentId}`
            });

            const data = await response.json();
            
            if (data.success) {
                // Create download link
                const link = document.createElement('a');
                link.href = 'data:application/pdf;base64,' + data.pdf_data;
                link.download = data.filename;
                link.click();
                
                // Log the download
                await this.logDocumentAccess(documentId, 'pdf_download');
                
                // Update download count in UI
                this.loadDocuments();
            } else {
                this.showError('Failed to generate PDF: ' + data.message);
            }
        } catch (error) {
            console.error('Error downloading document:', error);
            this.showError('Failed to download document. Please try again.');
        }
    }

    async downloadCurrentDocument() {
        if (this.currentDocumentId) {
            await this.downloadDocument(this.currentDocumentId);
        }
    }

    async logDocumentAccess(documentId, accessType) {
        try {
            await fetch('../api/documents.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=log_download&document_id=${documentId}&download_type=${accessType}`
            });
        } catch (error) {
            console.error('Error logging document access:', error);
        }
    }

    formatDocumentContent(content) {
        // Convert markdown-like content to HTML
        return content
            .replace(/### (.*)/g, '<h3>$1</h3>')
            .replace(/## (.*)/g, '<h2>$1</h2>')
            .replace(/# (.*)/g, '<h1>$1</h1>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\n\n/g, '</p><p>')
            .replace(/^/, '<p>')
            .replace(/$/, '</p>')
            .replace(/<p><\/p>/g, '')
            .replace(/\n/g, '<br>');
    }

    showAccessDenied() {
        document.getElementById('accessDeniedModal').style.display = 'block';
    }

    closeDocumentViewer() {
        document.getElementById('documentViewerModal').style.display = 'none';
        this.currentDocumentId = null;
    }

    closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    showError(message) {
        const container = document.getElementById('documentCategories');
        container.innerHTML = `
            <div class="alert alert-danger">
                <h3><i class="fas fa-exclamation-triangle"></i> Error</h3>
                <p>${message}</p>
                <button class="btn btn-secondary" onclick="documentManager.loadDocuments()">Try Again</button>
            </div>
        `;
    }
}

// Global functions for onclick handlers
function closeDocumentViewer() {
    documentManager.closeDocumentViewer();
}

function closeModal(modalId) {
    documentManager.closeModal(modalId);
}

// Initialize when page loads
let documentManager;
document.addEventListener('DOMContentLoaded', function() {
    documentManager = new ClientDocumentManager();
});
</script>

<?php include '../includes/footer.php'; ?>