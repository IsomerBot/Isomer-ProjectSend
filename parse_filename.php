<?php
/**
 * AJAX endpoint for parsing filenames
 */
require_once "bootstrap.php";

// Only allow logged-in users with proper permissions
$allowed_levels = [9, 8, 7, 0];
if (!defined("CURRENT_USER_ID") || !current_role_in($allowed_levels)) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit();
}

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit();
}

try {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!isset($input["filename"]) || !isset($input["action"])) {
        throw new Exception("Missing required parameters");
    }

    if ($input["action"] !== "parse_filename") {
        throw new Exception("Invalid action");
    }

    $filename = $input["filename"];

    // Initialize TransmittalHelper
    $helper = new \ProjectSend\Classes\TransmittalHelper();

    // Parse the filename
    $parsed_data = $helper->parseFilename($filename);

    // Add success flag
    $response = array_merge(["success" => true], $parsed_data);

    echo json_encode($response);
} catch (Exception $e) {
    error_log("Filename parsing error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage(),
        "parsed_successfully" => false,
    ]);
}
