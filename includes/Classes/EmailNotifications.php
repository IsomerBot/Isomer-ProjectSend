<?php
/**
 * Prepare and send email notifications
 * @todo: completely remake this class. Right now It's just a cleaner port of upload-send-notifications.php
 */
namespace ProjectSend\Classes;
use \PDO;

class EmailNotifications
{
    private $notifications_sent;
    private $notifications_failed;
    private $notifications_inactive_accounts;

    private $mail_by_user;
    private $clients_data;
    private $files_data;
    private $creators;

    private $dbh;

    public function __construct()
    {
        global $dbh;
        $this->dbh = $dbh;

        $this->notifications_sent = [];
        $this->notifications_failed = [];
        $this->notifications_inactive_accounts = [];

        $this->mail_by_user = [];
        $this->clients_data = [];
        $this->files_data = [];
        $this->creators = [];
    }

    public function getNotificationsSent()
    {
        return $this->notifications_sent;
    }

    public function getNotificationsFailed()
    {
        return $this->notifications_failed;
    }

    public function getNotificationsInactiveAccounts()
    {
        return $this->notifications_inactive_accounts;
    }

    public function getPendingNotificationsFromDatabase($parameters = [])
    {
        $notifications = [
            "pending" => [],
            "to_admins" => [],
            "to_clients" => [],
        ];

        // Get notifications
        $params = [];
        $query =
            "SELECT * FROM " .
            TABLE_NOTIFICATIONS .
            " WHERE sent_status = '0' AND times_failed < :times";
        $params[":times"] = get_option("notifications_max_tries");

        // In case we manually want to send specific notifications
        if (!empty($parameters["notification_id_in"])) {
            $notification_id_in = implode(
                ",",
                array_map("intval", $parameters["notification_id_in"])
            );
            if (!empty($notification_id_in)) {
                $query .= " AND FIND_IN_SET(id, :notification_id_in)";
                $params[":notification_id_in"] = $notification_id_in;
            }
        }

        // Add the time limit
        if (get_option("notifications_max_days") != "0") {
            $query .= " AND timestamp >= DATE_SUB(NOW(), INTERVAL :days DAY)";
            $params[":days"] = get_option("notifications_max_days");
        }

        if (get_option("notifications_max_emails_at_once") != "0") {
            $query .= " LIMIT :limit";
            $params[":limit"] = get_option("notifications_max_emails_at_once");
        }

        $statement = $this->dbh->prepare($query);
        $statement->execute($params);
        $statement->setFetchMode(PDO::FETCH_ASSOC);

        while ($row = $statement->fetch()) {
            $notifications["pending"][] = [
                "id" => $row["id"],
                "client_id" => $row["client_id"],
                "file_id" => $row["file_id"],
                "timestamp" => $row["timestamp"],
                "uploader_type" =>
                    $row["upload_type"] == "0" ? "client" : "user",
            ];

            // Add the file data to the global array
            if (!array_key_exists($row["file_id"], $this->files_data)) {
                $file = new \ProjectSend\Classes\Files($row["file_id"]);

                $uploader_name = "Isomer Project Group"; // fallback
                if (!empty($file->user_id)) {
                    $transmittal_helper = new \ProjectSend\Classes\TransmittalHelper();
                    $uploader = $transmittal_helper->getUserById(
                        $file->user_id
                    );
                    if ($uploader && !empty($uploader["name"])) {
                        $uploader_name = $uploader["name"];
                    }
                }

                $this->files_data[$file->id] = [
                    "id" => $file->id,
                    "filename" => $file->filename_original,
                    "title" => $file->title,
                    "description" => $file->description,
                    // Add transmittal fields
                    "transmittal_number" => $file->transmittal_number ?? "",
                    "project_name" => $file->project_name ?? "",
                    "project_number" => $file->project_number ?? "",
                    "package_description" => $file->package_description ?? "",
                    "issue_status" => $file->issue_status ?? "",
                    "discipline" => $file->discipline ?? "",
                    "deliverable_type" => $file->deliverable_type ?? "",
                    "document_title" => $file->document_title ?? "",
                    "revision_number" => $file->revision_number ?? "",
                    "comments" => $file->comments ?? "",
                    "transmittal_name" => $file->transmittal_name ?? "",
                    "uploader_name" => $uploader_name,
                ];
            }

            // Add the client data to the global array
            if (!array_key_exists($row["client_id"], $this->clients_data)) {
                $client = get_client_by_id($row["client_id"]);

                if (!empty($client)) {
                    $this->clients_data[$row["client_id"]] = $client;
                    $this->mail_by_user[$client["username"]] = $client["email"];

                    if (
                        !array_key_exists(
                            $client["created_by"],
                            $this->creators
                        )
                    ) {
                        $user = get_user_by_username($client["created_by"]);

                        if (!empty($user)) {
                            $this->creators[$client["created_by"]] = $user;
                            $this->mail_by_user[$client["created_by"]] =
                                $user["email"];
                        }
                    }
                }
            }
        }

        // Prepare the list of clients and admins that will be notified
        if (!empty($this->clients_data)) {
            foreach ($this->clients_data as $client) {
                foreach ($notifications["pending"] as $notification) {
                    if ($notification["client_id"] == $client["id"]) {
                        $notification_data = [
                            "notification_id" => $notification["id"],
                            "file_id" => $notification["file_id"],
                        ];

                        if ($notification["uploader_type"] == "client") {
                            $notifications["to_admins"][$client["created_by"]][
                                $client["name"]
                            ][] = $notification_data;
                        } elseif ($notification["uploader_type"] == "user") {
                            if ($client["notify_upload"] == "1") {
                                if ($client["active"] == "1") {
                                    $notifications["to_clients"][
                                        $client["username"]
                                    ][] = $notification_data;
                                } else {
                                    $this->notifications_inactive_accounts[] =
                                        $notification["id"];
                                }
                            }
                        }
                    }
                }
            }
        }

        return $notifications;
    }

