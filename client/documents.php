<?php
/**
 * Document Upload and Management
 * LoanFlow Personal Loan Management System
 */

require_once '../includes/functions.php';

// Require client login
requireLogin();

$current_user = getCurrentUser();
$application = getApplicationByUserId($current_user['id']);

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $document_type = $_POST['document_type'] ?? '';
        $file = $_FILES['document'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = "File upload failed. Please try again.";
        } elseif (!isAllowedFileType($file['name'])) {
            $error = "File type not allowed. Please upload PDF, JPG, PNG, DOC, or DOCX files.";
        } elseif ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
            $error = "File too large. Maximum size is 10MB.";
        } elseif (empty($document_type)) {
            $error = "Please select a document type.";
        } else {
            try {
                $db = getDB();
                
                // Check if document type already exists
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM documents WHERE user_id = ? AND document_type = ?");
                $stmt->execute([$current_user['id'], $document_type]);
                $existing = $stmt->fetch();
                
                if ($existing['count'] > 0) {
                    $error = "You have already uploaded this document type. Please contact support to replace it.";
                } else {
                    // Create upload directory
                    $upload_dir = getUploadDirectory($current_user['id']);
                    $stored_filename = generateSecureFilename($file['name']);
                    $file_path = $upload_dir . $stored_filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        // Save to database
                        $stmt = $db->prepare("
                            INSERT INTO documents (
                                user_id, application_id, document_type, original_filename, 
                                stored_filename, file_path, file_size, mime_type, upload_status
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'uploaded')
                        ");
                        
                        $result = $stmt->execute([
                            $current_user['id'],
                            $application['id'] ?? null,
                            $document_type,
                            $file['name'],
                            $stored_filename,
                            $file_path,
                            $file['size'],
                            $file['type']
                        ]);
                        
                        if ($result) {
                            // Add memo
                            addMemo($current_user['id'], "Document uploaded: " . ucfirst(str_replace('_', ' ', $document_type)), 'document_upload');
                            
                            // Log audit
                            logAudit('document_uploaded', 'documents', $db->lastInsertId(), null, [
                                'document_type' => $document_type,
                                'filename' => $file['name']
                            ]);
                            
                            // Check if all required documents are uploaded
                            $required_docs = ['photo_id', 'proof_income', 'proof_address'];
                            $stmt = $db->prepare("SELECT DISTINCT document_type FROM documents WHERE user_id = ?");
                            $stmt->execute([$current_user['id']]);
                            $uploaded_types = array_column($stmt->fetchAll(), 'document_type');
                            
                            if (count(array_intersect($required_docs, $uploaded_types)) === count($required_docs)) {
                                // All required documents uploaded, advance to next step
                                updateCurrentStep($current_user['id'], 3);
                                updateApplicationStatus($application['id'], 'document_review');
                            }
                            
                            redirectWithMessage('documents.php', 'Document uploaded successfully!', 'success');
                        } else {
                            unlink($file_path); // Clean up file
                            $error = "Failed to save document information. Please try again.";
                        }
                    } else {
                        $error = "Failed to save uploaded file. Please try again.";
                    }
                }
            } catch (Exception $e) {
                error_log("Document upload failed: " . $e->getMessage());
                $error = "Document upload failed. Please try again.";
            }
        }
    }
}

// Get user's documents
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$current_user['id']]);
    $documents = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Get documents failed: " . $e->getMessage());
    $documents = [];
}

// Required documents
$required_documents = [
    'photo_id' => [
        'name' => 'Photo ID',
        'description' => 'Government-issued photo identification (Driver\'s License, Passport, etc.)',
        'icon' => 'fa-id-card'
    ],
    'proof_income' => [
        'name' => 'Proof of Income',
        'description' => 'Recent pay stubs, tax returns, or employment letter',
        'icon' => 'fa-file-invoice-dollar'
    ],
    'proof_address' => [
        'name' => 'Proof of Address',
        'description' => 'Utility bill, bank statement, or lease agreement (within 3 months)',
        'icon' => 'fa-home'
    ]
];

