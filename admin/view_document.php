<?php
/**
 * Secure Document Viewer - Admin Panel
 * LoanFlow Personal Loan Management System
 */

require_once '../includes/functions.php';
require_once '../includes/admin_functions.php';

// Require admin login
requireAdminLogin();

$document_id = (int)($_GET['id'] ?? 0);

if (!$document_id) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Invalid document ID.</div>';
    exit;
}

try {
    $db = getDB();
    
    // Get document details
    $stmt = $db->prepare("
        SELECT d.*, u.first_name, u.last_name, u.email, u.reference_number
        FROM documents d
        JOIN users u ON d.user_id = u.id
        WHERE d.id = ?
    ");
    $stmt->execute([$document_id]);
    $document = $stmt->fetch();
    
    if (!$document) {
        http_response_code(404);
        echo '<div class="alert alert-danger">Document not found.</div>';
        exit;
    }
    
    // Check if file exists
    if (!file_exists($document['file_path'])) {
        echo '<div class="alert alert-danger">Document file not found on server.</div>';
        exit;
    }
    
    // Get file info
    $file_extension = strtolower(pathinfo($document['original_filename'], PATHINFO_EXTENSION));
    $mime_type = $document['mime_type'];
    
    // Log document view
    logAudit('document_viewed', 'documents', $document_id, getCurrentUser()['id'], [
        'user_id' => $document['user_id'],
        'document_type' => $document['document_type']
    ]);
    
} catch (Exception $e) {
    error_log("Document view failed: " . $e->getMessage());
    echo '<div class="alert alert-danger">Failed to load document.</div>';
    exit;
}
?>

<div class="document-info mb-3">
    <div class="row">
        <div class="col-md-6">
            <strong>Client:</strong> <?= htmlspecialchars($document['first_name'] . ' ' . $document['last_name']) ?><br>
            <strong>Email:</strong> <?= htmlspecialchars($document['email']) ?><br>
            <strong>Reference:</strong> <?= htmlspecialchars($document['reference_number']) ?>
        </div>
        <div class="col-md-6">
            <strong>Document Type:</strong> <?= ucfirst(str_replace('_', ' ', $document['document_type'])) ?><br>
            <strong>File Name:</strong> <?= htmlspecialchars($document['original_filename']) ?><br>
            <strong>File Size:</strong> <?= formatFileSize($document['file_size']) ?><br>
            <strong>Uploaded:</strong> <?= date('M j, Y g:i A', strtotime($document['created_at'])) ?>
        </div>
    </div>
</div>

<div class="document-viewer">
    <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
        <!-- Image viewer -->
        <div class="text-center">
            <img src="../<?= htmlspecialchars($document['file_path']) ?>" 
                 class="img-fluid" 
                 style="max-height: 600px; border: 1px solid #ddd; border-radius: 4px;"
                 alt="<?= htmlspecialchars($document['original_filename']) ?>">
        </div>
        
    <?php elseif ($file_extension === 'pdf'): ?>
        <!-- PDF viewer -->
        <div class="text-center">
            <embed src="../<?= htmlspecialchars($document['file_path']) ?>" 
                   type="application/pdf" 
                   width="100%" 
                   height="600px"
                   style="border: 1px solid #ddd; border-radius: 4px;">
            <div class="mt-2">
                <a href="../<?= htmlspecialchars($document['file_path']) ?>" 
                   target="_blank" 
                   class="btn btn-primary btn-sm">
                    <i class="fas fa-external-link-alt me-1"></i>Open in New Tab
                </a>
            </div>
        </div>
        
    <?php elseif (in_array($file_extension, ['doc', 'docx'])): ?>
        <!-- Word document -->
        <div class="alert alert-info text-center">
            <i class="fas fa-file-word fa-3x mb-3"></i>
            <h5>Microsoft Word Document</h5>
            <p>This document cannot be previewed in the browser.</p>
            <a href="../<?= htmlspecialchars($document['file_path']) ?>" 
               class="btn btn-primary" 
               download="<?= htmlspecialchars($document['original_filename']) ?>">
                <i class="fas fa-download me-1"></i>Download Document
            </a>
        </div>
        
    <?php elseif (in_array($file_extension, ['txt', 'csv'])): ?>
        <!-- Text files -->
        <div class="border rounded p-3" style="background-color: #f8f9fa; max-height: 600px; overflow-y: auto;">
            <pre style="white-space: pre-wrap; font-family: monospace; font-size: 14px;"><?= htmlspecialchars(file_get_contents($document['file_path'])) ?></pre>
        </div>
        
    <?php else: ?>
        <!-- Unsupported file type -->
        <div class="alert alert-warning text-center">
            <i class="fas fa-file fa-3x mb-3"></i>
            <h5>File Preview Not Available</h5>
            <p>This file type (<?= htmlspecialchars($file_extension) ?>) cannot be previewed in the browser.</p>
            <div class="mt-3">
                <strong>File Information:</strong><br>
                <small class="text-muted">
                    Type: <?= htmlspecialchars($mime_type) ?><br>
                    Size: <?= formatFileSize($document['file_size']) ?>
                </small>
            </div>
            <div class="mt-3">
                <a href="../<?= htmlspecialchars($document['file_path']) ?>" 
                   class="btn btn-primary" 
                   download="<?= htmlspecialchars($document['original_filename']) ?>">
                    <i class="fas fa-download me-1"></i>Download Document
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($document['verification_notes']): ?>
    <div class="mt-3">
        <div class="alert alert-info">
            <strong><i class="fas fa-sticky-note me-1"></i>Review Notes:</strong><br>
            <?= nl2br(htmlspecialchars($document['verification_notes'])) ?>
        </div>
    </div>
<?php endif; ?>

<div class="mt-3 text-center">
    <div class="btn-group" role="group">
        <?php if (in_array($document['upload_status'], ['uploaded', 'pending'])): ?>
            <button type="button" class="btn btn-success" 
                    onclick="parent.reviewDocument(<?= $document['id'] ?>, 'approve', '<?= htmlspecialchars($document['first_name'] . ' ' . $document['last_name']) ?>', '<?= ucfirst(str_replace('_', ' ', $document['document_type'])) ?>'); parent.bootstrap.Modal.getInstance(parent.document.getElementById('viewModal')).hide();">
                <i class="fas fa-check me-1"></i>Approve Document
            </button>
            <button type="button" class="btn btn-danger" 
                    onclick="parent.reviewDocument(<?= $document['id'] ?>, 'reject', '<?= htmlspecialchars($document['first_name'] . ' ' . $document['last_name']) ?>', '<?= ucfirst(str_replace('_', ' ', $document['document_type'])) ?>'); parent.bootstrap.Modal.getInstance(parent.document.getElementById('viewModal')).hide();">
                <i class="fas fa-times me-1"></i>Reject Document
            </button>
        <?php endif; ?>
        
        <a href="../<?= htmlspecialchars($document['file_path']) ?>" 
           class="btn btn-outline-primary" 
           download="<?= htmlspecialchars($document['original_filename']) ?>">
            <i class="fas fa-download me-1"></i>Download
        </a>
    </div>
</div>

<style>
.document-viewer {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    background-color: #fff;
}

.document-info {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
}

img {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

embed {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}
</style>