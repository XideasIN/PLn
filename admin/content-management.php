<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$page_title = 'Content Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - QuickFunds Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .content-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
        }
        .content-item.active {
            border-color: #28a745;
            background: #d4edda;
        }
        .content-item.inactive {
            border-color: #dc3545;
            background: #f8d7da;
        }
        .drag-handle {
            cursor: move;
            color: #6c757d;
        }
        .rating-stars {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                        <span>Admin Panel</span>
                    </h6>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="bi bi-house"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="content-management.php">
                                <i class="bi bi-file-text"></i> Content Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="applications.php">
                                <i class="bi bi-file-earmark-text"></i> Applications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="system-settings.php">
                                <i class="bi bi-gear"></i> Settings
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $page_title; ?></h1>
                </div>

                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs" id="contentTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="faq-tab" data-bs-toggle="tab" data-bs-target="#faq" type="button" role="tab">
                            <i class="bi bi-question-circle"></i> FAQs Management
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="testimonial-tab" data-bs-toggle="tab" data-bs-target="#testimonial" type="button" role="tab">
                            <i class="bi bi-chat-quote"></i> Testimonials Management
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="contentTabsContent">
                    <!-- FAQ Management Tab -->
                    <div class="tab-pane fade show active" id="faq" role="tabpanel">
                        <div class="row mt-4">
                            <div class="col-md-8">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4>Manage FAQs</h4>
                                    <button class="btn btn-primary" onclick="showAddFaqModal()">
                                        <i class="bi bi-plus-circle"></i> Add New FAQ
                                    </button>
                                </div>
                                <div id="faqList" class="sortable-list">
                                    <!-- FAQs will be loaded here -->
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>FAQ Guidelines</h5>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled">
                                            <li><i class="bi bi-check-circle text-success"></i> Keep questions clear and concise</li>
                                            <li><i class="bi bi-check-circle text-success"></i> Provide comprehensive answers</li>
                                            <li><i class="bi bi-check-circle text-success"></i> Use drag & drop to reorder</li>
                                            <li><i class="bi bi-check-circle text-success"></i> Only 7 FAQs show on homepage</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Testimonial Management Tab -->
                    <div class="tab-pane fade" id="testimonial" role="tabpanel">
                        <div class="row mt-4">
                            <div class="col-md-8">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4>Manage Testimonials</h4>
                                    <button class="btn btn-primary" onclick="showAddTestimonialModal()">
                                        <i class="bi bi-plus-circle"></i> Add New Testimonial
                                    </button>
                                </div>
                                <div id="testimonialList" class="sortable-list">
                                    <!-- Testimonials will be loaded here -->
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Testimonial Guidelines</h5>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled">
                                            <li><i class="bi bi-check-circle text-success"></i> Use real client feedback</li>
                                            <li><i class="bi bi-check-circle text-success"></i> Include client name and title</li>
                                            <li><i class="bi bi-check-circle text-success"></i> Keep testimonials authentic</li>
                                            <li><i class="bi bi-check-circle text-success"></i> Only 5 testimonials show on homepage</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- FAQ Modal -->
    <div class="modal fade" id="faqModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="faqModalTitle">Add New FAQ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="faqForm">
                        <input type="hidden" id="faqId" name="id">
                        <div class="mb-3">
                            <label for="faqQuestion" class="form-label">Question *</label>
                            <textarea class="form-control" id="faqQuestion" name="question" rows="2" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="faqAnswer" class="form-label">Answer *</label>
                            <textarea class="form-control" id="faqAnswer" name="answer" rows="4" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="faqOrder" class="form-label">Display Order</label>
                                    <input type="number" class="form-control" id="faqOrder" name="display_order" min="0" value="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="faqActive" name="is_active" checked>
                                        <label class="form-check-label" for="faqActive">Active</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveFaq()">Save FAQ</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Testimonial Modal -->
    <div class="modal fade" id="testimonialModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="testimonialModalTitle">Add New Testimonial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="testimonialForm">
                        <input type="hidden" id="testimonialId" name="id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="clientName" class="form-label">Client Name *</label>
                                    <input type="text" class="form-control" id="clientName" name="client_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="clientTitle" class="form-label">Client Title</label>
                                    <input type="text" class="form-control" id="clientTitle" name="client_title" placeholder="e.g., Business Owner">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="testimonialText" class="form-label">Testimonial Text *</label>
                            <textarea class="form-control" id="testimonialText" name="testimonial_text" rows="4" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="clientImage" class="form-label">Client Image URL</label>
                                    <input type="url" class="form-control" id="clientImage" name="client_image" placeholder="https://...">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="rating" class="form-label">Rating</label>
                                    <select class="form-control" id="rating" name="rating">
                                        <option value="5">5 Stars</option>
                                        <option value="4">4 Stars</option>
                                        <option value="3">3 Stars</option>
                                        <option value="2">2 Stars</option>
                                        <option value="1">1 Star</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="testimonialOrder" class="form-label">Display Order</label>
                                    <input type="number" class="form-control" id="testimonialOrder" name="display_order" min="0" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="testimonialActive" name="is_active" checked>
                                <label class="form-check-label" for="testimonialActive">Active</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveTestimonial()">Save Testimonial</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadFaqs();
            loadTestimonials();
            initializeSortable();
        });

        // Load FAQs
        function loadFaqs() {
            fetch('../api/manage-content.php?type=faq')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayFaqs(data.data);
                    } else {
                        console.error('Error loading FAQs:', data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Display FAQs
        function displayFaqs(faqs) {
            const container = document.getElementById('faqList');
            container.innerHTML = '';
            
            faqs.forEach(faq => {
                const faqElement = document.createElement('div');
                faqElement.className = `content-item ${faq.is_active ? 'active' : 'inactive'}`;
                faqElement.dataset.id = faq.id;
                faqElement.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-grip-vertical drag-handle me-2"></i>
                                <strong>Q: ${faq.question}</strong>
                                <span class="badge ${faq.is_active ? 'bg-success' : 'bg-danger'} ms-2">
                                    ${faq.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </div>
                            <p class="mb-0 text-muted">A: ${faq.answer}</p>
                        </div>
                        <div class="btn-group ms-3">
                            <button class="btn btn-sm btn-outline-primary" onclick="editFaq(${faq.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteFaq(${faq.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                container.appendChild(faqElement);
            });
        }

        // Load Testimonials
        function loadTestimonials() {
            fetch('../api/manage-content.php?type=testimonial')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayTestimonials(data.data);
                    } else {
                        console.error('Error loading testimonials:', data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Display Testimonials
        function displayTestimonials(testimonials) {
            const container = document.getElementById('testimonialList');
            container.innerHTML = '';
            
            testimonials.forEach(testimonial => {
                const testimonialElement = document.createElement('div');
                testimonialElement.className = `content-item ${testimonial.is_active ? 'active' : 'inactive'}`;
                testimonialElement.dataset.id = testimonial.id;
                
                const stars = '★'.repeat(testimonial.rating) + '☆'.repeat(5 - testimonial.rating);
                
                testimonialElement.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-grip-vertical drag-handle me-2"></i>
                                <strong>${testimonial.client_name}</strong>
                                ${testimonial.client_title ? `<span class="text-muted ms-2">- ${testimonial.client_title}</span>` : ''}
                                <span class="badge ${testimonial.is_active ? 'bg-success' : 'bg-danger'} ms-2">
                                    ${testimonial.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </div>
                            <div class="rating-stars mb-2">${stars}</div>
                            <p class="mb-0 text-muted">"${testimonial.testimonial_text}"</p>
                        </div>
                        <div class="btn-group ms-3">
                            <button class="btn btn-sm btn-outline-primary" onclick="editTestimonial(${testimonial.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteTestimonial(${testimonial.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                container.appendChild(testimonialElement);
            });
        }

        // Initialize sortable functionality
        function initializeSortable() {
            new Sortable(document.getElementById('faqList'), {
                handle: '.drag-handle',
                animation: 150,
                onEnd: function(evt) {
                    updateFaqOrder();
                }
            });
            
            new Sortable(document.getElementById('testimonialList'), {
                handle: '.drag-handle',
                animation: 150,
                onEnd: function(evt) {
                    updateTestimonialOrder();
                }
            });
        }

        // Show add FAQ modal
        function showAddFaqModal() {
            document.getElementById('faqModalTitle').textContent = 'Add New FAQ';
            document.getElementById('faqForm').reset();
            document.getElementById('faqId').value = '';
            new bootstrap.Modal(document.getElementById('faqModal')).show();
        }

        // Show add testimonial modal
        function showAddTestimonialModal() {
            document.getElementById('testimonialModalTitle').textContent = 'Add New Testimonial';
            document.getElementById('testimonialForm').reset();
            document.getElementById('testimonialId').value = '';
            new bootstrap.Modal(document.getElementById('testimonialModal')).show();
        }

        // Edit FAQ
        function editFaq(id) {
            fetch(`../api/manage-content.php?type=faq&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const faq = data.data;
                        document.getElementById('faqModalTitle').textContent = 'Edit FAQ';
                        document.getElementById('faqId').value = faq.id;
                        document.getElementById('faqQuestion').value = faq.question;
                        document.getElementById('faqAnswer').value = faq.answer;
                        document.getElementById('faqOrder').value = faq.display_order;
                        document.getElementById('faqActive').checked = faq.is_active == 1;
                        new bootstrap.Modal(document.getElementById('faqModal')).show();
                    }
                });
        }

        // Edit Testimonial
        function editTestimonial(id) {
            fetch(`../api/manage-content.php?type=testimonial&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const testimonial = data.data;
                        document.getElementById('testimonialModalTitle').textContent = 'Edit Testimonial';
                        document.getElementById('testimonialId').value = testimonial.id;
                        document.getElementById('clientName').value = testimonial.client_name;
                        document.getElementById('clientTitle').value = testimonial.client_title || '';
                        document.getElementById('testimonialText').value = testimonial.testimonial_text;
                        document.getElementById('clientImage').value = testimonial.client_image || '';
                        document.getElementById('rating').value = testimonial.rating;
                        document.getElementById('testimonialOrder').value = testimonial.display_order;
                        document.getElementById('testimonialActive').checked = testimonial.is_active == 1;
                        new bootstrap.Modal(document.getElementById('testimonialModal')).show();
                    }
                });
        }

        // Save FAQ
        function saveFaq() {
            const form = document.getElementById('faqForm');
            const formData = new FormData(form);
            const data = {
                question: formData.get('question'),
                answer: formData.get('answer'),
                display_order: parseInt(formData.get('display_order')) || 0,
                is_active: formData.get('is_active') ? 1 : 0
            };
            
            const id = formData.get('id');
            const method = id ? 'PUT' : 'POST';
            const url = id ? `../api/manage-content.php?type=faq&id=${id}` : '../api/manage-content.php?type=faq';
            
            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('faqModal')).hide();
                    loadFaqs();
                    alert(result.message);
                } else {
                    alert('Error: ' + (result.details ? result.details.join(', ') : result.error));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the FAQ.');
            });
        }

        // Save Testimonial
        function saveTestimonial() {
            const form = document.getElementById('testimonialForm');
            const formData = new FormData(form);
            const data = {
                client_name: formData.get('client_name'),
                client_title: formData.get('client_title'),
                testimonial_text: formData.get('testimonial_text'),
                client_image: formData.get('client_image'),
                rating: parseInt(formData.get('rating')) || 5,
                display_order: parseInt(formData.get('display_order')) || 0,
                is_active: formData.get('is_active') ? 1 : 0
            };
            
            const id = formData.get('id');
            const method = id ? 'PUT' : 'POST';
            const url = id ? `../api/manage-content.php?type=testimonial&id=${id}` : '../api/manage-content.php?type=testimonial';
            
            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('testimonialModal')).hide();
                    loadTestimonials();
                    alert(result.message);
                } else {
                    alert('Error: ' + (result.details ? result.details.join(', ') : result.error));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the testimonial.');
            });
        }

        // Delete FAQ
        function deleteFaq(id) {
            if (confirm('Are you sure you want to delete this FAQ?')) {
                fetch(`../api/manage-content.php?type=faq&id=${id}`, {
                    method: 'DELETE'
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        loadFaqs();
                        alert(result.message);
                    } else {
                        alert('Error: ' + result.error);
                    }
                });
            }
        }

        // Delete Testimonial
        function deleteTestimonial(id) {
            if (confirm('Are you sure you want to delete this testimonial?')) {
                fetch(`../api/manage-content.php?type=testimonial&id=${id}`, {
                    method: 'DELETE'
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        loadTestimonials();
                        alert(result.message);
                    } else {
                        alert('Error: ' + result.error);
                    }
                });
            }
        }

        // Update FAQ order after drag and drop
        function updateFaqOrder() {
            const items = document.querySelectorAll('#faqList .content-item');
            const updates = [];
            
            items.forEach((item, index) => {
                const id = item.dataset.id;
                updates.push({
                    id: id,
                    display_order: index + 1,
                    is_active: 1
                });
            });
            
            // Update each FAQ's order
            updates.forEach(update => {
                fetch(`../api/manage-content.php?type=faq&id=${update.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(update)
                });
            });
        }

        // Update Testimonial order after drag and drop
        function updateTestimonialOrder() {
            const items = document.querySelectorAll('#testimonialList .content-item');
            const updates = [];
            
            items.forEach((item, index) => {
                const id = item.dataset.id;
                updates.push({
                    id: id,
                    display_order: index + 1,
                    is_active: 1
                });
            });
            
            // Update each testimonial's order
            updates.forEach(update => {
                fetch(`../api/manage-content.php?type=testimonial&id=${update.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(update)
                });
            });
        }
    </script>
</body>
</html>