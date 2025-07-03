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

        // BRAND-COMPLIANT EMAIL STYLES - Updated to match Isomer guidelines
        $html .= '<style>
           /* Import Isomer Brand Fonts - Made Tommy and Metropolis */
           @import url("https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;800&display=swap");
           
           /* Isomer Brand Typography Styles - Updated to match brand guide */
           .isomer-h1 {
               font-family: "Montserrat", "Made Tommy", Arial, sans-serif;
               font-weight: 700;
               font-size: 40px;
               text-transform: uppercase;
               letter-spacing: 10px;
               color: #252c3a;
               margin: 0;
           }
           
           .isomer-h2 {
               font-family: "Montserrat", "Made Tommy", Arial, sans-serif;
               font-weight: 300;
               font-size: 30px;
               text-transform: uppercase;
               color: #252c3a;
               margin: 0;
           }
           
           .isomer-h3 {
               font-family: "Montserrat", "Metropolis", Arial, sans-serif;
               font-weight: 800;
               font-size: 14px;
               text-transform: uppercase;
               letter-spacing: 25px;
               color: #252c3a;
               margin: 0;
           }
           
           .isomer-body {
               font-family: "Montserrat", "Metropolis", Arial, sans-serif;
               font-weight: 400;
               font-size: 12px;
               color: #252c3a;
               line-height: 1.4;
           }
           
           /* Field labels - using brand typography */
           .field-label {
               font-family: "Montserrat", "Metropolis", Arial, sans-serif;
               font-weight: 800;
               font-size: 14px;
               text-transform: uppercase;
               letter-spacing: 2px;
               color: #252c3a;
           }
           
           /* Email-specific scaling for readability */
           .email-h1 {
               font-family: "Montserrat", "Made Tommy", Arial, sans-serif;
               font-weight: 700;
               font-size: 20px;
               text-transform: uppercase;
               letter-spacing: 2px;
               color: #252c3a;
               margin: 0;
           }
           
           .email-h2 {
               font-family: "Montserrat", "Made Tommy", Arial, sans-serif;
               font-weight: 300;
               font-size: 16px;
               text-transform: uppercase;
               color: #252c3a;
               margin: 0;
           }
           
           .email-h3 {
               font-family: "Montserrat", "Metropolis", Arial, sans-serif;
               font-weight: 800;
               font-size: 12px;
               text-transform: uppercase;
               letter-spacing: 2px;
               color: #252c3a;
               margin: 0;
           }
       </style>';

        // Header section with brand-compliant colors and spacing
        $html .=
            '<div style="font-family: Montserrat, Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #fff;">';

        // Get the first file to extract transmittal information
        $first_file_data = $this->files_data[$files[0]["file_id"]];

        // BRAND-COMPLIANT HEADER - Using exact brand colors
        $html .=
            '<div style="background: #252c3a; padding: 15px; display: flex; justify-content: space-between; align-items: center;">';

        // Left side - Logo with proper clear space (0.5X minimum)
        $html .=
            '<div style="margin-right: 20px; display: flex; align-items: center;">';

        // Check if logo file exists and get proper URL
        $logo_file_info = $this->getLogoFileInfo();

        if ($logo_file_info["exists"] === true) {
            // Logo with proper clear space - minimum 0.5X as per brand guide
            $html .=
                '<div style="padding: 8px; background-color: white; display: flex; align-items: center; justify-content: center;">';

            if ($logo_file_info["method"] === "svg_inline") {
                $svg_content = $logo_file_info["svg_content"];
                // Ensure minimum 5mm (approximately 50px) as per brand guide
                $svg_content = str_replace(
                    "<svg",
                    '<svg style="height: 50px; width: auto;"',
                    $svg_content
                );
                $html .= $svg_content;
            } else {
                // Minimum 5mm size requirement
                $html .=
                    '<img src="' .
                    $logo_file_info["url"] .
                    '" alt="Isomer Project Group" style="height: 50px; width: auto; max-width: 200px;" />';
            }

            $html .= "</div>";
        } else {
            // Fallback: Brand-compliant text using exact brand colors
            $html .=
                '<div style="padding: 8px; display: flex; align-items: center; justify-content: center;">';
            $html .= '<div style="color: white; text-align: left;">';
            $html .=
                '<div class="email-h1" style="color: white; margin-bottom: 2px;">ISOMER</div>';
            $html .=
                '<div class="email-h2" style="color: #f56600; font-size: 14px;">PROJECT GROUP</div>';
            $html .= "</div>";
            $html .= "</div>";
        }

        $html .= "</div>";

        // Right side - BIGGER, BOLDER "TRANSMITTAL"
        $html .= '<div style="text-align: right; margin-left: auto;">';
        $html .=
            '<div style="color: white; font-family: Montserrat, Arial, sans-serif; font-weight: 800; font-size: 24px; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 4px;">TRANSMITTAL</div>';
        $html .=
            '<div class="email-h1" style="color: #f56600; font-weight: 800;">' .
            htmlspecialchars($first_file_data["transmittal_name"] ?? "") .
            "</div>";
        $html .= "</div>";
        $html .= "</div>"; // End header section

        // Project information section - UPDATED: Bold labels, more spacing between columns
        $html .=
            '<div style="padding: 20px; border-bottom: 1px solid #c5e0ea; background: #fff;">';

        // Two-column layout with MORE SPACING
        $html .=
            '<div style="display: flex; justify-content: space-between; gap: 50px;">';

        // Left column with BOLDER labels
        $html .= '<div style="flex: 1;">';

        if (!empty($first_file_data["project_number"])) {
            $html .=
                '<div class="isomer-body" style="margin-bottom: 20px;"><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #252c3a;">PROJECT NO:</span><br>' .
                htmlspecialchars($first_file_data["project_number"]) .
                "</div>";
        }

        // Add transmittal date
        $formatted_date = date("F jS, Y");
        $html .=
            '<div class="isomer-body" style="margin-bottom: 20px;"><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #252c3a;">TRANSMITTAL DATE:</span><br>' .
            $formatted_date .
            "</div>";

        // Get recipients
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
            '<div class="isomer-body" style="margin-bottom: 20px;"><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #252c3a;">TO:</span><br>' .
            htmlspecialchars($recipients_text) .
            "</div>";
        $html .= "</div>";

        // Right column with BOLDER labels
        $html .= '<div style="flex: 1;">';
        if (!empty($first_file_data["project_name"])) {
            $html .=
                '<div class="isomer-body" style="margin-bottom: 20px;"><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #252c3a;">PROJECT NAME:</span><br>' .
                htmlspecialchars($first_file_data["project_name"]) .
                "</div>";
        }

        $from_text = !empty($first_file_data["uploader_name"])
            ? htmlspecialchars($first_file_data["uploader_name"])
            : "Isomer Project Group";
        $html .=
            '<div class="isomer-body" style="margin-bottom: 20px;"><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #252c3a;">FROM:</span><br>' .
            $from_text .
            "</div>";
        $html .= "</div>";

        $html .= "</div>"; // End two-column layout
        $html .= "</div>"; // End project info section

        // COMMENTS SECTION - Same boldness as project labels
        $html .=
            '<div style="padding: 20px; margin-bottom: 0; background: #fff;">';
        $html .=
            '<div style="font-family: Montserrat, Arial, sans-serif; font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #252c3a; margin-bottom: 8px;">COMMENTS:</div>';
        $html .=
            '<div style="border: 1px solid #c5e0ea; padding: 15px; min-height: 60px; background: #fafafa;">';

        $transmittal_comments = $this->getTransmittalComments(
            $first_file_data["transmittal_number"]
        );
        if (!empty($transmittal_comments)) {
            $clean_comments = html_entity_decode(
                strip_tags($transmittal_comments),
                ENT_QUOTES,
                "UTF-8"
            );
            $html .=
                '<span class="isomer-body">' .
                htmlspecialchars($clean_comments) .
                "</span>";
        }
        $html .= "</div>";
        $html .= "</div>";

        // FILES SECTION - Same boldness for main header
        $html .= '<div style="padding: 20px; background: #fff;">';
        $html .=
            '<div style="font-family: Montserrat, Arial, sans-serif; font-weight: 800; font-size: 16px; text-transform: uppercase; letter-spacing: 2px; color: #252c3a; text-align: center; margin-bottom: 15px;">ISOMER TRANSMITTAL AVAILABLE FOR DOWNLOAD</div>';
        $html .=
            '<div class="isomer-body" style="margin-bottom: 20px; text-align: center;">The following deliverables have been transmitted from Isomer Project Group</div>';

        // FILES TABLE - Blue header background with white font
        $html .=
            '<table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd; font-size: 12px; margin-bottom: 0;">';
        $html .=
            '<tr style="background: #252c3a; font-weight: bold; color: white;">';
        $html .=
            '<th style="border: 1px solid #ddd; padding: 8px; text-align: left; color: white;">File</th>';
        $html .=
            '<th style="border: 1px solid #ddd; padding: 8px; text-align: left; color: white;">Rev.</th>';
        $html .=
            '<th style="border: 1px solid #ddd; padding: 8px; text-align: left; color: white;">Issue Status</th>';
        $html .=
            '<th style="border: 1px solid #ddd; padding: 8px; text-align: left; color: white;">Document</th>';
        $html .=
            '<th style="border: 1px solid #ddd; padding: 8px; text-align: left; color: white;">Discipline</th>';
        $html .=
            '<th style="border: 1px solid #ddd; padding: 8px; text-align: left; color: white;">Deliverable</th>';
        $html .= "</tr>";

        // CRITICAL FIX: Loop through ALL files and add a row for each
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
                '<td style="border: 1px solid #ddd; padding: 8px;">Issued For: ' .
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

            // Description row for each file
            $html .= "<tr>";
            $html .=
                '<td colspan="6" style="border-left: 1px solid #ddd; border-right: 1px solid #ddd; border-bottom: 1px solid #ddd; padding: 0;">';

            // Description container
            $html .=
                '<div style="padding: 8px; background: #f9f9f9; border-top: 1px solid #eee;">';

            $description = $file_data["description"] ?? "";
            if (!empty($description)) {
                $description = html_entity_decode(
                    strip_tags($description),
                    ENT_QUOTES,
                    "UTF-8"
                );
                $description = htmlspecialchars($description);
            } else {
                $description = "";
            }

            $html .=
                '<span style="font-weight: bold; font-size: 11px;">File Description:</span>';
            if (!empty($description)) {
                $html .=
                    ' <span style="font-size: 11px;">' .
                    $description .
                    "</span>";
            }

            $html .= "</div>";
            $html .= "</td>";
            $html .= "</tr>";
        }

        $html .= "</table>";

        // FIXED LOGIN LINK SECTION - This was missing the %URI% replacement
        $html .=
            '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">';
        $html .=
            "<div>To access the files pertinent to this transmittal,</div>";
        $html .=
            '<div><a href="%URI%" style="color: #0066cc; text-decoration: underline;">please login here</a></div>';
        $html .= "</div>";

        // END OF TRANSMITTAL
        $html .=
            '<div style="text-align: center; margin-top: 20px; padding: 15px; border-top: 2px solid #ddd; font-weight: bold;">';
        $html .= "************END OF TRANSMITTAL************";
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

    /**
     * REPLACE YOUR getLogoFileInfo() METHOD WITH THIS VERSION (handles large files better)
     */
    private function getLogoFileInfo()
    {
        try {
            // Use ProjectSend's actual function name
            if (function_exists("generate_logo_url")) {
                $logo_file_info = generate_logo_url();

                if (
                    !empty($logo_file_info) &&
                    isset($logo_file_info["exists"]) &&
                    $logo_file_info["exists"] === true
                ) {
                    // Check if it's a local file that we can embed
                    if (
                        isset($logo_file_info["dir"]) &&
                        file_exists($logo_file_info["dir"])
                    ) {
                        // Check file size - if too large, use URL instead of embedding
                        $file_size = filesize($logo_file_info["dir"]);

                        // INCREASED LIMIT: Try to embed files up to 500KB
                        // If larger than 500KB, use absolute URL instead
                        if ($file_size <= 512000) {
                            // 500KB limit

                            // Get file extension
                            $file_extension = strtolower(
                                pathinfo(
                                    $logo_file_info["dir"],
                                    PATHINFO_EXTENSION
                                )
                            );

                            // Handle SVG files differently
                            if ($file_extension === "svg") {
                                // For SVG, we can inline it directly in HTML
                                $svg_content = file_get_contents(
                                    $logo_file_info["dir"]
                                );
                                return [
                                    "exists" => true,
                                    "url" => "",
                                    "svg_content" => $svg_content,
                                    "method" => "svg_inline",
                                    "path" => $logo_file_info["dir"],
                                ];
                            } else {
                                // For other image types, convert to base64
                                $image_data = file_get_contents(
                                    $logo_file_info["dir"]
                                );
                                if ($image_data !== false) {
                                    $base64 = base64_encode($image_data);

                                    // Get mime type properly
                                    $mime_types = [
                                        "jpg" => "image/jpeg",
                                        "jpeg" => "image/jpeg",
                                        "png" => "image/png",
                                        "gif" => "image/gif",
                                    ];
                                    $mime_type =
                                        $mime_types[$file_extension] ??
                                        "image/jpeg";

                                    $data_url = "data:$mime_type;base64,$base64";

                                    return [
                                        "exists" => true,
                                        "url" => $data_url,
                                        "path" => $logo_file_info["dir"],
                                        "method" => "base64_embedded",
                                    ];
                                }
                            }
                        }

                        // If file is too large for embedding OR base64 failed, use absolute URL
                        $absolute_url = $this->makeAbsoluteUrl(
                            $logo_file_info["url"]
                        );

                        return [
                            "exists" => true,
                            "url" => $absolute_url,
                            "path" => $logo_file_info["dir"],
                            "method" => "absolute_url_large_file",
                            "file_size" => $file_size,
                        ];
                    } else {
                        // If file doesn't exist locally, try to use the URL directly
                        $absolute_url = $this->makeAbsoluteUrl(
                            $logo_file_info["url"]
                        );

                        return [
                            "exists" => true,
                            "url" => $absolute_url,
                            "path" => $logo_file_info["dir"] ?? "",
                            "method" => "absolute_url",
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // Log error but continue with fallback
            error_log("Logo loading error: " . $e->getMessage());
        }

        // Fallback to text logo
        return [
            "exists" => false,
            "url" => "",
            "path" => "",
            "method" => "safe_fallback",
        ];
    }
    /**
     * Convert relative URL to absolute URL for emails
     */
    private function makeAbsoluteUrl($relative_url)
    {
        // If already absolute, return as-is
        if (filter_var($relative_url, FILTER_VALIDATE_URL)) {
            return $relative_url;
        }

        // Get the base URL from ProjectSend configuration
        $protocol =
            !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off"
                ? "https://"
                : "http://";
        $host = $_SERVER["HTTP_HOST"] ?? "localhost";
        $base_url = $protocol . $host;

        // If BASE_URI is defined, use it
        if (defined("BASE_URI")) {
            $base_url .= rtrim(BASE_URI, "/");
        }

        // Combine with relative URL
        return $base_url . "/" . ltrim($relative_url, "/");
    }
}
?>
