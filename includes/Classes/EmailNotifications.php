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
                    "document_description" => $file->document_description ?? "",
                    "revision_number" => $file->revision_number ?? "",
                    "comments" => $file->comments ?? "",
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
                        $files_list_html = $this->makeFilesListHtml(
                            $files,
                            $client_uploader
                        );

                        foreach ($files as $file) {
                            $processed_notifications[] =
                                $file["notification_id"];
                        }

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
                                $processed_notifications
                            );
                        } else {
                            $this->notifications_failed = array_merge(
                                $this->notifications_failed,
                                $processed_notifications
                            );
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

                $email = new \ProjectSend\Classes\Emails();
                if (
                    $email->send([
                        "type" => "new_files_by_user",
                        "address" => $this->mail_by_user[$mail_username],
                        "files_list" => $files_list_html,
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
        $html = "";

        // Header section similar to Isomer design
        $html .=
            '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #fff; border: 1px solid #ddd;">';

        foreach ($files as $file) {
            $file_data = $this->files_data[$file["file_id"]];

            // Company header with logo placeholder and transmittal info
            $html .=
                '<div style="background: #f8f9fa; padding: 15px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">';

            // Left side - Logo placeholder
            $html .=
                '<div style="width: 40px; height: 40px; background: #ff6600; border-radius: 4px; display: flex; align-items: center; justify-content: center;">';
            $html .=
                '<div style="width: 20px; height: 20px; background: #fff; border-radius: 2px;"></div>';
            $html .= "</div>";

            // Right side - Transmittal header
            $html .=
                '<div style="font-weight: bold; font-size: 16px;">TRANSMITTAL ' .
                htmlspecialchars(
                    $file_data["transmittal_number"] ?? "AAANNNN-T-XXX"
                ) .
                "</div>";

            $html .= "</div>";

            // Project information section
            $html .=
                '<div style="padding: 15px; border-bottom: 1px solid #eee;">';

            // Two-column layout for project info
            $html .=
                '<div style="display: flex; justify-content: space-between;">';

            // Left column
            $html .= '<div style="flex: 1; margin-right: 30px;">';

            if (!empty($file_data["project_name"])) {
                $html .=
                    '<div style="margin-bottom: 8px;"><strong>Project Name:</strong> ' .
                    htmlspecialchars($file_data["project_name"]) .
                    "</div>";
            }

            // Add transmittal date (you may need to get this from notification timestamp)
            $html .=
                '<div style="margin-bottom: 8px;"><strong>Transmittal Date:</strong> ' .
                date("Y-m-d") .
                "</div>";

            $html .=
                '<div style="margin-bottom: 8px;"><strong>To:</strong> All Recipients List Here -- NO BC or CC</div>';

            $html .= "</div>";

            // Right column
            $html .= '<div style="flex: 1;">';

            if (!empty($file_data["project_number"])) {
                $html .=
                    '<div style="margin-bottom: 8px;"><strong>Project No:</strong> ' .
                    htmlspecialchars($file_data["project_number"]) .
                    "</div>";
            }

            // Add uploader info as "From" - FIXED: using $uploader_username instead of $file_data["uploader"]
            if (!empty($uploader_username)) {
                $html .=
                    '<div style="margin-bottom: 8px;"><strong>From:</strong> ' .
                    htmlspecialchars($uploader_username) .
                    "</div>";
            }

            $html .= "</div>";

            $html .= "</div>"; // End two-column layout
            $html .= "</div>"; // End project info section

            // Description section
            if (!empty($file_data["description"])) {
                $html .= '<div style="padding: 15px; margin-bottom: 15px;">';
                $html .=
                    '<div style="font-weight: bold; margin-bottom: 5px;">Description:</div>';
                if (strpos($file_data["description"], "<p>") !== false) {
                    $html .=
                        '<div style="color: #666;">' .
                        $file_data["description"] .
                        "</div>";
                } else {
                    $html .=
                        '<div style="color: #666;">' .
                        htmlspecialchars($file_data["description"]) .
                        "</div>";
                }
                $html .= "</div>";
            }

            // FIXED: Added proper container and styling for the download section
            $html .= '<div style="padding: 15px;">';

            // Table header for deliverables (matching Isomer design)
            $html .=
                '<div style="background: #f8f9fa; padding: 10px; border: 1px solid #ddd; margin-bottom: 0; text-align: center; font-weight: bold;">Isomer Transmittal Available for Download</div>';

            $html .=
                '<div style="margin-bottom: 15px; padding: 10px; background: #fff; border: 1px solid #ddd; border-top: none;">The following deliverables have been transmitted from Isomer Project Group</div>';

            // Table-like structure for file details
            $html .=
                '<table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd; margin-bottom: 15px;">';

            // Table headers
            $html .=
                '<tr style="background: #f8f9fa; font-weight: bold; font-size: 12px;">';
            $html .=
                '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">File Title</th>';
            $html .=
                '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Revision No.</th>';
            $html .=
                '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Issue Status</th>';
            $html .=
                '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Document Title</th>';
            $html .=
                '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Discipline</th>';
            $html .=
                '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Deliverable Type</th>';
            $html .= "</tr>";

            // Table data row
            $html .= '<tr style="font-size: 12px;">';
            $html .=
                '<td style="border: 1px solid #ddd; padding: 8px;">' .
                htmlspecialchars(
                    $file_data["filename"] ?? $file_data["title"]
                ) .
                "</td>";
            $html .=
                '<td style="border: 1px solid #ddd; padding: 8px;">' .
                htmlspecialchars($file_data["revision_number"] ?? "") .
                "</td>";
            $html .=
                '<td style="border: 1px solid #ddd; padding: 8px;">' .
                htmlspecialchars($file_data["issue_status"] ?? "") .
                "</td>";
            $html .=
                '<td style="border: 1px solid #ddd; padding: 8px;">' .
                htmlspecialchars($file_data["document_title"] ?? "") .
                "</td>";
            $html .=
                '<td style="border: 1px solid #ddd; padding: 8px;">' .
                htmlspecialchars($file_data["discipline"] ?? "") .
                "</td>";
            $html .=
                '<td style="border: 1px solid #ddd; padding: 8px;">' .
                htmlspecialchars($file_data["deliverable_type"] ?? "") .
                "</td>";
            $html .= "</tr>";

            $html .= "</table>";

            // Comments section (if any)
            if (!empty($file_data["comments"])) {
                $html .= '<div style="margin-bottom: 15px;">';
                $html .=
                    '<div style="font-weight: bold; margin-bottom: 5px;">Comments:</div>';
                $html .=
                    '<div style="border: 1px solid #ddd; padding: 10px; min-height: 60px; background: #fafafa;">';

                if (strpos($file_data["comments"], "<p>") !== false) {
                    $html .= $file_data["comments"];
                } else {
                    $html .= htmlspecialchars($file_data["comments"]);
                }

                $html .= "</div>";
                $html .= "</div>";
            }

            // Access link section
            $html .=
                '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">';
            $html .=
                "<div>To access the files pertinent to this transmittal,</div>";
            $html .=
                '<div><a href="#" style="color: #0066cc; text-decoration: underline;">please login here</a></div>';
            $html .= "</div>";

            $html .= "</div>"; // End inner container
            $html .= "</div>"; // End file container
        }

        $html .= "</div>"; // End main container

        return $html;
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
