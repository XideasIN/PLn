<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/email_template_system.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$emailSystem = new EmailTemplateSystem();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'save_component':
                    $componentType = $_POST['component_type'];
                    $componentName = $_POST['component_name'];
                    $htmlContent = $_POST['html_content'];
                    $isDefault = isset($_POST['is_default']) ? 1 : 0;
                    
                    // If setting as default, unset other defaults for this type
                    if ($isDefault) {
                        $stmt = $pdo->prepare("UPDATE email_components SET is_default = 0 WHERE component_type = ?");
                        $stmt->execute([$componentType]);
                    }
                    
                    if (isset($_POST['component_id']) && !empty($_POST['component_id'])) {
                        // Update existing component
                        $stmt = $pdo->prepare("
                            UPDATE email_components 
                            SET component_name = ?, html_content = ?, is_default = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$componentName, $htmlContent, $isDefault, $_POST['component_id']]);
                        $message = 'Email component updated successfully!';
                    } else {
                        // Create new component
                        $stmt = $pdo->prepare("
                            INSERT INTO email_components (component_type, component_name, html_content, is_default, is_active) 
                            VALUES (?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([$componentType, $componentName, $htmlContent, $isDefault]);
                        $message = 'Email component created successfully!';
                    }
                    break;
                    
                case 'delete_component':
                    $componentId = $_POST['component_id'];
                    $stmt = $pdo->prepare("DELETE FROM email_components WHERE id = ?");
                    $stmt->execute([$componentId]);
                    $message = 'Email component deleted successfully!';
                    break;
                    
                case 'toggle_active':
                    $componentId = $_POST['component_id'];
                    $stmt = $pdo->prepare("UPDATE email_components SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$componentId]);
                    $message = 'Component status updated successfully!';
                    break;
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get all email components
$stmt = $pdo->prepare("SELECT * FROM email_components ORDER BY component_type, is_default DESC, created_at DESC");
$stmt->execute();
$components = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group components by type
$componentsByType = [];
foreach ($components as $component) {
    $componentsByType[$component['component_type']][] = $component;
}

// Get template variables for reference
$stmt = $pdo->prepare("SELECT * FROM email_template_variables ORDER BY is_system_variable DESC, variable_name");
$stmt->execute();
$templateVariables = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Template Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .preview-container {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            background: #f8f9fa;
            min-height: 300px;
        }
        .code-editor {
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        .variable-tag {
            background: #e7f3ff;
            color: #0066cc;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            cursor: pointer;
        }
        .component-card {
            transition: all 0.3s ease;
        }
        .component-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .design-panel {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .color-picker {
            width: 40px;
            height: 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .responsive-preview {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .device-preview {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }
        .device-preview.desktop {
            width: 600px;
            min-height: 400px;
        }
        .device-preview.tablet {
            width: 400px;
            min-height: 300px;
        }
        .device-preview.mobile {
            width: 320px;
            min-height: 250px;
        }
        .device-preview iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        .style-control {
            margin-bottom: 15px;
        }
        .style-control label {
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
        }
        .visual-editor {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            min-height: 200px;
            padding: 10px;
        }
        .editor-toolbar {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 8px;
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .toolbar-btn {
            background: white;
            border: 1px solid #dee2e6;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .toolbar-btn:hover {
            background: #e9ecef;
        }
        .toolbar-btn.active {
            background: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-envelope"></i> Email Template Management
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a>
                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Left Panel - Component Management -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-cogs"></i> Email Components</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#componentModal" onclick="openComponentModal()">
                            <i class="fas fa-plus"></i> Add Component
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Component Tabs -->
                        <ul class="nav nav-tabs" id="componentTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="header-tab" data-bs-toggle="tab" data-bs-target="#header" type="button" role="tab">
                                    <i class="fas fa-header"></i> Headers
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="footer-tab" data-bs-toggle="tab" data-bs-target="#footer" type="button" role="tab">
                                    <i class="fas fa-footer"></i> Footers
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="acknowledgment-tab" data-bs-toggle="tab" data-bs-target="#acknowledgment" type="button" role="tab">
                                    <i class="fas fa-check-circle"></i> Acknowledgments
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="componentTabContent">
                            <?php foreach (['header', 'footer', 'acknowledgment'] as $type): ?>
                            <div class="tab-pane fade <?php echo $type === 'header' ? 'show active' : ''; ?>" id="<?php echo $type; ?>" role="tabpanel">
                                <div class="mt-3">
                                    <?php if (isset($componentsByType[$type])): ?>
                                        <?php foreach ($componentsByType[$type] as $component): ?>
                                        <div class="component-card card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="card-title">
                                                            <?php echo htmlspecialchars($component['component_name']); ?>
                                                            <?php if ($component['is_default']): ?>
                                                                <span class="badge bg-success">Default</span>
                                                            <?php endif; ?>
                                                            <?php if (!$component['is_active']): ?>
                                                                <span class="badge bg-secondary">Inactive</span>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <small class="text-muted">
                                                            Created: <?php echo date('M j, Y g:i A', strtotime($component['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editComponent(<?php echo htmlspecialchars(json_encode($component)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-info" onclick="previewComponent(<?php echo $component['id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="toggle_active">
                                                            <input type="hidden" name="component_id" value="<?php echo $component['id']; ?>">
                                                            <button type="submit" class="btn btn-sm <?php echo $component['is_active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>">
                                                                <i class="fas <?php echo $component['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteComponent(<?php echo $component['id']; ?>, '<?php echo htmlspecialchars($component['component_name']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No <?php echo $type; ?> components found.</p>
                                            <button class="btn btn-primary" onclick="openComponentModal('<?php echo $type; ?>')">
                                                <i class="fas fa-plus"></i> Create First <?php echo ucfirst($type); ?>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel - Variables Reference -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-tags"></i> Available Variables</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Click any variable to copy to clipboard</small>
                        </div>
                        <?php foreach ($templateVariables as $variable): ?>
                        <div class="mb-2">
                            <span class="variable-tag" onclick="copyToClipboard('<?php echo $variable['variable_name']; ?>')" title="<?php echo htmlspecialchars($variable['variable_description']); ?>">
                                <?php echo htmlspecialchars($variable['variable_name']); ?>
                            </span>
                            <?php if ($variable['is_system_variable']): ?>
                                <i class="fas fa-cog text-primary" title="System Variable"></i>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Live Preview Panel -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-eye"></i> Live Preview</h6>
                    </div>
                    <div class="card-body">
                        <div id="previewContainer" class="preview-container p-3">
                            <div class="text-center text-muted">
                                <i class="fas fa-eye-slash fa-2x mb-2"></i>
                                <p>Select a component to preview</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Component Modal with Responsive Design Editor -->
    <div class="modal fade" id="componentModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="componentModalTitle">Add Email Component</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="toggleEditorMode()" id="editorModeBtn">
                            <i class="fas fa-code"></i> Code View
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <form method="POST" id="componentForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="save_component">
                        <input type="hidden" name="component_id" id="componentId">
                        
                        <!-- Basic Settings -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label for="componentType" class="form-label">Component Type</label>
                                <select class="form-select" name="component_type" id="componentType" required>
                                    <option value="header">Header</option>
                                    <option value="footer">Footer</option>
                                    <option value="acknowledgment">Acknowledgment</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="componentName" class="form-label">Component Name</label>
                                <input type="text" class="form-control" name="component_name" id="componentName" required>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_default" id="isDefault">
                                    <label class="form-check-label" for="isDefault">
                                        Set as default component
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Left Panel - Design Controls -->
                            <div class="col-lg-3">
                                <div class="design-panel">
                                    <h6><i class="fas fa-palette"></i> Design Controls</h6>
                                    
                                    <!-- Layout Settings -->
                                    <div class="style-control">
                                        <label>Layout</label>
                                        <select class="form-select form-select-sm" id="layoutType" onchange="updateLayout()">
                                            <option value="single-column">Single Column</option>
                                            <option value="two-column">Two Column</option>
                                            <option value="three-column">Three Column</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Typography -->
                                    <div class="style-control">
                                        <label>Font Family</label>
                                        <select class="form-select form-select-sm" id="fontFamily" onchange="updateStyle()">
                                            <option value="Arial, sans-serif">Arial</option>
                                            <option value="'Helvetica Neue', Helvetica, sans-serif">Helvetica</option>
                                            <option value="'Times New Roman', serif">Times New Roman</option>
                                            <option value="Georgia, serif">Georgia</option>
                                            <option value="'Courier New', monospace">Courier New</option>
                                        </select>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="style-control">
                                                <label>Font Size</label>
                                                <input type="range" class="form-range" id="fontSize" min="12" max="24" value="16" onchange="updateStyle()">
                                                <small id="fontSizeValue">16px</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="style-control">
                                                <label>Line Height</label>
                                                <input type="range" class="form-range" id="lineHeight" min="1" max="2" step="0.1" value="1.6" onchange="updateStyle()">
                                                <small id="lineHeightValue">1.6</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Colors -->
                                    <div class="style-control">
                                        <label>Text Color</label>
                                        <input type="color" class="color-picker" id="textColor" value="#333333" onchange="updateStyle()">
                                    </div>
                                    
                                    <div class="style-control">
                                        <label>Background Color</label>
                                        <input type="color" class="color-picker" id="backgroundColor" value="#ffffff" onchange="updateStyle()">
                                    </div>
                                    
                                    <div class="style-control">
                                        <label>Accent Color</label>
                                        <input type="color" class="color-picker" id="accentColor" value="#007bff" onchange="updateStyle()">
                                    </div>
                                    
                                    <!-- Spacing -->
                                    <div class="style-control">
                                        <label>Padding</label>
                                        <input type="range" class="form-range" id="padding" min="0" max="50" value="20" onchange="updateStyle()">
                                        <small id="paddingValue">20px</small>
                                    </div>
                                    
                                    <div class="style-control">
                                        <label>Border Radius</label>
                                        <input type="range" class="form-range" id="borderRadius" min="0" max="20" value="0" onchange="updateStyle()">
                                        <small id="borderRadiusValue">0px</small>
                                    </div>
                                    
                                    <!-- Responsive Settings -->
                                    <div class="style-control">
                                        <label>Mobile Breakpoint</label>
                                        <input type="number" class="form-control form-control-sm" id="mobileBreakpoint" value="600" onchange="updateResponsive()">
                                    </div>
                                    
                                    <!-- Quick Actions -->
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-sm btn-outline-primary w-100 mb-2" onclick="insertTemplate('header')">
                                            <i class="fas fa-plus"></i> Header Template
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary w-100 mb-2" onclick="insertTemplate('footer')">
                                            <i class="fas fa-plus"></i> Footer Template
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-success w-100" onclick="insertVariable()">
                                            <i class="fas fa-code"></i> Insert Variable
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Center Panel - Editor -->
                            <div class="col-lg-6">
                                <!-- Editor Toolbar -->
                                <div class="editor-toolbar">
                                    <button type="button" class="toolbar-btn" onclick="formatText('bold')" title="Bold">
                                        <i class="fas fa-bold"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" onclick="formatText('italic')" title="Italic">
                                        <i class="fas fa-italic"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" onclick="formatText('underline')" title="Underline">
                                        <i class="fas fa-underline"></i>
                                    </button>
                                    <div class="toolbar-separator"></div>
                                    <button type="button" class="toolbar-btn" onclick="insertElement('button')" title="Button">
                                        <i class="fas fa-square"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" onclick="insertElement('image')" title="Image">
                                        <i class="fas fa-image"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" onclick="insertElement('link')" title="Link">
                                        <i class="fas fa-link"></i>
                                    </button>
                                    <button type="button" class="toolbar-btn" onclick="insertElement('divider')" title="Divider">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                                
                                <!-- Visual Editor (Hidden by default) -->
                                <div id="visualEditor" class="visual-editor" contenteditable="true" style="display: none;" oninput="syncEditors()"></div>
                                
                                <!-- Code Editor -->
                                <div id="codeEditorContainer">
                                    <textarea class="form-control code-editor" name="html_content" id="htmlContent" rows="20" required oninput="syncEditors()"></textarea>
                                </div>
                                
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle"></i> Use variables from the right panel. HTML and inline CSS are supported.
                                </div>
                            </div>
                            
                            <!-- Right Panel - Responsive Preview -->
                            <div class="col-lg-3">
                                <h6><i class="fas fa-mobile-alt"></i> Responsive Preview</h6>
                                
                                <!-- Device Selector -->
                                <div class="btn-group w-100 mb-3" role="group">
                                    <button type="button" class="btn btn-outline-primary btn-sm active" onclick="switchDevice('desktop')" id="desktopBtn">
                                        <i class="fas fa-desktop"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="switchDevice('tablet')" id="tabletBtn">
                                        <i class="fas fa-tablet-alt"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="switchDevice('mobile')" id="mobileBtn">
                                        <i class="fas fa-mobile-alt"></i>
                                    </button>
                                </div>
                                
                                <!-- Preview Container -->
                                <div class="device-preview desktop" id="previewDevice">
                                    <iframe id="previewFrame" srcdoc="<div style='padding: 20px; text-align: center; color: #666;'>Start editing to see preview</div>"></iframe>
                                </div>
                                
                                <!-- Preview Controls -->
                                <div class="mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-info w-100 mb-2" onclick="refreshPreview()">
                                        <i class="fas fa-sync"></i> Refresh Preview
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success w-100" onclick="testEmail()">
                                        <i class="fas fa-paper-plane"></i> Send Test Email
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-info" onclick="saveAsDraft()">
                            <i class="fas fa-save"></i> Save as Draft
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Save & Activate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the component "<span id="deleteComponentName"></span>"?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_component">
                        <input type="hidden" name="component_id" id="deleteComponentId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let isVisualMode = false;
        let currentDevice = 'desktop';
        let previewUpdateTimeout;
        
        function openComponentModal(type = '') {
            document.getElementById('componentModalTitle').textContent = 'Add Email Component';
            document.getElementById('componentForm').reset();
            document.getElementById('componentId').value = '';
            resetDesignControls();
            
            if (type) {
                document.getElementById('componentType').value = type;
            }
            
            // Initialize preview
            setTimeout(() => {
                refreshPreview();
            }, 100);
        }
        
        function editComponent(component) {
            document.getElementById('componentModalTitle').textContent = 'Edit Email Component';
            document.getElementById('componentId').value = component.id;
            document.getElementById('componentType').value = component.component_type;
            document.getElementById('componentName').value = component.component_name;
            document.getElementById('htmlContent').value = component.html_content;
            document.getElementById('isDefault').checked = component.is_default == 1;
            
            // Parse existing styles from content
            parseExistingStyles(component.html_content);
            
            new bootstrap.Modal(document.getElementById('componentModal')).show();
            
            // Initialize preview
            setTimeout(() => {
                refreshPreview();
            }, 100);
        }
        
        function deleteComponent(id, name) {
            document.getElementById('deleteComponentId').value = id;
            document.getElementById('deleteComponentName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        
        function previewComponent(componentId) {
            fetch('ajax/preview-component.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'component_id=' + componentId
            })
            .then(response => response.text())
            .then(html => {
                document.getElementById('previewContainer').innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('previewContainer').innerHTML = '<div class="text-danger">Error loading preview</div>';
            });
        }
        
        // Enhanced Editor Functions
        function toggleEditorMode() {
            const visualEditor = document.getElementById('visualEditor');
            const codeEditor = document.getElementById('codeEditorContainer');
            const btn = document.getElementById('editorModeBtn');
            
            isVisualMode = !isVisualMode;
            
            if (isVisualMode) {
                visualEditor.style.display = 'block';
                codeEditor.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-eye"></i> Visual View';
                btn.classList.add('active');
                
                // Sync content to visual editor
                visualEditor.innerHTML = document.getElementById('htmlContent').value;
            } else {
                visualEditor.style.display = 'none';
                codeEditor.style.display = 'block';
                btn.innerHTML = '<i class="fas fa-code"></i> Code View';
                btn.classList.remove('active');
            }
        }
        
        function syncEditors() {
            if (isVisualMode) {
                document.getElementById('htmlContent').value = document.getElementById('visualEditor').innerHTML;
            } else {
                document.getElementById('visualEditor').innerHTML = document.getElementById('htmlContent').value;
            }
            
            // Debounced preview update
            clearTimeout(previewUpdateTimeout);
            previewUpdateTimeout = setTimeout(refreshPreview, 500);
        }
        
        function updateStyle() {
            const fontSize = document.getElementById('fontSize').value;
            const lineHeight = document.getElementById('lineHeight').value;
            const fontFamily = document.getElementById('fontFamily').value;
            const textColor = document.getElementById('textColor').value;
            const backgroundColor = document.getElementById('backgroundColor').value;
            const accentColor = document.getElementById('accentColor').value;
            const padding = document.getElementById('padding').value;
            const borderRadius = document.getElementById('borderRadius').value;
            
            // Update value displays
            document.getElementById('fontSizeValue').textContent = fontSize + 'px';
            document.getElementById('lineHeightValue').textContent = lineHeight;
            document.getElementById('paddingValue').textContent = padding + 'px';
            document.getElementById('borderRadiusValue').textContent = borderRadius + 'px';
            
            // Apply styles to current content
            applyStylesToContent();
            refreshPreview();
        }
        
        function applyStylesToContent() {
            const fontSize = document.getElementById('fontSize').value;
            const lineHeight = document.getElementById('lineHeight').value;
            const fontFamily = document.getElementById('fontFamily').value;
            const textColor = document.getElementById('textColor').value;
            const backgroundColor = document.getElementById('backgroundColor').value;
            const accentColor = document.getElementById('accentColor').value;
            const padding = document.getElementById('padding').value;
            const borderRadius = document.getElementById('borderRadius').value;
            
            const styles = `
                font-family: ${fontFamily};
                font-size: ${fontSize}px;
                line-height: ${lineHeight};
                color: ${textColor};
                background-color: ${backgroundColor};
                padding: ${padding}px;
                border-radius: ${borderRadius}px;
            `;
            
            let content = document.getElementById('htmlContent').value;
            
            // If no style attribute exists, add one
            if (!content.includes('style=')) {
                content = `<div style="${styles}">${content}</div>`;
            } else {
                // Update existing styles (simplified approach)
                content = content.replace(/style="[^"]*"/g, `style="${styles}"`);
            }
            
            document.getElementById('htmlContent').value = content;
            if (isVisualMode) {
                document.getElementById('visualEditor').innerHTML = content;
            }
        }
        
        function updateLayout() {
            const layoutType = document.getElementById('layoutType').value;
            let template = '';
            
            switch (layoutType) {
                case 'single-column':
                    template = '<div style="width: 100%; padding: 20px;">\n    <h2>Your Content Here</h2>\n    <p>Add your content in this single column layout.</p>\n</div>';
                    break;
                case 'two-column':
                    template = '<div style="display: flex; gap: 20px; padding: 20px;">\n    <div style="flex: 1;">\n        <h3>Column 1</h3>\n        <p>Content for first column.</p>\n    </div>\n    <div style="flex: 1;">\n        <h3>Column 2</h3>\n        <p>Content for second column.</p>\n    </div>\n</div>';
                    break;
                case 'three-column':
                    template = '<div style="display: flex; gap: 15px; padding: 20px;">\n    <div style="flex: 1;">\n        <h4>Column 1</h4>\n        <p>First column content.</p>\n    </div>\n    <div style="flex: 1;">\n        <h4>Column 2</h4>\n        <p>Second column content.</p>\n    </div>\n    <div style="flex: 1;">\n        <h4>Column 3</h4>\n        <p>Third column content.</p>\n    </div>\n</div>';
                    break;
            }
            
            document.getElementById('htmlContent').value = template;
            if (isVisualMode) {
                document.getElementById('visualEditor').innerHTML = template;
            }
            refreshPreview();
        }
        
        function switchDevice(device) {
            currentDevice = device;
            const previewDevice = document.getElementById('previewDevice');
            const buttons = ['desktopBtn', 'tabletBtn', 'mobileBtn'];
            
            // Update button states
            buttons.forEach(btnId => {
                document.getElementById(btnId).classList.remove('active');
            });
            document.getElementById(device + 'Btn').classList.add('active');
            
            // Update preview container class
            previewDevice.className = `device-preview ${device}`;
            
            refreshPreview();
        }
        
        function refreshPreview() {
            const content = document.getElementById('htmlContent').value;
            if (!content.trim()) {
                document.getElementById('previewFrame').srcdoc = '<div style="padding: 20px; text-align: center; color: #666;">Start editing to see preview</div>';
                return;
            }
            
            fetch('ajax/preview-content.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'content=' + encodeURIComponent(content) + '&device=' + currentDevice
            })
            .then(response => response.text())
            .then(html => {
                document.getElementById('previewFrame').srcdoc = html;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('previewFrame').srcdoc = '<div style="padding: 20px; color: red;">Error loading preview</div>';
            });
        }
        
        function formatText(command) {
            if (isVisualMode) {
                document.execCommand(command, false, null);
                syncEditors();
            } else {
                // Insert HTML tags for code view
                const textarea = document.getElementById('htmlContent');
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const selectedText = textarea.value.substring(start, end);
                
                let replacement = '';
                switch (command) {
                    case 'bold':
                        replacement = `<strong>${selectedText}</strong>`;
                        break;
                    case 'italic':
                        replacement = `<em>${selectedText}</em>`;
                        break;
                    case 'underline':
                        replacement = `<u>${selectedText}</u>`;
                        break;
                }
                
                textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
                syncEditors();
            }
        }
        
        function insertElement(type) {
            let element = '';
            
            switch (type) {
                case 'button':
                    element = '<a href="#" style="background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">Button Text</a>';
                    break;
                case 'image':
                    element = '<img src="/assets/images/email-placeholder.jpg" alt="Image description" style="max-width: 100%; height: auto;">';
                    break;
                case 'link':
                    element = '<a href="#" style="color: #007bff; text-decoration: underline;">Link Text</a>';
                    break;
                case 'divider':
                    element = '<hr style="border: none; border-top: 1px solid #dee2e6; margin: 20px 0;">';
                    break;
            }
            
            const textarea = document.getElementById('htmlContent');
            const cursorPos = textarea.selectionStart;
            textarea.value = textarea.value.substring(0, cursorPos) + element + textarea.value.substring(cursorPos);
            
            if (isVisualMode) {
                document.getElementById('visualEditor').innerHTML = textarea.value;
            }
            
            refreshPreview();
        }
        
        function insertTemplate(type) {
            let template = '';
            
            if (type === 'header') {
                template = `<div style="background-color: #007bff; color: white; padding: 30px 20px; text-align: center;">
    <h1 style="margin: 0; font-size: 28px;">{company_name}</h1>
    <p style="margin: 10px 0 0 0; opacity: 0.9;">Professional Email Communication</p>
</div>`;
            } else if (type === 'footer') {
                template = `<div style="background-color: #f8f9fa; padding: 30px 20px; text-align: center; border-top: 1px solid #dee2e6;">
    <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">
        © {current_year} {company_name}. All rights reserved.
    </p>
    <p style="margin: 0; font-size: 12px; color: #999;">
        {company_address} | {company_phone} | {company_email}
    </p>
</div>`;
            }
            
            document.getElementById('htmlContent').value = template;
            if (isVisualMode) {
                document.getElementById('visualEditor').innerHTML = template;
            }
            refreshPreview();
        }
        
        function insertVariable() {
            const variables = [
                '{customer_name}', '{company_name}', '{company_email}', 
                '{company_phone}', '{inquiry_id}', '{current_year}'
            ];
            
            const variable = prompt('Select a variable to insert:\n\n' + variables.join('\n'));
            if (variable && variables.includes(variable)) {
                const textarea = document.getElementById('htmlContent');
                const cursorPos = textarea.selectionStart;
                textarea.value = textarea.value.substring(0, cursorPos) + variable + textarea.value.substring(cursorPos);
                
                if (isVisualMode) {
                    document.getElementById('visualEditor').innerHTML = textarea.value;
                }
                
                refreshPreview();
            }
        }
        
        function testEmail() {
            const email = prompt('Enter email address to send test email:');
            if (email) {
                const content = document.getElementById('htmlContent').value;
                
                fetch('ajax/send-test-email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'email=' + encodeURIComponent(email) + '&content=' + encodeURIComponent(content)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Test email sent successfully!');
                    } else {
                        alert('Error sending test email: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error sending test email');
                });
            }
        }
        
        function saveAsDraft() {
            // Add draft functionality if needed
            alert('Draft functionality can be implemented based on requirements');
        }
        
        function resetDesignControls() {
            document.getElementById('fontSize').value = 16;
            document.getElementById('lineHeight').value = 1.6;
            document.getElementById('fontFamily').value = 'Arial, sans-serif';
            document.getElementById('textColor').value = '#333333';
            document.getElementById('backgroundColor').value = '#ffffff';
            document.getElementById('accentColor').value = '#007bff';
            document.getElementById('padding').value = 20;
            document.getElementById('borderRadius').value = 0;
            updateStyle();
        }
        
        function parseExistingStyles(content) {
            // Basic style parsing - can be enhanced
            const styleMatch = content.match(/style="([^"]*)"/i);
            if (styleMatch) {
                const styles = styleMatch[1];
                
                // Parse font-size
                const fontSizeMatch = styles.match(/font-size:\s*(\d+)px/i);
                if (fontSizeMatch) {
                    document.getElementById('fontSize').value = fontSizeMatch[1];
                }
                
                // Parse color
                const colorMatch = styles.match(/color:\s*(#[0-9a-f]{6})/i);
                if (colorMatch) {
                    document.getElementById('textColor').value = colorMatch[1];
                }
                
                updateStyle();
            }
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show temporary success feedback
                const element = event.target;
                const originalText = element.textContent;
                element.textContent = 'Copied!';
                element.style.backgroundColor = '#28a745';
                element.style.color = 'white';
                
                setTimeout(() => {
                    element.textContent = originalText;
                    element.style.backgroundColor = '';
                    element.style.color = '';
                }, 1000);
            });
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set up real-time preview updates
            document.getElementById('htmlContent').addEventListener('input', function() {
                clearTimeout(previewUpdateTimeout);
                previewUpdateTimeout = setTimeout(refreshPreview, 500);
            });
        });
    </script>
</body>
</html>