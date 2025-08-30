<?php
/**
 * Migration Script: Populate tbl_transmittal_summary from existing tbl_files data
 * Run this once to migrate historical transmittal data
 */

require_once "bootstrap.php";

// Only allow admin users to run this migration
if (CURRENT_USER_LEVEL != 9) {
    die("Access denied. Only system administrators can run this migration.");
}

echo "<h1>Transmittal Data Migration</h1>\n";
echo "<p>Migrating existing transmittal data to centralized table...</p>\n";

$migration_stats = [
    "transmittals_created" => 0,
    "transmittals_updated" => 0,
    "errors" => 0,
    "skipped" => 0,
];

try {
    global $dbh;

    // Get all unique transmittals from tbl_files
    $query = "SELECT 
                transmittal_number,
                transmittal_name,
                project_number,
                project_name,
                package_description,
                issue_status,
                discipline,
                deliverable_type,
                comments,
                file_bcc_addresses,
                file_cc_addresses,
                user_id,
                COUNT(*) as file_count,
                MIN(id) as first_file_id,
                MAX(timestamp) as last_updated
              FROM tbl_files 
              WHERE transmittal_number IS NOT NULL 
                AND transmittal_number != ''
              GROUP BY transmittal_number
              ORDER BY project_number, transmittal_number";

    $statement = $dbh->prepare($query);
    $statement->execute();
    $transmittals = $statement->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Found " .
        count($transmittals) .
        " unique transmittals to migrate.</p>\n";
    echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
    echo "<tr><th>Transmittal Number</th><th>Project</th><th>Files</th><th>Status</th><th>Details</th></tr>\n";

    $transmittalManager = new \ProjectSend\Classes\TransmittalSummaryManager();

    foreach ($transmittals as $transmittal) {
        $status = "";
        $details = "";

        try {
            // Skip if transmittal number is empty
            if (empty($transmittal["transmittal_number"])) {
                $migration_stats["skipped"]++;
                $status = "SKIPPED";
                $details = "Empty transmittal number";
                echo "<tr><td>-</td><td>{$transmittal["project_number"]}</td><td>{$transmittal["file_count"]}</td><td style='color: orange;'>$status</td><td>$details</td></tr>\n";
                continue;
            }

            // Check if this transmittal already exists in summary table
            $existing = $transmittalManager->getTransmittalData(
                $transmittal["transmittal_number"]
            );

            // Look up reference IDs
            $group_id = null;
            $discipline_id = null;
            $deliverable_type_id = null;

            // Get group_id from project_number
            if (!empty($transmittal["project_number"])) {
                $group_query =
                    "SELECT id FROM tbl_groups WHERE name = :project_number LIMIT 1";
                $group_stmt = $dbh->prepare($group_query);
                $group_stmt->execute([
                    ":project_number" => $transmittal["project_number"],
                ]);
                $group_result = $group_stmt->fetch(PDO::FETCH_ASSOC);
                $group_id = $group_result ? $group_result["id"] : null;
            }

            // Get discipline_id from discipline name
            if (!empty($transmittal["discipline"])) {
                $discipline_query =
                    "SELECT id FROM tbl_discipline WHERE discipline_name = :discipline_name LIMIT 1";
                $discipline_stmt = $dbh->prepare($discipline_query);
                $discipline_stmt->execute([
                    ":discipline_name" => $transmittal["discipline"],
                ]);
                $discipline_result = $discipline_stmt->fetch(PDO::FETCH_ASSOC);
                $discipline_id = $discipline_result
                    ? $discipline_result["id"]
                    : null;
            }

            // Get deliverable_type_id from deliverable_type name and discipline
            if (!empty($transmittal["deliverable_type"]) && $discipline_id) {
                $deliverable_query = "SELECT id FROM tbl_deliverable_type 
                                     WHERE deliverable_type = :deliverable_type 
                                     AND discipline_id = :discipline_id LIMIT 1";
                $deliverable_stmt = $dbh->prepare($deliverable_query);
                $deliverable_stmt->execute([
                    ":deliverable_type" => $transmittal["deliverable_type"],
                    ":discipline_id" => $discipline_id,
                ]);
                $deliverable_result = $deliverable_stmt->fetch(
                    PDO::FETCH_ASSOC
                );
                $deliverable_type_id = $deliverable_result
                    ? $deliverable_result["id"]
                    : null;
            }

            // Prepare migration data
            $migration_data = [
                "project_number" => $transmittal["project_number"],
                "group_id" => $group_id,
                "discipline_id" => $discipline_id,
                "deliverable_type_id" => $deliverable_type_id,
                "uploader_user_id" => $transmittal["user_id"],
                "project_name" => $transmittal["project_name"],
                "package_description" => $transmittal["package_description"],
                "comments" => $transmittal["comments"],
                "cc_addresses" => $transmittal["file_cc_addresses"],
                "bcc_addresses" => $transmittal["file_bcc_addresses"],
                "file_count" => $transmittal["file_count"],
            ];

            // Create or update
            $success = $transmittalManager->createOrUpdate(
                $transmittal["transmittal_number"],
                $migration_data
            );

            if ($success) {
                if ($existing) {
                    $migration_stats["transmittals_updated"]++;
                    $status = "UPDATED";
                    $details = "Existing record updated";
                } else {
                    $migration_stats["transmittals_created"]++;
                    $status = "CREATED";
                    $details = "New record created";
                }

                // Add warnings for missing reference data
                $warnings = [];
                if (!$group_id && !empty($transmittal["project_number"])) {
                    $warnings[] = "Group not found";
                }
                if (!$discipline_id && !empty($transmittal["discipline"])) {
                    $warnings[] = "Discipline not found";
                }
                if (
                    !$deliverable_type_id &&
                    !empty($transmittal["deliverable_type"])
                ) {
                    $warnings[] = "Deliverable type not found";
                }

                if (!empty($warnings)) {
                    $details .= " (Warnings: " . implode(", ", $warnings) . ")";
                }

                echo "<tr><td>{$transmittal["transmittal_number"]}</td><td>{$transmittal["project_number"]}</td><td>{$transmittal["file_count"]}</td><td style='color: green;'>$status</td><td>$details</td></tr>\n";
            } else {
                $migration_stats["errors"]++;
                $status = "ERROR";
                $details = "Failed to save to database";
                echo "<tr><td>{$transmittal["transmittal_number"]}</td><td>{$transmittal["project_number"]}</td><td>{$transmittal["file_count"]}</td><td style='color: red;'>$status</td><td>$details</td></tr>\n";
            }
        } catch (Exception $e) {
            $migration_stats["errors"]++;
            $status = "ERROR";
            $details = "Exception: " . $e->getMessage();
            echo "<tr><td>{$transmittal["transmittal_number"]}</td><td>{$transmittal["project_number"]}</td><td>{$transmittal["file_count"]}</td><td style='color: red;'>$status</td><td>$details</td></tr>\n";
            error_log(
                "Migration error for transmittal {$transmittal["transmittal_number"]}: " .
                    $e->getMessage()
            );
        }

        // Flush output for real-time progress
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    echo "</table>\n";

    // Migration summary
    echo "<h2>Migration Summary</h2>\n";
    echo "<ul>\n";
    echo "<li><strong>Transmittals Created:</strong> {$migration_stats["transmittals_created"]}</li>\n";
    echo "<li><strong>Transmittals Updated:</strong> {$migration_stats["transmittals_updated"]}</li>\n";
    echo "<li><strong>Errors:</strong> {$migration_stats["errors"]}</li>\n";
    echo "<li><strong>Skipped:</strong> {$migration_stats["skipped"]}</li>\n";
    echo "<li><strong>Total Processed:</strong> " .
        array_sum($migration_stats) .
        "</li>\n";
    echo "</ul>\n";

    // Verification query
    echo "<h2>Verification</h2>\n";
    $verify_query =
        "SELECT COUNT(*) as summary_count FROM tbl_transmittal_summary";
    $verify_stmt = $dbh->prepare($verify_query);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->fetch(PDO::FETCH_ASSOC);

    echo "<p>Records in tbl_transmittal_summary: <strong>{$verify_result["summary_count"]}</strong></p>\n";

    // Check for missing reference data
    echo "<h2>Data Quality Check</h2>\n";
    $quality_checks = [
        "Missing Groups" =>
            "SELECT COUNT(*) as count FROM tbl_transmittal_summary WHERE group_id IS NULL AND project_number IS NOT NULL",
        "Missing Disciplines" =>
            "SELECT COUNT(*) as count FROM tbl_transmittal_summary WHERE discipline_id IS NULL",
        "Missing Deliverable Types" =>
            "SELECT COUNT(*) as count FROM tbl_transmittal_summary WHERE deliverable_type_id IS NULL",
    ];

    foreach ($quality_checks as $check_name => $check_query) {
        $check_stmt = $dbh->prepare($check_query);
        $check_stmt->execute();
        $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        $color = $check_result["count"] > 0 ? "orange" : "green";
        echo "<p><strong>$check_name:</strong> <span style='color: $color;'>{$check_result["count"]}</span></p>\n";
    }

    if ($migration_stats["errors"] == 0) {
        echo "<h2 style='color: green;'>Migration Completed Successfully!</h2>\n";
        echo "<p>Your EmailNotifications system will now use the centralized transmittal data.</p>\n";
    } else {
        echo "<h2 style='color: orange;'>Migration Completed with Errors</h2>\n";
        echo "<p>Check the error log for details on failed migrations.</p>\n";
    }
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Migration Failed</h2>\n";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    error_log("Migration script failed: " . $e->getMessage());
}
?>
<style>
body { 
    font-family: Arial, sans-serif; 
    margin: 20px; 
    background: #f5f5f5; 
}
table { 
    background: white; 
    margin: 20px 0; 
    border-collapse: collapse; 
    width: 100%; 
}
th { 
    background: #252c3a; 
    color: white; 
    padding: 10px; 
}
td { 
    padding: 8px; 
    border: 1px solid #ddd; 
}
tr:nth-child(even) { 
    background: #f9f9f9; 
}
</style>