    public function sendNotifications()
    {
        $notifications = $this->getPendingNotificationsFromDatabase();

        if (empty($notifications["pending"])) {
            return [
                "status" => "success",
                "message" => __("No pending notifications found", "cftp_admin"),
            ];
        }

        $this->sendNotificationsToAdmins($notifications["to_admins"]);
        $this->sendNotificationsToClients($notifications["to_clients"]);

        $this->updateDatabaseNotificationsSent($this->notifications_sent);
        $this->updateDatabaseNotificationsFailed($this->notifications_failed);
        $this->updateDatabaseNotificationsInactiveAccounts(
            $this->notifications_inactive_accounts
        );
    }

    private function sendNotificationsToAdmins($notifications = [])
    {
        $system_admin_email = get_option("admin_email_address");

        if (!empty($notifications)) {
            foreach ($notifications as $mail_username => $admin_files) {
                $email_to = "";

                if (empty($mail_username)) {
                    if (!empty($system_admin_email)) {
                        $email_to = $system_admin_email;
                    }
                } else {
                    if (
                        isset($this->creators[$mail_username]) &&
                        $this->creators[$mail_username]["active"] == "1"
                    ) {
                        $email_to = $this->mail_by_user[$mail_username];
                    }
                }

                if (!empty($email_to)) {
                    $processed_notifications = [];

                    foreach ($admin_files as $client_uploader => $files) {
                        // ORIGINAL BEHAVIOR: Process each file individually for admin notifications
                        foreach ($files as $file) {
                            $files_list_html = $this->makeFilesListHtml(
                                [$file], // Pass single file array to maintain original behavior
                                $client_uploader
                            );

                            $processed_notifications[] =
                                $file["notification_id"];

                            $email = new \ProjectSend\Classes\Emails();
                            if (
                                $email->send([
                                    "type" => "new_files_by_client",
                                    "address" => $email_to,
                                    "files_list" => $files_list_html,
                                ])
                            ) {
                                $this->notifications_sent = array_merge(
                                    $this->notifications_sent,
                                    [$file["notification_id"]]
                                );
                            } else {
                                $this->notifications_failed = array_merge(
                                    $this->notifications_failed,
                                    [$file["notification_id"]]
                                );
                            }
                        }
                    }
                } else {
                    foreach ($admin_files as $mail_files) {
                        foreach ($mail_files as $mail_file) {
                            $this->notifications_inactive_accounts[] =
                                $mail_file["notification_id"];
                        }
                    }
                }
            }
        }
    }

