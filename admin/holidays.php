<?php
/**
 * Holiday Management System
 * Comprehensive holiday folder system for country-specific holiday management and compliance
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/audit_logger.php';

// Check admin authentication
requireAdmin();

$audit_logger = new AuditLogger();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_holiday':
                    $result = addHoliday($_POST);
                    if ($result) {
                        $message = 'Holiday added successfully!';
                        $audit_logger->logActivity([
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'holiday_added',
                            'details' => [
                                'country' => $_POST['country_code'],
                                'holiday_name' => $_POST['holiday_name'],
                                'holiday_date' => $_POST['holiday_date']
                            ]
                        ]);
                    } else {
                        $error = 'Failed to add holiday.';
                    }
                    break;
                    
                case 'update_holiday':
                    $result = updateHoliday($_POST);
                    if ($result) {
                        $message = 'Holiday updated successfully!';
                        $audit_logger->logActivity([
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'holiday_updated',
                            'details' => [
                                'holiday_id' => $_POST['holiday_id'],
                                'country' => $_POST['country_code'],
                                'holiday_name' => $_POST['holiday_name']
                            ]
                        ]);
                    } else {
                        $error = 'Failed to update holiday.';
                    }
                    break;
                    
                case 'delete_holiday':
                    $result = deleteHoliday($_POST['holiday_id']);
                    if ($result) {
                        $message = 'Holiday deleted successfully!';
                        $audit_logger->logActivity([
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'holiday_deleted',
                            'details' => ['holiday_id' => $_POST['holiday_id']]
                        ]);
                    } else {
                        $error = 'Failed to delete holiday.';
                    }
                    break;
                    
                case 'import_holidays':
                    $result = importHolidaysForYear($_POST['import_year'], $_POST['import_country']);
                    if ($result) {
                        $message = "Holidays imported successfully for {$_POST['import_country']} {$_POST['import_year']}!";
                        $audit_logger->logActivity([
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'holidays_imported',
                            'details' => [
                                'year' => $_POST['import_year'],
                                'country' => $_POST['import_country'],
                                'count' => $result
                            ]
                        ]);
                    } else {
                        $error = 'Failed to import holidays.';
                    }
                    break;
                    
                case 'bulk_delete':
                    if (isset($_POST['selected_holidays']) && is_array($_POST['selected_holidays'])) {
                        $deleted_count = 0;
                        foreach ($_POST['selected_holidays'] as $holiday_id) {
                            if (deleteHoliday($holiday_id)) {
                                $deleted_count++;
                            }
                        }
                        $message = "Deleted {$deleted_count} holidays successfully!";
                        $audit_logger->logActivity([
                            'user_id' => $_SESSION['user_id'],
                            'action' => 'holidays_bulk_deleted',
                            'details' => ['count' => $deleted_count]
                        ]);
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
        error_log("Holiday management error: " . $e->getMessage());
    }
}

// Get filter parameters
$filter_country = $_GET['country'] ?? '';
$filter_year = $_GET['year'] ?? date('Y');
$search = $_GET['search'] ?? '';

// Get holidays with filters
$holidays = getHolidays($filter_country, $filter_year, $search);
$countries = getSupportedCountries();
$holiday_stats = getHolidayStats();

// Helper functions
function addHoliday($data) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO holidays (country_code, holiday_name, holiday_date, is_recurring, year) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $year = date('Y', strtotime($data['holiday_date']));
    $is_recurring = isset($data['is_recurring']) ? 1 : 0;
    
    return $stmt->execute([
        $data['country_code'],
        $data['holiday_name'],
        $data['holiday_date'],
        $is_recurring,
        $year
    ]);
}

function updateHoliday($data) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE holidays 
        SET country_code = ?, holiday_name = ?, holiday_date = ?, is_recurring = ?, year = ?
        WHERE id = ?
    ");
    
    $year = date('Y', strtotime($data['holiday_date']));
    $is_recurring = isset($data['is_recurring']) ? 1 : 0;
    
    return $stmt->execute([
        $data['country_code'],
        $data['holiday_name'],
        $data['holiday_date'],
        $is_recurring,
        $year,
        $data['holiday_id']
    ]);
}

function deleteHoliday($holiday_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("DELETE FROM holidays WHERE id = ?");
    return $stmt->execute([$holiday_id]);
}

function getHolidays($country = '', $year = '', $search = '') {
    global $pdo;
    
    $sql = "SELECT * FROM holidays WHERE 1=1";
    $params = [];
    
    if ($country) {
        $sql .= " AND country_code = ?";
        $params[] = $country;
    }
    
    if ($year) {
        $sql .= " AND year = ?";
        $params[] = $year;
    }
    
    if ($search) {
        $sql .= " AND holiday_name LIKE ?";
        $params[] = "%{$search}%";
    }
    
    $sql .= " ORDER BY holiday_date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getHolidayStats() {
    global $pdo;
    
    $stats = [];
    
    // Total holidays
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM holidays");
    $stats['total'] = $stmt->fetch()['total'];
    
    // Holidays by country
    $stmt = $pdo->query("
        SELECT country_code, COUNT(*) as count 
        FROM holidays 
        GROUP BY country_code 
        ORDER BY count DESC
    ");
    $stats['by_country'] = $stmt->fetchAll();
    
    // Upcoming holidays (next 30 days)
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM holidays 
        WHERE holiday_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $stats['upcoming'] = $stmt->fetch()['count'];
    
    return $stats;
}

function importHolidaysForYear($year, $country) {
    global $pdo;
    
    $holidays_data = getCountryHolidaysData($country, $year);
    $imported_count = 0;
    
    foreach ($holidays_data as $holiday) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO holidays (country_code, holiday_name, holiday_date, is_recurring, year) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([
            $country,
            $holiday['name'],
            $holiday['date'],
            $holiday['recurring'] ?? 1,
            $year
        ])) {
            $imported_count++;
        }
    }
    
    return $imported_count;
}

function getCountryHolidaysData($country, $year) {
    // Comprehensive holiday data for different countries
    $holidays = [];
    
    switch ($country) {
        case 'USA':
            $holidays = [
                ['name' => "New Year's Day", 'date' => "$year-01-01", 'recurring' => 1],
                ['name' => "Martin Luther King Jr. Day", 'date' => getThirdMondayOfJanuary($year), 'recurring' => 1],
                ['name' => "Presidents' Day", 'date' => getThirdMondayOfFebruary($year), 'recurring' => 1],
                ['name' => "Memorial Day", 'date' => getLastMondayOfMay($year), 'recurring' => 1],
                ['name' => "Independence Day", 'date' => "$year-07-04", 'recurring' => 1],
                ['name' => "Labor Day", 'date' => getFirstMondayOfSeptember($year), 'recurring' => 1],
                ['name' => "Columbus Day", 'date' => getSecondMondayOfOctober($year), 'recurring' => 1],
                ['name' => "Veterans Day", 'date' => "$year-11-11", 'recurring' => 1],
                ['name' => "Thanksgiving Day", 'date' => getFourthThursdayOfNovember($year), 'recurring' => 1],
                ['name' => "Christmas Day", 'date' => "$year-12-25", 'recurring' => 1]
            ];
            break;
            
        case 'CAN':
            $holidays = [
                ['name' => "New Year's Day", 'date' => "$year-01-01", 'recurring' => 1],
                ['name' => "Family Day", 'date' => getThirdMondayOfFebruary($year), 'recurring' => 1],
                ['name' => "Good Friday", 'date' => getGoodFriday($year), 'recurring' => 1],
                ['name' => "Easter Monday", 'date' => getEasterMonday($year), 'recurring' => 1],
                ['name' => "Victoria Day", 'date' => getVictoriaDay($year), 'recurring' => 1],
                ['name' => "Canada Day", 'date' => "$year-07-01", 'recurring' => 1],
                ['name' => "Civic Holiday", 'date' => getFirstMondayOfAugust($year), 'recurring' => 1],
                ['name' => "Labour Day", 'date' => getFirstMondayOfSeptember($year), 'recurring' => 1],
                ['name' => "Thanksgiving Day", 'date' => getSecondMondayOfOctober($year), 'recurring' => 1],
                ['name' => "Remembrance Day", 'date' => "$year-11-11", 'recurring' => 1],
                ['name' => "Christmas Day", 'date' => "$year-12-25", 'recurring' => 1],
                ['name' => "Boxing Day", 'date' => "$year-12-26", 'recurring' => 1]
            ];
            break;
            
        case 'GBR':
            $holidays = [
                ['name' => "New Year's Day", 'date' => "$year-01-01", 'recurring' => 1],
                ['name' => "Good Friday", 'date' => getGoodFriday($year), 'recurring' => 1],
                ['name' => "Easter Monday", 'date' => getEasterMonday($year), 'recurring' => 1],
                ['name' => "Early May Bank Holiday", 'date' => getFirstMondayOfMay($year), 'recurring' => 1],
                ['name' => "Spring Bank Holiday", 'date' => getLastMondayOfMay($year), 'recurring' => 1],
                ['name' => "Summer Bank Holiday", 'date' => getLastMondayOfAugust($year), 'recurring' => 1],
                ['name' => "Christmas Day", 'date' => "$year-12-25", 'recurring' => 1],
                ['name' => "Boxing Day", 'date' => "$year-12-26", 'recurring' => 1]
            ];
            break;
            
        case 'AUS':
            $holidays = [
                ['name' => "New Year's Day", 'date' => "$year-01-01", 'recurring' => 1],
                ['name' => "Australia Day", 'date' => "$year-01-26", 'recurring' => 1],
                ['name' => "Good Friday", 'date' => getGoodFriday($year), 'recurring' => 1],
                ['name' => "Easter Saturday", 'date' => getEasterSaturday($year), 'recurring' => 1],
                ['name' => "Easter Monday", 'date' => getEasterMonday($year), 'recurring' => 1],
                ['name' => "Anzac Day", 'date' => "$year-04-25", 'recurring' => 1],
                ['name' => "Queen's Birthday", 'date' => getSecondMondayOfJune($year), 'recurring' => 1],
                ['name' => "Christmas Day", 'date' => "$year-12-25", 'recurring' => 1],
                ['name' => "Boxing Day", 'date' => "$year-12-26", 'recurring' => 1]
            ];
            break;
    }
    
    return $holidays;
}

// Helper functions for calculating holiday dates
function getThirdMondayOfJanuary($year) {
    return date('Y-m-d', strtotime("third monday of january $year"));
}

function getThirdMondayOfFebruary($year) {
    return date('Y-m-d', strtotime("third monday of february $year"));
}

function getLastMondayOfMay($year) {
    return date('Y-m-d', strtotime("last monday of may $year"));
}

function getFirstMondayOfSeptember($year) {
    return date('Y-m-d', strtotime("first monday of september $year"));
}

function getSecondMondayOfOctober($year) {
    return date('Y-m-d', strtotime("second monday of october $year"));
}

function getFourthThursdayOfNovember($year) {
    return date('Y-m-d', strtotime("fourth thursday of november $year"));
}

function getFirstMondayOfAugust($year) {
    return date('Y-m-d', strtotime("first monday of august $year"));
}

function getVictoriaDay($year) {
    // Monday before May 25
    $may25 = new DateTime("$year-05-25");
    $dayOfWeek = $may25->format('w');
    $daysToSubtract = ($dayOfWeek == 1) ? 0 : (8 - $dayOfWeek);
    $may25->sub(new DateInterval("P{$daysToSubtract}D"));
    return $may25->format('Y-m-d');
}

function getGoodFriday($year) {
    $easter = new DateTime("$year-03-21");
    $easter->add(new DateInterval('P' . easter_days($year) . 'D'));
    $easter->sub(new DateInterval('P2D'));
    return $easter->format('Y-m-d');
}

function getEasterMonday($year) {
    $easter = new DateTime("$year-03-21");
    $easter->add(new DateInterval('P' . easter_days($year) . 'D'));
    $easter->add(new DateInterval('P1D'));
    return $easter->format('Y-m-d');
}

function getEasterSaturday($year) {
    $easter = new DateTime("$year-03-21");
    $easter->add(new DateInterval('P' . easter_days($year) . 'D'));
    $easter->sub(new DateInterval('P1D'));
    return $easter->format('Y-m-d');
}

function getFirstMondayOfMay($year) {
    return date('Y-m-d', strtotime("first monday of may $year"));
}

function getLastMondayOfAugust($year) {
    return date('Y-m-d', strtotime("last monday of august $year"));
}

function getSecondMondayOfJune($year) {
    return date('Y-m-d', strtotime("second monday of june $year"));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holiday Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .holiday-card {
            transition: transform 0.2s;
        }
        .holiday-card:hover {
            transform: translateY(-2px);
        }
        .country-badge {
            font-size: 0.8em;
        }
        .holiday-date {
            font-weight: bold;
            color: #0d6efd;
        }
        .recurring-badge {
            background-color: #198754;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .filter-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-calendar-alt me-2"></i>Holiday Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addHolidayModal">
                            <i class="fas fa-plus me-1"></i>Add Holiday
                        </button>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importHolidaysModal">
                            <i class="fas fa-download me-1"></i>Import Holidays
                        </button>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                <h3><?php echo $holiday_stats['total']; ?></h3>
                                <p class="mb-0">Total Holidays</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <h3><?php echo $holiday_stats['upcoming']; ?></h3>
                                <p class="mb-0">Upcoming (30 days)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-globe fa-2x mb-2"></i>
                                <h3><?php echo count($holiday_stats['by_country']); ?></h3>
                                <p class="mb-0">Countries</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-day fa-2x mb-2"></i>
                                <h3><?php echo date('Y'); ?></h3>
                                <p class="mb-0">Current Year</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="country" class="form-label">Country</label>
                            <select name="country" id="country" class="form-select">
                                <option value="">All Countries</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo $country; ?>" <?php echo $filter_country === $country ? 'selected' : ''; ?>>
                                        <?php echo getCountryName($country); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="year" class="form-label">Year</label>
                            <select name="year" id="year" class="form-select">
                                <?php for ($y = date('Y') - 2; $y <= date('Y') + 5; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $filter_year == $y ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search Holiday Name</label>
                            <input type="text" name="search" id="search" class="form-control" 
                                   value="<?php echo htmlspecialchars($search); ?>" placeholder="Enter holiday name...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Holidays List -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Holidays List</h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="bulkDelete()" id="bulkDeleteBtn" style="display: none;">
                                <i class="fas fa-trash me-1"></i>Delete Selected
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($holidays)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No holidays found</h5>
                                <p class="text-muted">Try adjusting your filters or add some holidays.</p>
                            </div>
                        <?php else: ?>
                            <form id="bulkForm" method="POST">
                                <input type="hidden" name="action" value="bulk_delete">
                                <div class="row">
                                    <?php foreach ($holidays as $holiday): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card holiday-card h-100">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div class="form-check">
                                                            <input class="form-check-input holiday-checkbox" type="checkbox" 
                                                                   name="selected_holidays[]" value="<?php echo $holiday['id']; ?>" 
                                                                   onchange="toggleBulkDelete()">
                                                        </div>
                                                        <span class="badge bg-primary country-badge">
                                                            <?php echo htmlspecialchars($holiday['country_code']); ?>
                                                        </span>
                                                    </div>
                                                    <h6 class="card-title"><?php echo htmlspecialchars($holiday['holiday_name']); ?></h6>
                                                    <p class="holiday-date mb-2">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo date('M j, Y', strtotime($holiday['holiday_date'])); ?>
                                                    </p>
                                                    <?php if ($holiday['is_recurring']): ?>
                                                        <span class="badge recurring-badge mb-2">
                                                            <i class="fas fa-redo me-1"></i>Recurring
                                                        </span>
                                                    <?php endif; ?>
                                                    <div class="mt-3">
                                                        <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                                                onclick="editHoliday(<?php echo htmlspecialchars(json_encode($holiday)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteHoliday(<?php echo $holiday['id']; ?>, '<?php echo htmlspecialchars($holiday['holiday_name']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Holiday Modal -->
    <div class="modal fade" id="addHolidayModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add_holiday">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Holiday</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="add_country_code" class="form-label">Country</label>
                            <select name="country_code" id="add_country_code" class="form-select" required>
                                <option value="">Select Country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo $country; ?>">
                                        <?php echo getCountryName($country); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="add_holiday_name" class="form-label">Holiday Name</label>
                            <input type="text" name="holiday_name" id="add_holiday_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_holiday_date" class="form-label">Holiday Date</label>
                            <input type="date" name="holiday_date" id="add_holiday_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_recurring" id="add_is_recurring" class="form-check-input" checked>
                                <label for="add_is_recurring" class="form-check-label">
                                    Recurring Holiday (repeats every year)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Holiday</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Holiday Modal -->
    <div class="modal fade" id="editHolidayModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_holiday">
                    <input type="hidden" name="holiday_id" id="edit_holiday_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Holiday</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_country_code" class="form-label">Country</label>
                            <select name="country_code" id="edit_country_code" class="form-select" required>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo $country; ?>">
                                        <?php echo getCountryName($country); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_holiday_name" class="form-label">Holiday Name</label>
                            <input type="text" name="holiday_name" id="edit_holiday_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_holiday_date" class="form-label">Holiday Date</label>
                            <input type="date" name="holiday_date" id="edit_holiday_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_recurring" id="edit_is_recurring" class="form-check-input">
                                <label for="edit_is_recurring" class="form-check-label">
                                    Recurring Holiday (repeats every year)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Holiday</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Import Holidays Modal -->
    <div class="modal fade" id="importHolidaysModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="import_holidays">
                    <div class="modal-header">
                        <h5 class="modal-title">Import Holidays</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This will import standard holidays for the selected country and year.
                        </div>
                        <div class="mb-3">
                            <label for="import_country" class="form-label">Country</label>
                            <select name="import_country" id="import_country" class="form-select" required>
                                <option value="">Select Country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo $country; ?>">
                                        <?php echo getCountryName($country); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="import_year" class="form-label">Year</label>
                            <select name="import_year" id="import_year" class="form-select" required>
                                <?php for ($y = date('Y'); $y <= date('Y') + 5; $y++): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Import Holidays</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editHoliday(holiday) {
            document.getElementById('edit_holiday_id').value = holiday.id;
            document.getElementById('edit_country_code').value = holiday.country_code;
            document.getElementById('edit_holiday_name').value = holiday.holiday_name;
            document.getElementById('edit_holiday_date').value = holiday.holiday_date;
            document.getElementById('edit_is_recurring').checked = holiday.is_recurring == 1;
            
            new bootstrap.Modal(document.getElementById('editHolidayModal')).show();
        }
        
        function deleteHoliday(id, name) {
            if (confirm(`Are you sure you want to delete the holiday "${name}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_holiday">
                    <input type="hidden" name="holiday_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function toggleBulkDelete() {
            const checkboxes = document.querySelectorAll('.holiday-checkbox:checked');
            const bulkBtn = document.getElementById('bulkDeleteBtn');
            
            if (checkboxes.length > 0) {
                bulkBtn.style.display = 'inline-block';
            } else {
                bulkBtn.style.display = 'none';
            }
        }
        
        function bulkDelete() {
            const checkboxes = document.querySelectorAll('.holiday-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('Please select holidays to delete.');
                return;
            }
            
            if (confirm(`Are you sure you want to delete ${checkboxes.length} selected holidays?`)) {
                document.getElementById('bulkForm').submit();
            }
        }
    </script>
</body>
</html>