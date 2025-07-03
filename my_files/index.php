<?php
define("VIEW_TYPE", "template");
require_once "../bootstrap.php";

if (!defined("CURRENT_USER_USERNAME")) {
    if (!empty($_SERVER["QUERY_STRING"])) {
        $_SESSION["redirect_after_login"] = $_SERVER["REQUEST_URI"];
    }

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
$display_transmittal = null; // Add this for proper display

if ($filter_transmittal) {
    if (strpos($filter_transmittal, "-") !== false) {
        // Check if this looks like: PROJECT-PROJECT-T-XXXX format
        if (
            preg_match(
                '/^([A-Z0-9]+)-\1-T-(\d+)$/',
                $filter_transmittal,
                $matches
            )
        ) {
            // Handle duplicate project format: DOM2502-DOM2502-T-0009
            $filter_project = $matches[1];
            $filter_transmittal_num = $matches[2];
            $display_transmittal =
                $filter_project . "-T-" . $filter_transmittal_num; // DOM2502-T-0009
        } elseif (
            preg_match('/^([A-Z0-9]+)-(.+)$/', $filter_transmittal, $matches)
        ) {
            // Handle normal format: PROJECT-TRANSMITTAL (e.g., DOM2504-0001)
            $filter_project = $matches[1];
            $filter_transmittal_num = $matches[2];
            $display_transmittal = $filter_transmittal; // Use as-is
        } else {
            // Fallback to original logic
            list($filter_project, $filter_transmittal_num) = explode(
                "-",
                $filter_transmittal,
                2
            );
            $display_transmittal = $filter_transmittal;
        }
    } else {
        // Legacy format: just transmittal number
        $filter_transmittal_num = $filter_transmittal;
        $display_transmittal = $filter_transmittal;
    }
}

// If transmittal filter is requested, we need to modify the database query
if (!empty($filter_transmittal)) {
    // Set a global variable that can be used to modify SQL queries
    $GLOBALS["TRANSMITTAL_FILTER"] = $display_transmittal;
}

// Now load the actual default template with the bug fixed
require get_template_file_location("template.php");

// Additional function to get transmittal file count for display
function get_transmittal_file_count($transmittal_number, $user_id)
{
    global $dbh;

    // Using the correct table name from your schema
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
        $GLOBALS["TRANSMITTAL_NUMBER"] = $display_transmittal; // Use clean display version
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

        var notice = document.createElement("div");
        notice.className = "transmittal-filter-notice";
        notice.innerHTML = "<div><strong>📁 Transmittal Filter Active:</strong> Showing files from transmittal <strong>' .
        htmlspecialchars($display_transmittal) . // Use clean display version
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
        htmlspecialchars($display_transmittal) . // Use clean display version
        ' - " + currentTitle;
        }
    });
    </script>';
}

?>
