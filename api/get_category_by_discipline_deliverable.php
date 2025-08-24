<?php
require_once "../bootstrap.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);

if (empty($input["discipline"]) || empty($input["deliverable_type"])) {
    echo json_encode(["success" => false, "error" => "Missing parameters"]);
    exit();
}

try {
    global $dbh;

    // Get discipline abbreviation from database
    $disc_query = "SELECT abbreviation FROM tbl_discipline 
                   WHERE discipline_name = :discipline AND active = 1";
    $disc_stmt = $dbh->prepare($disc_query);
    $disc_stmt->execute([":discipline" => $input["discipline"]]);
    $discipline_abbr = $disc_stmt->fetchColumn();

    if (!$discipline_abbr) {
        echo json_encode([
            "success" => false,
            "error" => "Discipline not found",
        ]);
        exit();
    }

    // Get deliverable abbreviation from database
    $deliv_query = "SELECT dt.abbreviation FROM tbl_deliverable_type dt
                    JOIN tbl_discipline d ON dt.discipline_id = d.id
                    WHERE d.discipline_name = :discipline 
                    AND dt.deliverable_type = :deliverable_type
                    AND dt.active = 1 AND d.active = 1";
    $deliv_stmt = $dbh->prepare($deliv_query);
    $deliv_stmt->execute([
        ":discipline" => $input["discipline"],
        ":deliverable_type" => $input["deliverable_type"],
    ]);
    $deliverable_abbr = $deliv_stmt->fetchColumn();

    if (!$deliverable_abbr) {
        echo json_encode([
            "success" => false,
            "error" => "Deliverable type not found for this discipline",
        ]);
        exit();
    }

    // Find the specific category that matches both discipline and deliverable type
    // Pattern: child category under discipline parent
    $query = "SELECT child.id, child.name as category_name, parent.name as parent_name
              FROM tbl_categories child
              JOIN tbl_categories parent ON child.parent = parent.id
              WHERE parent.name = :discipline_pattern
              AND child.name = :deliverable_pattern
              AND child.active = 1 
              LIMIT 1";

    $stmt = $dbh->prepare($query);
    $stmt->execute([
        ":discipline_pattern" =>
            "($discipline_abbr) - All " . $input["discipline"],
        ":deliverable_pattern" =>
            "($deliverable_abbr) - " . $input["deliverable_type"],
    ]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode([
            "success" => true,
            "category_id" => $result["id"],
            "category_name" => $result["category_name"],
            "parent_name" => $result["parent_name"],
        ]);
    } else {
        echo json_encode(["success" => false, "error" => "Category not found"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
