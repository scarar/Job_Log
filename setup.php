<?php
require_once "config/database.php";

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Drop existing tables if they exist (in correct order to handle foreign keys)
// $sql = "DROP TABLE IF EXISTS job_status_history";
// mysqli_query($conn, $sql);

// $sql = "DROP TABLE IF EXISTS jobs";
// mysqli_query($conn, $sql);

// $sql = "DROP TABLE IF EXISTS application_methods";
// mysqli_query($conn, $sql);

// Create application methods table
$sql = "CREATE TABLE IF NOT EXISTS application_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method_name VARCHAR(255) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if(!mysqli_query($conn, $sql)) {
    die("Error creating application_methods table: " . mysqli_error($conn));
}

// Create the jobs table with proper application method handling
$sql = "CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_applied DATE NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    position_title VARCHAR(100) NOT NULL,
    job_description TEXT,
    application_method_id INT,
    custom_application_method VARCHAR(255),
    job_posting_url TEXT,
    follow_up_date DATE,
    status ENUM('Applied', 'Interviewing', 'Offer', 'Rejected', 'Accepted') DEFAULT 'Applied',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company_name (company_name),
    INDEX idx_date_applied (date_applied),
    INDEX idx_status (status),
    INDEX idx_follow_up (follow_up_date),
    INDEX idx_application_method (application_method_id),
    FOREIGN KEY (application_method_id) REFERENCES application_methods(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if(mysqli_query($conn, $sql)){
    echo "Database and tables created successfully!";
    
    // Alter the company_name column if it exists with the old length
    $alter_sql = "ALTER TABLE jobs MODIFY COLUMN company_name VARCHAR(255) NOT NULL";
    if(!mysqli_query($conn, $alter_sql)) {
        echo "Warning: Could not alter company_name column: " . mysqli_error($conn);
    }
    
    // Insert default application methods if they don't exist
    $default_methods = array(
        'LinkedIn',
        'Indeed',
        'Company Website',
        'Referral',
        'Virtual',
        'In-Person',
        'Other'
    );

    foreach ($default_methods as $method) {
        $check_sql = "SELECT id FROM application_methods WHERE method_name = ?";
        $stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($stmt, "s", $method);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 0) {
            $insert_sql = "INSERT INTO application_methods (method_name) VALUES (?)";
            $stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($stmt, "s", $method);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
    }
} else {
    die("Error creating jobs table: " . mysqli_error($conn));
}

// Create job status history table
$sql = "CREATE TABLE IF NOT EXISTS job_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    old_status ENUM('Applied', 'Interviewing', 'Offer', 'Rejected', 'Accepted'),
    new_status ENUM('Applied', 'Interviewing', 'Offer', 'Rejected', 'Accepted') NOT NULL,
    change_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if(!mysqli_query($conn, $sql)) {
    echo "Warning: Could not create job_status_history table: " . mysqli_error($conn);
}

// Create a trigger to log status changes
$sql = "CREATE TRIGGER IF NOT EXISTS log_status_change 
        AFTER UPDATE ON jobs 
        FOR EACH ROW 
        BEGIN
            IF OLD.status != NEW.status THEN
                INSERT INTO job_status_history (job_id, old_status, new_status, notes)
                VALUES (NEW.id, OLD.status, NEW.status, 'Status changed via application');
            END IF;
        END";

if(!mysqli_query($conn, $sql)) {
    echo "Warning: Could not create status change trigger: " . mysqli_error($conn);
}

// Close connection
mysqli_close($conn);
?> 