<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "config/database.php";

// Initialize variables
$date_applied = $company_name = $position_title = $job_description = $application_method_id = $custom_application_method = $job_posting_url = $follow_up_date = $status = $notes = "";
$date_applied_err = $company_name_err = $position_title_err = "";
$success_message = "";
$error_message = "";

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Pagination variables
$records_per_page = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $records_per_page;

// Get application methods from database
$application_methods = array();
$methods_sql = "SELECT id, method_name FROM application_methods WHERE is_active = 1 ORDER BY 
    CASE 
        WHEN method_name = 'LinkedIn' THEN 1
        WHEN method_name = 'Indeed' THEN 2
        WHEN method_name = 'Company Website' THEN 3
        WHEN method_name = 'Referral' THEN 4
        WHEN method_name = 'Virtual' THEN 5
        WHEN method_name = 'In-Person' THEN 6
        WHEN method_name = 'Other' THEN 7
        ELSE 8
    END";
$methods_result = mysqli_query($conn, $methods_sql);
if ($methods_result) {
    while ($row = mysqli_fetch_assoc($methods_result)) {
        $application_methods[$row['id']] = $row['method_name'];
    }
    mysqli_free_result($methods_result);
}

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    try {
        // Validate date applied
        if(empty(trim($_POST["date_applied"]))){
            $date_applied_err = "Please enter the application date.";
        } else{
            $date_applied = trim($_POST["date_applied"]);
        }
        
        // Validate company name
        if(empty(trim($_POST["company_name"]))){
            $company_name_err = "Please enter the company name.";
        } else{
            $company_name = trim($_POST["company_name"]);
        }
        
        // Validate position title
        if(empty(trim($_POST["position_title"]))){
            $position_title_err = "Please enter the position title.";
        } else{
            $position_title = trim($_POST["position_title"]);
        }
        
        // Get application method
        $application_method_id = !empty($_POST["application_method_id"]) ? $_POST["application_method_id"] : null;
        $custom_application_method = null;
        
        // If "Other" is selected, get the custom method
        if ($application_method_id && $application_methods[$application_method_id] === 'Other') {
            $custom_application_method = !empty($_POST["custom_application_method"]) ? trim($_POST["custom_application_method"]) : null;
        }
        
        // Check input errors before inserting in database
        if(empty($date_applied_err) && empty($company_name_err) && empty($position_title_err)){
            $sql = "INSERT INTO jobs (date_applied, company_name, position_title, job_description, application_method_id, custom_application_method, job_posting_url, follow_up_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            if($stmt = mysqli_prepare($conn, $sql)){
                $job_description = trim($_POST["job_description"]);
                $job_posting_url = trim($_POST["job_posting_url"]);
                $follow_up_date = !empty(trim($_POST["follow_up_date"])) ? trim($_POST["follow_up_date"]) : null;
                $status = trim($_POST["status"]);
                $notes = trim($_POST["notes"]);
                
                mysqli_stmt_bind_param($stmt, "ssssisssss", $date_applied, $company_name, $position_title, $job_description, $application_method_id, $custom_application_method, $job_posting_url, $follow_up_date, $status, $notes);
                
                if(mysqli_stmt_execute($stmt)){
                    $success_message = "Job application added successfully!";
                    // Reset form values
                    $date_applied = $company_name = $position_title = $job_description = $application_method_id = $custom_application_method = $job_posting_url = $follow_up_date = $status = $notes = "";
                } else{
                    $error_message = "Error: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_message = "Error preparing statement: " . mysqli_error($conn);
            }
        }
    } catch (Exception $e) {
        $error_message = "An error occurred: " . $e->getMessage();
    }
}

// Handle delete request
if(isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM jobs WHERE id = ?";
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        if(mysqli_stmt_execute($stmt)) {
            header("location: index.php");
            exit();
        }
        mysqli_stmt_close($stmt);
    }
}

// Get statistics
$stats = array(
    'total' => 0,
    'applied' => 0,
    'interviewing' => 0,
    'offers' => 0,
    'rejected' => 0,
    'accepted' => 0
);

// Get total count
$total_sql = "SELECT COUNT(*) as total FROM jobs";
$total_result = mysqli_query($conn, $total_sql);
if ($total_result) {
    $row = mysqli_fetch_assoc($total_result);
    $stats['total'] = $row['total'];
    mysqli_free_result($total_result);
}

// Get counts by status
$status_sql = "SELECT status, COUNT(*) as count FROM jobs GROUP BY status";
$status_result = mysqli_query($conn, $status_sql);
if ($status_result) {
    while ($row = mysqli_fetch_assoc($status_result)) {
        switch ($row['status']) {
            case 'Applied':
                $stats['applied'] = $row['count'];
                break;
            case 'Interviewing':
                $stats['interviewing'] = $row['count'];
                break;
            case 'Offer':
                $stats['offers'] = $row['count'];
                break;
            case 'Rejected':
                $stats['rejected'] = $row['count'];
                break;
            case 'Accepted':
                $stats['accepted'] = $row['count'];
                break;
        }
    }
    mysqli_free_result($status_result);
}

