/**
 * Chatbot Admin Management JavaScript
 * LoanFlow Personal Loan Management System
 */

// Global variables
let conversationChart = null;
let statsRefreshInterval = null;

// Initialize when document is ready
$(document).ready(function() {
    initializeChatbotAdmin();
    loadChatbotStats();
    loadRecentConversations();
    
    // Auto-refresh stats every 30 seconds
    statsRefreshInterval = setInterval(loadChatbotStats, 30000);
});

/**
 * Initialize chatbot admin interface
 */
function initializeChatbotAdmin() {
    // Handle settings form submission
    $('#chatbot-settings-form').on('submit', function(e) {
        e.preventDefault();
        saveChatbotSettings();
    });
    
    // Handle test connection button
    $('#test-connection-btn').on('click', function() {
        testOpenAIConnection();
    });
    
    // Handle clear logs button
    $('#clear-logs-btn').on('click', function() {
        if (confirm('Are you sure you want to clear old conversation logs? This action cannot be undone.')) {
            clearOldLogs();
        }
    });
    
    // Handle export buttons
    $('#export-csv-btn').on('click', function() {
        exportConversationLogs('csv');
    });
    
    $('#export-json-btn').on('click', function() {
        exportConversationLogs('json');
    });
    
    // Handle conversation details modal
    $(document).on('click', '.view-conversation', function() {
        const conversationId = $(this).data('id');
        viewConversationDetails(conversationId);
    });
    
    // Handle refresh buttons
    $('#refresh-stats-btn').on('click', loadChatbotStats);
    $('#refresh-conversations-btn').on('click', loadRecentConversations);
}

/**
 * Load chatbot statistics
 */
function loadChatbotStats() {
    $.ajax({
        url: '../api/chatbot.php?action=get_stats',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                updateStatsDisplay(response);
                updateConversationChart(response.daily_stats);
            } else {
                showAlert('Error loading statistics: ' + response.error, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading stats:', error);
            showAlert('Failed to load chatbot statistics', 'danger');
        }
    });
}

/**
 * Update statistics display
 */
function updateStatsDisplay(data) {
    const stats = data.stats;
    
    // Update stat cards
    $('#total-conversations').text(stats.total_conversations || 0);
    $('#unique-users').text(stats.unique_users || 0);
    $('#conversations-24h').text(stats.conversations_24h || 0);
    $('#conversations-7d').text(stats.conversations_7d || 0);
    
    // Update additional stats
    $('#avg-message-length').text(Math.round(stats.avg_message_length || 0));
    $('#avg-response-length').text(Math.round(stats.avg_response_length || 0));
    $('#conversations-1h').text(stats.conversations_1h || 0);
    $('#conversations-30d').text(stats.conversations_30d || 0);
    
    // Update chatbot status indicator
    const statusIndicator = $('#chatbot-status');
    if (data.chatbot_enabled) {
        statusIndicator.removeClass('badge-danger').addClass('badge-success').text('Active');
    } else {
        statusIndicator.removeClass('badge-success').addClass('badge-danger').text('Disabled');
    }
    
    // Update top messages
    updateTopMessages(data.top_messages);
}

/**
 * Update top messages display
 */
function updateTopMessages(topMessages) {
    const container = $('#top-messages-list');
    container.empty();
    
    if (topMessages && topMessages.length > 0) {
        topMessages.forEach(function(message, index) {
            const messageHtml = `
                <div class="d-flex justify-content-between align-items-center py-2 ${index < topMessages.length - 1 ? 'border-bottom' : ''}">
                    <div class="flex-grow-1">
                        <small class="text-muted">${escapeHtml(message.user_message.substring(0, 80))}${message.user_message.length > 80 ? '...' : ''}</small>
                    </div>
                    <span class="badge badge-primary ml-2">${message.frequency}</span>
                </div>
            `;
            container.append(messageHtml);
        });
    } else {
        container.html('<p class="text-muted text-center">No frequent messages found</p>');
    }
}

/**
 * Update conversation chart
 */
