<?php
/**
 * Transmittal Helper for ProjectSend Integration
 * Handles all transmittal-related operations and data access
 */
namespace ProjectSend\Classes;
use \PDO;

class TransmittalHelper
{
    private $dbh;

    public function __construct()
    {
        global $dbh;
        $this->dbh = $dbh;
    }

    /**
     * Parse filename and return data including matching category
     * Expected format: AAA####-AA-AAA-####
     * Example: TVT2502-PR-PFD-0001
     */
    public function parseFilename($filename)
    {
        // Remove file extension
        $filename_no_ext = pathinfo($filename, PATHINFO_FILENAME);

        // Parse the filename pattern: PROJECT-DISCIPLINE-DELIVERABLE-NUMBER
        if (
            preg_match(
                '/^([A-Z]{3}\d{4})-([A-Z]{2})-([A-Z]{3})-(\d{4})$/',
                $filename_no_ext,
                $matches
            )
        ) {
            $project_number = $matches[1];
            $discipline_abbr = $matches[2];
            $deliverable_abbr = $matches[3];
            $document_number = $matches[4];

            // Get discipline name from abbreviation
            $discipline_name = $this->getDisciplineNameByAbbr($discipline_abbr);
            $deliverable_type = null;
            $category_ids = [];

            if ($discipline_name) {
                // Get deliverable type name
                $deliverable_type = $this->getDeliverableTypeByAbbr(
                    $discipline_name,
                    $deliverable_abbr
                );

                if ($deliverable_type) {
                    // Find matching category IDs
                    $category_ids = $this->findCategoryIds(
                        $discipline_name,
                        $deliverable_type
                    );
                }
            }

            return [
                "parsed_successfully" => true,
                "project_number" => $project_number,
                "discipline" => $discipline_name,
                "discipline_abbr" => $discipline_abbr,
                "deliverable_type" => $deliverable_type,
                "deliverable_abbr" => $deliverable_abbr,
                "document_number" => $document_number,
                "category_ids" => $category_ids,
                "category_id" => !empty($category_ids)
                    ? $category_ids[0]
                    : null, // For single-select compatibility
            ];
        }

        return [
            "parsed_successfully" => false,
            "message" =>
                "Could not parse filename format. Expected: AAA####-AA-AAA-####",
            "category_ids" => [],
        ];
    }

    /**
     * Get discipline name by abbreviation
     */
    private function getDisciplineNameByAbbr($abbreviation)
    {
        $query = "SELECT discipline_name FROM tbl_discipline 
              WHERE abbreviation = :abbr AND active = 1 LIMIT 1";
        $stmt = $this->dbh->prepare($query);
        $stmt->execute([":abbr" => $abbreviation]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result["discipline_name"] : null;
    }

    /**
     * Get deliverable type by abbreviation within a specific discipline
     */
    private function getDeliverableTypeByAbbr($discipline_name, $abbreviation)
    {
        $query = "SELECT dt.deliverable_type 
              FROM tbl_deliverable_type dt
              JOIN tbl_discipline d ON dt.discipline_id = d.id
              WHERE d.discipline_name = :discipline 
              AND dt.abbreviation = :abbr 
              AND dt.active = 1 AND d.active = 1 
              LIMIT 1";

        $stmt = $this->dbh->prepare($query);
        $stmt->execute([
            ":discipline" => $discipline_name,
            ":abbr" => $abbreviation,
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result["deliverable_type"] : null;
    }

    /**
     * FIXED: Find category IDs that match YOUR specific category structure
     * Your categories use pattern: "(PFD) - PFDs" under parent "(PR) - All Process"
     */
    private function findCategoryIds($discipline_name, $deliverable_type)
    {
        $category_ids = [];

        // Get discipline abbreviation for matching your category pattern
        $discipline_abbr = $this->getDisciplineAbbrByName($discipline_name);
        $deliverable_abbr = $this->getDeliverableAbbrByName(
            $discipline_name,
            $deliverable_type
        );

        // Strategy 1: Look for deliverable abbreviation pattern in your structure
        // Your pattern: "(PFD) - PFDs"
        if ($deliverable_abbr) {
            $query = "SELECT id FROM tbl_categories 
                  WHERE name LIKE :abbr_pattern AND active = 1";

            $stmt = $this->dbh->prepare($query);
            $stmt->execute([
                ":abbr_pattern" => "(" . $deliverable_abbr . ") - %",
            ]);

            while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $category_ids[] = $result["id"];
            }
        }

        // Strategy 2: Look for deliverable type name pattern
        if (empty($category_ids) && $deliverable_type) {
            $deliverable_patterns = [
                "% - " . $deliverable_type,
                "% " . $deliverable_type,
                "% - " . $deliverable_type . " %",
            ];

            foreach ($deliverable_patterns as $pattern) {
                $query = "SELECT id FROM tbl_categories 
                      WHERE name LIKE :pattern
                      AND parent IS NOT NULL 
                      AND active = 1";

                $stmt = $this->dbh->prepare($query);
                $stmt->execute([":pattern" => $pattern]);

                while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $category_ids[] = $result["id"];
                }
            }
        }

        // Strategy 3: Look for child categories under discipline parent
        // Your parent pattern: "(PR) - All Process"
        if (empty($category_ids) && $discipline_abbr) {
            $query = "SELECT child.id 
                  FROM tbl_categories child
                  JOIN tbl_categories parent ON child.parent = parent.id
                  WHERE parent.name LIKE :discipline_pattern
                  AND (child.name LIKE :deliverable_pattern1 
                       OR child.name LIKE :deliverable_pattern2)
                  AND child.active = 1";

            $stmt = $this->dbh->prepare($query);
            $stmt->execute([
                ":discipline_pattern" => "(" . $discipline_abbr . ") - All %",
                ":deliverable_pattern1" => "%" . $deliverable_type . "%",
                ":deliverable_pattern2" => "%" . $deliverable_abbr . "%",
            ]);

            while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $category_ids[] = $result["id"];
            }
        }

        return array_unique($category_ids);
    }