// Build the SQL query for job applications
$sql = "SELECT j.*, am.method_name FROM jobs j LEFT JOIN application_methods am ON j.application_method_id = am.id WHERE 1=1";
$params = array();
$types = "";

if (!empty($search)) {
    $sql .= " AND (company_name LIKE ? OR position_title LIKE ? OR job_description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_from)) {
    $sql .= " AND date_applied >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $sql .= " AND date_applied <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Count total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM jobs WHERE 1=1";
$count_params = [];
$count_types = "";

if (!empty($search)) {
    $count_sql .= " AND (company_name LIKE ? OR position_title LIKE ? OR job_description LIKE ?)"; 
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_types .= "sss";
}

if (!empty($status_filter)) {
    $count_sql .= " AND status = ?";
    $count_params[] = $status_filter;
    $count_types .= "s";
}

if (!empty($date_from)) {
    $count_sql .= " AND date_applied >= ?";
    $count_params[] = $date_from;
    $count_types .= "s";
}

if (!empty($date_to)) {
    $count_sql .= " AND date_applied <= ?";
    $count_params[] = $date_to;
    $count_types .= "s";
}

// Get total records count
$total_records = 0;
if($count_stmt = mysqli_prepare($conn, $count_sql)) {
    if(!empty($count_params)) {
        mysqli_stmt_bind_param($count_stmt, $count_types, ...$count_params);
    }
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    
    if($count_row = mysqli_fetch_assoc($count_result)) {
        $total_records = $count_row['total'];
    }
    
    mysqli_free_result($count_result);
    mysqli_stmt_close($count_stmt);
}

// Calculate total pages
$total_pages = ceil($total_records / $records_per_page);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $records_per_page;
}

