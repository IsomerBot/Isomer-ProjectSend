<?php
/**
 * Public custom download handler - NO LOGIN REQUIRED
 */

// Include bootstrap but NO login requirement
require_once "bootstrap.php";

use ProjectSend\Classes\Files;

if (!isset($_GET["link"]) || empty($_GET["link"])) {
    http_response_code(404);
    die("Download link not found");
}

$custom_link = trim($_GET["link"]);

if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $custom_link)) {
    http_response_code(400);
    die("Invalid download link format");
}

// Check if user confirmed download
$confirmed = isset($_GET["confirm"]) && $_GET["confirm"] === "yes";

try {
    global $dbh;

    // Look up the file ID from custom downloads
    $statement = $dbh->prepare(
        "SELECT file_id, client_id FROM " .
            TABLE_CUSTOM_DOWNLOADS .
            " WHERE link = :link LIMIT 1"
    );
    $statement->bindParam(":link", $custom_link, PDO::PARAM_STR);
    $statement->execute();

    $custom_download = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$custom_download) {
        http_response_code(404);
        die("Download link not found");
    }

    $file_id = $custom_download["file_id"];

    // Get file info
    $file = new Files($file_id);

    if (!$file->recordExists()) {
        http_response_code(404);
        die("File not found");
    }

    if (!$file->existsOnDisk()) {
        http_response_code(404);
        die("File not available on disk");
    }

    // If not confirmed, show the confirmation page
    if (!$confirmed) {
        // Get file size in a readable format
        $file_size = filesize($file->full_path);
        $file_size_formatted = formatBytes($file_size);

        // Show confirmation page
        showDownloadConfirmation($file, $custom_link, $file_size_formatted);
        exit();
    }

    // User confirmed - proceed with download

    // Update visit count
    $update_statement = $dbh->prepare(
        "UPDATE " .
            TABLE_CUSTOM_DOWNLOADS .
            " SET visit_count = visit_count + 1 WHERE link = :link"
    );
    $update_statement->bindParam(":link", $custom_link, PDO::PARAM_STR);
    $update_statement->execute();

    // Log the download (anonymously)
    $logger = new \ProjectSend\Classes\ActionsLog();
    $logger->addEntry([
        "action" => 7, // File downloaded
        "owner_id" => 0, // Anonymous download
        "affected_file" => $file_id,
        "affected_file_name" => $file->filename_original,
        "affected_account_name" => "Anonymous",
        "custom_message" => "Downloaded via custom link: {$custom_link}",
    ]);

    // Serve the file directly
    $file_path = $file->full_path;

    // Set headers for file download
    header("Content-Type: application/octet-stream");
    header(
        'Content-Disposition: attachment; filename="' .
            $file->filename_original .
            '"'
    );
    header("Content-Length: " . filesize($file_path));
    header("Cache-Control: no-cache, must-revalidate");

    // Output the file
    readfile($file_path);
    exit();
} catch (PDOException $e) {
    error_log("Custom download error: " . $e->getMessage());
    http_response_code(500);
    die("Internal server error");
}

/**
 * Format bytes into readable file size
 */
function formatBytes($bytes, $precision = 2)
{
    $units = ["B", "KB", "MB", "GB", "TB"];

    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . " " . $units[$i];
}

/**
 * Show download confirmation page with Isomer brand styling
 */
