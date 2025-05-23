<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "config/database.php";

// Get job ID from URL parameter
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$new_status = isset($_GET['status']) ? $_GET['status'] : 'Rejected';

if ($id <= 0) {
    die("Please provide a valid job ID");
}

echo "<h1>Status Update Test</h1>";
echo "<p>Attempting to update job ID: {$id} to status: {$new_status}</p>";

// Get current job details
$check_sql = "SELECT id, status FROM jobs WHERE id = ?";
if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
    mysqli_stmt_bind_param($check_stmt, "i", $id);
    if (mysqli_stmt_execute($check_stmt)) {
        $result = mysqli_stmt_get_result($check_stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            echo "<p>Current job status: " . htmlspecialchars($row['status']) . "</p>";
        } else {
            die("Job not found");
        }
    } else {
        echo "<p>Error checking job: " . mysqli_error($conn) . "</p>";
    }
    mysqli_stmt_close($check_stmt);
}

// Try a direct update
echo "<h2>Method 1: Direct Query</h2>";
$direct_sql = "UPDATE jobs SET status='{$new_status}' WHERE id={$id}";
if (mysqli_query($conn, $direct_sql)) {
    echo "<p>Direct update successful</p>";
} else {
    echo "<p>Direct update failed: " . mysqli_error($conn) . "</p>";
}

// Verify the update
$verify_sql = "SELECT status FROM jobs WHERE id = ?";
if ($verify_stmt = mysqli_prepare($conn, $verify_sql)) {
    mysqli_stmt_bind_param($verify_stmt, "i", $id);
    if (mysqli_stmt_execute($verify_stmt)) {
        $result = mysqli_stmt_get_result($verify_stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            echo "<p>New job status after direct update: " . htmlspecialchars($row['status']) . "</p>";
        }
    }
    mysqli_stmt_close($verify_stmt);
}

// Try a prepared statement update
echo "<h2>Method 2: Prepared Statement</h2>";
$stmt_sql = "UPDATE jobs SET status=? WHERE id=?";
if ($update_stmt = mysqli_prepare($conn, $stmt_sql)) {
    mysqli_stmt_bind_param($update_stmt, "si", $new_status, $id);
    if (mysqli_stmt_execute($update_stmt)) {
        echo "<p>Prepared statement update successful</p>";
    } else {
        echo "<p>Prepared statement update failed: " . mysqli_error($conn) . " | Stmt error: " . mysqli_stmt_error($update_stmt) . "</p>";
    }
    mysqli_stmt_close($update_stmt);
}

// Verify the update again
$verify_sql = "SELECT status FROM jobs WHERE id = ?";
if ($verify_stmt = mysqli_prepare($conn, $verify_sql)) {
    mysqli_stmt_bind_param($verify_stmt, "i", $id);
    if (mysqli_stmt_execute($verify_stmt)) {
        $result = mysqli_stmt_get_result($verify_stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            echo "<p>New job status after prepared update: " . htmlspecialchars($row['status']) . "</p>";
        }
    }
    mysqli_stmt_close($verify_stmt);
}

// Debug database structure
echo "<h2>Database Schema Check</h2>";
$schema_sql = "SHOW COLUMNS FROM jobs WHERE Field='status'";
$schema_result = mysqli_query($conn, $schema_sql);
if ($schema_row = mysqli_fetch_assoc($schema_result)) {
    echo "<p>Status field definition: " . htmlspecialchars($schema_row['Type']) . "</p>";
}

// Check if there's a trigger
echo "<h2>Trigger Check</h2>";
$trigger_sql = "SHOW TRIGGERS WHERE `Table` = 'jobs'";
$trigger_result = mysqli_query($conn, $trigger_sql);
if (mysqli_num_rows($trigger_result) > 0) {
    echo "<p>Found triggers on the jobs table:</p><ul>";
    while ($trigger_row = mysqli_fetch_assoc($trigger_result)) {
        echo "<li>" . htmlspecialchars($trigger_row['Trigger']) . " - " . htmlspecialchars($trigger_row['Event']) . " - " . htmlspecialchars($trigger_row['Statement']) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No triggers found on the jobs table</p>";
}

echo "<p><a href='index.php'>Return to Job List</a></p>";
?>
