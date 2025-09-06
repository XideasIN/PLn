<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$page_title = 'AI Learning System';
$current_page = 'ai-learning';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_learning_request':
                $request_type = $_POST['request_type'];
                $source_id = $_POST['source_id'] ?? null;
                $source_name = $_POST['source_name'];
                $content = $_POST['content'];
                $priority = intval($_POST['priority']);
                
                // Create learning request via API
                $api_data = [
                    'request_type' => $request_type,
                    'source_id' => $source_id,
                    'source_name' => $source_name,
                    'content' => $content,
                    'priority' => $priority
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'] . '/api/ai-learning.php/requests');
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($http_code === 200) {
                    $result = json_decode($response, true);
                    if ($result['success']) {
                        $success_message = 'Learning request created successfully!';
                    } else {
                        $error_message = 'Failed to create learning request: ' . $result['message'];
                    }
                } else {
                    $error_message = 'Failed to create learning request. Please try again.';
                }
                break;
        }
    }
}

// Get learning requests
$learning_requests = [];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'] . '/api/ai-learning.php/requests');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

if ($response) {
    $result = json_decode($response, true);
    if ($result && $result['success']) {
        $learning_requests = $result['requests'];
    }
}

// Get knowledge base stats
$kb_stats = [];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'] . '/api/ai-learning.php/knowledge-base/stats');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

if ($response) {
    $result = json_decode($response, true);
    if ($result && $result['success']) {
        $kb_stats = $result['stats'];
    }
}

// Get available sources
$available_sources = [];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'] . '/api/ai-learning.php/sources');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

if ($response) {
    $result = json_decode($response, true);
    if ($result && $result['success']) {
        $available_sources = $result['sources'];
    }
}

