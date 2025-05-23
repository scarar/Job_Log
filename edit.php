<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "config/database.php";

// Initialize variables
$id = $date_applied = $company_name = $position_title = $job_description = $application_method_id = $custom_application_method = $job_posting_url = $follow_up_date = $status = $notes = "";
$date_applied_err = $company_name_err = $position_title_err = "";
$success_message = "";
$error_message = "";

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
        // Debug: Print all POST data
        error_log("POST data: " . print_r($_POST, true));
        
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
        
        // Check input errors before updating in database
        if(empty($date_applied_err) && empty($company_name_err) && empty($position_title_err)){
            // Debug: Print the values being updated
            error_log("Updating job with values:");
            error_log("date_applied: " . $date_applied);
            error_log("company_name: " . $company_name);
            error_log("position_title: " . $position_title);
            error_log("application_method_id: " . $application_method_id);
            error_log("custom_application_method: " . $custom_application_method);
            
            // Let's try a different approach - use separate updates for critical fields
            // First update everything EXCEPT status
            $basic_sql = "UPDATE jobs SET date_applied=?, company_name=?, position_title=?, job_description=?, application_method_id=?, custom_application_method=?, job_posting_url=?, follow_up_date=?, notes=? WHERE id=?";
            if($basic_stmt = mysqli_prepare($conn, $basic_sql)) {
                $job_description = trim($_POST["job_description"]);
                $job_posting_url = trim($_POST["job_posting_url"]);
                $follow_up_date = !empty(trim($_POST["follow_up_date"])) ? trim($_POST["follow_up_date"]) : null;
                $notes = trim($_POST["notes"]);
                $id = trim($_POST["id"]); // Explicitly get ID from form
                
                mysqli_stmt_bind_param($basic_stmt, "ssssissssi", 
                    $date_applied, $company_name, $position_title, $job_description, 
                    $application_method_id, $custom_application_method, $job_posting_url, 
                    $follow_up_date, $notes, $id);
                
                $basic_success = mysqli_stmt_execute($basic_stmt);
                mysqli_stmt_close($basic_stmt);
                
                error_log("Basic update result: " . ($basic_success ? "Success" : "Failed"));
                
                // Now update just the status in a separate query
                $status_sql = "UPDATE jobs SET status=? WHERE id=?";
                if($status_stmt = mysqli_prepare($conn, $status_sql)) {
                    $status = trim($_POST["status"]);
                    mysqli_stmt_bind_param($status_stmt, "si", $status, $id);
                    $status_success = mysqli_stmt_execute($status_stmt);
                    mysqli_stmt_close($status_stmt);
                    
                    error_log("Status update result: " . ($status_success ? "Success" : "Failed"));
                    
                    if($basic_success && $status_success) {
                        $success_message = "Job application updated successfully!";
                        // Redirect to index.php after successful update
                        header("location: index.php");
                        exit();
                    } else {
                        $error_message = "Error updating job. Basic update: " . ($basic_success ? "OK" : "Failed") . 
                                        ", Status update: " . ($status_success ? "OK" : "Failed") . 
                                        " | " . mysqli_error($conn);
                        error_log("Error updating job: " . mysqli_error($conn));
                    }
                } else {
                    $error_message = "Error preparing status update: " . mysqli_error($conn);
                    error_log("Error preparing status update: " . mysqli_error($conn));
                }
            } else {
                $error_message = "Error preparing basic update: " . mysqli_error($conn);
                error_log("Error preparing basic update: " . mysqli_error($conn));
            }
        }
    } catch (Exception $e) {
        $error_message = "An error occurred: " . $e->getMessage();
    }
} else {
    // Get job details
    if(isset($_GET["id"]) && !empty(trim($_GET["id"]))){
        $id = trim($_GET["id"]);
        $sql = "SELECT j.*, am.method_name FROM jobs j LEFT JOIN application_methods am ON j.application_method_id = am.id WHERE j.id = ?";
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "i", $id);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                if(mysqli_num_rows($result) == 1){
                    $row = mysqli_fetch_assoc($result);
                    $date_applied = $row["date_applied"];
                    $company_name = $row["company_name"];
                    $position_title = $row["position_title"];
                    $job_description = $row["job_description"];
                    $application_method_id = $row["application_method_id"];
                    $custom_application_method = $row["custom_application_method"];
                    $job_posting_url = $row["job_posting_url"];
                    $follow_up_date = $row["follow_up_date"];
                    $status = $row["status"];
                    $notes = $row["notes"];
                    
                    // If there's a custom application method, set the dropdown to "Other"
                    if (!empty($custom_application_method)) {
                        foreach ($application_methods as $id => $method) {
                            if ($method === 'Other') {
                                $application_method_id = $id;
                                break;
                            }
                        }
                    }
                } else{
                    header("location: index.php");
                    exit();
                }
            } else{
                $error_message = "Error executing query: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Error preparing query: " . mysqli_error($conn);
        }
    } else{
        header("location: index.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job Application</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Edit Job Application</h1>
        
        <?php if(!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $id); ?>" method="post">
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Date Applied</label>
                                <input type="date" name="date_applied" class="form-control <?php echo (!empty($date_applied_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $date_applied; ?>" required>
                                <span class="invalid-feedback"><?php echo $date_applied_err; ?></span>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" name="company_name" class="form-control <?php echo (!empty($company_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $company_name; ?>" required>
                                <span class="invalid-feedback"><?php echo $company_name_err; ?></span>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Position Title</label>
                                <input type="text" name="position_title" class="form-control <?php echo (!empty($position_title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $position_title; ?>" required>
                                <span class="invalid-feedback"><?php echo $position_title_err; ?></span>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Job Description</label>
                                <textarea name="job_description" class="form-control" rows="3"><?php echo htmlspecialchars($job_description); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Application Method</label>
                                <select name="application_method_id" id="application_method_id" class="form-control" onchange="toggleOtherMethod()">
                                    <option value="">Select Method</option>
                                    <?php foreach ($application_methods as $method_id => $method_name): ?>
                                        <option value="<?php echo $method_id; ?>" <?php echo ($application_method_id == $method_id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($method_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="other_method_container" style="display: <?php echo (!empty($custom_application_method)) ? 'block' : 'none'; ?>;">
                                <label class="form-label">Other Application Method</label>
                                <input type="text" name="custom_application_method" class="form-control" value="<?php echo $custom_application_method !== null ? htmlspecialchars($custom_application_method) : ''; ?>" placeholder="Please specify other application method">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Job Posting URL</label>
                                <input type="url" name="job_posting_url" class="form-control" value="<?php echo htmlspecialchars($job_posting_url); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Follow-up Date</label>
                                <input type="date" name="follow_up_date" class="form-control" value="<?php echo $follow_up_date; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="Applied" <?php echo ($status == 'Applied') ? 'selected' : ''; ?>>Applied</option>
                                    <option value="Interviewing" <?php echo ($status == 'Interviewing') ? 'selected' : ''; ?>>Interviewing</option>
                                    <option value="Offer" <?php echo ($status == 'Offer') ? 'selected' : ''; ?>>Offer</option>
                                    <option value="Rejected" <?php echo ($status == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="Accepted" <?php echo ($status == 'Accepted') ? 'selected' : ''; ?>>Accepted</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($notes); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <input type="submit" class="btn btn-primary" value="Update">
                                <a href="index.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
    document.addEventListener('DOMContentLoaded', function() {
        toggleOtherMethod();
    });
    </script>
</body>
</html>