    private function sendNotificationsToClients($notifications = [])
    {
        if (!empty($notifications)) {
            foreach ($notifications as $mail_username => $files) {
                $files_list_html = $this->makeFilesListHtml($files);
                $processed_notifications = [];

                foreach ($files as $file) {
                    $processed_notifications[] = $file["notification_id"];
                }

                $first_file_data = $this->files_data[$files[0]["file_id"]];

                $email = new \ProjectSend\Classes\Emails();
                if (
                    $email->send([
                        "type" => "new_files_by_user",
                        "address" => $this->mail_by_user[$mail_username],
                        "files_list" => $files_list_html,
                        "file_data" => $first_file_data,
                    ])
                ) {
                    $this->notifications_sent = array_merge(
                        $this->notifications_sent,
                        $processed_notifications
                    );
                } else {
                    $this->notifications_failed = array_merge(
                        $this->notifications_failed,
                        $processed_notifications
                    );
                }
            }
        }
    }

    private function makeFilesListHtml($files, $uploader_username = null)
    {
        if (empty($files)) {
            return "";
        }

        $html = "";

        // Header section similar to Isomer design
        $html .=
            '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #fff; ">';

        // Get the first file to extract transmittal information (same for all files)
        $first_file_data = $this->files_data[$files[0]["file_id"]];

        // Company header with logo placeholder and transmittal info
        $html .=
            '<div style="background: #f8f9fa; padding: 15px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">';

        // Left side - Logo with colored background
        $html .=
            '<div style="width: 120px; height: 40px; background: #ff6600; border-radius: 4px; display: flex; align-items: center; justify-content: center; padding: 5px;">';
        $html .=
            '<img src="' .
            BASE_URI .
            'assets/img/Isomer-email-logo.png" alt="Isomer Logo" style="max-height: 30px; max-width: 110px;" />';
        $html .= "</div>";

        $html .= '<div style="text-align: right; margin-left: auto;">';
        $html .=
            '<div style="font-weight: bold; font-size: 16px; margin-bottom: 2px;">TRANSMITTAL</div>';
        $html .=
            '<div style="font-weight: bold; font-size: 16px;">' .
            htmlspecialchars($first_file_data["transmittal_name"] ?? "") .
            "</div>";
        $html .= "</div>";
        $html .= "</div>"; // End header section

        // Project information section
        $html .= '<div style="padding: 15px; border-bottom: 1px solid #eee;">';

        // Two-column layout for project info
        $html .= '<div style="display: flex; justify-content: space-between;">';

        // Left column
        $html .= '<div style="flex: 1; margin-right: 30px;">';

        if (!empty($first_file_data["project_number"])) {
            $html .=
                '<div style="margin-bottom: 8px;"><strong>Project No:</strong> ' .
                htmlspecialchars($first_file_data["project_number"]) .
                "</div>";
        }

        // Add transmittal date with formatted date
        $formatted_date = date("F jS, Y"); // Format: May 9th, 2025
        $html .=
            '<div style="margin-bottom: 8px;"><strong>Transmittal Date:</strong> ' .
            $formatted_date .
            "</div>";

        // Get all recipients for this transmittal
        $recipients_text = "All Recipients";
        if (!empty($first_file_data["transmittal_number"])) {
            $transmittal_helper = new \ProjectSend\Classes\TransmittalHelper();
            $recipients = $transmittal_helper->getTransmittalRecipients(
                $first_file_data["transmittal_number"]
            );

            if (!empty($recipients)) {
                $recipient_names = [];
                foreach ($recipients as $recipient) {
                    $recipient_names[] = $recipient["name"];
                }
                $recipients_text = implode(", ", $recipient_names);
            }
        }

        $html .=
            '<div style="margin-bottom: 8px;"><strong>To:</strong> ' .
            htmlspecialchars($recipients_text) .
            "</div>";
        $html .= "</div>";

        // Right column
        $html .= '<div style="flex: 1;">';
        if (!empty($first_file_data["project_name"])) {
            $html .=
                '<div style="margin-bottom: 8px;"><strong>Project Name:</strong> ' .
                htmlspecialchars($first_file_data["project_name"]) .
                "</div>";
        }

        // Get uploader name from file data
        $from_text = !empty($first_file_data["uploader_name"])
            ? htmlspecialchars($first_file_data["uploader_name"])
            : "Isomer Project Group";
        $html .=
            '<div style="margin-bottom: 8px;"><strong>From:</strong> ' .
            $from_text .
            "</div>";
        $html .= "</div>";

        $html .= "</div>"; // End two-column layout
        $html .= "</div>"; // End project info section

        // Comments section (always show, even if empty) - now get from transmittal level
        $html .= '<div style="padding: 15px; margin-bottom: 15px;">';
        $html .=
            '<div style="font-weight: bold; margin-bottom: 5px;">Comments:</div>';
        $html .=
            '<div style="border: 1px solid #ddd; padding: 10px; min-height: 60px; background: #fafafa;">';

        // Get comments from transmittal level (should be same for all files in this transmittal)
        $transmittal_comments = $this->getTransmittalComments(
            $first_file_data["transmittal_number"]
        );
        if (!empty($transmittal_comments)) {
            // Strip HTML tags and decode entities to get plain text
            $clean_comments = html_entity_decode(
                strip_tags($transmittal_comments),
                ENT_QUOTES,
                "UTF-8"
            );
            $html .= htmlspecialchars($clean_comments);
        }
        $html .= "</div>";
        $html .= "</div>";

        // Section above the files table
        $html .= '<div style="padding: 15px;">';

        // Simple headers above the table (no boxes)
        $html .=
            '<div style="text-align: center; font-weight: bold; margin-bottom: 10px;">Isomer Transmittal Available for Download</div>';
        $html .=
            '<div style="margin-bottom: 15px;">The following deliverables have been transmitted from Isomer Project Group</div>';

        // NEW STRUCTURE: Table without Description column + Individual description boxes below each file
        // NO EXTRA CONTAINER - table should align with the padding of parent div

        // Table headers - REMOVED Description column
        $html .=
            '<table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd; font-size: 12px; margin-bottom: 0;">';
        $html .= '<tr style="background: #f8f9fa; font-weight: bold;">';
        $html .=
            '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">File Title</th>';
        $html .=
            '<th style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 12%;">Revision</th>';
        $html .=
            '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Issue Status</th>';
        $html .=
            '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Document Title</th>';
        $html .=
            '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Discipline</th>';
        $html .=
            '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Deliverable Type</th>';
        $html .= "</tr>";

        // Loop through ALL files and add a row for each + description box
        foreach ($files as $file) {
            $file_data = $this->files_data[$file["file_id"]];

            // File row in table
            $html .= "<tr>";

            // File Title
            $filename = htmlspecialchars(
                $file_data["filename"] ?? $file_data["title"]
            );
            $html .=
                '<td style="border: 1px solid #ddd; padding: 8px; word-wrap: break-word;">' .
                $filename .
                "</td>";

            // Revision Number
            $html .=
                '<td style="border: 1px solid #ddd; padding: 8px; text-align: center;">' .
                htmlspecialchars($file_data["revision_number"] ?? "") .
                "</td>";

            // Issue Status
            $html .=
                '<td style="border: 1px solid #ddd; padding: 8px;">' .
                "Issued For: " .
                htmlspecialchars($file_data["issue_status"] ?? "") .
                "</td>";

            // Document Title
            $doc_title = htmlspecialchars($file_data["document_title"] ?? "");
            $html .=
                '<td style="border: 1px solid #ddd; padding: 8px; word-wrap: break-word;">' .
                $doc_title .
                "</td>";

            // Discipline
            $html .=
                '<td style="border: 1px solid #ddd; padding: 8px;">' .
                htmlspecialchars($file_data["discipline"] ?? "") .
                "</td>";

            // Deliverable Type
            $deliverable_type = html_entity_decode(
                htmlspecialchars($file_data["deliverable_type"] ?? ""),
                ENT_QUOTES,
                "UTF-8"
            );
            $html .=
                '<td style="border: 1px solid #ddd; padding: 8px;">' .
                $deliverable_type .
                "</td>";

            $html .= "</tr>";
            // NEW: Individual Description Box below each file row
            $html .= "<tr>";
            $html .=
                '<td colspan="6" style="border-left: 1px solid #ddd; border-right: 1px solid #ddd; border-bottom: 1px solid #ddd; padding: 0;">';

            // Description container - ALL content in gray box
            $html .=
                '<div style="padding: 8px; background: #f9f9f9; border-top: 1px solid #eee;">';

            // Description content - label and text together in gray container
            $description = $file_data["description"] ?? "";
            if (!empty($description)) {
                // Strip HTML tags and decode entities to get plain text
                $description = html_entity_decode(
                    strip_tags($description),
                    ENT_QUOTES,
                    "UTF-8"
                );
                $description = htmlspecialchars($description);
            } else {
                $description = ""; // Empty if no description
            }

            $html .=
                '<span style="font-weight: bold; font-size: 11px;">File Description:</span>';
            if (!empty($description)) {
                $html .=
                    ' <span style="font-size: 11px;">' .
                    $description .
                    "</span>";
            }

            $html .= "</div>"; // End description container
            $html .= "</td>";
            $html .= "</tr>";
        }

        $html .= "</table>";
        // End of files table section

        // Access link section
        $html .=
            '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">';
        $html .=
            "<div>To access the files pertinent to this transmittal,</div>";
        $html .=
            '<div><a href="%URI%" style="color: #0066cc; text-decoration: underline;">please login here</a></div>';
        $html .= "</div>";

        $html .= "</div>"; // End inner container
        $html .= "</div>"; // End main container

        return $html;
    }

