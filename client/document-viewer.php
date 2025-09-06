<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_info = getUserInfo($user_id);

// Get client's permitted documents
$permitted_documents = [];
try {
    $stmt = $pdo->prepare("
        SELECT ed.*, edc.category_name, cdp.can_download, cdp.granted_at
        FROM editable_documents ed
        JOIN editable_document_categories edc ON ed.category_id = edc.id
        LEFT JOIN client_document_permissions cdp ON ed.id = cdp.document_id AND cdp.client_id = ?
        WHERE ed.is_visible = 1 AND (cdp.client_id IS NOT NULL OR ed.public_access = 1)
        ORDER BY edc.category_name, ed.title
    ");
    $stmt->execute([$user_id]);
    $permitted_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching permitted documents: " . $e->getMessage());
}

// Group documents by category
$documents_by_category = [];
foreach ($permitted_documents as $doc) {
    $documents_by_category[$doc['category_name']][] = $doc;
}

// Handle document download
if (isset($_GET['download']) && isset($_GET['doc_id'])) {
    $doc_id = (int)$_GET['doc_id'];
    
    // Verify user has permission to download this document
    $stmt = $pdo->prepare("
        SELECT ed.*, cdp.can_download
        FROM editable_documents ed
        LEFT JOIN client_document_permissions cdp ON ed.id = cdp.document_id AND cdp.client_id = ?
        WHERE ed.id = ? AND ed.is_visible = 1 AND (cdp.can_download = 1 OR ed.public_access = 1)
    ");
    $stmt->execute([$user_id, $doc_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($document) {
        // Log the download
        try {
            $stmt = $pdo->prepare("
                INSERT INTO document_download_logs (document_id, client_id, downloaded_at, ip_address)
                VALUES (?, ?, NOW(), ?)
            ");
            $stmt->execute([$doc_id, $user_id, $_SERVER['REMOTE_ADDR']]);
        } catch (PDOException $e) {
            error_log("Error logging document download: " . $e->getMessage());
        }
        
        // Generate PDF
        require_once '../includes/pdf_generator.php';
        $pdf_generator = new PDFGenerator();
        $pdf_content = $pdf_generator->generateDocumentPDF($document, $user_info);
        
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . sanitize_filename($document['title']) . '.pdf"');
        header('Content-Length: ' . strlen($pdf_content));
        
        echo $pdf_content;
        exit();
    } else {
        $_SESSION['error'] = 'Document not found or access denied.';
    }
}

function sanitize_filename($filename) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Library - LoanFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin: 20px auto;
            max-width: 1200px;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            font-weight: 300;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .content {
            padding: 30px;
        }
        
        .category-section {
            margin-bottom: 40px;
        }
        
        .category-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .category-header h3 {
            color: #2c3e50;
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .document-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .document-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border-color: #007bff;
        }
        
        .document-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }
        
        .document-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .document-icon {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 5px 0;
            line-height: 1.3;
        }
        
        .document-meta {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .document-description {
            color: #495057;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        
        .document-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            box-shadow: 0 2px 4px rgba(0, 123, 255, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.4);
        }
        
        .btn-outline-primary {
            border: 2px solid #007bff;
            color: #007bff;
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: #007bff;
            color: white;
            transform: translateY(-1px);
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9rem;
        }
        
        .access-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .access-badge.download {
            background: #007bff;
        }
        
        .access-badge.view-only {
            background: #ffc107;
            color: #212529;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h4 {
            margin-bottom: 10px;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px 25px;
        }
        
        .modal-body {
            padding: 25px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .document-preview {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            font-family: 'Times New Roman', serif;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .main-container {
                margin: 10px;
                border-radius: 10px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .content {
                padding: 20px;
            }
            
            .documents-grid {
                grid-template-columns: 1fr;
            }
            
            .document-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="main-container">
            <div class="header">
                <h1><i class="fas fa-file-alt me-3"></i>Document Library</h1>
                <p>Access your loan documents and company information</p>
            </div>
            
            <div class="content">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($documents_by_category)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h4>No Documents Available</h4>
                        <p>You don't have access to any documents at this time.<br>
                        Please contact your loan officer if you believe this is an error.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($documents_by_category as $category => $documents): ?>
                        <div class="category-section">
                            <div class="category-header">
                                <h3><i class="fas fa-folder me-2"></i><?php echo htmlspecialchars($category); ?></h3>
                            </div>
                            
                            <div class="documents-grid">
                                <?php foreach ($documents as $doc): ?>
                                    <div class="document-card">
                                        <?php if ($doc['can_download']): ?>
                                            <div class="access-badge download">Download</div>
                                        <?php else: ?>
                                            <div class="access-badge view-only">View Only</div>
                                        <?php endif; ?>
                                        
                                        <div class="document-header">
                                            <div class="document-icon">
                                                <i class="fas fa-file-pdf"></i>
                                            </div>
                                            <div class="document-info">
                                                <h5 class="document-title"><?php echo htmlspecialchars($doc['title']); ?></h5>
                                                <div class="document-meta">
                                                    <small>
                                                        <i class="fas fa-calendar me-1"></i>
                                                        Updated: <?php echo date('M j, Y', strtotime($doc['updated_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($doc['description'])): ?>
                                            <div class="document-description">
                                                <?php echo nl2br(htmlspecialchars($doc['description'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="document-actions">
                                            <button class="btn btn-outline-primary btn-sm" 
                                                    onclick="previewDocument(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['title'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-eye me-1"></i>Preview
                                            </button>
                                            
                                            <?php if ($doc['can_download']): ?>
                                                <a href="?download=1&doc_id=<?php echo $doc['id']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-download me-1"></i>Download PDF
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Document Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalTitle">
                        <i class="fas fa-file-alt me-2"></i>Document Preview
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="previewContent">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3">Loading document preview...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Access Denied Modal -->
    <div class="modal fade" id="accessDeniedModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-lock me-2"></i>Access Denied
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-exclamation-triangle text-warning" style="font-size: 3rem; margin-bottom: 20px;"></i>
                    <h5>Document Access Restricted</h5>
                    <p>You don't have permission to access this document. Please contact your loan officer for assistance.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewDocument(docId, docTitle) {
            const modal = new bootstrap.Modal(document.getElementById('previewModal'));
            const modalTitle = document.getElementById('previewModalTitle');
            const previewContent = document.getElementById('previewContent');
            
            modalTitle.innerHTML = `<i class="fas fa-file-alt me-2"></i>${docTitle}`;
            
            // Show loading state
            previewContent.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Loading document preview...</p>
                </div>
            `;
            
            modal.show();
            
            // Fetch document preview
            fetch('../api/document-preview.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=preview&doc_id=${docId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    previewContent.innerHTML = `
                        <div class="document-preview">
                            ${data.content}
                        </div>
                    `;
                } else {
                    previewContent.innerHTML = `
                        <div class="alert alert-danger text-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${data.message || 'Error loading document preview'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                previewContent.innerHTML = `
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading document preview. Please try again.
                    </div>
                `;
            });
        }
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>