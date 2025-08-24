<?php
/**
 * Transmittal Summary Manager
 * Handles CRUD operations for the centralized tbl_transmittal_summary table
 */
namespace ProjectSend\Classes;
use \PDO;

class TransmittalSummaryManager
{
    private $dbh;

    public function __construct()
    {
        global $dbh;
        $this->dbh = $dbh;
    }

    /**
     * Create or update transmittal summary data
     */
    public function createOrUpdate($transmittal_number, $data)
    {
        try {
            // Check if transmittal already exists
            $existing = $this->getTransmittalData($transmittal_number);

            if ($existing) {
                return $this->updateTransmittal($transmittal_number, $data);
            } else {
                return $this->createTransmittal($transmittal_number, $data);
            }
        } catch (Exception $e) {
            error_log("TransmittalSummaryManager Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create new transmittal summary record
     */
    private function createTransmittal($transmittal_number, $data)
    {
        $query = "INSERT INTO tbl_transmittal_summary 
                  (transmittal_number, project_number, group_id, discipline_id, 
                   deliverable_type_id, uploader_user_id, project_name, 
                   package_description, comments, cc_addresses, bcc_addresses, file_count)
                  VALUES 
                  (:transmittal_number, :project_number, :group_id, :discipline_id,
                   :deliverable_type_id, :uploader_user_id, :project_name,
                   :package_description, :comments, :cc_addresses, :bcc_addresses, :file_count)";

        $statement = $this->dbh->prepare($query);
        return $statement->execute([
            ":transmittal_number" => $transmittal_number,
            ":project_number" => $data["project_number"] ?? null,
            ":group_id" => $data["group_id"] ?? null,
            ":discipline_id" => $data["discipline_id"] ?? null,
            ":deliverable_type_id" => $data["deliverable_type_id"] ?? null,
            ":uploader_user_id" => $data["uploader_user_id"] ?? null,
            ":project_name" => $data["project_name"] ?? null,
            ":package_description" => $data["package_description"] ?? null,
            ":comments" => $data["comments"] ?? null,
            ":cc_addresses" => $data["cc_addresses"] ?? null,
            ":bcc_addresses" => $data["bcc_addresses"] ?? null,
            ":file_count" => $data["file_count"] ?? 0,
        ]);
    }

    /**
     * Update existing transmittal summary record
     */
    private function updateTransmittal($transmittal_number, $data)
    {
        $query = "UPDATE tbl_transmittal_summary SET
                  project_number = :project_number,
                  group_id = :group_id,
                  discipline_id = :discipline_id,
                  deliverable_type_id = :deliverable_type_id,
                  uploader_user_id = :uploader_user_id,
                  project_name = :project_name,
                  package_description = :package_description,
                  comments = :comments,
                  cc_addresses = :cc_addresses,
                  bcc_addresses = :bcc_addresses,
                  file_count = :file_count,
                  updated_at = CURRENT_TIMESTAMP
                  WHERE transmittal_number = :transmittal_number";

        $statement = $this->dbh->prepare($query);
        return $statement->execute([
            ":transmittal_number" => $transmittal_number,
            ":project_number" => $data["project_number"] ?? null,
            ":group_id" => $data["group_id"] ?? null,
            ":discipline_id" => $data["discipline_id"] ?? null,
            ":deliverable_type_id" => $data["deliverable_type_id"] ?? null,
            ":uploader_user_id" => $data["uploader_user_id"] ?? null,
            ":project_name" => $data["project_name"] ?? null,
            ":package_description" => $data["package_description"] ?? null,
            ":comments" => $data["comments"] ?? null,
            ":cc_addresses" => $data["cc_addresses"] ?? null,
            ":bcc_addresses" => $data["bcc_addresses"] ?? null,
            ":file_count" => $data["file_count"] ?? 0,
        ]);
    }

    /**
     * Get transmittal data by transmittal number
     */
    public function getTransmittalData($transmittal_number)
    {
        $query = "SELECT ts.*, 
                         g.name as group_name, g.description as group_description,
                         d.discipline_name, d.abbreviation as discipline_abbr,
                         dt.deliverable_type, dt.abbreviation as deliverable_abbr,
                         u.name as uploader_name, u.email as uploader_email
                  FROM tbl_transmittal_summary ts
                  LEFT JOIN tbl_groups g ON ts.group_id = g.id
                  LEFT JOIN tbl_discipline d ON ts.discipline_id = d.id
                  LEFT JOIN tbl_deliverable_type dt ON ts.deliverable_type_id = dt.id
                  LEFT JOIN tbl_users u ON ts.uploader_user_id = u.id
                  WHERE ts.transmittal_number = :transmittal_number";

        $statement = $this->dbh->prepare($query);
        $statement->execute([":transmittal_number" => $transmittal_number]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Populate transmittal data from filename parsing
     */
    public function populateFromFilename($filename, $uploader_user_id = null)
    {
        $parsed = $this->parseFilename($filename);

        if (!$parsed || !$parsed["project_number"]) {
            return false;
        }

        // Get group_id from project_number
        $group_id = $this->getGroupIdByProjectNumber($parsed["project_number"]);

        // Get discipline_id from abbreviation
        $discipline_id = $this->getDisciplineIdByAbbreviation(
            $parsed["discipline_abbr"]
        );

        // Get deliverable_type_id from abbreviation and discipline
        $deliverable_type_id = $this->getDeliverableTypeId(
            $parsed["deliverable_abbr"],
            $discipline_id
        );

        // Get project name from group
        $project_name = $this->getProjectNameByGroupId($group_id);

        return [
            "project_number" => $parsed["project_number"],
            "group_id" => $group_id,
            "discipline_id" => $discipline_id,
            "deliverable_type_id" => $deliverable_type_id,
            "uploader_user_id" => $uploader_user_id,
            "project_name" => $project_name,
            "file_count" => 1, // Starting with first file
        ];
    }

    /**
     * Parse filename following AAA####-AA-AAA-#### format
     */
    private function parseFilename($filename)
    {
        // Pattern: AAA####-AA-AAA-####
        // Example: TVT2501-PI-CAL-0001
        if (
            preg_match(
                "/^([A-Z]{3}\d{4})-([A-Z]{2})-([A-Z]{3})-(\d{4})/",
                $filename,
                $matches
            )
        ) {
            return [
                "project_number" => $matches[1], // TVT2501
                "discipline_abbr" => $matches[2], // PI
                "deliverable_abbr" => $matches[3], // CAL
                "sequence_number" => $matches[4], // 0001
            ];
        }

        return false;
    }

    /**
     * Get group ID by project number (groups.name = project_number)
     */
    private function getGroupIdByProjectNumber($project_number)
    {
        $query =
            "SELECT id FROM tbl_groups WHERE name = :project_number LIMIT 1";
        $statement = $this->dbh->prepare($query);
        $statement->execute([":project_number" => $project_number]);

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result ? $result["id"] : null;
    }

    /**
     * Get discipline ID by abbreviation
     */
    private function getDisciplineIdByAbbreviation($abbreviation)
    {
        $query =
            "SELECT id FROM tbl_discipline WHERE abbreviation = :abbreviation AND active = 1 LIMIT 1";
        $statement = $this->dbh->prepare($query);
        $statement->execute([":abbreviation" => $abbreviation]);

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result ? $result["id"] : null;
    }

    /**
     * Get deliverable type ID by abbreviation and discipline
     */
    private function getDeliverableTypeId($abbreviation, $discipline_id)
    {
        if (!$discipline_id) {
            return null;
        }

        $query = "SELECT id FROM tbl_deliverable_type 
                  WHERE abbreviation = :abbreviation 
                  AND discipline_id = :discipline_id 
                  AND active = 1 LIMIT 1";

        $statement = $this->dbh->prepare($query);
        $statement->execute([
            ":abbreviation" => $abbreviation,
            ":discipline_id" => $discipline_id,
        ]);

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result ? $result["id"] : null;
    }

    /**
     * Get project name from group description
     */
    private function getProjectNameByGroupId($group_id)
    {
        if (!$group_id) {
            return null;
        }

        $query =
            "SELECT description FROM tbl_groups WHERE id = :group_id LIMIT 1";
        $statement = $this->dbh->prepare($query);
        $statement->execute([":group_id" => $group_id]);

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        if ($result && $result["description"]) {
            // Decode HTML entities and strip HTML tags
            $description = html_entity_decode(
                $result["description"],
                ENT_QUOTES,
                "UTF-8"
            );
            $description = strip_tags($description);
            return trim($description);
        }
        return null;
    }

    /**
     * Update file count for a transmittal
     */
    public function syncFileCount($transmittal_number)
    {
        $query = "UPDATE tbl_transmittal_summary 
                  SET file_count = (
                      SELECT COUNT(*) FROM tbl_files 
                      WHERE transmittal_number = :transmittal_number
                  )
                  WHERE transmittal_number = :transmittal_number2";

        $statement = $this->dbh->prepare($query);
        return $statement->execute([
            ":transmittal_number" => $transmittal_number,
            ":transmittal_number2" => $transmittal_number,
        ]);
    }

    /**
     * Get all transmittals for a client (for filtering)
     */
    public function getClientTransmittals($client_id)
    {
        $query = "SELECT DISTINCT ts.transmittal_number, ts.project_number, 
                         ts.project_name, ts.file_count,
                         d.discipline_name, dt.deliverable_type
                  FROM tbl_transmittal_summary ts
                  JOIN tbl_files f ON ts.transmittal_number = f.transmittal_number
                  JOIN tbl_files_relations fr ON f.id = fr.file_id
                  LEFT JOIN tbl_discipline d ON ts.discipline_id = d.id
                  LEFT JOIN tbl_deliverable_type dt ON ts.deliverable_type_id = dt.id
                  WHERE fr.client_id = :client_id AND fr.hidden = '0'
                  ORDER BY ts.project_number DESC, ts.transmittal_number DESC";

        $statement = $this->dbh->prepare($query);
        $statement->execute([":client_id" => $client_id]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generate next transmittal number for a project
     */
    public function generateTransmittalNumber($project_number)
    {
        $query = "SELECT MAX(CAST(SUBSTRING(transmittal_number, -4) AS UNSIGNED)) as max_num
                  FROM tbl_transmittal_summary 
                  WHERE project_number = :project_number";

        $statement = $this->dbh->prepare($query);
        $statement->execute([":project_number" => $project_number]);

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        $next_number = ($result["max_num"] ?? 0) + 1;

        return sprintf("%s-T-%04d", $project_number, $next_number);
    }
}
?>