$sql .= " ORDER BY date_applied DESC LIMIT $records_per_page OFFSET $offset";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https: data:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net;">
    <title>Job Application Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Job Application Log</h1>
        
        <?php if(!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Dashboard -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Job Application Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <h6>Total</h6>
                                    <h3><?php echo $stats['total']; ?></h3>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <h6>Applied</h6>
                                    <h3><?php echo $stats['applied']; ?></h3>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <h6>Interviewing</h6>
                                    <h3><?php echo $stats['interviewing']; ?></h3>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <h6>Offers</h6>
                                    <h3><?php echo $stats['offers']; ?></h3>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <h6>Rejected</h6>
                                    <h3><?php echo $stats['rejected']; ?></h3>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <h6>Accepted</h6>
                                    <h3><?php echo $stats['accepted']; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col">
                <a href="export.php" class="btn btn-success">
                    <i class="bi bi-download"></i> Export to Excel
                </a>
                <a href="export_pdf.php" class="btn btn-danger">
                    <i class="bi bi-file-pdf"></i> Export to PDF
                </a>
            </div>
        </div>
        
        <!-- Search and Filter Form -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    <option value="Applied" <?php echo ($status_filter == 'Applied') ? 'selected' : ''; ?>>Applied</option>
                                    <option value="Interviewing" <?php echo ($status_filter == 'Interviewing') ? 'selected' : ''; ?>>Interviewing</option>
                                    <option value="Offer" <?php echo ($status_filter == 'Offer') ? 'selected' : ''; ?>>Offer</option>
                                    <option value="Rejected" <?php echo ($status_filter == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="Accepted" <?php echo ($status_filter == 'Accepted') ? 'selected' : ''; ?>>Accepted</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>" placeholder="From Date">
                            </div>
                            <div class="col-md-2">
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>" placeholder="To Date">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="index.php" class="btn btn-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <!-- Add Job Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Add New Job Application</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="mb-3">
                                <label for="date_applied" class="form-label">Date Applied</label>
                                <input type="date" class="form-control <?php echo (!empty($date_applied_err)) ? 'is-invalid' : ''; ?>" id="date_applied" name="date_applied" value="<?php echo $date_applied; ?>" required>
                                <div class="invalid-feedback"><?php echo $date_applied_err; ?></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control <?php echo (!empty($company_name_err)) ? 'is-invalid' : ''; ?>" id="company_name" name="company_name" value="<?php echo $company_name; ?>" required>
                                <div class="invalid-feedback"><?php echo $company_name_err; ?></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="position_title" class="form-label">Position Title</label>
                                <input type="text" class="form-control <?php echo (!empty($position_title_err)) ? 'is-invalid' : ''; ?>" id="position_title" name="position_title" value="<?php echo $position_title; ?>" required>
                                <div class="invalid-feedback"><?php echo $position_title_err; ?></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="application_method_id" class="form-label">Application Method</label>
                                <select class="form-control" id="application_method_id" name="application_method_id" onchange="toggleOtherMethod()">
                                    <option value="">Select Method</option>
                                    <?php foreach ($application_methods as $id => $method): ?>
                                        <option value="<?php echo $id; ?>" <?php echo ($application_method_id == $id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($method); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="other_method_container" style="display: none;">
                                <label for="custom_application_method" class="form-label">Other Application Method</label>
                                <input type="text" class="form-control" id="custom_application_method" name="custom_application_method" value="<?php echo $custom_application_method !== null ? htmlspecialchars($custom_application_method) : ''; ?>" placeholder="Please specify other application method">
                            </div>
                            
                            <div class="mb-3">
                                <label for="job_description" class="form-label">Job Description</label>
                                <textarea class="form-control" id="job_description" name="job_description" rows="3"><?php echo $job_description; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="job_posting_url" class="form-label">Job Posting URL</label>
                                <input type="url" class="form-control" id="job_posting_url" name="job_posting_url" value="<?php echo $job_posting_url; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="follow_up_date" class="form-label">Follow-up Date</label>
                                <input type="date" class="form-control" id="follow_up_date" name="follow_up_date" value="<?php echo $follow_up_date; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="Applied" <?php echo ($status == 'Applied') ? 'selected' : ''; ?>>Applied</option>
                                    <option value="Interviewing" <?php echo ($status == 'Interviewing') ? 'selected' : ''; ?>>Interviewing</option>
                                    <option value="Offer" <?php echo ($status == 'Offer') ? 'selected' : ''; ?>>Offer</option>
                                    <option value="Rejected" <?php echo ($status == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="Accepted" <?php echo ($status == 'Accepted') ? 'selected' : ''; ?>>Accepted</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo $notes; ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Add Job Application</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Job Applications</h5>
                        <span class="badge bg-primary"><?php echo $total_records; ?> Total</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Company</th>
                                        <th>Position</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if($stmt = mysqli_prepare($conn, $sql)){
                                        if(!empty($params)){
                                            mysqli_stmt_bind_param($stmt, $types, ...$params);
                                        }
                                        mysqli_stmt_execute($stmt);
                                        $result = mysqli_stmt_get_result($stmt);
                                        
                                        if(mysqli_num_rows($result) > 0){
                                            while($row = mysqli_fetch_assoc($result)){
                                                echo "<tr>";
                                                echo "<td>" . date('m/d/Y', strtotime($row['date_applied'])) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['company_name']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['position_title']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                                                echo "<td>";
                                                echo "<a href='edit.php?id=" . $row['id'] . "' class='btn btn-sm btn-primary me-1'><i class='bi bi-pencil'></i></a>";
                                                echo "<a href='index.php?delete=" . $row['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete this application?\")'><i class='bi bi-trash'></i></a>";
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='5' class='text-center'>No job applications found</td></tr>";
                                        }
                                        mysqli_free_result($result);
                                        mysqli_stmt_close($stmt);
                                    } else {
                                        echo "<tr><td colspan='5' class='text-center'>Error loading job applications</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                            
                            <!-- Pagination Controls -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Job application pagination">
                                    <ul class="pagination justify-content-center mt-3">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?' . http_build_query(array_merge($_GET, ['page' => 1]))); ?>" aria-label="First">
                                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))); ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item disabled">
                                                <a class="page-link" href="#" aria-label="First">
                                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item disabled">
                                                <a class="page-link" href="#" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php 
                                        // Determine the range of page numbers to display
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $start_page + 4);
                                        
                                        if ($end_page - $start_page < 4 && $start_page > 1) {
                                            $start_page = max(1, $end_page - 4);
                                        }
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                <a class="page-link" href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?' . http_build_query(array_merge($_GET, ['page' => $i]))); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))); ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?' . http_build_query(array_merge($_GET, ['page' => $total_pages]))); ?>" aria-label="Last">
                                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                                </a>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item disabled">
                                                <a class="page-link" href="#" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item disabled">
                                                <a class="page-link" href="#" aria-label="Last">
                                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                <div class="text-center text-muted mt-2">
                                    <small>Showing <?php echo min($total_records, ($page - 1) * $records_per_page + 1); ?> to <?php echo min($total_records, $page * $records_per_page); ?> of <?php echo $total_records; ?> entries</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form submission handling
            const form = document.querySelector('form');
            if(form) {
                form.addEventListener('submit', function(e) {
                    // Validate required fields
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if(!field.value.trim()) {
                            isValid = false;
                            field.classList.add('is-invalid');
                        } else {
                            field.classList.remove('is-invalid');
                        }
                    });
                    
                    if(!isValid) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                });
            }
            
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
            
            // Delete confirmation
            const deleteButtons = document.querySelectorAll('a[href*="delete="]');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this application?')) {
                        e.preventDefault();
                    }
                });
            });

            function toggleOtherMethod() {
                var methodSelect = document.getElementById('application_method_id');
                var otherContainer = document.getElementById('other_method_container');
                var otherMethod = document.getElementById('custom_application_method');
                
                // Get the selected option text
                var selectedOption = methodSelect.options[methodSelect.selectedIndex].text;
                
                if (selectedOption === 'Other') {
                    otherContainer.style.display = 'block';
                    otherMethod.required = true;
                } else {
                    otherContainer.style.display = 'none';
                    otherMethod.required = false;
                    otherMethod.value = '';
                }
            }

            // Call on page load to set initial state
            toggleOtherMethod();
        });
    </script>
</body>
</html> 