<?php
require_once "../bootstrap.php";
header("Content-Type: application/json");

if (!isset($_GET["project_number"])) {
    http_response_code(400);
    echo json_encode(["error" => "Project number is missing"]);
    exit();
}

$project_number = $_GET["project_number"];

try {
    global $dbh;

    // FIXED: Look in tbl_groups.description where tbl_groups.name matches project_number
    $query =
        "SELECT description FROM tbl_groups WHERE name = :project_number LIMIT 1";

    $statement = $dbh->prepare($query);
    $statement->bindParam(":project_number", $project_number);
    $statement->execute();

    $project_description = $statement->fetchColumn();

    if ($project_description) {
        // Clean up HTML entities and tags from the description
        $project_name = html_entity_decode(
            $project_description,
            ENT_QUOTES,
            "UTF-8"
        );
        $project_name = strip_tags($project_name); // Remove HTML tags like <p>
        $project_name = trim($project_name); // Remove extra whitespace

        echo json_encode([
            "success" => true,
            "project_name" => $project_name,
            "project_number" => $project_number,
        ]);
    } else {
        // FALLBACK: Try the transmittal summary table as backup
        $fallback_query =
            "SELECT project_name FROM tbl_transmittal_summary WHERE project_number = :project_number LIMIT 1";
        $fallback_statement = $dbh->prepare($fallback_query);
        $fallback_statement->bindParam(":project_number", $project_number);
        $fallback_statement->execute();

        $fallback_name = $fallback_statement->fetchColumn();

        if ($fallback_name) {
            echo json_encode([
                "success" => true,
                "project_name" => $fallback_name,
                "project_number" => $project_number,
                "source" => "fallback",
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "error" =>
                    "Project name not found in groups or transmittal summary",
                "searched_project_number" => $project_number,
            ]);
        }
    }
} catch (PDOException $e) {
    error_log("Database error in get_project_name.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Database error occurred"]);
}
?>
