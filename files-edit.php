<?php
/**
 * File information editor
 */
define("IS_FILE_EDITOR", true);

$allowed_levels = [9, 8, 7, 0];
require_once "bootstrap.php";
log_in_required($allowed_levels);

$active_nav = "files";

$page_title = __("Edit files", "cftp_admin");

$page_id = "file_editor";

define("CAN_INCLUDE_FILES", true);

// Editable
$editable = [];
$files = explode(",", $_GET["ids"]);
foreach ($files as $file_id) {
    if (is_numeric($file_id)) {
        if (user_can_edit_file(CURRENT_USER_ID, $file_id)) {
            $editable[] = (int) $file_id;
        }
    }
}

$saved_files = [];

// Fill the categories array that will be used on the form
$categories = [];
$get_categories = get_categories();

function custom_download_exists($link)
{
    global $dbh;
    $statement = $dbh->prepare(
        "SELECT link, file_id FROM " .
            TABLE_CUSTOM_DOWNLOADS .
            " WHERE link=:link"
    );
    $statement->bindParam(":link", $link);
    $statement->execute();
    return $statement->fetchColumn();
}

function create_custom_download($link, $file_id, $client_id)
{
    global $dbh;
    if (custom_download_exists($link)) {
        $statement = $dbh->prepare(
            "UPDATE " .
                TABLE_CUSTOM_DOWNLOADS .
                " SET file_id=:file_id, client_id=:client_id WHERE link=:link"
        );
        $statement->bindParam(":link", $link);
        $statement->bindParam(":file_id", $file_id, PDO::PARAM_INT);
        $statement->bindParam(":client_id", $client_id, PDO::PARAM_INT);
        $statement->execute();
        return true;
    } else {
        $statement = $dbh->prepare(
            "INSERT INTO " .
                TABLE_CUSTOM_DOWNLOADS .
                " (link, file_id, client_id) VALUES (:link, :file_id, :client_id)"
        );
        $statement->bindParam(":link", $link);
        $statement->bindParam(":file_id", $file_id);
        $statement->bindParam(":client_id", $client_id, PDO::PARAM_INT);
        $statement->execute();
    }
    return false;
}

