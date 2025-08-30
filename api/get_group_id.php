<?php
/**
 * API endpoint to get a group ID from a project number (group name).
 */
require_once "../bootstrap.php"; // Adjust path as needed
header("Content-Type: application/json");

if (!isset($_GET["project_number"])) {
    http_response_code(400);
    echo json_encode(["error" => "Project number is missing"]);
    exit();
}

$project_number = $_GET["project_number"];

try {
    global $dbh;
    $query =
        "SELECT id FROM " .
        TABLE_GROUPS .
        " WHERE name = :project_number LIMIT 1";

    $statement = $dbh->prepare($query);
    $statement->bindParam(":project_number", $project_number);
    $statement->execute();

    $group_id = $statement->fetchColumn();

    if ($group_id) {
        echo json_encode(["success" => true, "group_id" => $group_id]);
    } else {
        echo json_encode(["success" => false, "error" => "Group not found"]);
    }
} catch (PDOException $e) {
    error_log("Database error in get_group_id.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "An unexpected error occurred."]);
}
