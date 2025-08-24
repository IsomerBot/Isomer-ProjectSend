<?php
require_once "../bootstrap.php";
header("Content-Type: application/json");

// Permission check
if (!user_is_logged_in()) {
    http_response_code(403);
    echo json_encode(["error" => "Not logged in"]);
    exit();
}

$allowed_levels = [9, 8, 7, 0];
if (!in_array(CURRENT_USER_LEVEL, $allowed_levels)) {
    http_response_code(403);
    echo json_encode(["error" => "Permission denied"]);
    exit();
}

// Handle both GET and POST requests
$group_id = null;

// Try GET first (primary method)
if (isset($_GET["group_id"]) && is_numeric($_GET["group_id"])) {
    $group_id = (int) $_GET["group_id"];
} else {
    // Try POST JSON input as fallback
    $raw_input = file_get_contents("php://input");
    if ($raw_input) {
        $input = json_decode($raw_input, true);
        if (
            $input &&
            isset($input["group_id"]) &&
            is_numeric($input["group_id"])
        ) {
            $group_id = (int) $input["group_id"];
        }
    }
}

// Debug logging
error_log("GET parameters: " . print_r($_GET, true));
error_log("Raw POST input: " . file_get_contents("php://input"));
error_log("Processed group_id: " . ($group_id ?: "NULL"));

if (!$group_id) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "Invalid or missing group_id parameter",
        "debug" => [
            "get_params" => $_GET,
            "received_group_id" => $group_id,
        ],
    ]);
    exit();
}

try {
    global $dbh;

    // Get all members of the specified group
    $query = "SELECT m.client_id, u.name, u.email, u.active
              FROM tbl_members m
              JOIN tbl_users u ON m.client_id = u.id
              WHERE m.group_id = :group_id AND u.active = 1
              ORDER BY u.name";

    $statement = $dbh->prepare($query);
    $statement->execute([":group_id" => $group_id]);
    $members = $statement->fetchAll(PDO::FETCH_ASSOC);

    error_log("Found " . count($members) . " members for group " . $group_id);

    // Extract just the client IDs for the response
    $member_ids = array_map(function ($member) {
        return (int) $member["client_id"];
    }, $members);

    // Also include member details for debugging/display
    $member_details = [];
    foreach ($members as $member) {
        $member_details[] = [
            "id" => (int) $member["client_id"],
            "name" => $member["name"],
            "email" => $member["email"],
        ];
    }

    echo json_encode([
        "success" => true,
        "group_id" => $group_id,
        "members" => $member_ids,
        "member_details" => $member_details,
        "count" => count($members),
    ]);
} catch (Exception $e) {
    error_log("Get group members error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Server error occurred",
        "details" => $e->getMessage(),
    ]);
}
?>