// *** START OF MAIN POST HANDLING BLOCK ***
if (isset($_POST["save"])) {
    global $flash; // Make $flash available for use within this block

    // --- NEW: Normalize the global cc/BCC input here once for the whole batch ---
    $global_file_bcc_addresses = $_POST["file_bcc_addresses"] ?? "";
    $global_file_cc_addresses = $_POST["file_cc_addresses"] ?? "";

    if (!empty($global_file_bcc_addresses)) {
        $raw_bcc = $global_file_bcc_addresses;
        $cleaned_bcc = str_replace(["\r\n", "\r", "\n"], ",", $raw_bcc);
        $cleaned_bcc = preg_replace("/[,]+/", ",", $cleaned_bcc);
        $cleaned_bcc = trim($cleaned_bcc, ", ");
        $individual_emails = array_map("trim", explode(",", $cleaned_bcc));
        $individual_emails = array_unique(array_filter($individual_emails));
        $global_file_bcc_addresses = implode(",", $individual_emails);
    }

    if (!empty($global_file_cc_addresses)) {
        $raw_cc = $global_file_cc_addresses;
        $cleaned_cc = str_replace(["\r\n", "\r", "\n"], ",", $raw_cc);
        $individual_emails = array_map("trim", explode(",", $cleaned_cc));
        $individual_emails = array_unique(array_filter($individual_emails));
        $global_file_cc_addresses = implode(",", $individual_emails);
    }

    // Process transmittal data - only if project data is provided
    if (isset($_POST["project_number"]) && !empty($_POST["project_number"])) {
        try {
            global $dbh;

            // Get the global transmittal data that applies to all files being edited
            $global_project_name = $_POST["project_name"] ?? "";
            $global_project_number = $_POST["project_number"] ?? "";
            $global_package_description = $_POST["package_description"] ?? "";
            $global_issue_status = $_POST["issue_status"] ?? "";
            $global_comments = $_POST["comments"] ?? "";
            $global_discipline = $_POST["transmittal_discipline"] ?? "";
            $global_deliverable_type =
                $_POST["transmittal_deliverable_type"] ?? "";

            // Get the next transmittal number for this project
            // This will be the SAME for all files in this upload batch
            $next_transmittal_query = "SELECT LPAD(COALESCE(MAX(CAST(SUBSTRING(transmittal_name, -4) AS UNSIGNED)), 0) + 1, 4, '0') AS next_transmittal
            FROM tbl_files
            WHERE project_number = :project_number
            AND transmittal_name IS NOT NULL
            AND transmittal_name != ''";
            $stmt = $dbh->prepare($next_transmittal_query);
            $stmt->execute([":project_number" => $global_project_number]);
            $next_transmittal_number = $stmt->fetchColumn();

            // Generate transmittal name using the new number
            $generated_transmittal_name = "";
            if (
                !empty($global_project_number) &&
                !empty($next_transmittal_number)
            ) {
                $generated_transmittal_name = sprintf(
                    "%s-T-%s",
                    $global_project_number,
                    $next_transmittal_number
                );
            }

            // UPDATED: Only update fields that are still available in the UI
            // Removed: description, expires, expiry_date, public_allow, public_token, folder_id
            foreach ($_POST["file"] as $file_data_from_post) {
                if (
                    isset($file_data_from_post["id"]) &&
                    is_numeric($file_data_from_post["id"])
                ) {
                    $query = "UPDATE tbl_files SET
                           transmittal_number = :transmittal_number,
                           transmittal_name = :transmittal_name,
                           project_name = :project_name,
                           project_number = :project_number,
                           package_description = :package_description,
                           issue_status = :issue_status,
                           issue_status_override = :issue_status_override,
                           original_issue_status = :original_issue_status,
                           deliverable_type = :deliverable_type,
                           discipline = :discipline,
                           document_title = :document_title,
                           revision_number = :revision_number,
                           comments = :comments,
                           file_bcc_addresses = :file_bcc_addresses,
                           file_cc_addresses = :file_cc_addresses,
                           file_comments = :file_comments,
                           client_document_number = :client_document_number
                         WHERE id = :file_id";

                    // FIXED: Ensure original_issue_status is ALWAYS set to transmittal-level status
                    $original_issue_status = $global_issue_status; // This is the transmittal-level status
                    $file_issue_status = $global_issue_status; // Default to transmittal-level status
                    $issue_status_override = 0; // Default: no override

                    // Check if this file has an issue status override
                    if (
                        isset($file_data_from_post["issue_status_override"]) &&
                        $file_data_from_post["issue_status_override"] == "1" &&
                        !empty($file_data_from_post["custom_issue_status"])
                    ) {
                        $file_issue_status =
                            $file_data_from_post["custom_issue_status"];
                        $issue_status_override = 1; // Mark as override

                        error_log(
                            "File {$file_data_from_post["id"]}: OVERRIDE - Transmittal: '{$original_issue_status}' → File: '{$file_issue_status}'"
                        );
                    } else {
                        error_log(
                            "File {$file_data_from_post["id"]}: Using transmittal issue status '{$file_issue_status}' (no override)"
                        );
                    }

                    $statement = $dbh->prepare($query);
                    $statement->execute([
                        ":file_id" => $file_data_from_post["id"],
                        ":transmittal_number" => $generated_transmittal_name,
                        ":transmittal_name" => $generated_transmittal_name,
                        ":project_name" => $global_project_name,
                        ":project_number" => $global_project_number,
                        ":package_description" => $global_package_description,
                        ":issue_status" => $file_issue_status, // Current status (override if set, otherwise global)
                        ":issue_status_override" => $issue_status_override, // Track override status
                        ":original_issue_status" => $original_issue_status, // ALWAYS store transmittal-level status
                        ":discipline" => $global_discipline,
                        ":deliverable_type" => $global_deliverable_type,
                        ":document_title" =>
                            $file_data_from_post["document_title"] ?? "",
                        ":revision_number" =>
                            $file_data_from_post["revision_number"] ?? "",
                        ":comments" => $global_comments,
                        ":file_bcc_addresses" => $global_file_bcc_addresses,
                        ":file_cc_addresses" => $global_file_cc_addresses,
                        ":file_comments" =>
                            $file_data_from_post["file_comments"] ?? "",
                        ":client_document_number" =>
                            $file_data_from_post["client_document_number"] ??
                            "",
                    ]);
                }
            }

            // Set success message with the transmittal number used
            $flash->success(
                sprintf(
                    __(
                        "Transmittal information saved successfully. Transmittal Number: %s",
                        "cftp_admin"
                    ),
                    $next_transmittal_number
                )
            );

            // ADD THIS CODE RIGHT AFTER THE SUCCESS MESSAGE (around line 176)
            // After the flash->success message and before the catch block

            // *** TRANSMITTAL SUMMARY TABLE INTEGRATION ***
            try {
                // Instantiate the TransmittalSummaryManager
                $transmittalManager = new \ProjectSend\Classes\TransmittalSummaryManager();

                // Look up the discipline_id and deliverable_type_id from the text values
                $discipline_id = null;
                $deliverable_type_id = null;

                if (!empty($global_discipline)) {
                    $discipline_query =
                        "SELECT id FROM tbl_discipline WHERE discipline_name = :discipline_name LIMIT 1";
                    $discipline_stmt = $dbh->prepare($discipline_query);
                    $discipline_stmt->execute([
                        ":discipline_name" => $global_discipline,
                    ]);
                    $discipline_result = $discipline_stmt->fetch(
                        PDO::FETCH_ASSOC
                    );
                    $discipline_id = $discipline_result
                        ? $discipline_result["id"]
                        : null;
                }

                if (!empty($global_deliverable_type) && $discipline_id) {
                    $deliverable_query =
                        "SELECT id FROM tbl_deliverable_type WHERE deliverable_type = :deliverable_type AND discipline_id = :discipline_id LIMIT 1";
                    $deliverable_stmt = $dbh->prepare($deliverable_query);
                    $deliverable_stmt->execute([
                        ":deliverable_type" => $global_deliverable_type,
                        ":discipline_id" => $discipline_id,
                    ]);
                    $deliverable_result = $deliverable_stmt->fetch(
                        PDO::FETCH_ASSOC
                    );
                    $deliverable_type_id = $deliverable_result
                        ? $deliverable_result["id"]
                        : null;
                }

                // Look up group_id from project_number
                $group_id = null;
                if (!empty($global_project_number)) {
                    $group_query =
                        "SELECT id FROM tbl_groups WHERE name = :project_number LIMIT 1";
                    $group_stmt = $dbh->prepare($group_query);
                    $group_stmt->execute([
                        ":project_number" => $global_project_number,
                    ]);
                    $group_result = $group_stmt->fetch(PDO::FETCH_ASSOC);
                    $group_id = $group_result ? $group_result["id"] : null;
                }

                // Prepare transmittal summary data
                $transmittal_data = [
                    "project_number" => $global_project_number,
                    "group_id" => $group_id,
                    "discipline_id" => $discipline_id,
                    "deliverable_type_id" => $deliverable_type_id,
                    "uploader_user_id" => CURRENT_USER_ID,
                    "project_name" => $global_project_name,
                    "package_description" => $global_package_description,
                    "comments" => $global_comments,
                    "cc_addresses" => $global_file_cc_addresses,
                    "bcc_addresses" => $global_file_bcc_addresses,
                    "file_count" => count($_POST["file"]),
                ];

                // Save to the centralized summary table
                $summary_saved = $transmittalManager->createOrUpdate(
                    $generated_transmittal_name,
                    $transmittal_data
                );

                if (!$summary_saved) {
                    error_log(
                        "Warning: Failed to save transmittal summary for " .
                            $generated_transmittal_name
                    );
                }
            } catch (Exception $e) {
                // Log error but don't break the main flow
                error_log("TransmittalSummary save error: " . $e->getMessage());
            }
        } catch (Exception $e) {
            // Log error and show user-friendly message
            error_log("Transmittal save error: " . $e->getMessage());
            $flash->error(
                sprintf(
                    __(
                        "Error saving transmittal information: %s",
                        "cftp_admin"
                    ),
                    $e->getMessage()
                )
            );
        }
    }

    // Edit each file and its assignations
    // UPDATED: Process transmittal-level client assignments
    $confirm = false;
    foreach ($_POST["file"] as $file) {
        // Assign the normalized global BCC to each file's data array
        $file["file_bcc_addresses"] = $global_file_bcc_addresses;
        $file["file_cc_addresses"] = $global_file_cc_addresses;

        // NEW: Apply transmittal-level client assignments to each file
        if (
            isset($_POST["transmittal_clients"]) &&
            is_array($_POST["transmittal_clients"])
        ) {
            $file["assignments"]["clients"] = $_POST["transmittal_clients"];
        }

        // NEW: Apply transmittal-level group assignments to each file
        if (
            isset($_POST["transmittal_groups"]) &&
            is_array($_POST["transmittal_groups"])
        ) {
            $file["assignments"]["groups"] = $_POST["transmittal_groups"];
        }

        // NEW: Apply transmittal-level categories to each file
        if (
            isset($_POST["transmittal_categories"]) &&
            is_array($_POST["transmittal_categories"])
        ) {
            $file["categories"] = $_POST["transmittal_categories"];
        }

        // UNCHANGED: Use original Files class - backend processing intact
        $object = new \ProjectSend\Classes\Files($file["id"]);
        if ($object->recordExists()) {
            if ($object->save($file) != false) {
                $saved_files[] = $file["id"];
            }
        }

        // Handle custom downloads if they exist - UNCHANGED
        if (
            isset($file["custom_downloads"]) &&
            is_array($file["custom_downloads"])
        ) {
            foreach ($file["custom_downloads"] as $custom_download) {
                global $dbh;

                if (
                    custom_download_exists($custom_download["link"]) &&
                    (!isset($_GET["confirmed"]) || !$_GET["confirmed"])
                ) {
                    $confirm = true;
                    continue;
                }

                if ($custom_download["id"]) {
                    if ($custom_download["link"]) {
                        if (
                            $custom_download["link"] != $custom_download["id"]
                        ) {
                            $statement = $dbh->prepare(
                                "UPDATE " .
                                    TABLE_CUSTOM_DOWNLOADS .
                                    " SET file_id=NULL WHERE link=:link"
                            );
                            $statement->bindParam(
                                ":link",
                                $custom_download["id"]
                            );
                            $statement->execute();
                            if (
                                create_custom_download(
                                    $custom_download["link"],
                                    $file["id"],
                                    CURRENT_USER_ID
                                )
                            ) {
                                $flash->warning(
                                    __(
                                        "Updated existing custom link to point to this file.",
                                        "cftp_admin"
                                    )
                                );
                            }
                        }
                    } else {
                        // remove file_id from custom download
                        $statement = $dbh->prepare(
                            "UPDATE " .
                                TABLE_CUSTOM_DOWNLOADS .
                                " SET file_id=NULL WHERE link=:link"
                        );
                        $statement->bindParam(":link", $custom_download["id"]);
                        $statement->execute();
                    }
                } else {
                    if ($custom_download["link"]) {
                        if (
                            create_custom_download(
                                $custom_download["link"],
                                $file["id"],
                                CURRENT_USER_ID
                            )
                        ) {
                            $flash->warning(
                                __(
                                    "Updated existing custom link to point to this file.",
                                    "cftp_admin"
                                )
                            );
                        }
                    }
                }
            }
        }
    }

    // Send the notifications - UNCHANGED
    if (get_option("notifications_send_when_saving_files") == "1") {
        $notifications = new \ProjectSend\Classes\EmailNotifications();
        $notifications->sendNotifications();
        if (!empty($notifications->getNotificationsSent())) {
            $flash->success(
                __("E-mail notifications have been sent.", "cftp_admin")
            );
        }
        if (!empty($notifications->getNotificationsFailed())) {
            $flash->error(
                __(
                    "One or more notifications couldn't be sent. Please confirm all email addresses and try again.",
                    "cftp_admin"
                )
            );
        }
        if (!empty($notifications->getNotificationsInactiveAccounts())) {
            if (CURRENT_USER_LEVEL == 0) {
                /**
                 * Clients do not need to know about the status of the
                 * creator's account. Show the ok message instead.
                 */
                $flash->success(
                    __("E-mail notifications have been sent.", "cftp_admin")
                );
            } else {
                $flash->warning(
                    __(
                        "E-mail notifications for inactive clients were not sent.",
                        "cftp_admin"
                    )
                );
            }
        }
    } else {
        $flash->warning(
            __(
                "E-mail notifications were not sent according to your settings. Make sure you have a cron job enabled if you need to send them.",
                "cftp_admin"
            )
        );
    }

    // Redirect
    $saved = implode(",", $saved_files);

    if ($confirm) {
        $flash->success(__("Files saved successfully", "cftp_admin"));
        $flash->warning(
            __(
                "A custom link like this already exists, enter it again to override.",
                "cftp_admin"
            )
        );
        ps_redirect("files-edit.php?&ids=" . $saved . "&confirm=true");
    } else {
        $flash->success(__("Files saved successfully", "cftp_admin"));
        ps_redirect("files-edit.php?&ids=" . $saved . "&saved=true");
    }
}