    private function getTransmittalComments($transmittal_number)
    {
        if (empty($transmittal_number)) {
            return "";
        }

        // Get comments from the most recent file with this transmittal number that has comments
        $statement = $this->dbh->prepare(
            "SELECT comments FROM " .
                TABLE_FILES .
                " WHERE transmittal_number = :transmittal_number 
                  AND comments IS NOT NULL 
                  AND comments != ''
                  ORDER BY id DESC 
                  LIMIT 1"
        );
        $statement->bindParam(":transmittal_number", $transmittal_number);
        $statement->execute();

        if ($statement->rowCount() > 0) {
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            return $row["comments"] ?? "";
        }

        return "";
    }

    private function updateDatabaseNotificationsSent($notifications = [])
    {
        if (!empty($notifications) && count($notifications) > 0) {
            $notifications = implode(",", array_unique($notifications));
            $statement = $this->dbh->prepare(
                "UPDATE " .
                    TABLE_NOTIFICATIONS .
                    " SET sent_status = '1' WHERE FIND_IN_SET(id, :sent)"
            );
            $statement->bindParam(":sent", $notifications);
            $statement->execute();
        }
    }

    private function updateDatabaseNotificationsFailed($notifications = [])
    {
        if (!empty($notifications) && count($notifications) > 0) {
            $notifications = implode(",", array_unique($notifications));
            $statement = $this->dbh->prepare(
                "UPDATE " .
                    TABLE_NOTIFICATIONS .
                    " SET sent_status = '0', times_failed = times_failed + 1 WHERE FIND_IN_SET(id, :failed)"
            );
            $statement->bindParam(":failed", $notifications);
            $statement->execute();
        }
    }

    private function updateDatabaseNotificationsInactiveAccounts($notifications)
    {
        if (!empty($notifications) && count($notifications) > 0) {
            $notifications = implode(",", array_unique($notifications));
            $statement = $this->dbh->prepare(
                "UPDATE " .
                    TABLE_NOTIFICATIONS .
                    " SET sent_status = '3' WHERE FIND_IN_SET(id, :inactive)"
            );
            $statement->bindParam(":inactive", $notifications);
            $statement->execute();
        }
    }
}
?>
