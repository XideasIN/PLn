<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/chatbot.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$page_title = 'AI Chatbot Management';
$current_page = 'chatbot-management';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_settings':
                $enabled = isset($_POST['chatbot_enabled']) ? 1 : 0;
                $api_key = trim($_POST['openai_api_key']);
                $model = trim($_POST['chatbot_model']);
                $max_tokens = intval($_POST['chatbot_max_tokens']);
                $temperature = floatval($_POST['chatbot_temperature']);
                
                // Update settings
                updateSystemSetting('chatbot_enabled', $enabled);
                if (!empty($api_key)) {
                    updateSystemSetting('openai_api_key', $api_key);
                }
                updateSystemSetting('chatbot_model', $model);
                updateSystemSetting('chatbot_max_tokens', $max_tokens);
                updateSystemSetting('chatbot_temperature', $temperature);
                
                $success_message = 'Chatbot settings updated successfully!';
                break;
                
            case 'test_connection':
                $test_message = 'Hello, this is a test message to verify the chatbot connection.';
                $result = ChatbotManager::processMessage($test_message);
                
                if ($result['success']) {
                    $success_message = 'Connection test successful! Response: ' . substr($result['response'], 0, 100) . '...';
                } else {
                    $error_message = 'Connection test failed: ' . $result['error'];
                }
                break;
                
            case 'clear_conversations':
                $db = getDB();
                $stmt = $db->prepare("DELETE FROM chatbot_conversations WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $stmt->execute();
                $success_message = 'Old conversations cleared successfully!';
                break;
        }
    }
}

// Get current settings
$settings = [
    'chatbot_enabled' => getSystemSetting('chatbot_enabled', '1'),
    'openai_api_key' => getSystemSetting('openai_api_key', ''),
    'chatbot_model' => getSystemSetting('chatbot_model', 'gpt-3.5-turbo'),
    'chatbot_max_tokens' => getSystemSetting('chatbot_max_tokens', '500'),
    'chatbot_temperature' => getSystemSetting('chatbot_temperature', '0.7')
];

