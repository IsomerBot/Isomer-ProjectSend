<?php
define("VIEW_TYPE", "template");
require_once "../bootstrap.php";

if (!defined("CURRENT_USER_USERNAME")) {
    ps_redirect("../index.php");
}

$view_files_as =
    !empty($_GET["client"]) && CURRENT_USER_LEVEL != "0"
        ? $_GET["client"]
        : CURRENT_USER_USERNAME;

// CRITICAL FIX: Define TEMPLATE_RESULTS_PER_PAGE before loading template
// This fixes the bug that was causing 500 errors in all templates
if (!defined("TEMPLATE_RESULTS_PER_PAGE")) {
    define("TEMPLATE_RESULTS_PER_PAGE", 10); // Default pagination
}

// ENHANCEMENT: Add transmittal filtering support
$filter_transmittal = isset($_GET["transmittal"])
    ? trim($_GET["transmittal"])
    : null;

// Handle both new format (DOM2504-0001) and legacy format (0001)
$filter_project = null;
$filter_transmittal_num = null;

if ($filter_transmittal) {
    if (strpos($filter_transmittal, "-") !== false) {
        // New format: PROJECT-TRANSMITTAL (e.g., DOM2504-0001)
        list($filter_project, $filter_transmittal_num) = explode(
            "-",
            $filter_transmittal,
            2
        );
    } else {
        // Legacy format: just transmittal number
        $filter_transmittal_num = $filter_transmittal;
    }
}

// If transmittal filter is requested, we need to modify the database query
if (!empty($filter_transmittal)) {
    // Set a global variable that can be used to modify SQL queries
    $GLOBALS["TRANSMITTAL_FILTER"] = $filter_transmittal;
}

// Now load the actual default template with the bug fixed
require get_template_file_location("template.php");

// Additional function to get transmittal file count for display
function get_transmittal_file_count($transmittal_number, $user_id)
{
    global $dbh;

    // Using the correct table name from your schema
    $count = $dbh->get_var(
        $dbh->prepare(
            "SELECT COUNT(*) FROM " .
                TABLE_FILES .
                " WHERE transmittal_number = ? AND user_id = ?"
        )
    );
    $stmt = $dbh->prepare(
        "SELECT COUNT(*) FROM " .
            TABLE_FILES .
            " WHERE transmittal_number = :transmittal_number AND user_id = :user_id"
    );
    $stmt->execute([
        ":transmittal_number" => $transmittal_number,
        ":user_id" => $user_id,
    ]);

    return intval($stmt->fetchColumn());
}

// If we have a transmittal filter, let's also provide some context
if (!empty($filter_transmittal)) {
    // Get the current user info from client_info (loaded in common.php)
    $current_user_id = isset($client_info["id"]) ? $client_info["id"] : null;

    if ($current_user_id) {
        $file_count = get_transmittal_file_count(
            $filter_transmittal,
            $current_user_id
        );

        // Store this for potential use in the template
        $GLOBALS["TRANSMITTAL_FILE_COUNT"] = $file_count;
        $GLOBALS["TRANSMITTAL_NUMBER"] = $filter_transmittal;
    }
}

// Add CSS and JavaScript for transmittal filtering (after template loads)
if (!empty($filter_transmittal)) {
    echo '<style>
    .transmittal-filter-notice {
        background: #e3f2fd;
        border: 1px solid #2196f3;
        padding: 15px;
        margin: 20px 0;
        border-radius: 5px;
        font-family: Arial, sans-serif;
        animation: slideDown 0.3s ease-out;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .transmittal-filter-notice a:hover {
        text-decoration: underline !important;
    }
    </style>';

    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Add filter notice to the page
        var notice = document.createElement("div");
        notice.className = "transmittal-filter-notice";
        notice.innerHTML = "<div><strong>📁 Transmittal Filter Active:</strong> Showing files from transmittal <strong>' .
        htmlspecialchars($filter_transmittal) .
        '</strong> | <a href=\"" + window.location.pathname + "\" style=\"color: #1976d2; text-decoration: none;\">🔄 Show All Files</a></div>";
        
        // Insert notice at the top of content area
        var content = document.querySelector("#content") || document.querySelector(".content") || document.querySelector("main") || document.body;
        if (content && content.children.length > 0) {
            content.insertBefore(notice, content.firstChild);
        } else if (content) {
            content.appendChild(notice);
        }
        
        // Update page title to reflect filter
        var currentTitle = document.title;
        if (currentTitle && !currentTitle.includes("Transmittal")) {
            document.title = "Transmittal ' .
        htmlspecialchars($filter_transmittal) .
        ' - " + currentTitle;
        }
    });
    </script>';
}

// Debug information (remove in production)
if (defined("DEBUG") && DEBUG && !empty($filter_transmittal)) {
    echo "<!-- DEBUG: Transmittal filter active for: " .
        htmlspecialchars($filter_transmittal) .
        " -->";
    if (isset($GLOBALS["TRANSMITTAL_FILE_COUNT"])) {
        echo "<!-- DEBUG: File count: " .
            $GLOBALS["TRANSMITTAL_FILE_COUNT"] .
            " -->";
    }
}
?>