    /**
     * Get discipline abbreviation by name
     */
    private function getDisciplineAbbrByName($discipline_name)
    {
        $query = "SELECT abbreviation FROM tbl_discipline 
              WHERE discipline_name = :name AND active = 1 LIMIT 1";
        $stmt = $this->dbh->prepare($query);
        $stmt->execute([":name" => $discipline_name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result["abbreviation"] : null;
    }

    /**
     * Get deliverable type abbreviation by name and discipline
     */
    private function getDeliverableAbbrByName(
        $discipline_name,
        $deliverable_type
    ) {
        $query = "SELECT dt.abbreviation 
              FROM tbl_deliverable_type dt
              JOIN tbl_discipline d ON dt.discipline_id = d.id
              WHERE d.discipline_name = :discipline 
              AND dt.deliverable_type = :deliverable_type 
              AND dt.active = 1 AND d.active = 1 
              LIMIT 1";

        $stmt = $this->dbh->prepare($query);
        $stmt->execute([
            ":discipline" => $discipline_name,
            ":deliverable_type" => $deliverable_type,
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result["abbreviation"] : null;
    }

    /**
     * Get dropdown options for various transmittal fields
     * @param string $type - The type of dropdown (issue_status, discipline, etc.)
     * @return array - Array of option values
     */
    public function getDropdownOptions($type)
    {
        // Map dropdown types to their database tables
        $table_map = [
            "issue_status" => "tbl_issue_status",
            "discipline" => "tbl_discipline",
            "deliverable_type" => "tbl_deliverable_type",
        ];

        // Map dropdown types to their column names
        $field_map = [
            "issue_status" => "status_name",
            "discipline" => "discipline_name",
            "deliverable_type" => "deliverable_type", // FIXED: was "type_name"
        ];

        // Check if the requested type exists in our mapping
        if (!isset($table_map[$type])) {
            return [];
        }

        // Build and execute the query
        $query = "SELECT {$field_map[$type]} as name FROM {$table_map[$type]} 
                  WHERE active = 1 ORDER BY {$field_map[$type]} ASC";

        $statement = $this->dbh->prepare($query);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get discipline details with abbreviations
     * @return array - Array with discipline_name => abbreviation mapping
     */
    public function getDisciplineDetails()
    {
        $query = "SELECT discipline_name, abbreviation FROM tbl_discipline 
              WHERE active = 1 ORDER BY discipline_name ASC";

        $statement = $this->dbh->prepare($query);
        $statement->execute();
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        $details = [];
        foreach ($results as $row) {
            $details[$row["discipline_name"]] = $row["abbreviation"];
        }

        return $details;
    }

    /**
     * Get deliverable types by discipline (for AJAX and dynamic dropdowns)
     * @param string $discipline_name - The discipline name
     * @return array - Array of deliverable types with abbreviations for this discipline
     */
    public function getDeliverableTypesByDiscipline($discipline_name)
    {
        // Join with discipline table to match by discipline name
        $query = "SELECT dt.deliverable_type, dt.abbreviation 
              FROM tbl_deliverable_type dt
              JOIN tbl_discipline d ON dt.discipline_id = d.id
              WHERE d.discipline_name = :discipline_name 
              AND dt.active = 1 
              ORDER BY dt.deliverable_type ASC";

        $statement = $this->dbh->prepare($query);
        $statement->execute([":discipline_name" => $discipline_name]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generate dropdown HTML with abbreviations
     * @param string $type - The dropdown type
     * @param string $selected_value - Currently selected value
     * @param bool $include_empty - Whether to include empty option
     * @param bool $show_abbreviations - Whether to show abbreviations
     * @return string - HTML option elements
     */
    public function generateDropdownHtmlWithAbbr(
        $type,
        $selected_value = "",
        $include_empty = true,
        $show_abbreviations = true
    ) {
        $html = "";

        // Add empty option if requested
        if ($include_empty) {
            $html .=
                '<option value="">Select ' .
                ucfirst(str_replace("_", " ", $type)) .
                "</option>";
        }

        if ($type === "discipline") {
            $details = $this->getDisciplineDetails();
            foreach ($details as $discipline => $abbreviation) {
                $selected = $discipline == $selected_value ? " selected" : "";
                $display_text =
                    $show_abbreviations && $abbreviation
                        ? "$discipline ($abbreviation)"
                        : $discipline;
                $html .=
                    '<option value="' .
                    htmlspecialchars($discipline) .
                    '"' .
                    $selected .
                    ">";
                $html .= htmlspecialchars($display_text) . "</option>";
            }
        } else {
            // Fallback to original method for other types
            $options = $this->getDropdownOptions($type);
            foreach ($options as $option) {
                $selected = $option == $selected_value ? " selected" : "";
                $html .=
                    '<option value="' .
                    htmlspecialchars($option) .
                    '"' .
                    $selected .
                    ">";
                $html .= htmlspecialchars($option) . "</option>";
            }
        }

        return $html;
    }

    /**
     * Get discipline ID by name (helper method)
     * @param string $discipline_name - The discipline name
     * @return int|null - Discipline ID or null if not found
     */
    public function getDisciplineIdByName($discipline_name)
    {
        $query =
            "SELECT id FROM tbl_discipline WHERE discipline_name = :discipline_name AND active = 1";
        $statement = $this->dbh->prepare($query);
        $statement->execute([":discipline_name" => $discipline_name]);
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result ? $result["id"] : null;
    }

    /**
     * Generate HTML for dropdown options
     * @param string $type - The dropdown type
     * @param string $selected_value - Currently selected value
     * @param bool $include_empty - Whether to include empty option
     * @return string - HTML option elements
     */
    public function generateDropdownHtml(
        $type,
        $selected_value = "",
        $include_empty = true
    ) {
        $options = $this->getDropdownOptions($type);
        $html = "";

        // Add empty option if requested
        if ($include_empty) {
            $html .=
                '<option value="">Select ' .
                ucfirst(str_replace("_", " ", $type)) .
                "</option>";
        }

        // Generate option elements
        foreach ($options as $option) {
            $selected = $option == $selected_value ? " selected" : "";
            $html .=
                '<option value="' .
                htmlspecialchars($option) .
                '"' .
                $selected .
                ">";
            $html .= htmlspecialchars($option) . "</option>";
        }

        return $html;
    }

    /**
     * Get transmittal data by transmittal number
     * @param string $transmittal_number - The transmittal number to look up
     * @return array|false - Transmittal data or false if not found
     */
    public function getTransmittalData($transmittal_number)
    {
        $query = "SELECT t.*, u.name as created_by_name 
                  FROM tbl_transmittals t 
                  LEFT JOIN tbl_users u ON t.created_by = u.id 
                  WHERE t.transmittal_number = :transmittal_number";

        $statement = $this->dbh->prepare($query);
        $statement->execute([":transmittal_number" => $transmittal_number]);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create or update transmittal record
     * @param array $data - Transmittal data to save
     * @return bool - Success status
     */
    public function saveTransmittal($data)
    {
        // Check if transmittal already exists
        $check_query =
            "SELECT id FROM tbl_transmittals WHERE transmittal_number = :transmittal_number";
        $check_statement = $this->dbh->prepare($check_query);
        $check_statement->execute([
            ":transmittal_number" => $data["transmittal_number"],
        ]);

        if ($check_statement->fetch()) {
            // Update existing transmittal
            $query = "UPDATE tbl_transmittals SET 
                        project_name = :project_name,
                        package_description = :package_description,
                        status = :status,
                        comments = :comments,
                        project_number = :project_number
                      WHERE transmittal_number = :transmittal_number";
        } else {
            // Insert new transmittal
            $query = "INSERT INTO tbl_transmittals 
                        (transmittal_number, project_name, package_description, status, comments, created_by, project_number) 
                      VALUES 
                        (:transmittal_number, :project_name, :package_description, :status, :comments, :created_by, :project_number)";
        }

        // Prepare parameters
        $params = [
            ":transmittal_number" => $data["transmittal_number"],
            ":project_name" => $data["project_name"],
            ":project_number" => $data["project_number"] ?? "",
            ":package_description" => $data["package_description"] ?? "",
            ":status" => $data["status"] ?? "Active",
            ":comments" => $data["comments"] ?? "",
        ];

        // Add created_by for new records
        if (!$check_statement->fetch()) {
            $params[":created_by"] =
                $data["created_by"] ?? ($_SESSION["userlevel"] ?? 0);
        }

        $statement = $this->dbh->prepare($query);
        return $statement->execute($params);
    }

    /**
     * Get files by transmittal number
     * @param string $transmittal_number - The transmittal number
     * @return array - Array of file records
     */
    public function getFilesByTransmittal($transmittal_number)
    {
        $query = "SELECT f.id, f.filename, f.original_url, f.description,
                         f.transmittal_number, f.transmittal_name, f.issue_status, f.discipline, 
                        f.deliverable_type, f.abbreviation,
                         f.document_title, f.project_name, f.project_number
                  FROM tbl_files f 
                  WHERE f.transmittal_number = :transmittal_number 
                  ORDER BY f.id ASC";

        $statement = $this->dbh->prepare($query);
        $statement->execute([":transmittal_number" => $transmittal_number]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all recipients for a transmittal from the centralized table
     * @param string $transmittal_number - The transmittal number
     * @return array - Array of recipient data
     */
    public function getTransmittalRecipients($transmittal_number)
    {
        error_log(
            "DEBUG: getTransmittalRecipients called with: " .
                $transmittal_number
        );

        // First, get the centralized transmittal data
        $query =
            "SELECT contacts FROM tbl_transmittal_summary WHERE transmittal_number = :transmittal_number";
        $statement = $this->dbh->prepare($query);
        $statement->execute([":transmittal_number" => $transmittal_number]);
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        error_log("DEBUG: Summary table result: " . print_r($result, true));

        if (empty($result) || empty($result["contacts"])) {
            error_log(
                "DEBUG: No contacts found in summary table, falling back to direct query"
            );
            return $this->getRecipientsFromFileRelations($transmittal_number);
        }

        // Decode the JSON string to get the user IDs
        $contact_ids = json_decode($result["contacts"], true);
        error_log("DEBUG: Decoded contact IDs: " . print_r($contact_ids, true));

        if (empty($contact_ids)) {
            error_log("DEBUG: No contact IDs after JSON decode");
            return $this->getRecipientsFromFileRelations($transmittal_number);
        }

        // FIXED: Correct SQL query without alias issue
        $in_clause = implode(",", array_fill(0, count($contact_ids), "?"));
        $query_users = "SELECT id, name, email, user FROM tbl_users WHERE id IN ($in_clause) AND active = '1'";
        $statement_users = $this->dbh->prepare($query_users);
        $statement_users->execute($contact_ids);

        $recipients = $statement_users->fetchAll(PDO::FETCH_ASSOC);
        error_log("DEBUG: Found recipients: " . print_r($recipients, true));

        return $recipients;
    }

    /**
     * Fallback method to get recipients directly from file relations
     * @param string $transmittal_number - The transmittal number
     * @return array - Array of recipient data
     */
    private function getRecipientsFromFileRelations($transmittal_number)
    {
        error_log(
            "DEBUG: Using fallback method for transmittal: " .
                $transmittal_number
        );

        try {
            // Get all clients assigned to files with this transmittal number
            $query = "SELECT DISTINCT u.id, u.name, u.email, u.user 
                  FROM tbl_files f
                  INNER JOIN tbl_files_relations fr ON f.id = fr.file_id
                  INNER JOIN tbl_users u ON fr.client_id = u.id
                  WHERE f.transmittal_number = :transmittal_number
                  AND u.level = '0'
                  AND u.active = '1'
                  ORDER BY u.name";

            $statement = $this->dbh->prepare($query);
            $statement->execute([":transmittal_number" => $transmittal_number]);

            $recipients = [];
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $recipients[] = $row;
            }

            if (!empty($recipients)) {
                error_log(
                    "DEBUG: Found " .
                        count($recipients) .
                        " recipients via direct client query"
                );
                return $recipients;
            }

            // If no individual clients found, try to get from groups
            $query_groups = "SELECT DISTINCT u.id, u.name, u.email, u.user 
                        FROM tbl_files f
                        INNER JOIN tbl_files_relations fr ON f.id = fr.file_id
                        INNER JOIN tbl_groups g ON fr.group_id = g.id
                        INNER JOIN tbl_members m ON g.id = m.group_id
                        INNER JOIN tbl_users u ON m.client_id = u.id
                        WHERE f.transmittal_number = :transmittal_number
                        AND u.level = '0'
                        AND u.active = '1'
                        ORDER BY u.name";

            $statement_groups = $this->dbh->prepare($query_groups);
            $statement_groups->execute([
                ":transmittal_number" => $transmittal_number,
            ]);

            $group_recipients = [];
            while ($row = $statement_groups->fetch(PDO::FETCH_ASSOC)) {
                $group_recipients[] = $row;
            }

            if (!empty($group_recipients)) {
                error_log(
                    "DEBUG: Found " .
                        count($group_recipients) .
                        " recipients via group query"
                );
                return $group_recipients;
            }

            error_log(
                "DEBUG: No recipients found for transmittal: " .
                    $transmittal_number
            );
            return [];
        } catch (Exception $e) {
            error_log(
                "ERROR: Failed to get recipients from file relations: " .
                    $e->getMessage()
            );
            return [];
        }
    }

    /**
     * Get user information by user ID
     * @param int $user_id - The user ID to look up
     * @return array|false - User data or false if not found
     */
    public function getUserById($user_id)
    {
        if (empty($user_id) || !is_numeric($user_id)) {
            return false;
        }

        $query = "SELECT id, name, email, user as username 
              FROM tbl_users 
              WHERE id = :user_id AND active = '1'";

        $statement = $this->dbh->prepare($query);
        $statement->execute([":user_id" => $user_id]);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update file with transmittal information
     * @param int $file_id - The file ID to update
     * @param array $transmittal_data - The transmittal data to save
     * @return bool - Success status
     */
    public function updateFileTransmittalInfo($file_id, $transmittal_data)
    {
        $query = "UPDATE tbl_files SET 
                    transmittal_number = :transmittal_number,
                    project_name = :project_name,
                    package_description = :package_description,
                    issue_status = :issue_status,
                    discipline = :discipline,
                    description = :description,
                    deliverable_type = :deliverable_type,
                    abbreviation = :abbreviation,
                    document_title = :document_title,
                    revision_number = :revision_number,
                    comments = :comments,
                    project_number = :project_number,
                    transmittal_name = :transmittal_name,
                    file_bcc_addresses = :file_bcc_addresses,
                    file_cc_addresses = :file_cc_addresses,
                    file_comments = :file_comments,
                    client_document_number = :client_document_number
                  WHERE id = :file_id";

        $statement = $this->dbh->prepare($query);
        return $statement->execute([
            ":file_id" => $file_id,
            ":transmittal_number" => $transmittal_data["transmittal_number"],
            ":project_name" => $transmittal_data["project_name"],
            ":package_description" =>
                $transmittal_data["package_description"] ?? "",
            ":issue_status" => $transmittal_data["issue_status"] ?? "",
            ":discipline" => $transmittal_data["discipline"] ?? "",
            ":description" => $transmittal_data["description"] ?? "",
            ":deliverable_type" => $transmittal_data["deliverable_type"] ?? "",
            ":abbreviation" => $transmittal_data["abbreviation"] ?? "",
            ":document_title" => $transmittal_data["document_title"] ?? "",
            ":revision_number" => $transmittal_data["revision_number"] ?? 1,
            ":comments" => $transmittal_data["comments"] ?? "",
            ":project_number" => $transmittal_data["project_number"] ?? "",
            ":transmittal_name" => $transmittal_data["transmittal_name"] ?? "",
            ":file_bcc_addresses" =>
                $transmittal_data["file_bcc_addresses"] ?? "",
            ":file_cc_addresses" =>
                $transmittal_data["file_cc_addresses"] ?? "",
            ":file_comments" => $transmittal_data["file_comments"] ?? "",
            ":client_document_number" =>
                $transmittal_data["client_document_number"] ?? "",
        ]);
    }
}
?>
