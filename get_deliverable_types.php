<?php
require_once "bootstrap.php";

// Check if user is logged in
if (!defined("CURRENT_USER_ID")) {
    http_response_code(403);
    exit("Access denied");
}

header("Content-Type: application/json");

if (empty($_GET["discipline"])) {
    echo json_encode([]);
    exit();
}

try {
    $helper = new \ProjectSend\Classes\TransmittalHelper();
    $deliverable_types = $helper->getDeliverableTypesByDiscipline(
        $_GET["discipline"]
    );

    // Format for frontend
    $formatted_types = [];
    foreach ($deliverable_types as $type) {
        $display_text = $type["abbreviation"]
            ? $type["deliverable_type"] . " (" . $type["abbreviation"] . ")"
            : $type["deliverable_type"];

        $formatted_types[] = [
            "value" => $type["deliverable_type"],
            "text" => $display_text,
        ];
    }

    echo json_encode($formatted_types);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to fetch deliverable types"]);
}
?>
