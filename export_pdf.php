<?php
// Start output buffering
ob_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "config/database.php";
require_once 'vendor/autoload.php';

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Job Application Log');
$pdf->SetAuthor('Job Application Log');
$pdf->SetTitle('Job Applications');

// Set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, 'Job Applications', 'Generated on ' . date('Y-m-d'));

// Set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Query to get all job applications, joining with application_methods table
$sql = "SELECT j.*, am.method_name 
        FROM jobs j 
        LEFT JOIN application_methods am ON j.application_method_id = am.id 
        ORDER BY j.date_applied DESC";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    // Table header
    $html = '<table border="1" cellpadding="4">
        <tr style="background-color:#f0f0f0;">
            <th width="15%">Date Applied</th>
            <th width="20%">Company</th>
            <th width="20%">Position</th>
            <th width="15%">Status</th>
            <th width="15%">Follow-up Date</th>
            <th width="15%">Application Method</th>
        </tr>';

    // Table content
    while ($row = mysqli_fetch_assoc($result)) {
        // Determine the application method to display
        $application_method_display = 'N/A'; // Default to N/A
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

        $html .= '<tr>
            <td>' . date('m/d/Y', strtotime($row['date_applied'])) . '</td>
            <td>' . htmlspecialchars($row['company_name'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['position_title'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['status'] ?? '') . '</td>
            <td>' . ($row['follow_up_date'] ? date('m/d/Y', strtotime($row['follow_up_date'])) : '') . '</td>
            <td>' . $application_method_display . '</td>
        </tr>';
    }

    $html .= '</table>';

    // Output the HTML content
    $pdf->writeHTML($html, true, false, true, false, '');

    // Add a new page for detailed information
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Detailed Job Application Information', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);

    // Reset the result pointer
    mysqli_data_seek($result, 0);

    while ($row = mysqli_fetch_assoc($result)) {
        // Determine the application method to display (needed again after data seek)
        $application_method_display = 'N/A'; // Default to N/A
        if (!empty($row['method_name'])) {
            if ($row['method_name'] === 'Other' && !empty($row['custom_application_method'])) {
                $application_method_display = htmlspecialchars($row['custom_application_method']);
            } else {
                $application_method_display = htmlspecialchars($row['method_name']);
            }
        } elseif (!empty($row['custom_application_method'])) {
            $application_method_display = htmlspecialchars($row['custom_application_method']);
        }

        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 10, htmlspecialchars($row['company_name'] ?? '') . ' - ' . htmlspecialchars($row['position_title'] ?? ''), 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        
        $details = 'Date Applied: ' . date('m/d/Y', strtotime($row['date_applied'])) . "\n";
        $details .= 'Company Name: ' . htmlspecialchars($row['company_name'] ?? '') . "\n";
        $details .= 'Position Title: ' . htmlspecialchars($row['position_title'] ?? '') . "\n";
        $details .= 'Status: ' . htmlspecialchars($row['status'] ?? '') . "\n";
        $details .= 'Application Method: ' . $application_method_display . "\n";
        if (!empty($row['follow_up_date'])) {
            $details .= 'Follow-up Date: ' . date('m/d/Y', strtotime($row['follow_up_date'])) . "\n";
        }
        if (!empty($row['job_posting_url'])) {
            $details .= 'Job Posting URL: ' . htmlspecialchars($row['job_posting_url']) . "\n";
        }
        if (!empty($row['job_description'])) {
            $details .= 'Job Description: ' . htmlspecialchars($row['job_description']) . "\n";
        }
        if (!empty($row['notes'])) {
            $details .= 'Notes: ' . htmlspecialchars($row['notes']) . "\n";
        }
        
        $pdf->MultiCell(0, 10, $details, 0, 'L');
        $pdf->Ln(2);
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 190, $pdf->GetY());
    }
} else {
    $pdf->Cell(0, 10, 'No job applications found.', 0, 1);
}

// Clear any previous output
ob_end_clean();

// Close and output PDF document
$pdf->Output('job_applications.pdf', 'D');

// Close database connection
mysqli_close($conn);
?> 