$uploaded_types = array_column($documents, 'document_type');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents - LoanFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/client.css" rel="stylesheet">
</head>
<body>
    <!-- Client Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-coins me-2"></i>LoanFlow Client
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="documents.php">
                            <i class="fas fa-folder me-1"></i>Documents
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="agreements.php">
                            <i class="fas fa-file-signature me-1"></i>Agreements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="banking.php">
                            <i class="fas fa-university me-1"></i>Banking
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="payments.php">
                            <i class="fas fa-credit-card me-1"></i>Payments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="fas fa-envelope me-1"></i>Messages
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($current_user['first_name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Flash Messages -->
        <?php 
        $flash = getFlashMessage();
        if ($flash): 
        ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
                <?= htmlspecialchars($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-folder me-2"></i>Document Management</h2>
                        <p class="text-muted">Upload required documents to process your loan application</p>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-primary fs-6">
                            <?= count($uploaded_types) ?>/<?= count($required_documents) ?> Required Documents
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Upload Section -->
        <div class="row mb-4">
            <?php foreach ($required_documents as $type => $info): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 <?= in_array($type, $uploaded_types) ? 'border-success' : 'border-warning' ?>">
                        <div class="card-header <?= in_array($type, $uploaded_types) ? 'bg-success text-white' : 'bg-warning' ?>">
                            <h6 class="mb-0">
                                <i class="fas <?= $info['icon'] ?> me-2"></i><?= $info['name'] ?>
                                <?php if (in_array($type, $uploaded_types)): ?>
                                    <i class="fas fa-check-circle float-end"></i>
                                <?php endif; ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="card-text small"><?= $info['description'] ?></p>
                            
                            <?php if (in_array($type, $uploaded_types)): ?>
                                <?php 
                                $doc = array_filter($documents, function($d) use ($type) {
                                    return $d['document_type'] === $type;
                                });
                                $doc = reset($doc);
                                ?>
                                <div class="alert alert-success mb-2">
                                    <small>
                                        <strong>Uploaded:</strong> <?= htmlspecialchars($doc['original_filename']) ?><br>
                                        <strong>Status:</strong> <?= ucfirst($doc['upload_status']) ?><br>
                                        <strong>Date:</strong> <?= formatDateTime($doc['created_at']) ?>
                                    </small>
                                </div>
                                <small class="text-success">
                                    <i class="fas fa-check me-1"></i>Document uploaded successfully
                                </small>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary btn-sm" 
                                        onclick="showUploadModal('<?= $type ?>', '<?= $info['name'] ?>')">
                                    <i class="fas fa-upload me-1"></i>Upload Document
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Uploaded Documents Table -->
        <?php if (!empty($documents)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Uploaded Documents</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Document Type</th>
                                        <th>File Name</th>
                                        <th>Size</th>
                                        <th>Upload Date</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <td>
                                                <i class="fas <?= $required_documents[$doc['document_type']]['icon'] ?? 'fa-file' ?> me-2"></i>
                                                <?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?>
                                            </td>
                                            <td><?= htmlspecialchars($doc['original_filename']) ?></td>
                                            <td><?= formatFileSize($doc['file_size']) ?></td>
                                            <td><?= formatDateTime($doc['created_at']) ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $doc['upload_status'] ?>">
                                                    <?= ucfirst($doc['upload_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($doc['verification_notes']): ?>
                                                    <small><?= htmlspecialchars($doc['verification_notes']) ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">No notes</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Help Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6><i class="fas fa-question-circle me-2"></i>Document Upload Guidelines</h6>
                        <ul class="mb-0">
                            <li>Accepted formats: PDF, JPG, PNG, DOC, DOCX</li>
                            <li>Maximum file size: 10MB per document</li>
                            <li>Ensure documents are clear and readable</li>
                            <li>Documents must be current and valid</li>
                            <li>Personal information should be visible and match your application</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="document_type" id="documentType">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Upload Document</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="file-upload-area" id="fileUploadArea">
                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                            <h6>Drag and drop your file here</h6>
                            <p class="text-muted">or click to browse</p>
                            <input type="file" class="d-none" id="fileInput" name="document" 
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                        </div>
                        <div id="filePreview" class="mt-3" style="display: none;">
                            <div class="alert alert-info">
                                <i class="fas fa-file me-2"></i>
                                <span id="fileName"></span>
                                <span class="float-end" id="fileSize"></span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="uploadBtn" disabled>
                            <i class="fas fa-upload me-1"></i>Upload Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showUploadModal(type, name) {
            document.getElementById('documentType').value = type;
            document.getElementById('modalTitle').textContent = 'Upload ' + name;
            new bootstrap.Modal(document.getElementById('uploadModal')).show();
        }

        // File upload handling
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('fileInput');
        const filePreview = document.getElementById('filePreview');
        const uploadBtn = document.getElementById('uploadBtn');

        fileUploadArea.addEventListener('click', () => fileInput.click());
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('drag-over');
        });
        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('drag-over');
        });
        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect();
            }
        });

        fileInput.addEventListener('change', handleFileSelect);

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('fileSize').textContent = formatFileSize(file.size);
                filePreview.style.display = 'block';
                uploadBtn.disabled = false;
            }
        }

        function formatFileSize(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let size = bytes;
            let unitIndex = 0;
            
            while (size >= 1024 && unitIndex < units.length - 1) {
                size /= 1024;
                unitIndex++;
            }
            
            return size.toFixed(2) + ' ' + units[unitIndex];
        }

        // Reset modal when closed
        document.getElementById('uploadModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('uploadForm').reset();
            filePreview.style.display = 'none';
            uploadBtn.disabled = true;
        });
    </script>
</body>
</html>
