<?php
session_start();
// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Get filters
$table_filter = isset($_GET['table']) ? $_GET['table'] : '';
$user_filter = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';

// Build query based on filters
$query = "SELECT a.*, u.username, u.first_name, u.last_name 
          FROM audittrail a 
          JOIN user u ON a.user_id = u.user_id 
          WHERE 1=1";
$params = [];

if (!empty($table_filter)) {
    $query .= " AND a.table_affected = :table";
    $params[':table'] = $table_filter;
}

if (!empty($user_filter)) {
    $query .= " AND a.user_id = :user_id";
    $params[':user_id'] = $user_filter;
}

if (!empty($action_filter)) {
    $query .= " AND a.action_performed = :action";
    $params[':action'] = $action_filter;
}

if (!empty($date_start)) {
    $query .= " AND DATE(a.timestamp) >= :date_start";
    $params[':date_start'] = $date_start;
}

if (!empty($date_end)) {
    $query .= " AND DATE(a.timestamp) <= :date_end";
    $params[':date_end'] = $date_end;
}

// Add sorting
$query .= " ORDER BY a.timestamp DESC";

// Add limit for pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

$query .= " LIMIT :offset, :limit";
$params[':offset'] = $offset;
$params[':limit'] = $records_per_page;

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM audittrail a WHERE 1=1";
$count_params = [];

if (!empty($table_filter)) {
    $count_query .= " AND a.table_affected = :table";
    $count_params[':table'] = $table_filter;
}

if (!empty($user_filter)) {
    $count_query .= " AND a.user_id = :user_id";
    $count_params[':user_id'] = $user_filter;
}

if (!empty($action_filter)) {
    $count_query .= " AND a.action_performed = :action";
    $count_params[':action'] = $action_filter;
}

if (!empty($date_start)) {
    $count_query .= " AND DATE(a.timestamp) >= :date_start";
    $count_params[':date_start'] = $date_start;
}

if (!empty($date_end)) {
    $count_query .= " AND DATE(a.timestamp) <= :date_end";
    $count_params[':date_end'] = $date_end;
}

