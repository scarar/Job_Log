<?php
// Start output buffering
ob_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "config/database.php";

// Set headers for Excel file
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="job_applications.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Create table headers
echo '<table border="1">';
echo '<tr>';
echo '<th>Date Applied</th>';
echo '<th>Company Name</th>';
echo '<th>Position Title</th>';
echo '<th>Job Description</th>';
echo '<th>Application Method</th>';
echo '<th>Job Posting URL</th>';
echo '<th>Follow-up Date</th>';
echo '<th>Status</th>';
echo '<th>Notes</th>';
echo '</tr>';

// Query to get all job applications, joining with application_methods table
$sql = "SELECT j.*, am.method_name 
        FROM jobs j 
        LEFT JOIN application_methods am ON j.application_method_id = am.id 
        ORDER BY j.date_applied DESC";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Determine the application method to display
        $application_method_display = '';
        if (!empty($row['method_name'])) {
            if ($row['method_name'] === 'Other' && !empty($row['custom_application_method'])) {
                $application_method_display = htmlspecialchars($row['custom_application_method']);
            } else {
                $application_method_display = htmlspecialchars($row['method_name']);
            }
        } elseif (!empty($row['custom_application_method'])) {
            // Fallback to custom method if method_name is somehow empty but custom exists
            $application_method_display = htmlspecialchars($row['custom_application_method']);
        }

        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['date_applied']) . '</td>';
        echo '<td>' . htmlspecialchars($row['company_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['position_title']) . '</td>';
        echo '<td>' . htmlspecialchars($row['job_description'] ?? '') . '</td>';
        echo '<td>' . $application_method_display . '</td>';
        echo '<td>' . htmlspecialchars($row['job_posting_url'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['follow_up_date'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['status'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['notes'] ?? '') . '</td>';
        echo '</tr>';
    }
    mysqli_free_result($result);
}

echo '</table>';

// Close database connection
mysqli_close($conn);

// End output buffering and send output
ob_end_flush();
?> 