<?php
/**
 * AJAX endpoint to get deliverable types by discipline
 */

// Include your ProjectSend configuration
require_once "../bootstrap.php"; // Adjust path as needed

// Check if user is logged in (adjust this based on your authentication system)
if (!defined("CURRENT_USER_ID") || !CURRENT_USER_ID) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

// Set content type to JSON
header("Content-Type: application/json");

try {
    // Get the discipline parameter
    $discipline = $_GET["discipline"] ?? "";

    if (empty($discipline)) {
        echo json_encode([]);
        exit();
    }

    // Create the helper instance
    $helper = new \ProjectSend\Classes\TransmittalHelper();

    // Get deliverable types for the selected discipline
    $deliverable_types = $helper->getDeliverableTypesByDiscipline($discipline);

    // Format the response for the dropdown
    $formatted_response = [];
    foreach ($deliverable_types as $type) {
        $formatted_response[] = [
            "value" => $type["deliverable_type"],
            "text" => !empty($type["abbreviation"])
                ? $type["deliverable_type"] . " (" . $type["abbreviation"] . ")"
                : $type["deliverable_type"],
        ];
    }

    echo json_encode($formatted_response);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get_deliverable_types.php: " . $e->getMessage());
    echo json_encode(["error" => "Internal server error"]);
}
?>