// Message
if (!empty($editable) && !isset($_GET["saved"])) {
    if (CURRENT_USER_LEVEL != 0) {
        $flash->info(
            __("Please complete all red required fields.", "cftp_admin")
        );
    }
}

// Include layout files
include_once ADMIN_VIEWS_DIR . DS . "header.php";
?>
<div class="row">
    <div class="col-12">
        <?php
        // Saved files display
        $saved_files = [];
        if (!empty($_GET["saved"])) {
            foreach ($editable as $file_id) {
                if (is_numeric($file_id)) {
                    $saved_files[] = $file_id;
                }
            }

            // UPDATED: Simplified table
            $table = new \ProjectSend\Classes\Layout\Table([
                "id" => "uploaded_files_tbl",
                "class" => "footable table",
                "origin" => basename(__FILE__),
            ]);

            $thead_columns = [
                [
                    "content" => __("Title", "cftp_admin"),
                ],
                [
                    "content" => __("File Name", "cftp_admin"),
                ],
                [
                    "content" => __("Recipients", "cftp_admin"), // NEW: Added this header
                ],
                [
                    "content" => __("Actions", "cftp_admin"),
                    "hide" => "phone",
                ],
            ];
            $table->thead($thead_columns);

            foreach ($saved_files as $file_id) {
                $file = new \ProjectSend\Classes\Files($file_id);
                if ($file->recordExists()) {
                    $table->addRow();

                    // --- BEGIN: NEW RECIPIENT DISPLAY LOGIC ---
                    $recipients_text = "All Recipients";
                    if (!empty($file->transmittal_number)) {
                        $transmittal_helper = new \ProjectSend\Classes\TransmittalHelper();
                        $recipients = $transmittal_helper->getTransmittalRecipients(
                            $file->transmittal_number
                        );

                        if (!empty($recipients)) {
                            $recipient_names = [];
                            foreach ($recipients as $recipient) {
                                $recipient_names[] = $recipient["name"];
                            }
                            $recipients_text = implode(", ", $recipient_names);
                        }
                    }
                    // --- END: NEW RECIPIENT DISPLAY LOGIC ---

                    $col_actions =
                        '<a href="files-edit.php?ids=' .
                        $file->id .
                        '" class="btn-primary btn btn-sm">
                <i class="fa fa-pencil"></i><span class="button_label">' .
                        __("Edit file", "cftp_admin") .
                        '</span>
            </a>';

                    // Show the "My files" button only to clients
                    if (CURRENT_USER_LEVEL == 0) {
                        $col_actions .=
                            ' <a href="' .
                            CLIENT_VIEW_FILE_LIST_URL .
                            '" class="btn-primary btn btn-sm">' .
                            __("View my files", "cftp_admin") .
                            "</a>";
                    }

                    // UPDATED: Simplified table cells - removed description and public columns
                    $tbody_cells = [
                        [
                            "content" => $file->title,
                        ],
                        [
                            "content" => $file->filename_original,
                        ],
                        [
                            "content" => $recipients_text, // NEW: Displays the fetched recipient names
                        ],
                        [
                            "content" => $col_actions,
                        ],
                    ];

                    foreach ($tbody_cells as $cell) {
                        $table->addCell($cell);
                    }

                    $table->end_row();
                }
            }

            echo $table->render();
        } else {
            // Generate the table of files ready to be edited
            if (!empty($editable)) {
                include_once FORMS_DIR . DS . "file_editor.php";
            }
        }
        ?></div>
</div>
<?php include_once ADMIN_VIEWS_DIR . DS . "footer.php"; ?>