// Get conversation statistics
$db = getDB();
$stats_query = $db->query("
    SELECT 
        COUNT(*) as total_conversations,
        COUNT(DISTINCT user_id) as unique_users,
        AVG(CHAR_LENGTH(user_message)) as avg_message_length,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as conversations_24h,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as conversations_7d
    FROM chatbot_conversations
");
$stats = $stats_query->fetch();

// Get recent conversations
$recent_query = $db->query("
    SELECT cc.*, u.first_name, u.last_name, u.email
    FROM chatbot_conversations cc
    LEFT JOIN users u ON cc.user_id = u.id
    ORDER BY cc.created_at DESC
    LIMIT 20
");
$recent_conversations = $recent_query->fetchAll();

include '../includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-robot me-2"></i>AI Chatbot Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="testConnection()">
                            <i class="fas fa-plug"></i> Test Connection
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="viewLogs()">
                            <i class="fas fa-list"></i> View Logs
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

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Conversations
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($stats['total_conversations']); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-comments fa-2x text-gray-300"></i>
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
                                        Unique Users
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($stats['unique_users']); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                        Last 24 Hours
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($stats['conversations_24h']); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                                        Last 7 Days
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($stats['conversations_7d']); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-week fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Form -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-cog me-2"></i>Chatbot Settings
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="chatbot_enabled" 
                                               name="chatbot_enabled" <?php echo $settings['chatbot_enabled'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="chatbot_enabled">
                                            <strong>Enable Chatbot</strong>
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">Turn the AI chatbot on or off for all users</small>
                                </div>

                                <div class="form-group mb-3">
                                    <label for="openai_api_key" class="form-label">OpenAI API Key</label>
                                    <input type="password" class="form-control" id="openai_api_key" name="openai_api_key" 
                                           placeholder="sk-..." value="<?php echo $settings['openai_api_key'] ? '••••••••••••••••' : ''; ?>">
                                    <small class="form-text text-muted">Your OpenAI API key for chatbot functionality</small>
                                </div>

                                <div class="form-group mb-3">
                                    <label for="chatbot_model" class="form-label">AI Model</label>
                                    <select class="form-control" id="chatbot_model" name="chatbot_model">
                                        <option value="gpt-3.5-turbo" <?php echo $settings['chatbot_model'] === 'gpt-3.5-turbo' ? 'selected' : ''; ?>>GPT-3.5 Turbo (Recommended)</option>
                                        <option value="gpt-4" <?php echo $settings['chatbot_model'] === 'gpt-4' ? 'selected' : ''; ?>>GPT-4 (More Expensive)</option>
                                        <option value="gpt-4-turbo-preview" <?php echo $settings['chatbot_model'] === 'gpt-4-turbo-preview' ? 'selected' : ''; ?>>GPT-4 Turbo</option>
                                    </select>
                                    <small class="form-text text-muted">Choose the AI model for responses</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="chatbot_max_tokens" class="form-label">Max Tokens</label>
                                    <input type="number" class="form-control" id="chatbot_max_tokens" name="chatbot_max_tokens" 
                                           value="<?php echo $settings['chatbot_max_tokens']; ?>" min="50" max="2000">
                                    <small class="form-text text-muted">Maximum length of chatbot responses (50-2000)</small>
                                </div>

                                <div class="form-group mb-3">
                                    <label for="chatbot_temperature" class="form-label">Temperature</label>
                                    <input type="number" class="form-control" id="chatbot_temperature" name="chatbot_temperature" 
                                           value="<?php echo $settings['chatbot_temperature']; ?>" min="0" max="2" step="0.1">
                                    <small class="form-text text-muted">Response creativity (0.0 = focused, 2.0 = creative)</small>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label">Status</label>
                                    <div class="d-flex align-items-center">
                                        <span class="badge <?php echo ChatbotManager::isEnabled() ? 'bg-success' : 'bg-danger'; ?> me-2">
                                            <?php echo ChatbotManager::isEnabled() ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        <?php if (ChatbotManager::isEnabled()): ?>
                                            <small class="text-success">Chatbot is operational</small>
                                        <?php else: ?>
                                            <small class="text-danger">Chatbot is disabled</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                            <div>
                                <button type="button" class="btn btn-outline-info me-2" onclick="testConnection()">
                                    <i class="fas fa-plug me-2"></i>Test Connection
                                </button>
                                <button type="button" class="btn btn-outline-warning" onclick="clearOldConversations()">
                                    <i class="fas fa-trash me-2"></i>Clear Old Logs
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Conversations -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history me-2"></i>Recent Conversations
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="conversationsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Message</th>
                                    <th>Response</th>
                                    <th>IP Address</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_conversations as $conversation): ?>
                                <tr>
                                    <td><?php echo date('M j, Y H:i', strtotime($conversation['created_at'])); ?></td>
                                    <td>
                                        <?php if ($conversation['user_id']): ?>
                                            <span class="badge bg-primary">
                                                <?php echo htmlspecialchars($conversation['first_name'] . ' ' . $conversation['last_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Anonymous</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($conversation['user_message']); ?>">
                                            <?php echo htmlspecialchars(substr($conversation['user_message'], 0, 50)); ?>
                                            <?php if (strlen($conversation['user_message']) > 50): ?>..<?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($conversation['bot_response']); ?>">
                                            <?php echo htmlspecialchars(substr($conversation['bot_response'], 0, 50)); ?>
                                            <?php if (strlen($conversation['bot_response']) > 50): ?>...<?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($conversation['ip_address']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewConversation(<?php echo $conversation['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Conversation Detail Modal -->
<div class="modal fade" id="conversationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Conversation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="conversationDetails">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
function testConnection() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="action" value="test_connection">';
    document.body.appendChild(form);
    form.submit();
}

function clearOldConversations() {
    if (confirm('This will delete conversations older than 30 days. Continue?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="clear_conversations">';
        document.body.appendChild(form);
        form.submit();
    }
}

function viewConversation(id) {
    fetch(`../api/chatbot.php?action=get_conversation&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('conversationDetails').innerHTML = `
                    <div class="mb-3">
                        <strong>User Message:</strong>
                        <div class="border p-3 bg-light rounded">${data.conversation.user_message}</div>
                    </div>
                    <div class="mb-3">
                        <strong>Bot Response:</strong>
                        <div class="border p-3 bg-primary text-white rounded">${data.conversation.bot_response}</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Date:</strong> ${new Date(data.conversation.created_at).toLocaleString()}
                        </div>
                        <div class="col-md-6">
                            <strong>IP Address:</strong> ${data.conversation.ip_address}
                        </div>
                    </div>
                    ${data.conversation.user_agent ? `<div class="mt-2"><strong>User Agent:</strong> ${data.conversation.user_agent}</div>` : ''}
                `;
                new bootstrap.Modal(document.getElementById('conversationModal')).show();
            } else {
                alert('Error loading conversation details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading conversation details');
        });
}

function viewLogs() {
    window.open('../api/chatbot.php?action=export_logs', '_blank');
}

// Initialize DataTable
$(document).ready(function() {
    $('#conversationsTable').DataTable({
        "order": [[ 0, "desc" ]],
        "pageLength": 25,
        "responsive": true
    });
});
</script>

<?php include '../includes/admin_footer.php'; ?>