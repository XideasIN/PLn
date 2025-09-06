<?php
/**
 * Document Management Admin Interface
 * LoanFlow Personal Loan Management System
 * 
 * This interface allows admins to:
 * - Edit the four specified documents
 * - Control document visibility in client area
 * - Manage client download permissions
 * - View document analytics
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$pageTitle = 'Document Management';
$currentPage = 'document-management';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'save_document':
                $documentId = intval($_POST['document_id']);
                $content = $_POST['content'];
                $isVisible = isset($_POST['is_client_visible']) ? 1 : 0;
                $requiresPermission = isset($_POST['requires_download_permission']) ? 1 : 0;
                
                // Save current version to history
                $stmt = $pdo->prepare("INSERT INTO document_versions (document_id, version, content, change_summary, modified_by) 
                                     SELECT id, version, content, 'Auto-backup before edit', ? FROM editable_documents WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $documentId]);
                
                // Update document
                $stmt = $pdo->prepare("UPDATE editable_documents SET 
                                     content = ?, 
                                     is_client_visible = ?, 
                                     requires_download_permission = ?, 
                                     last_modified_by = ?, 
                                     updated_at = NOW(),
                                     version = CONCAT(SUBSTRING_INDEX(version, '.', 1), '.', SUBSTRING_INDEX(version, '.', -1) + 1)
                                     WHERE id = ?");
                $stmt->execute([$content, $isVisible, $requiresPermission, $_SESSION['user_id'], $documentId]);
                
                echo json_encode(['success' => true, 'message' => 'Document saved successfully']);
                break;
                
            case 'grant_access':
                $userId = intval($_POST['user_id']);
                $documentId = intval($_POST['document_id']);
                $canView = isset($_POST['can_view']) ? 1 : 0;
                $canDownload = isset($_POST['can_download']) ? 1 : 0;
                $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
                $notes = $_POST['notes'] ?? '';
                
                $stmt = $pdo->prepare("CALL GrantDocumentAccess(?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $documentId, $canView, $canDownload, $_SESSION['user_id'], $expiresAt, $notes]);
                
                echo json_encode(['success' => true, 'message' => 'Access permissions updated']);
                break;
                
            case 'revoke_access':
                $userId = intval($_POST['user_id']);
                $documentId = intval($_POST['document_id']);
                
                $stmt = $pdo->prepare("DELETE FROM client_document_permissions WHERE user_id = ? AND document_id = ?");
                $stmt->execute([$userId, $documentId]);
                
                echo json_encode(['success' => true, 'message' => 'Access revoked successfully']);
                break;
                
            case 'get_document_stats':
                $documentId = intval($_POST['document_id']);
                
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(DISTINCT cdp.user_id) as users_with_access,
                        COUNT(DISTINCT CASE WHEN cdp.can_download = 1 THEN cdp.user_id END) as users_can_download,
                        COUNT(DISTINCT ddl.user_id) as users_downloaded,
                        COUNT(ddl.id) as total_downloads,
                        MAX(ddl.download_time) as last_download
                    FROM editable_documents ed
                    LEFT JOIN client_document_permissions cdp ON ed.id = cdp.document_id
                    LEFT JOIN document_download_log ddl ON ed.id = ddl.document_id
                    WHERE ed.id = ?
                ");
                $stmt->execute([$documentId]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'stats' => $stats]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Get all editable documents
$stmt = $pdo->query("
    SELECT ed.*, dc.name as category_name, 
           u.first_name, u.last_name,
           (SELECT COUNT(*) FROM client_document_permissions WHERE document_id = ed.id) as access_count,
           (SELECT COUNT(*) FROM document_download_log WHERE document_id = ed.id) as download_count
    FROM editable_documents ed
    LEFT JOIN document_categories dc ON ed.category_id = dc.id
    LEFT JOIN users u ON ed.last_modified_by = u.id
    ORDER BY dc.sort_order, ed.title
");
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all clients for permission management
$stmt = $pdo->query("
    SELECT id, first_name, last_name, email, 
           (SELECT COUNT(*) FROM client_document_permissions WHERE user_id = users.id) as document_access_count
    FROM users 
    WHERE role = 'client' 
    ORDER BY first_name, last_name
");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/document-admin.css">
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<div class="document-management-container">
    <div class="page-header">
        <h1><i class="fas fa-file-alt"></i> Document Management</h1>
        <p>Manage editable documents, client visibility, and download permissions</p>
    </div>

    <div class="management-tabs">
        <button class="tab-btn active" data-tab="documents">Documents</button>
        <button class="tab-btn" data-tab="permissions">Client Permissions</button>
        <button class="tab-btn" data-tab="analytics">Analytics</button>
    </div>

    <!-- Documents Tab -->
    <div class="tab-content active" id="documents-tab">
        <div class="documents-grid">
            <?php foreach ($documents as $doc): ?>
            <div class="document-card" data-document-id="<?= $doc['id'] ?>">
                <div class="document-header">
                    <h3><?= htmlspecialchars($doc['title']) ?></h3>
                    <div class="document-status">
                        <span class="status-badge <?= $doc['is_client_visible'] ? 'visible' : 'hidden' ?>">
                            <?= $doc['is_client_visible'] ? 'Visible' : 'Hidden' ?>
                        </span>
                        <?php if ($doc['requires_download_permission']): ?>
                        <span class="permission-badge">Permission Required</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="document-info">
                    <p><strong>Category:</strong> <?= htmlspecialchars($doc['category_name']) ?></p>
                    <p><strong>Version:</strong> <?= htmlspecialchars($doc['version']) ?></p>
                    <p><strong>Last Modified:</strong> 
                        <?= date('M j, Y g:i A', strtotime($doc['updated_at'])) ?>
                        <?php if ($doc['first_name']): ?>
                        by <?= htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) ?>
                        <?php endif; ?>
                    </p>
                    <p><strong>Client Access:</strong> <?= $doc['access_count'] ?> users</p>
                    <p><strong>Downloads:</strong> <?= $doc['download_count'] ?> total</p>
                </div>
                
                <div class="document-actions">
                    <button class="btn btn-primary edit-document" data-id="<?= $doc['id'] ?>">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-secondary preview-document" data-id="<?= $doc['id'] ?>">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                    <button class="btn btn-info document-stats" data-id="<?= $doc['id'] ?>">
                        <i class="fas fa-chart-bar"></i> Stats
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Client Permissions Tab -->
    <div class="tab-content" id="permissions-tab">
        <div class="permissions-header">
            <h2>Client Document Permissions</h2>
            <button class="btn btn-primary" id="bulk-permissions-btn">
                <i class="fas fa-users"></i> Bulk Permissions
            </button>
        </div>
        
        <div class="permissions-table-container">
            <table class="permissions-table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Email</th>
                        <th>Documents Access</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                    <tr>
                        <td><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></td>
                        <td><?= htmlspecialchars($client['email']) ?></td>
                        <td>
                            <span class="access-count"><?= $client['document_access_count'] ?> documents</span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary manage-permissions" data-user-id="<?= $client['id'] ?>">
                                <i class="fas fa-key"></i> Manage
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Analytics Tab -->
    <div class="tab-content" id="analytics-tab">
        <div class="analytics-dashboard">
            <div class="stats-cards">
                <div class="stat-card">
                    <h3>Total Documents</h3>
                    <div class="stat-number"><?= count($documents) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Visible Documents</h3>
                    <div class="stat-number"><?= count(array_filter($documents, fn($d) => $d['is_client_visible'])) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Downloads</h3>
                    <div class="stat-number"><?= array_sum(array_column($documents, 'download_count')) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Clients</h3>
                    <div class="stat-number"><?= count($clients) ?></div>
                </div>
            </div>
            
            <div class="analytics-charts">
                <div class="chart-container">
                    <h3>Document Downloads by Type</h3>
                    <canvas id="downloadsChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3>Client Access Distribution</h3>
                    <canvas id="accessChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Document Editor Modal -->
<div class="modal" id="document-editor-modal">
    <div class="modal-content large">
        <div class="modal-header">
            <h2>Edit Document</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="document-editor-form">
                <input type="hidden" id="edit-document-id">
                
                <div class="editor-toolbar">
                    <div class="toolbar-group">
                        <button type="button" class="toolbar-btn" data-action="bold" title="Bold">
                            <i class="fas fa-bold"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-action="italic" title="Italic">
                            <i class="fas fa-italic"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-action="heading" title="Heading">
                            <i class="fas fa-heading"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-action="list" title="List">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                    <div class="toolbar-group">
                        <button type="button" class="toolbar-btn" id="preview-toggle" title="Toggle Preview">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button type="button" class="toolbar-btn" id="variables-help" title="Available Variables">
                            <i class="fas fa-question-circle"></i> Variables
                        </button>
                    </div>
                </div>
                
                <div class="editor-container">
                    <div class="editor-pane">
                        <textarea id="document-content" placeholder="Enter document content in Markdown format..."></textarea>
                    </div>
                    <div class="preview-pane" id="preview-pane" style="display: none;">
                        <div id="preview-content"></div>
                    </div>
                </div>
                
                <div class="document-settings">
                    <div class="settings-row">
                        <label>
                            <input type="checkbox" id="is-client-visible"> 
                            Visible in Client Area
                        </label>
                        <label>
                            <input type="checkbox" id="requires-download-permission"> 
                            Require Download Permission
                        </label>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" id="cancel-edit">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Permission Management Modal -->
<div class="modal" id="permissions-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Manage Document Permissions</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="permissions-content">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Variables Help Modal -->
<div class="modal" id="variables-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Available Template Variables</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="variables-list">
                <h3>Common Variables</h3>
                <div class="variable-item">
                    <code>{{client_full_name}}</code>
                    <span>Client's full name</span>
                </div>
                <div class="variable-item">
                    <code>{{client_email}}</code>
                    <span>Client's email address</span>
                </div>
                <div class="variable-item">
                    <code>{{client_phone}}</code>
                    <span>Client's phone number</span>
                </div>
                <div class="variable-item">
                    <code>{{current_date}}</code>
                    <span>Current date</span>
                </div>
                <div class="variable-item">
                    <code>{{company_name}}</code>
                    <span>Company name</span>
                </div>
                
                <h3>Loan-Specific Variables</h3>
                <div class="variable-item">
                    <code>{{loan_amount}}</code>
                    <span>Requested loan amount</span>
                </div>
                <div class="variable-item">
                    <code>{{interest_rate}}</code>
                    <span>Interest rate</span>
                </div>
                <div class="variable-item">
                    <code>{{loan_term}}</code>
                    <span>Loan term in months</span>
                </div>
                <div class="variable-item">
                    <code>{{monthly_payment}}</code>
                    <span>Monthly payment amount</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../assets/js/document-admin.js"></script>

<?php include '../includes/footer.php'; ?>