function showDownloadConfirmation($file, $custom_link, $file_size)
{
    $confirm_url = $_SERVER["REQUEST_URI"] . "&confirm=yes";
    $filename = htmlspecialchars($file->filename_original);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Isomer ProjectSend - File Download</title>
        <style>
            /* Import Isomer Brand Fonts - Montserrat */
            @import url("https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;800&display=swap");
            
            /* Isomer Brand Typography Styles */
            .isomer-h1 {
                font-family: "Montserrat", Arial, sans-serif;
                font-weight: 700;
                font-size: 32px;
                text-transform: uppercase;
                letter-spacing: 3px;
                color: #252c3a;
                margin: 0;
            }
            
            .isomer-h2 {
                font-family: "Montserrat", Arial, sans-serif;
                font-weight: 300;
                font-size: 24px;
                text-transform: uppercase;
                color: #252c3a;
                margin: 0;
            }
            
            .isomer-h3 {
                font-family: "Montserrat", Arial, sans-serif;
                font-weight: 800;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 2px;
                color: #252c3a;
                margin: 0;
            }
            
            .isomer-body {
                font-family: "Montserrat", Arial, sans-serif;
                font-weight: 400;
                font-size: 14px;
                color: #252c3a;
                line-height: 1.4;
            }
            
            /* Page styling */
            body {
                font-family: "Montserrat", Arial, sans-serif;
                background: linear-gradient(135deg, #252c3a 0%, #384150 100%);
                margin: 0;
                padding: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #252c3a;
            }
            
            .download-container {
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.2);
                padding: 0;
                text-align: center;
                max-width: 600px;
                width: 90%;
                overflow: hidden;
                border: 4px solid #f56600;
            }
            
            /* Header section matching email styling */
            .header-section {
                background: #252c3a;
                padding: 20px 40px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                min-height: 80px;
            }
            
            .logo-section {
                display: flex;
                align-items: center;
            }
            
            .logo-text {
                color: white;
                font-family: "Montserrat", Arial, sans-serif;
                font-weight: 700;
                font-size: 20px;
                text-transform: uppercase;
                letter-spacing: 2px;
            }
            
            .logo-text-accent {
                color: #f56600;
                font-family: "Montserrat", Arial, sans-serif;
                font-weight: 300;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 1px;
                margin-left: 6px;
            }
            
            .download-section {
                color: white;
                font-family: "Montserrat", Arial, sans-serif;
                font-weight: 800;
                font-size: 18px;
                text-transform: uppercase;
                letter-spacing: 2px;
            }
            
            /* Content section */
            .content-section {
                padding: 40px;
            }
            
            .file-icon {
                font-size: 48px;
                color: #f56600;
                margin-bottom: 20px;
            }
            
            .file-info-card {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 30px;
            }
            
            .file-info-header {
                font-family: "Montserrat", Arial, sans-serif;
                font-weight: 700;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 2px;
                color: #252c3a;
                margin-bottom: 15px;
                border-bottom: 2px solid #252c3a;
                padding-bottom: 8px;
            }
            
            .file-name {
                font-family: "Montserrat", Arial, sans-serif;
                font-weight: 600;
                font-size: 18px;
                color: #252c3a;
                margin-bottom: 10px;
                word-wrap: break-word;
            }
            
            .file-size {
                font-family: "Montserrat", Arial, sans-serif;
                font-weight: 400;
                font-size: 14px;
                color: #666;
                margin-bottom: 5px;
            }
            
            .file-link {
                font-family: "Montserrat", Arial, sans-serif;
                font-weight: 400;
                font-size: 12px;
                color: #999;
                font-style: italic;
            }
            
            .download-message {
                font-family: "Montserrat", Arial, sans-serif;
                font-weight: 400;
                font-size: 16px;
                color: #252c3a;
                margin-bottom: 30px;
                line-height: 1.5;
            }
            
            .download-message strong {
                font-weight: 700;
                color: #252c3a;
            }
            
            .button-group {
                display: flex;
                gap: 15px;
                justify-content: center;
                margin-bottom: 20px;
            }
            
            .btn {
                padding: 15px 30px;
                border: none;
                border-radius: 6px;
                font-family: "Montserrat", Arial, sans-serif;
                font-size: 14px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 1px;
                cursor: pointer;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s ease;
                min-width: 150px;
                justify-content: center;
            }
            
            .btn-primary {
                background: #f56600;
                color: white;
                border: 2px solid #f56600;
            }
            
            .btn-primary:hover {
                background: #e55a00;
                border-color: #e55a00;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(245, 102, 0, 0.3);
            }
            
            .btn-secondary {
                background: transparent;
                color: #252c3a;
                border: 2px solid #252c3a;
            }
            
            .btn-secondary:hover {
                background: #252c3a;
                color: white;
                transform: translateY(-2px);
            }
            
            .powered-by {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #e9ecef;
                font-family: "Montserrat", Arial, sans-serif;
                font-size: 12px;
                color: #999;
                font-weight: 400;
            }
            
            /* Responsive design */
            @media (max-width: 600px) {
                .header-section {
                    flex-direction: column;
                    gap: 15px;
                    text-align: center;
                }
                
                .content-section {
                    padding: 30px 20px;
                }
                
                .button-group {
                    flex-direction: column;
                    align-items: center;
                }
                
                .btn {
                    width: 100%;
                    max-width: 250px;
                }
                
                .isomer-h1 {
                    font-size: 24px;
                    letter-spacing: 2px;
                }
            }
        </style>
    </head>
    <body>
        <div class="download-container">
            <!-- Header section matching email template -->
            <div class="header-section">
                <div class="logo-section">
                    <span class="logo-text">ISOMER</span>
                    <span class="logo-text-accent">PROJECT GROUP</span>
                </div>
                <div class="download-section">
                    SECURE DOWNLOAD
                </div>
            </div>
            
            <!-- Content section -->
            <div class="content-section">
                <div class="file-icon">📄</div>
                
                <div class="file-info-card">
                    <div class="file-info-header">FILE INFORMATION</div>
                    <div class="file-name"><?php echo $filename; ?></div>
                    <div class="file-size">File Size: <?php echo $file_size; ?></div>
                    <div class="file-link">Custom Link: <?php echo htmlspecialchars(
                        $custom_link
                    ); ?></div>
                </div>
                
                <div class="download-message">
                    <strong>File Ready for Download</strong><br>
                    This file has been securely transmitted from Isomer Project Group. Click "Download File" to proceed with your download.
                </div>
                
                <div class="button-group">
                    <a href="<?php echo htmlspecialchars(
                        $confirm_url
                    ); ?>" class="btn btn-primary">
                        📥 Download File
                    </a>
                    <a href="javascript:window.close();" class="btn btn-secondary">
                        ❌ Cancel
                    </a>
                </div>
                
                <div class="powered-by">
                    Powered by Isomer ProjectSend<br>
                    Secure File Transfer System
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