$count_stmt = $conn->prepare($count_query);
foreach ($count_params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Execute the main query
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    if ($key == ':offset' || $key == ':limit') {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value);
    }
}
$stmt->execute();
$audit_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique tables for filter
$tables_stmt = $conn->prepare("SELECT DISTINCT table_affected FROM audittrail ORDER BY table_affected");
$tables_stmt->execute();
$tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique actions for filter
$actions_stmt = $conn->prepare("SELECT DISTINCT action_performed FROM audittrail ORDER BY action_performed");
$actions_stmt->execute();
$actions = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get users for filter
$users_stmt = $conn->prepare("SELECT DISTINCT u.user_id, u.username, u.first_name, u.last_name 
                             FROM audittrail a 
                             JOIN user u ON a.user_id = u.user_id 
                             ORDER BY u.username");
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail - JRC Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Page Content -->
        <div id="page-content-wrapper">
            <!-- Navbar -->
            <?php include 'includes/navbar.php'; ?>
            
            <div class="container-fluid px-4 py-3">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="mb-0">Audit Trail</h3>
                    <a href="export_audit.php" class="btn btn-outline-primary">
                        <i class="fas fa-file-export me-2"></i> Export Audit Log
                    </a>
                </div>
                
                <!-- Filter Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-3">
                                <label for="table" class="form-label">Table</label>
                                <select class="form-select" id="table" name="table">
                                    <option value="">All Tables</option>
                                    <?php foreach($tables as $table): ?>
                                    <option value="<?= $table ?>" <?= $table_filter == $table ? 'selected' : '' ?>><?= $table ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="user_id" class="form-label">User</label>
                                <select class="form-select" id="user_id" name="user_id">
                                    <option value="">All Users</option>
                                    <?php foreach($users as $user): ?>
                                    <option value="<?= $user['user_id'] ?>" <?= $user_filter == $user['user_id'] ? 'selected' : '' ?>>
                                        <?= $user['username'] ?> (<?= $user['first_name'] . ' ' . $user['last_name'] ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="action" class="form-label">Action</label>
                                <select class="form-select" id="action" name="action">
                                    <option value="">All Actions</option>
                                    <?php foreach($actions as $action): ?>
                                    <option value="<?= $action ?>" <?= $action_filter == $action ? 'selected' : '' ?>><?= $action ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="daterange" class="form-label">Date Range</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="daterange" name="daterange" 
                                           value="<?= (!empty($date_start) && !empty($date_end)) ? date('m/d/Y', strtotime($date_start)) . ' - ' . date('m/d/Y', strtotime($date_end)) : '' ?>" 
                                           placeholder="All Dates">
                                    <input type="hidden" name="date_start" id="date_start" value="<?= $date_start ?>">
                                    <input type="hidden" name="date_end" id="date_end" value="<?= $date_end ?>">
                                    <button class="btn btn-outline-secondary" type="button" id="clearDate">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 d-flex">
                                <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                                <a href="audittrail.php" class="btn btn-outline-secondary">Reset Filters</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Audit Records Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span>Showing <?= count($audit_records) ?> of <?= $total_records ?> records</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Table</th>
                                        <th>Timestamp</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($audit_records) > 0): ?>
                                        <?php foreach($audit_records as $record): ?>
                                        <tr>
                                            <td><?= $record['audit_id'] ?></td>
                                            <td><?= htmlspecialchars($record['username']) ?></td>
                                            <td><?= htmlspecialchars($record['action_performed']) ?></td>
                                            <td><?= htmlspecialchars($record['table_affected']) ?></td>
                                            <td><?= date('M d, Y h:i A', strtotime($record['timestamp'])) ?></td>
                                            <td>
                                                <?php if(strlen($record['details']) > 50): ?>
                                                <span class="d-inline-block text-truncate" style="max-width: 150px;">
                                                    <?= htmlspecialchars($record['details']) ?>
                                                </span>
                                                <button class="btn btn-sm btn-link p-0 view-details" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#detailsModal"
                                                        data-details="<?= htmlspecialchars($record['details']) ?>">
                                                    View
                                                </button>
                                                <?php else: ?>
                                                <?= htmlspecialchars($record['details']) ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No records found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mt-4">
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page-1 ?><?= !empty($table_filter) ? '&table='.$table_filter : '' ?><?= !empty($user_filter) ? '&user_id='.$user_filter : '' ?><?= !empty($action_filter) ? '&action='.$action_filter : '' ?><?= !empty($date_start) ? '&date_start='.$date_start : '' ?><?= !empty($date_end) ? '&date_end='.$date_end : '' ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <?php 
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $start_page + 4);
                                if ($end_page - $start_page < 4 && $start_page > 1) {
                                    $start_page = max(1, $end_page - 4);
                                }
                                
                                for($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?><?= !empty($table_filter) ? '&table='.$table_filter : '' ?><?= !empty($user_filter) ? '&user_id='.$user_filter : '' ?><?= !empty($action_filter) ? '&action='.$action_filter : '' ?><?= !empty($date_start) ? '&date_start='.$date_start : '' ?><?= !empty($date_end) ? '&date_end='.$date_end : '' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page+1 ?><?= !empty($table_filter) ? '&table='.$table_filter : '' ?><?= !empty($user_filter) ? '&user_id='.$user_filter : '' ?><?= !empty($action_filter) ? '&action='.$action_filter : '' ?><?= !empty($date_start) ? '&date_start='.$date_start : '' ?><?= !empty($date_end) ? '&date_end='.$date_end : '' ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">Audit Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="detail-content"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // View details click
            document.querySelectorAll('.view-details').forEach(button => {
                button.addEventListener('click', function() {
                    const details = this.getAttribute('data-details');
                    document.getElementById('detail-content').textContent = details;
                });
            });
            
            // Date range picker
            $('#daterange').daterangepicker({
                opens: 'left',
                autoUpdateInput: false,
                locale: {
                    cancelLabel: 'Clear'
                }
            });
            
            $('#daterange').on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
                $('#date_start').val(picker.startDate.format('YYYY-MM-DD'));
                $('#date_end').val(picker.endDate.format('YYYY-MM-DD'));
            });
            
            $('#daterange').on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
                $('#date_start').val('');
                $('#date_end').val('');
            });
            
            // Clear date button
            $('#clearDate').on('click', function() {
                $('#daterange').val('');
                $('#date_start').val('');
                $('#date_end').val('');
            });
        });
    </script>
</body>
</html>