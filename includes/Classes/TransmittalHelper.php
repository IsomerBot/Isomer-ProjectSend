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
     * Get all recipients for a transmittal (clients assigned to files)
     * @param string $transmittal_number - The transmittal number
     * @return array - Array of recipient data
     */
    public function getTransmittalRecipients($transmittal_number)
    {
        $query = "SELECT DISTINCT u.name, u.email, u.user as username
                  FROM tbl_files f
                  JOIN tbl_files_relations fr ON f.id = fr.file_id  
                  JOIN tbl_users u ON fr.client_id = u.id
                  WHERE f.transmittal_number = :transmittal_number 
                  AND u.active = '1'
                  AND u.level = '0'";

        $statement = $this->dbh->prepare($query);
        $statement->execute([":transmittal_number" => $transmittal_number]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
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

                    deliverable_type = :deliverable_type,
                    abbreviation = :abbreviation,
                    document_title = :document_title,
                    revision_number = :revision_number,
                    comments = :comments,
                    project_number = :project_number,
                    transmittal_name = :transmittal_name
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

            ":deliverable_type" => $transmittal_data["deliverable_type"] ?? "",
            ":abbreviation" => $transmittal_data["abbreviation"] ?? "",
            ":document_title" => $transmittal_data["document_title"] ?? "",
            ":revision_number" => $transmittal_data["revision_number"] ?? 1,
            ":comments" => $transmittal_data["comments"] ?? "",
            ":project_number" => $transmittal_data["project_number"] ?? "",
            ":transmittal_name" => $transmittal_data["transmittal_name"] ?? "",
        ]);
    }
}