function updateConversationChart(dailyStats) {
    const ctx = document.getElementById('conversationChart');
    if (!ctx) return;
    
    // Prepare data for chart
    const labels = [];
    const data = [];
    
    // Fill in the last 7 days
    for (let i = 6; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        const dateStr = date.toISOString().split('T')[0];
        labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        
        // Find data for this date
        const dayData = dailyStats.find(stat => stat.date === dateStr);
        data.push(dayData ? parseInt(dayData.count) : 0);
    }
    
    // Destroy existing chart if it exists
    if (conversationChart) {
        conversationChart.destroy();
    }
    
    // Create new chart
    conversationChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Conversations',
                data: data,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

/**
 * Load recent conversations
 */
function loadRecentConversations() {
    // This would typically load from a separate endpoint
    // For now, we'll show a placeholder
    const tableBody = $('#recent-conversations tbody');
    tableBody.html('<tr><td colspan="5" class="text-center">Loading conversations...</td></tr>');
    
    // Simulate loading recent conversations
    setTimeout(function() {
        tableBody.html('<tr><td colspan="5" class="text-center text-muted">No recent conversations found</td></tr>');
    }, 1000);
}

/**
 * Save chatbot settings
 */
function saveChatbotSettings() {
    const formData = new FormData($('#chatbot-settings-form')[0]);
    const submitBtn = $('#save-settings-btn');
    
    // Show loading state
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
    
    $.ajax({
        url: '../api/settings.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Chatbot settings saved successfully!', 'success');
                loadChatbotStats(); // Refresh stats to show updated status
            } else {
                showAlert('Error saving settings: ' + response.error, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error saving settings:', error);
            showAlert('Failed to save chatbot settings', 'danger');
        },
        complete: function() {
            // Reset button state
            submitBtn.prop('disabled', false).html('<i class="fas fa-save"></i> Save Settings');
        }
    });
}

/**
 * Test OpenAI connection
 */
function testOpenAIConnection() {
    const testBtn = $('#test-connection-btn');
    const apiKey = $('#openai_api_key').val();
    
    if (!apiKey) {
        showAlert('Please enter an OpenAI API key first', 'warning');
        return;
    }
    
    // Show loading state
    testBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Testing...');
    
    $.ajax({
        url: '../api/chatbot.php',
        method: 'POST',
        data: JSON.stringify({
            message: 'Test connection',
            test_mode: true
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('OpenAI connection test successful!', 'success');
            } else {
                showAlert('Connection test failed: ' + response.error, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Connection test error:', error);
            showAlert('Connection test failed. Please check your API key and settings.', 'danger');
        },
        complete: function() {
            // Reset button state
            testBtn.prop('disabled', false).html('<i class="fas fa-plug"></i> Test Connection');
        }
    });
}

/**
 * Clear old conversation logs
 */
function clearOldLogs() {
    const clearBtn = $('#clear-logs-btn');
    
    // Show loading state
    clearBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Clearing...');
    
    $.ajax({
        url: '../api/chatbot.php?action=clear_logs',
        method: 'POST',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Old conversation logs cleared successfully!', 'success');
                loadChatbotStats();
                loadRecentConversations();
            } else {
                showAlert('Error clearing logs: ' + response.error, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error clearing logs:', error);
            showAlert('Failed to clear conversation logs', 'danger');
        },
        complete: function() {
            // Reset button state
            clearBtn.prop('disabled', false).html('<i class="fas fa-trash"></i> Clear Old Logs');
        }
    });
}

/**
 * Export conversation logs
 */
function exportConversationLogs(format) {
    const days = $('#export-days').val() || 30;
    const url = `../api/chatbot.php?action=export_logs&format=${format}&days=${days}`;
    
    // Create a temporary link to trigger download
    const link = document.createElement('a');
    link.href = url;
    link.download = `chatbot_logs_${new Date().toISOString().split('T')[0]}.${format}`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showAlert(`Conversation logs exported as ${format.toUpperCase()}`, 'info');
}

/**
 * View conversation details
 */
function viewConversationDetails(conversationId) {
    $.ajax({
        url: `../api/chatbot.php?action=get_conversation&id=${conversationId}`,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayConversationModal(response.conversation);
            } else {
                showAlert('Error loading conversation: ' + response.error, 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading conversation:', error);
            showAlert('Failed to load conversation details', 'danger');
        }
    });
}

/**
 * Display conversation in modal
 */
function displayConversationModal(conversation) {
    const modal = $('#conversationModal');
    
    // Update modal content
    modal.find('.modal-title').text(`Conversation #${conversation.id}`);
    modal.find('#conversation-date').text(new Date(conversation.created_at).toLocaleString());
    modal.find('#conversation-user').text(
        conversation.first_name && conversation.last_name 
            ? `${conversation.first_name} ${conversation.last_name} (${conversation.email})` 
            : (conversation.email || 'Anonymous User')
    );
    modal.find('#conversation-ip').text(conversation.ip_address || 'Unknown');
    modal.find('#user-message').text(conversation.user_message);
    modal.find('#bot-response').text(conversation.bot_response);
    
    // Show modal
    modal.modal('show');
}

/**
 * Show alert message
 */
function showAlert(message, type = 'info') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `;
    
    $('#alert-container').prepend(alertHtml);
    
    // Auto-dismiss after 5 seconds
    setTimeout(function() {
        $('.alert').first().alert('close');
    }, 5000);
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Cleanup when page unloads
$(window).on('beforeunload', function() {
    if (statsRefreshInterval) {
        clearInterval(statsRefreshInterval);
    }
    if (conversationChart) {
        conversationChart.destroy();
    }
});