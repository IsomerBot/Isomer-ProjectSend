<?php
/**
 * Prepare and send email notifications
 * UPDATED: Now uses centralized tbl_transmittal_summary table for performance
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
    private $transmittal_summaries; // NEW: Cache for transmittal summary data

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
        $this->transmittal_summaries = []; // NEW: Initialize transmittal cache
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

            // UPDATED: Load file data with transmittal summary integration
            if (!array_key_exists($row["file_id"], $this->files_data)) {
                $file = new \ProjectSend\Classes\Files($row["file_id"]);

                // Get transmittal summary data if this file has a transmittal number
                $transmittal_summary = null;
                if (!empty($file->transmittal_number)) {
                    $transmittal_summary = $this->getTransmittalSummaryData(
                        $file->transmittal_number
                    );
                }

                // Default uploader name
                $uploader_name = "Isomer Project Group";
                if (!empty($file->user_id)) {
                    $transmittal_helper = new \ProjectSend\Classes\TransmittalHelper();
                    $uploader = $transmittal_helper->getUserById(
                        $file->user_id
                    );
                    if ($uploader && !empty($uploader["name"])) {
                        $uploader_name = $uploader["name"];
                    }
                }

                // Use transmittal summary data if available, otherwise fall back to file data
                $this->files_data[$file->id] = [
                    "id" => $file->id,
                    "filename" => $file->filename_original,
                    "title" => $file->title,
                    "description" => $file->description,
                    // Transmittal fields - use summary data if available
                    "transmittal_number" => $transmittal_summary
                        ? $transmittal_summary["transmittal_number"]
                        : $file->transmittal_number ?? "",
                    "project_name" => $transmittal_summary
                        ? $transmittal_summary["project_name"]
                        : $file->project_name ?? "",
                    "project_number" => $transmittal_summary
                        ? $transmittal_summary["project_number"]
                        : $file->project_number ?? "",
                    "package_description" => $transmittal_summary
                        ? $transmittal_summary["package_description"]
                        : $file->package_description ?? "",
                    "issue_status" => $file->issue_status ?? "",
                    "discipline" => $transmittal_summary
                        ? $transmittal_summary["discipline_name"]
                        : $file->discipline ?? "",
                    "deliverable_type" => $transmittal_summary
                        ? $transmittal_summary["deliverable_type"]
                        : $file->deliverable_type ?? "",
                    "document_title" => $file->document_title ?? "",
                    "revision_number" => $file->revision_number ?? "",
                    "comments" => $transmittal_summary
                        ? $transmittal_summary["comments"]
                        : $file->comments ?? "",
                    "transmittal_name" => $transmittal_summary
                        ? $transmittal_summary["transmittal_number"]
                        : $file->transmittal_name ?? "",
                    "uploader_name" => $transmittal_summary
                        ? $transmittal_summary["uploader_name"]
                        : $uploader_name,
                    "file_bcc_addresses" => $transmittal_summary
                        ? $transmittal_summary["bcc_addresses"]
                        : $file->file_bcc_addresses ?? "",
                    "file_cc_addresses" => $transmittal_summary
                        ? $transmittal_summary["cc_addresses"]
                        : $file->file_cc_addresses ?? "",
                    "file_comments" => $file->file_comments ?? "",
                    "client_document_number" =>
                        $file->client_document_number ?? "",
                ];
            }

            // Add the client data to the global array (unchanged)
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

        // Prepare the list of clients and admins that will be notified (unchanged)
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

    /**
     * NEW: Get transmittal summary data with joined reference information
     */
    private function getTransmittalSummaryData($transmittal_number)
    {
        // Check cache first
        if (isset($this->transmittal_summaries[$transmittal_number])) {
            return $this->transmittal_summaries[$transmittal_number];
        }

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

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        // Cache the result
        $this->transmittal_summaries[$transmittal_number] = $result ?: null;

        return $this->transmittal_summaries[$transmittal_number];
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

    // Admin notification methods remain unchanged
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
                        foreach ($files as $file) {
                            $files_list_html = $this->makeSimpleFilesListHtml(
                                [$file],
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

    private function makeSimpleFilesListHtml($files, $uploader_username = null)
    {
        $html = "";

        if (!empty($uploader_username)) {
            $html .=
                '<li style="font-size:15px; font-weight:bold; margin-bottom:5px;">' .
                $uploader_username .
                "</li>";
        }
        foreach ($files as $file) {
            $file_data = $this->files_data[$file["file_id"]];
            $html .= '<li style="margin-bottom:12px;">';
            $html .=
                '<p style="font-weight:bold; margin:0 0 5px 0; font-size:14px;">' .
                $file_data["title"] .
                "<br>(" .
                $file_data["filename"] .
                ")</p>";
            if (!empty($file_data["description"])) {
                if (strpos($file_data["description"], "<p>") !== false) {
                    $html .= $file_data["description"];
                } else {
                    $html .= "<p>" . $file_data["description"] . "</p>";
                }
            }
            $html .= "</li>";
        }

        return $html;
    }

    // Client notification method - enhanced with transmittal summary data
    private function sendNotificationsToClients($notifications = [])
    {
        if (!empty($notifications)) {
            foreach ($notifications as $mail_username => $files) {
                $files_list_html = $this->makeFilesListHtml($files);
                $processed_notifications = [];

                foreach ($files as $file) {
                    $processed_notifications[] = $file["notification_id"];
                }

                // Get the full file data for the first file in the batch
                $first_file_data = $this->files_data[$files[0]["file_id"]];

                // Extract both BCC and CC addresses - now from centralized data
                $dynamic_bcc_for_this_email =
                    $first_file_data["file_bcc_addresses"] ?? "";
                $dynamic_cc_for_this_email =
                    $first_file_data["file_cc_addresses"] ?? "";

                $email = new \ProjectSend\Classes\Emails();
                if (
                    $email->send([
                        "type" => "new_files_by_user",
                        "address" => $this->mail_by_user[$mail_username],
                        "files_list" => $files_list_html,
                        "file_data" => $first_file_data,
                        "dynamic_bcc_addresses" => $dynamic_bcc_for_this_email,
                        "dynamic_cc_addresses" => $dynamic_cc_for_this_email,
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

    // UPDATED: Enhanced makeFilesListHtml method with better transmittal data integration
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
            '<div style="font-family: Montserrat, Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #fff; display:flex justify-content:center; align-items:center; border-bottom: 4px solid #f56600;">';

        // Get the first file to extract transmittal information
        $first_file_data = $this->files_data[$files[0]["file_id"]];

        // BRAND-COMPLIANT HEADER - Table-based layout with much closer spacing
        $html .=
            '<table style="width: 100%; background: #252c3a; padding: 15px 80px; min-height: 80px; border-collapse: collapse; margin: 0; border: 0;" cellpadding="0" cellspacing="0">';
        $html .= "<tr>";

        // Left side - Logo cell
        $html .=
            '<td style="width: 40%; vertical-align: middle; text-align: right; padding: 8px 15px 8px 0;">';

        // Check if logo file exists and get proper URL
        $logo_file_info = $this->getLogoFileInfo();

        if ($logo_file_info["exists"] === true) {
            if ($logo_file_info["method"] === "svg_inline") {
                $svg_content = $logo_file_info["svg_content"];
                // Ensure minimum 5mm (approximately 50px) as per brand guide
                $svg_content = str_replace(
                    "<svg",
                    '<svg style="height: 50px; width: auto; vertical-align: middle;"',
                    $svg_content
                );
                $html .= $svg_content;
            } else {
                // Minimum 5mm size requirement with vertical alignment
                $html .=
                    '<img src="' .
                    $logo_file_info["url"] .
                    '" alt="Isomer Project Group" style="height: 50px; width: auto; max-width: 200px; vertical-align: middle; border: 0;" />';
            }
        } else {
            // Fallback: Brand-compliant text using exact brand colors
            $html .=
                '<span style="color: white; font-family: Montserrat, Arial, sans-serif; font-weight: 700; font-size: 24px; text-transform: uppercase; letter-spacing: 3px;">ISOMER</span>';
            $html .=
                '<span style="color: #f56600; font-family: Montserrat, Arial, sans-serif; font-weight: 300; font-size: 16px; text-transform: uppercase; letter-spacing: 2px; margin-left: 8px;">PROJECT GROUP</span>';
        }

        $html .= "</td>";

        // Right side - Transmittal cell
        $html .=
            '<td style="width: 50%; vertical-align: middle; text-align: left; padding: 8px 0 8px 50px;">';
        $html .=
            '<div style="color: white; font-family: Montserrat, Arial, sans-serif; font-weight: 800; font-size: 24px; text-transform: uppercase; letter-spacing: 3px; margin: 0; line-height: 1;">TRANSMITTAL</div>';
        $html .=
            '<div style="color: #f56600; font-weight: 800; margin: 4px 0 0 0; font-family: Montserrat, Arial, sans-serif; font-size: 16px; text-transform: uppercase; letter-spacing: 2px; line-height: 1;">' .
            htmlspecialchars(
                html_entity_decode(
                    $first_file_data["transmittal_name"] ?? "",
                    ENT_QUOTES,
                    "UTF-8"
                )
            ) .
            "</div>";
        $html .= "</td>";

        $html .= "</tr>";
        $html .= "</table>";

        // Project information section - MERGED LAYOUT (Transmittal Info + Recipients)
        $html .= '<div style="padding: 20px; background: #fff;">';

        // Get recipients - UPDATED: Use TransmittalHelper if available, otherwise fallback
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

        // Get CC addresses from the first file data
        $copies_to_text = "";
        if (!empty($first_file_data["file_cc_addresses"])) {
            $copies_to_text = $first_file_data["file_cc_addresses"];
        }

        // SINGLE TRANSMITTAL INFORMATION CARD (merged project info + recipients)
        $html .=
            '<div style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px;">';

        $html .=
            '<div style="font-family: Montserrat, Arial, sans-serif; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 2px; color: #252c3a; margin-bottom: 15px; border-bottom: 2px solid #252c3a; padding-bottom: 8px;">TRANSMITTAL INFORMATION</div>';

        // Three-column layout for project details
        $html .=
            '<div style="display: flex; width: 100%; margin-bottom: 20px;">';

        // LEFT COLUMN (33.33%) - Project details only
        $html .=
            '<div style="width: 33.33%; padding-right: 15px; box-sizing: border-box; overflow: hidden;">';

        $html .=
            '<div style="margin-bottom: 15px;"><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 600; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 1px;">Project Number:</span><br><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 400; font-size: 14px; color: #252c3a; word-wrap: break-word; overflow-wrap: break-word;">' .
            htmlspecialchars(
                html_entity_decode(
                    $first_file_data["project_number"] ?? "",
                    ENT_QUOTES,
                    "UTF-8"
                )
            ) .
            "</span></div>";

        $html .=
            '<div style="margin-bottom: 15px;"><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 600; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 1px;">Discipline:</span><br><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 400; font-size: 14px; color: #252c3a; word-wrap: break-word; overflow-wrap: break-word;">' .
            htmlspecialchars(
                html_entity_decode(
                    $first_file_data["discipline"] ?? "",
                    ENT_QUOTES,
                    "UTF-8"
                )
            ) .
            "</span></div>";

        $html .= "</div>"; // End left column

        // MIDDLE COLUMN (33.33%) - Project details only
        $html .=
            '<div style="width: 33.33%; padding-left: 15px; padding-right: 15px; box-sizing: border-box; border-left: 1px solid #e9ecef; overflow: hidden;">';

        $html .=
            '<div style="margin-bottom: 15px;"><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 600; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 1px;">Project Name:</span><br><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 400; font-size: 14px; color: #252c3a; word-wrap: break-word; overflow-wrap: break-word;">' .
            htmlspecialchars(
                html_entity_decode(
                    $first_file_data["project_name"] ?? "",
                    ENT_QUOTES,
                    "UTF-8"
                )
            ) .
            "</span></div>";

        // DELIVERABLE TYPE
        $deliverable_type = "";
        if (!empty($first_file_data["deliverable_type"])) {
            $deliverable_type = html_entity_decode(
                $first_file_data["deliverable_type"],
                ENT_QUOTES,
                "UTF-8"
            );
        }
        $html .=
            '<div style="margin-bottom: 15px;"><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 600; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 1px;">Deliverable Type:</span><br><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 400; font-size: 14px; color: #252c3a; word-wrap: break-word; overflow-wrap: break-word;">' .
            htmlspecialchars($deliverable_type) .
            "</span></div>";

        $html .= "</div>"; // End middle column

        // RIGHT COLUMN (33.33%) - Date and From only
        $html .=
            '<div style="width: 33.33%; padding-left: 15px; box-sizing: border-box; border-left: 1px solid #e9ecef; overflow: hidden;">';

        // Transmittal Date
        $formatted_date = date("F jS, Y");
        $html .=
            '<div style="margin-bottom: 15px;"><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 600; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 1px;">Transmittal Date:</span><br><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 400; font-size: 14px; color: #252c3a; word-wrap: break-word; overflow-wrap: break-word;">' .
            $formatted_date .
            "</span></div>";

        // FROM field
        $from_text = !empty($first_file_data["uploader_name"])
            ? htmlspecialchars(
                html_entity_decode(
                    $first_file_data["uploader_name"],
                    ENT_QUOTES,
                    "UTF-8"
                )
            )
            : "Isomer Project Group";
        $html .=
            '<div style="margin-bottom: 15px;"><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 600; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 1px;">From:</span><br><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 400; font-size: 14px; color: #252c3a; word-wrap: break-word; overflow-wrap: break-word;">' .
            $from_text .
            "</span></div>";

        $html .= "</div>"; // End right column

        $html .= "</div>"; // End three-column layout

        // FULL-WIDTH RECIPIENT ROWS
        // TO field - Full width row
        $html .=
            '<div style="margin-bottom: 15px; padding-top: 15px; border-top: 1px solid #e9ecef;"><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 600; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 1px;">To:</span><br><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 400; font-size: 14px; color: #252c3a; word-wrap: break-word; overflow-wrap: break-word;">' .
            htmlspecialchars(
                html_entity_decode($recipients_text, ENT_QUOTES, "UTF-8")
            ) .
            "</span></div>";

        // COPIES TO field - Full width row (only show if there's data)
        if (!empty($copies_to_text)) {
            $html .=
                '<div style="margin-bottom: 0;"><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 600; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 1px;">Copies To:</span><br><span style="font-family: Montserrat, Arial, sans-serif; font-weight: 400; font-size: 14px; color: #252c3a; word-wrap: break-word; overflow-wrap: break-word;">' .
                htmlspecialchars(
                    html_entity_decode($copies_to_text, ENT_QUOTES, "UTF-8")
                ) .
                "</span></div>";
        }

        $html .= "</div>"; // End transmittal information card
        $html .= "</div>"; // End project info section

        // COMMENTS SECTION - Matching card style with REDUCED TOP SPACING
        $html .=
            '<div style="padding: 20px; margin-bottom: 20px; background: #fff;">';

        $html .=
            '<div style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px;">';

        $html .=
            '<div style="font-family: Montserrat, Arial, sans-serif; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 2px; color: #252c3a; margin-bottom: 15px; border-bottom: 2px solid #252c3a; padding-bottom: 8px;">TRANSMITTAL COMMENTS</div>';

        $html .=
            '<div style="border: 1px solid #e9ecef; border-radius: 4px; padding: 15px; min-height: 60px; background: #fff;">';

        // UPDATED: Use centralized comments data
        $transmittal_comments = $first_file_data["comments"] ?? "";
        if (!empty($transmittal_comments)) {
            $clean_comments = strip_tags($transmittal_comments);
            $html .=
                '<span style="font-family: Montserrat, Arial, sans-serif; font-weight: 400; font-size: 14px; color: #252c3a; line-height: 1.4;">' .
                htmlspecialchars($clean_comments) .
                "</span>";
        }
        $html .= "</div>";
        $html .= "</div>";
        $html .= "</div>";

        // FILES SECTION - Same boldness for main header
        $html .=
            '<div style="padding: 20px; background: #fff; border-top: 1px solid #c5e0ea;">';
        $html .=
            '<div style="font-family: Montserrat, Arial, sans-serif; font-weight: 800; font-size: 16px; text-transform: uppercase; letter-spacing: 2px; color: #252c3a; text-align: center; margin-bottom: 15px; margin-top:20px">ISOMER TRANSMITTAL AVAILABLE FOR DOWNLOAD</div>';
        $html .=
            '<div class="isomer-body" style="margin-bottom: 20px; text-align: center;">The following deliverables have been transmitted from Isomer Project Group</div>';

        // Check if any files have comments before showing the column
        $has_file_comments = false;
        foreach ($files as $file) {
            $file_data = $this->files_data[$file["file_id"]];
            $file_comments = $file_data["file_comments"] ?? "";
            if (!empty(trim($file_comments))) {
                $has_file_comments = true;
                break;
            }
        }

        // FILES TABLE - Conditionally show File Comments column
        $html .=
            '<table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd; font-size: 12px; margin-bottom: 0;">';
        $html .=
            '<tr style="background: #252c3a; font-weight: bold; color: white;">';
        $html .=
            '<th style="border: 1px solid #ddd; padding: 8px; text-align: left; color: white;">File Name</th>';
        $html .=
            '<th style="border: 1px solid #ddd; padding: 8px; text-align: left; color: white;">Revision</th>';
        $html .=
            '<th style="border: 1px solid #ddd; padding: 8px; text-align: left; color: white;">Document Title</th>';

        // Only show File Comments column if there are comments
        if ($has_file_comments) {
            $html .=
                '<th style="border: 1px solid #ddd; padding: 8px; text-align: left; color: white;">File Comments</th>';
        }

        $html .= "</tr>";

        // Loop through ALL files and add a row for each
        foreach ($files as $file) {
            $file_data = $this->files_data[$file["file_id"]];

            // File row in table
            $html .= "<tr>";

            // File Title - REMOVE EXTENSION
            $filename_raw = $file_data["filename"] ?? $file_data["title"];

            // Remove the file extension
            $filename_without_extension = pathinfo(
                $filename_raw,
                PATHINFO_FILENAME
            );

            $filename = htmlspecialchars(
                html_entity_decode(
                    $filename_without_extension,
                    ENT_QUOTES,
                    "UTF-8"
                )
            );
            $html .=
                '<td style="border: 1px solid #ddd; padding: 8px; word-wrap: break-word;">' .
                $filename .
                "</td>";

            // Revision Number
            $html .=
                '<td style="border: 1px solid #ddd; padding: 8px; text-align: center;">' .
                htmlspecialchars(
                    html_entity_decode(
                        $file_data["revision_number"] ?? "",
                        ENT_QUOTES,
                        "UTF-8"
                    )
                ) .
                "</td>";

            // Document Title
            $document_title = "";
            if (!empty($file_data["document_title"])) {
                $document_title = html_entity_decode(
                    $file_data["document_title"],
                    ENT_QUOTES,
                    "UTF-8"
                );
            }
            $html .=
                '<td style="border: 1px solid #ddd; padding: 8px; word-wrap: break-word;">' .
                htmlspecialchars($document_title) .
                "</td>";

            // File Comments
            if ($has_file_comments) {
                $file_comments = $file_data["file_comments"] ?? "";
                if (!empty($file_comments)) {
                    $file_comments = html_entity_decode(
                        strip_tags($file_comments),
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    $file_comments = htmlspecialchars($file_comments);
                }
                $html .=
                    '<td style="border: 1px solid #ddd; padding: 8px; word-wrap: break-word;">' .
                    $file_comments .
                    "</td>";
            }

            $html .= "</tr>";
        }

        $html .= "</table>";

        // FIXED LOGIN LINK SECTION
        $html .=
            '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">';
        $html .=
            "<div>To access the files pertinent to this transmittal,</div>";
        $html .=
            '<div><a href="%URI%" style="color: #0066cc; text-decoration: underline;">please login here</a></div>';
        $html .= "</div>";

        // END OF TRANSMITTAL
        $html .=
            '<div style="text-align: center; margin-top: 20px; padding: 15px;  font-weight: bold;">';
        $html .= "************END OF TRANSMITTAL************";
        $html .= "</div>";

        $html .= "</div>"; // End inner container
        $html .= "</div>"; // End main container

        return $html;
    }

    /**
     * UPDATED: Simplified getTransmittalComments method - now uses centralized data
     */
    private function getTransmittalComments($transmittal_number)
    {
        // First try to get from transmittal summary
        $transmittal_summary = $this->getTransmittalSummaryData(
            $transmittal_number
        );
        if ($transmittal_summary && !empty($transmittal_summary["comments"])) {
            return html_entity_decode(
                $transmittal_summary["comments"],
                ENT_QUOTES,
                "UTF-8"
            );
        }

        // Fallback to original method
        if (empty($transmittal_number)) {
            return "";
        }

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
            return html_entity_decode(
                $row["comments"] ?? "",
                ENT_QUOTES,
                "UTF-8"
            );
        }

        return "";
    }

    // Database update methods remain unchanged
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
     * Logo handling methods remain unchanged from original
     */
    private function getLogoFileInfo()
    {
        try {
            if (function_exists("generate_logo_url")) {
                $logo_file_info = generate_logo_url();

                if (
                    !empty($logo_file_info) &&
                    isset($logo_file_info["exists"]) &&
                    $logo_file_info["exists"] === true
                ) {
                    if (
                        isset($logo_file_info["dir"]) &&
                        file_exists($logo_file_info["dir"])
                    ) {
                        $file_size = filesize($logo_file_info["dir"]);

                        if ($file_size <= 512000) {
                            $file_extension = strtolower(
                                pathinfo(
                                    $logo_file_info["dir"],
                                    PATHINFO_EXTENSION
                                )
                            );

                            if ($file_extension === "svg") {
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
                                $image_data = file_get_contents(
                                    $logo_file_info["dir"]
                                );
                                if ($image_data !== false) {
                                    $base64 = base64_encode($image_data);

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
            error_log("Logo loading error: " . $e->getMessage());
        }

        return [
            "exists" => false,
            "url" => "",
            "path" => "",
            "method" => "safe_fallback",
        ];
    }

    private function makeAbsoluteUrl($relative_url)
    {
        if (filter_var($relative_url, FILTER_VALIDATE_URL)) {
            return $relative_url;
        }

        $protocol =
            !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off"
                ? "https://"
                : "http://";
        $host = $_SERVER["HTTP_HOST"] ?? "localhost";
        $base_url = $protocol . $host;

        if (defined("BASE_URI")) {
            $base_url .= rtrim(BASE_URI, "/");
        }

        return $base_url . "/" . ltrim($relative_url, "/");
    }
}
?>
