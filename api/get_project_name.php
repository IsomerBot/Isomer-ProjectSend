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
    $query =
        "SELECT project_name FROM tbl_transmittal_summary WHERE project_number = :project_number LIMIT 1";

    $statement = $dbh->prepare($query);
    $statement->bindParam(":project_number", $project_number);
    $statement->execute();

    $project_name = $statement->fetchColumn();

    if ($project_name) {
        echo json_encode(["success" => true, "project_name" => $project_name]);
    } else {
        echo json_encode([
            "success" => false,
            "error" => "Project name not found",
        ]);
    }
} catch (PDOException $e) {
    error_log("Database error in get_project_name.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Database error occurred"]);
}
?>