include '../includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-brain me-2"></i>AI Learning System</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createLearningRequestModal">
                            <i class="fas fa-plus"></i> Request AI Learning
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="searchKnowledgeBase()">
                            <i class="fas fa-search"></i> Search Knowledge Base
                        </button>
                    </div>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Knowledge Base Statistics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Entries
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($kb_stats['total_entries'] ?? 0); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-database fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Learning Requests
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo count($learning_requests); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-tasks fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Content Types
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo count($kb_stats['content_types'] ?? []); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-tags fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Last Updated
                                    </div>
                                    <div class="h6 mb-0 font-weight-bold text-gray-800">
                                        <?php 
                                        if (isset($kb_stats['last_updated']) && $kb_stats['last_updated']) {
                                            echo date('M j, Y', strtotime($kb_stats['last_updated']));
                                        } else {
                                            echo 'Never';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Learning Requests Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list me-2"></i>Learning Requests
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="learningRequestsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Source</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Requested By</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($learning_requests as $request): ?>
                                <tr>
                                    <td><?php echo $request['id']; ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst(str_replace('_', ' ', $request['request_type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['source_name']); ?></td>
                                    <td>
                                        <?php 
                                        $priority_class = '';
                                        $priority_text = '';
                                        switch ($request['priority']) {
                                            case 3:
                                                $priority_class = 'bg-danger';
                                                $priority_text = 'High';
                                                break;
                                            case 2:
                                                $priority_class = 'bg-warning';
                                                $priority_text = 'Medium';
                                                break;
                                            default:
                                                $priority_class = 'bg-secondary';
                                                $priority_text = 'Low';
                                        }
                                        ?>
                                        <span class="badge <?php echo $priority_class; ?>">
                                            <?php echo $priority_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = '';
                                        switch ($request['status']) {
                                            case 'completed':
                                                $status_class = 'bg-success';
                                                break;
                                            case 'processing':
                                                $status_class = 'bg-primary';
                                                break;
                                            case 'failed':
                                                $status_class = 'bg-danger';
                                                break;
                                            default:
                                                $status_class = 'bg-warning';
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($request['first_name'] && $request['last_name']): ?>
                                            <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y H:i', strtotime($request['requested_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewLearningRequest(<?php echo $request['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($request['status'] === 'failed'): ?>
                                            <button class="btn btn-sm btn-outline-warning" onclick="retryLearningRequest(<?php echo $request['id']; ?>)">
                                                <i class="fas fa-redo"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Content Type Distribution -->
            <?php if (!empty($kb_stats['content_types'])): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-pie me-2"></i>Content Type Distribution
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($kb_stats['content_types'] as $type): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card border-left-info">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        <?php echo ucfirst($type['content_type'] ?? 'Unknown'); ?>
                                    </div>
                                    <div class="h6 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($type['count']); ?> entries
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Create Learning Request Modal -->
<div class="modal fade" id="createLearningRequestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request AI Learning</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_learning_request">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="request_type" class="form-label">Source Type</label>
                                <select class="form-control" id="request_type" name="request_type" required onchange="updateSourceOptions()">
                                    <option value="">Select source type...</option>
                                    <option value="email_template">Email Template</option>
                                    <option value="document_template">Document Template</option>
                                    <option value="custom_content">Custom Content</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-control" id="priority" name="priority" required>
                                    <option value="1">Low - Background processing</option>
                                    <option value="2" selected>Medium - Normal priority</option>
                                    <option value="3">High - Immediate processing</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3" id="source_selection" style="display: none;">
                        <label for="source_id" class="form-label">Select Source</label>
                        <select class="form-control" id="source_id" name="source_id">
                            <option value="">Select source...</option>
                        </select>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="source_name" class="form-label">Source Name</label>
                        <input type="text" class="form-control" id="source_name" name="source_name" required 
                               placeholder="Enter a descriptive name for this learning source">
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="content" class="form-label">Content</label>
                        <textarea class="form-control" id="content" name="content" rows="8" required 
                                  placeholder="Paste the content you want the AI to learn from..."></textarea>
                        <small class="form-text text-muted">The AI will extract key information, phrases, and patterns from this content.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-brain me-2"></i>Request Learning
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Learning Request Detail Modal -->
<div class="modal fade" id="learningRequestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Learning Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="learningRequestDetails">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Knowledge Base Search Modal -->
<div class="modal fade" id="knowledgeBaseSearchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Search Knowledge Base</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-3">
                    <label for="search_query" class="form-label">Search Query</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="search_query" placeholder="Enter search terms...">
                        <button class="btn btn-primary" type="button" onclick="performSearch()">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
                <div id="search_results">
                    <!-- Search results will be displayed here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Available sources data
const availableSources = <?php echo json_encode($available_sources); ?>;

function updateSourceOptions() {
    const requestType = document.getElementById('request_type').value;
    const sourceSelection = document.getElementById('source_selection');
    const sourceSelect = document.getElementById('source_id');
    const sourceNameInput = document.getElementById('source_name');
    
    // Clear existing options
    sourceSelect.innerHTML = '<option value="">Select source...</option>';
    
    if (requestType === 'custom_content') {
        sourceSelection.style.display = 'none';
        sourceNameInput.value = '';
        return;
    }
    
    if (requestType && availableSources[requestType + 's']) {
        sourceSelection.style.display = 'block';
        
        availableSources[requestType + 's'].forEach(source => {
            const option = document.createElement('option');
            option.value = source.id;
            option.textContent = source.name || source.subject;
            sourceSelect.appendChild(option);
        });
        
        // Update source name when selection changes
        sourceSelect.onchange = function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                sourceNameInput.value = selectedOption.textContent;
            }
        };
    } else {
        sourceSelection.style.display = 'none';
    }
}

function viewLearningRequest(id) {
    fetch(`../api/ai-learning.php/requests/${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const request = data.request;
                let metadataHtml = '';
                
                if (request.learning_metadata) {
                    const metadata = request.learning_metadata;
                    metadataHtml = `
                        <div class="mt-3">
                            <h6>Learning Results:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Content Type:</strong> ${metadata.content_type || 'Unknown'}<br>
                                    <strong>Word Count:</strong> ${metadata.word_count || 0}<br>
                                    <strong>Processed:</strong> ${metadata.processed_at || 'N/A'}
                                </div>
                                <div class="col-md-6">
                                    <strong>Key Phrases:</strong><br>
                                    ${metadata.key_phrases ? metadata.key_phrases.map(phrase => `<span class="badge bg-secondary me-1">${phrase}</span>`).join('') : 'None'}
                                </div>
                            </div>
                            ${metadata.summary ? `<div class="mt-2"><strong>Summary:</strong><br><div class="border p-2 bg-light rounded">${metadata.summary}</div></div>` : ''}
                        </div>
                    `;
                }
                
                document.getElementById('learningRequestDetails').innerHTML = `
                    <div class="mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>ID:</strong> ${request.id}<br>
                                <strong>Type:</strong> ${request.request_type.replace('_', ' ')}<br>
                                <strong>Source:</strong> ${request.source_name}<br>
                                <strong>Priority:</strong> ${request.priority === 3 ? 'High' : request.priority === 2 ? 'Medium' : 'Low'}
                            </div>
                            <div class="col-md-6">
                                <strong>Status:</strong> <span class="badge bg-${request.status === 'completed' ? 'success' : request.status === 'failed' ? 'danger' : 'warning'}">${request.status}</span><br>
                                <strong>Requested:</strong> ${new Date(request.requested_at).toLocaleString()}<br>
                                <strong>Processed:</strong> ${request.processed_at ? new Date(request.processed_at).toLocaleString() : 'Not yet'}<br>
                                <strong>Requested By:</strong> ${request.first_name} ${request.last_name}
                            </div>
                        </div>
                    </div>
                    
                    ${request.error_message ? `<div class="alert alert-danger"><strong>Error:</strong> ${request.error_message}</div>` : ''}
                    
                    <div class="mb-3">
                        <strong>Content:</strong>
                        <div class="border p-3 bg-light rounded" style="max-height: 300px; overflow-y: auto;">${request.content}</div>
                    </div>
                    
                    ${metadataHtml}
                `;
                
                new bootstrap.Modal(document.getElementById('learningRequestModal')).show();
            } else {
                alert('Error loading learning request details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading learning request details');
        });
}

function retryLearningRequest(id) {
    if (confirm('Retry this failed learning request?')) {
        fetch(`../api/ai-learning.php/requests/${id}/retry`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Learning request retried successfully!');
                location.reload();
            } else {
                alert('Error retrying learning request: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error retrying learning request');
        });
    }
}

function searchKnowledgeBase() {
    new bootstrap.Modal(document.getElementById('knowledgeBaseSearchModal')).show();
}

function performSearch() {
    const query = document.getElementById('search_query').value.trim();
    if (!query) {
        alert('Please enter a search query');
        return;
    }
    
    const resultsDiv = document.getElementById('search_results');
    resultsDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
    
    fetch('../api/ai-learning.php/knowledge-base/search', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ query: query })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.results.length === 0) {
                resultsDiv.innerHTML = '<div class="alert alert-info">No results found for your search query.</div>';
            } else {
                let html = `<div class="mb-2"><strong>Found ${data.results.length} results for "${data.query}":</strong></div>`;
                
                data.results.forEach(result => {
                    html += `
                        <div class="card mb-2">
                            <div class="card-body">
                                <h6 class="card-title">${result.source_name}</h6>
                                <p class="card-text">${result.summary || 'No summary available'}</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-info">${result.request_type.replace('_', ' ')}</span>
                                        <span class="badge bg-secondary">${result.content_type || 'general'}</span>
                                    </div>
                                    <small class="text-muted">${new Date(result.processed_at).toLocaleDateString()}</small>
                                </div>
                                ${result.key_phrases && result.key_phrases.length > 0 ? `
                                    <div class="mt-2">
                                        <small><strong>Key phrases:</strong> ${result.key_phrases.map(phrase => `<span class="badge bg-light text-dark me-1">${phrase}</span>`).join('')}</small>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                });
                
                resultsDiv.innerHTML = html;
            }
        } else {
            resultsDiv.innerHTML = '<div class="alert alert-danger">Error performing search: ' + data.message + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        resultsDiv.innerHTML = '<div class="alert alert-danger">Error performing search</div>';
    });
}

// Initialize DataTable
$(document).ready(function() {
    $('#learningRequestsTable').DataTable({
        "order": [[ 6, "desc" ]],
        "pageLength": 25,
        "responsive": true
    });
    
    // Allow Enter key to trigger search
    document.getElementById('search_query').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
});
</script>

<?php include '../includes/admin_footer.php'; ?>