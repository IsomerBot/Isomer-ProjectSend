<?php
// Load existing transmittal data for editing - ADMIN ONLY
$existing_transmittal_data = [];
if (!empty($editable) && !isset($_GET["saved"]) && CURRENT_USER_LEVEL != 0) {
    // Get transmittal data from the first file
    $first_file_id = $editable[0];
    global $dbh;
    $query = "SELECT transmittal_number, transmittal_name, project_name, project_number, package_description, 
                     issue_status, discipline, deliverable_type, document_title,
                     revision_number, comments, file_bcc_addresses, file_cc_addresses, file_comments
              FROM tbl_files 
              WHERE id = :file_id";
    $statement = $dbh->prepare($query);
    $statement->execute([":file_id" => $first_file_id]);
    $existing_transmittal_data = $statement->fetch(PDO::FETCH_ASSOC);

    // If no data found, initialize empty array
    if (!$existing_transmittal_data) {
        $existing_transmittal_data = [
            "transmittal_number" => "",
            "transmittal_name" => "",
            "project_name" => "",
            "project_number" => "",
            "package_description" => "",
            "issue_status" => "",
            "discipline" => "",
            "deliverable_type" => "",
            "document_title" => "",
            "revision_number" => "",
            "comments" => "",
        ];
    }
}
?>

<form action="files-edit.php?ids=<?php
echo html_output($_GET["ids"]);
if (isset($_GET["confirm"])) {
    echo "&confirmed=true";
}
?>" name="files" id="files" method="post" enctype="multipart/form-data">
    <?php addCsrf(); ?>

    <div class="container-fluid">
        <?php $i = 1; ?>
        
        <?php if (CURRENT_USER_LEVEL != 0): ?>
            <div class="row">
                <div class="col-md-6">
                    <h3><?php _e(
                        "Transmittal Information",
                        "cftp_admin"
                    ); ?></h3>

                    <div class="form-group">
                        <label for="transmittal_number"><?php _e(
                            "Transmittal Number",
                            "cftp_admin"
                        ); ?>*</label>
                        <input type="text" 
                               name="transmittal_number" 
                               id="transmittal_number" 
                               class="form-control readonly-field" 
                               value="Auto-generated"
                               readonly
                               placeholder="Will be assigned automatically" />
                        <small class="form-text text-muted">
                            <i class="fa fa-info-circle"></i> 
                            Will be assigned automatically based on project number
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="project_name"><?php _e(
                            "Project Name",
                            "cftp_admin"
                        ); ?>*</label>
                        <input type="text" name="project_name" id="project_name" class="form-control" 
                               value="<?php echo htmlspecialchars(
                                   html_entity_decode(
                                       $existing_transmittal_data[
                                           "project_name"
                                       ] ?? "",
                                       ENT_QUOTES,
                                       "UTF-8"
                                   )
                               ); ?>"
                               placeholder="<?php _e(
                                   "Enter Project Name",
                                   "cftp_admin"
                               ); ?>" required />
                    </div>

                    <div class="form-group">
                        <label for="project_number"><?php _e(
                            "Project Number",
                            "cftp_admin"
                        ); ?>*</label>
                        <input type="text" name="project_number" id="project_number" class="form-control" 
                               value="<?php echo htmlspecialchars(
                                   $existing_transmittal_data[
                                       "project_number"
                                   ] ?? ""
                               ); ?>"
                               placeholder="<?php _e(
                                   "AAA####",
                                   "cftp_admin"
                               ); ?>" required />
                    </div>

                    <div class="form-group">
                        <label for="package_description"><?php _e(
                            "Package Description",
                            "cftp_admin"
                        ); ?>*</label>
                        <input type="text" name="package_description" id="package_description" class="form-control" 
                               value="<?php echo htmlspecialchars(
                                   html_entity_decode(
                                       $existing_transmittal_data[
                                           "package_description"
                                       ] ?? "",
                                       ENT_QUOTES,
                                       "UTF-8"
                                   )
                               ); ?>"
                               placeholder="<?php _e(
                                   "Enter Package Description",
                                   "cftp_admin"
                               ); ?>" required />
                    </div>

                    <div class="form-group">
                        <label for="issue_status"><?php _e(
                            "Issue Status",
                            "cftp_admin"
                        ); ?>*</label>
                        <select id="issue_status" name="issue_status" class="form-select" required>
                            <option value=""><?php _e(
                                "Select Issue Status",
                                "cftp_admin"
                            ); ?></option>
                            <?php try {
                                $helper = new \ProjectSend\Classes\TransmittalHelper();
                                $statuses = $helper->getDropdownOptions(
                                    "issue_status"
                                );
                                foreach ($statuses as $status) {
                                    $selected =
                                        $status ==
                                        ($existing_transmittal_data[
                                            "issue_status"
                                        ] ??
                                            "")
                                            ? " selected"
                                            : "";
                                    echo '<option value="' .
                                        htmlspecialchars($status) .
                                        '"' .
                                        $selected .
                                        ">" .
                                        htmlspecialchars($status) .
                                        "</option>";
                                }
                            } catch (Exception $e) {
                                error_log(
                                    "Error loading issue statuses: " .
                                        $e->getMessage()
                                );
                                echo '<option value="">Error loading statuses</option>';
                            } ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="transmittal_discipline"><?php _e(
                            "Discipline",
                            "cftp_admin"
                        ); ?> *</label>
                        <select id="transmittal_discipline" name="transmittal_discipline" class="form-select transmittal-discipline-select" required>
                            <?php try {
                                $helper = new \ProjectSend\Classes\TransmittalHelper();
                                echo $helper->generateDropdownHtmlWithAbbr(
                                    "discipline",
                                    $existing_transmittal_data["discipline"] ??
                                        "",
                                    true,
                                    true
                                );
                            } catch (Exception $e) {
                                error_log(
                                    "Error loading disciplines: " .
                                        $e->getMessage()
                                );
                                echo '<option value="">Error loading disciplines</option>';
                            } ?>
                        </select>
                        <p class="field_note form-text"><?php _e(
                            "All files in this transmittal will use this discipline.",
                            "cftp_admin"
                        ); ?></p>
                    </div>

                    <div class="form-group">
                        <label for="transmittal_deliverable_type"><?php _e(
                            "Deliverable Type",
                            "cftp_admin"
                        ); ?> *</label>
                        <select id="transmittal_deliverable_type" name="transmittal_deliverable_type" class="form-select transmittal-deliverable-type-select" required>
                            <option value=""><?php _e(
                                "Select Discipline First",
                                "cftp_admin"
                            ); ?></option>
                            <?php if (
                                !empty(
                                    $existing_transmittal_data["discipline"]
                                ) &&
                                !empty(
                                    $existing_transmittal_data[
                                        "deliverable_type"
                                    ]
                                )
                            ) {
                                try {
                                    $helper = new \ProjectSend\Classes\TransmittalHelper();
                                    $deliverable_types = $helper->getDeliverableTypesByDiscipline(
                                        $existing_transmittal_data["discipline"]
                                    );
                                    foreach ($deliverable_types as $type) {
                                        $selected =
                                            $type["deliverable_type"] ==
                                            ($existing_transmittal_data[
                                                "deliverable_type"
                                            ] ??
                                                "")
                                                ? " selected"
                                                : "";
                                        $display_text = !empty(
                                            $type["abbreviation"]
                                        )
                                            ? $type["deliverable_type"] .
                                                " (" .
                                                $type["abbreviation"] .
                                                ")"
                                            : $type["deliverable_type"];
                                        echo '<option value="' .
                                            htmlspecialchars(
                                                $type["deliverable_type"]
                                            ) .
                                            '"' .
                                            $selected .
                                            ">" .
                                            htmlspecialchars($display_text) .
                                            "</option>";
                                    }
                                } catch (Exception $e) {
                                    error_log(
                                        "Error loading deliverable types: " .
                                            $e->getMessage()
                                    );
                                }
                            } ?>
                        </select>
                        <p class="field_note form-text"><?php _e(
                            "All files in this transmittal will use this deliverable type.",
                            "cftp_admin"
                        ); ?></p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="comments"><?php _e(
                            "Transmittal Comments*",
                            "cftp_admin"
                        ); ?></label>
                        <textarea id="comments" 
                                     name="comments" 
                                     class="form-control"
                                     rows="3"
                                     required
                                     placeholder="<?php _e(
                                         "Enter any additional comments",
                                         "cftp_admin"
                                     ); ?>"><?php echo htmlspecialchars(
    $existing_transmittal_data["comments"] ?? ""
); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="file_cc_addresses"><?php _e(
                            "CC Recipients",
                            "cftp_admin"
                        ); ?></label>
                        <textarea name="file_cc_addresses" id="file_cc_addresses" class="form-control" rows="3" placeholder="<?php _e(
                            "Enter email addresses separated by commas",
                            "cftp_admin"
                        ); ?>"><?php echo htmlspecialchars(
    $existing_transmittal_data["file_cc_addresses"] ?? ""
); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="file_bcc_addresses"><?php _e(
                            "BCC Recipients",
                            "cftp_admin"
                        ); ?></label>
                        <textarea name="file_bcc_addresses" id="file_bcc_addresses" class="form-control" rows="3" placeholder="<?php _e(
                            "Enter email addresses separated by commas",
                            "cftp_admin"
                        ); ?>"><?php echo htmlspecialchars(
    $existing_transmittal_data["file_bcc_addresses"] ?? ""
); ?></textarea>
                        <p class="field_note form-text"><?php _e(
                            "These email addresses will receive a hidden copy of the notification for this specific transmittal.",
                            "cftp_admin"
                        ); ?></p>
                    </div>

                    <h3><?php _e("Assignments", "cftp_admin"); ?></h3>
                    
                    <div class="form-group">
                      
                            <?php
                            // Get all groups for transmittal assignment
                            $me = new \ProjectSend\Classes\Users(
                                CURRENT_USER_ID
                            );

                            foreach ($transmittal_groups as $id => $name) { ?>
                                <option value="<?php echo html_output($id); ?>">
                                    <?php echo html_output($name); ?>
                                </option>
                            <?php }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="transmittal_clients"><?php _e(
                            "Project Contacts",
                            "cftp_admin"
                        ); ?></label>
                        <select class="form-select select2 none" multiple="multiple" 
                                 id="transmittal_clients" name="transmittal_clients[]" 
                                 data-placeholder="<?php _e(
                                     "Confirm or revise Project Contacts. Type to search.",
                                     "cftp_admin"
                                 ); ?>">
                            <?php
                            // Get all clients for transmittal assignment
                            if (
                                $me->shouldLimitUploadTo() &&
                                !empty($me->limit_upload_to)
                            ) {
                                $transmittal_clients = file_editor_get_clients_by_ids(
                                    $me->limit_upload_to
                                );
                            } else {
                                $transmittal_clients = file_editor_get_all_clients();
                            }

                            foreach ($transmittal_clients as $id => $name) { ?>
                                <option value="<?php echo html_output($id); ?>">
                                    <?php echo html_output($name); ?>
                                </option>
                            <?php }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="transmittal_categories"><?php _e(
                            "Categories",
                            "cftp_admin"
                        ); ?></label>
                        <select class="form-select select2 none" multiple="multiple" 
                                 id="transmittal_categories" name="transmittal_categories[]" 
                                 data-placeholder="<?php _e(
                                     "Confirm or revise Categories.",
                                     "cftp_admin"
                                 ); ?>">
                            <?php if (!empty($get_categories["arranged"])) {
                                $generate_categories_options = generate_categories_options(
                                    $get_categories["arranged"],
                                    0,
                                    []
                                );
                                echo render_categories_options(
                                    $generate_categories_options,
                                    ["selected" => [], "ignore" => []]
                                );
                            } ?>
                        </select>
                        <p class="form-text text-muted"><?php _e(
                            "Please select one category to assign to this transmittal",
                            "cftp_admin"
                        ); ?></p>
                    </div>

                    <div class="divider"></div>
                </div>
            </div>

        <?php // Add Collapse/Expand All buttons before the file list

            if (count($editable) > 1) { ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="file-controls">
                        <h3><?php _e("File Editor", "cftp_admin"); ?></h3>
                        <div class="collapse-expand-controls">
                            <button type="button" class="btn btn-sm btn-secondary" id="expand-all-files">
                                <i class="fa fa-expand" aria-hidden="true"></i> <?php _e(
                                    "Expand All",
                                    "cftp_admin"
                                ); ?>
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" id="collapse-all-files">
                                <i class="fa fa-compress" aria-hidden="true"></i> <?php _e(
                                    "Collapse All",
                                    "cftp_admin"
                                ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php
        // Remove the duplicate assignments section that was below
        $me = new \ProjectSend\Classes\Users(CURRENT_USER_ID);
        if ($me->shouldLimitUploadTo() && !empty($me->limit_upload_to)) {
            $clients = file_editor_get_clients_by_ids($me->limit_upload_to);
            $groups = file_editor_get_groups_by_members($me->limit_upload_to);
        } else {
            $clients = file_editor_get_all_clients();
            $groups = file_editor_get_all_groups();
        }

        foreach ($editable as $file_id) {
            clearstatcache();
            $file = new ProjectSend\Classes\Files($file_id);
            if ($file->recordExists()) {
                if ($file->existsOnDisk()) { ?>
                    <div class="file_editor_wrapper">
                        <div class="row">
                            <div class="col-12">
                                <div class="file_title">
                                    <button type="button" class="btn btn-md btn-secondary toggle_file_editor">
                                        <i class="fa fa-chevron-right" aria-hidden="true"></i>
                                    </button>
                                    <p><?php
                                    // Remove file extension for display
                                    $display_filename = pathinfo(
                                        $file->filename_original,
                                        PATHINFO_FILENAME
                                    );
                                    echo htmlspecialchars($display_filename);
                                    ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="row file_editor">
                            <div class="col-12">
                                <div class="row gx-5">
                                    <div class="col">
                                        <div class="file_data">
                                            <h3><?php _e(
                                                "File information",
                                                "cftp_admin"
                                            ); ?></h3>
                                            <input type="hidden" name="file[<?php echo $i; ?>][id]" value="<?php echo $file->id; ?>" />
                                            <input type="hidden" name="file[<?php echo $i; ?>][original]" value="<?php echo $file->filename_original; ?>" />
                                            <input type="hidden" name="file[<?php echo $i; ?>][file]" value="<?php echo $file->filename_on_disk; ?>" />

                                            <?php if (
                                                CURRENT_USER_LEVEL != 0
                                            ): ?>
                                                
                                                <div class="form-group">
                                                    <label for="revision_number_<?php echo $i; ?>"><?php _e(
    "Revision",
    "cftp_admin"
); ?> *</label>
                                                    <input type="text" name="file[<?php echo $i; ?>][revision_number]" id="revision_number_<?php echo $i; ?>" class="form-control"
                                                           value="<?php echo htmlspecialchars(
                                                               $file->revision_number ??
                                                                   ""
                                                           ); ?>" 
                                                           placeholder="<?php _e(
                                                               "Enter Revision",
                                                               "cftp_admin"
                                                           ); ?>" required /> 
                                                </div>

                                                <div class="form-group">
                                                    <label for="document_title_<?php echo $i; ?>"><?php _e(
    "Document Title",
    "cftp_admin"
); ?></label>
                                                    <input type="text" id="document_title_<?php echo $i; ?>" name="file[<?php echo $i; ?>][document_title]" class="form-control"
                                                           value="<?php echo htmlspecialchars(
                                                               html_entity_decode(
                                                                   $file->document_title ??
                                                                       "",
                                                                   ENT_QUOTES,
                                                                   "UTF-8"
                                                               )
                                                           ); ?>"  
                                                           placeholder="<?php _e(
                                                               "Enter Document Title",
                                                               "cftp_admin"
                                                           ); ?>" />
                                                </div>
                                                
                                            <?php endif; ?>

                                            <div class="form-group">
                                                <label><?php _e(
                                                    "File Name",
                                                    "cftp_admin"
                                                ); ?></label>
                                                <input type="text" name="file[<?php echo $i; ?>][name]" 
                                                       value="<?php echo pathinfo(
                                                           $file->title,
                                                           PATHINFO_FILENAME
                                                       ); ?>" 
                                                       class="form-control" 
                                                       placeholder="<?php _e(
                                                           "Enter here the required file name",
                                                           "cftp_admin"
                                                       ); ?>" />
                                            </div>

                                            <div class="form-group">
                                                <label><?php _e(
                                                    "File Comments",
                                                    "cftp_admin"
                                                ); ?></label>
                                                <textarea id="file_comments_<?php echo $file->id; ?>" name="file[<?php echo $i; ?>][file_comments]" 
                                                        class="<?php if (
                                                            get_option(
                                                                "files_file_comments_use_ckeditor"
                                                            ) == 1
                                                        ) {
                                                            echo "ckeditor";
                                                        } ?> form-control textarea_file_comments" 
                                                        placeholder="<?php _e(
                                                            "Optional, enter any comments about this file",
                                                            "cftp_admin"
                                                        ); ?>"><?php if (
    !empty($file->file_comments)
) {
    echo $file->file_comments;
} ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (CURRENT_USER_LEVEL != 0) { ?>
                                        <div class="col assigns">
                                            <div class="file_data">
                                                <h3><?php _e(
                                                    "Additional Information",
                                                    "cftp_admin"
                                                ); ?></h3>

                                                <div class="form-group">
                                                    <label for="client_document_number_<?php echo $i; ?>"><?php _e(
    "Client Document Number",
    "cftp_admin"
); ?></label>
                                                    <input type="text" 
                                                           id="client_document_number_<?php echo $i; ?>" 
                                                           name="file[<?php echo $i; ?>][client_document_number]" 
                                                           class="form-control"
                                                           value="<?php echo htmlspecialchars(
                                                               $file->client_document_number ??
                                                                   ""
                                                           ); ?>"  
                                                           placeholder="<?php _e(
                                                               "Enter Client Document Number",
                                                               "cftp_admin"
                                                           ); ?>" />
                                                    <p class="field_note form-text"><?php _e(
                                                        "Optional: Enter the client's reference number for this document.",
                                                        "cftp_admin"
                                                    ); ?></p>
                                                </div>

                                                <div class="form-group">
                                                    <label for="custom_download_<?php echo $i; ?>"><?php _e(
    "Custom Download Alias",
    "cftp_admin"
); ?></label>
                                                    <?php foreach (
                                                        $file->getCustomDownloads()
                                                        as $j =>
                                                            $custom_download
                                                    ) {
                                                        $trans = __(
                                                            "Enter a custom download link.",
                                                            "cftp_admin"
                                                        );
                                                        $custom_download_uri = get_option(
                                                            "custom_download_uri"
                                                        );
                                                        if (
                                                            !$custom_download_uri
                                                        ) {
                                                            $custom_download_uri =
                                                                BASE_URI .
                                                                "custom-download.php?link=";
                                                        }
                                                        echo <<<EOL
                                                            <div class="input-group">
                                                                <input type="hidden" value="{$custom_download["link"]}" name="file[$i][custom_downloads][$j][id]" />
                                                                <input type="text" name="file[$i][custom_downloads][$j][link]"
                                                                    id="custom_download_input_{$i}_{$j}"
                                                                    value="{$custom_download["link"]}"
                                                                    class="form-control"
                                                                    placeholder="$trans" />
                                                                <a href="#" class="input-group-text" onclick="copyTextToClipboard('$custom_download_uri' + document.getElementById('custom_download_input_{$i}_{$j}').value);">
                                                                    <i class="fa fa-copy" style="cursor: pointer"></i>
                                                                </a>
                                                            </div>
EOL;
                                                    } ?>
                                                    <p class="field_note form-text">
                                                        <?php echo sprintf(
                                                            __(
                                                                'Optional: enter an alias to use on the custom download link. Ej: "my-first-file" will let you download this file from %s'
                                                            ),
                                                            BASE_URI .
                                                                "custom-download.php?link=my-first-file"
                                                        ); ?>
                                                    </p>
                                                </div>

                                                <h3><?php _e(
                                                    "File Settings",
                                                    "cftp_admin"
                                                ); ?></h3>

                                                <div class="checkbox">
                                                    <label for="hid_checkbox_<?php echo $i; ?>">
                                                        <input type="checkbox" class="checkbox_setting_hidden" id="hid_checkbox_<?php echo $i; ?>" 
                                                                    name="file[<?php echo $i; ?>][hidden]" value="1" /> 
                                                        <?php _e(
                                                            "Hidden (This file will not send notifications or show in client files list)",
                                                            "cftp_admin"
                                                        ); ?>
                                                    </label>
                                                </div>

                                                <div class="checkbox">
                                                    <label for="issue_override_checkbox_<?php echo $i; ?>">
                                                        <input type="checkbox" class="checkbox_setting_issue_override" id="issue_override_checkbox_<?php echo $i; ?>" 
                                                                    name="file[<?php echo $i; ?>][issue_status_override]" value="1"
                                                                    <?php if (
                                                                        isset(
                                                                            $file->issue_status_override
                                                                        ) &&
                                                                        $file->issue_status_override ==
                                                                            1
                                                                    ) {
                                                                        echo " checked";
                                                                    } ?> /> 
                                                        <?php _e(
                                                            "Issue Status Override (Use this issue status for this file only)",
                                                            "cftp_admin"
                                                        ); ?>
                                                    </label>
                                                </div>

                                                <div class="form-group issue-status-override-field" id="issue_override_field_<?php echo $i; ?>" 
     style="display: <?php echo isset($file->issue_status_override) &&
     $file->issue_status_override == 1
         ? "block"
         : "none"; ?>; margin-top: 10px;">
    <label for="custom_issue_status_<?php echo $i; ?>"><?php _e(
    "Custom Issue Status",
    "cftp_admin"
); ?></label>
    <select id="custom_issue_status_<?php echo $i; ?>" name="file[<?php echo $i; ?>][custom_issue_status]" class="form-select">
        <option value=""><?php _e(
            "Select Custom Issue Status",
            "cftp_admin"
        ); ?></option>
        <?php try {
            $helper = new \ProjectSend\Classes\TransmittalHelper();
            $statuses = $helper->getDropdownOptions("issue_status");
            foreach ($statuses as $status) {
                $selected = "";
                // If override is enabled and this status matches the file's current issue_status, select it
                if (
                    isset($file->issue_status_override) &&
                    $file->issue_status_override == 1 &&
                    $status == ($file->issue_status ?? "")
                ) {
                    $selected = " selected";
                }
                echo '<option value="' .
                    htmlspecialchars($status) .
                    '"' .
                    $selected .
                    ">" .
                    htmlspecialchars($status) .
                    "</option>";
            }
        } catch (Exception $e) {
            error_log("Error loading issue statuses: " . $e->getMessage());
            echo '<option value="">Error loading statuses</option>';
        } ?>
    </select>
    <p class="field_note form-text"><?php _e(
        "This will override the transmittal-level issue status for this specific file.",
        "cftp_admin"
    ); ?></p>
</div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>

                                <?php
                                // UPDATED: Apply to All Files buttons
                                $copy_buttons = [];
                                if (count($editable) > 1) {
                                    if (CURRENT_USER_LEVEL != 0) {
                                        // Hidden status setting
                                        $copy_buttons["hidden"] = [
                                            "label" => __(
                                                "Hidden status",
                                                "cftp_admin"
                                            ),
                                            "class" => "copy-hidden-settings",
                                            "data" => [
                                                "copy-from" =>
                                                    "hid_checkbox_" . $i,
                                            ],
                                        ];

                                        // File Comments Button
                                        $copy_buttons["file_comments"] = [
                                            "label" => __(
                                                "File Comments",
                                                "cftp_admin"
                                            ),
                                            "class" => "copy-file-comments",
                                            "data" => [
                                                "copy-from" =>
                                                    "file_comments_" .
                                                    $file->id,
                                            ],
                                        ];

                                        // Document Title Button
                                        $copy_buttons["document_title"] = [
                                            "label" => __(
                                                "Document Title",
                                                "cftp_admin"
                                            ),
                                            "class" => "copy-document-title",
                                            "data" => [
                                                "copy-from" =>
                                                    "document_title_" . $i,
                                            ],
                                        ];

                                        // Client Document Number
                                        $copy_buttons["client_doc_number"] = [
                                            "label" => __(
                                                "Client Document Number",
                                                "cftp_admin"
                                            ),
                                            "class" => "copy-client-doc-number",
                                            "data" => [
                                                "copy-from" =>
                                                    "client_document_number_" .
                                                    $i,
                                            ],
                                        ];

                                        // Revision Button
                                        $copy_buttons["revision"] = [
                                            "label" => __(
                                                "Revision",
                                                "cftp_admin"
                                            ),
                                            "class" => "copy-revision",
                                            "data" => [
                                                "copy-from" =>
                                                    "revision_number_" . $i,
                                            ],
                                        ];

                                        // Custom Download Alias Button
                                        $copy_buttons["custom_download"] = [
                                            "label" => __(
                                                "Custom Download Alias",
                                                "cftp_admin"
                                            ),
                                            "class" => "copy-custom-download",
                                            "data" => [
                                                "copy-from" =>
                                                    "custom_download_" . $i,
                                            ],
                                        ];

                                        // Issue Status Override Button
                                        $copy_buttons["issue_override"] = [
                                            "label" => __(
                                                "Issue Status Override",
                                                "cftp_admin"
                                            ),
                                            "class" => "copy-issue-override",
                                            "data" => [
                                                "copy-from" =>
                                                    "issue_override_checkbox_" .
                                                    $i,
                                            ],
                                        ];

                                        // Custom Issue Status Button
                                        $copy_buttons["custom_issue_status"] = [
                                            "label" => __(
                                                "Custom Issue Status",
                                                "cftp_admin"
                                            ),
                                            "class" =>
                                                "copy-custom-issue-status",
                                            "data" => [
                                                "copy-from" =>
                                                    "custom_issue_status_" . $i,
                                            ],
                                        ];
                                    }

                                    if (count($copy_buttons) > 0) { ?>
                                        <footer>
                                            <div class="row">
                                                <div class="col">
                                                    <h3><?php _e(
                                                        "Apply to all files",
                                                        "cftp_admin"
                                                    ); ?></h3>
                                                    <?php foreach (
                                                        $copy_buttons
                                                        as $id => $button
                                                    ) {

                                                        $disabled_class =
                                                            isset(
                                                                $button[
                                                                    "disabled"
                                                                ]
                                                            ) &&
                                                            $button["disabled"]
                                                                ? " disabled"
                                                                : "";
                                                        $disabled_attr =
                                                            isset(
                                                                $button[
                                                                    "disabled"
                                                                ]
                                                            ) &&
                                                            $button["disabled"]
                                                                ? " disabled"
                                                                : "";
                                                        $title_attr =
                                                            isset(
                                                                $button[
                                                                    "disabled"
                                                                ]
                                                            ) &&
                                                            $button["disabled"]
                                                                ? ' title="Coming soon - database field needs to be created"'
                                                                : "";
                                                        ?>
                                                        <button type="button" class="btn btn-sm btn-pslight mb-2 <?php echo $button[
                                                            "class"
                                                        ] .
                                                            $disabled_class; ?>"<?php echo $disabled_attr .
    $title_attr; ?>
                                                            <?php if (
                                                                !isset(
                                                                    $button[
                                                                        "disabled"
                                                                    ]
                                                                ) ||
                                                                !$button[
                                                                    "disabled"
                                                                ]
                                                            ) {
                                                                foreach (
                                                                    $button[
                                                                        "data"
                                                                    ]
                                                                    as $key =>
                                                                        $value
                                                                ) {
                                                                    echo " data-" .
                                                                        $key .
                                                                        '="' .
                                                                        $value .
                                                                        '"';
                                                                }
                                                            } ?>
                                                        >
                                                            <?php echo $button[
                                                                "label"
                                                            ]; ?>
                                                            <?php if (
                                                                isset(
                                                                    $button[
                                                                        "disabled"
                                                                    ]
                                                                ) &&
                                                                $button[
                                                                    "disabled"
                                                                ]
                                                            ) {
                                                                echo " <small>(Coming Soon)</small>";
                                                            } ?>
                                                        </button>
                                                    <?php
                                                    } ?>
                                                </div>
                                            </div>
                                        </footer>
                                    <?php }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php $i++;} else {$msg = sprintf(
                        __("File not found on disk: %s"),
                        $file->filename_on_disk
                    );
                    echo system_message("danger", $msg);}
            }
        }
        ?>
    </div>

    <div class="after_form_buttons">
        <button type="submit" name="save" class="btn btn-wide btn-primary" id="upload-continue"><?php _e(
            "Save",
            "cftp_admin"
        ); ?></button>
    </div>

    <style>
    /* CSS for read-only transmittal number field */
    .readonly-field {
        background-color: #f8f9fa !important;
        color: #6c757d !important;
        cursor: not-allowed !important;
        border-color: #dee2e6 !important;
    }

    .readonly-field:focus {
        background-color: #f8f9fa !important;
        border-color: #dee2e6 !important;
        box-shadow: none !important;
    }

    /* Light red border for required fields */
    input[required], 
    select[required], 
    textarea[required] {
        border: 1px solid #ffb3b3 !important;
    }

    input[required]:focus, 
    select[required]:focus, 
    textarea[required]:focus {
        border-color: #ff8080 !important;
        box-shadow: 0 0 0 0.2rem rgba(255, 179, 179, 0.25) !important;
    }

    /* Ensure the required styling doesn't interfere with readonly fields */
    .readonly-field[required] {
        border-color: #dee2e6 !important;
    }

    .readonly-field[required]:focus {
        border-color: #dee2e6 !important;
        box-shadow: none !important;
    }

    /* Issue Status Override styling */
    .issue-status-override-field {
        background-color: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 4px;
        padding: 15px;
        margin-top: 10px;
    }

    .checkbox_setting_issue_override:checked + label {
        font-weight: bold;
        color: #856404;
    }

    .issue-status-override-field select {
        border-color: #ffc107;
    }

    .issue-status-override-field label {
        font-weight: bold;
        color: #856404;
    }

    .issue-status-override-field .field_note {
        font-style: italic;
        color: #856404;
        margin-top: 5px;
    }

    /* File toggle buttons styling */
    .toggle_file_editor {
        background-color: #4c2ab6 !important;
    }

    /* Expand/Collapse all buttons styling */
    #expand-all-files,
    #collapse-all-files {
        background-color: #4c2ab6 !important;
    }

    /* File controls styling */
    .file-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .collapse-expand-controls {
        display: flex;
        gap: 10px;
    }

    /* File title styling */
    .file_title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
    }

    .file_title p {
        margin: 0;
        font-weight: bold;
        color: #333;
    }

    /* Message styling for automation feedback */
    #filename-parsing-message {
        margin: 15px 0;
        padding: 12px;
        border-radius: 6px;
        border: 1px solid;
        font-size: 14px;
    }

    #filename-parsing-message i {
        margin-right: 8px;
    }
    </style>

   <?php if (CURRENT_USER_LEVEL != 0): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Collapse/Expand All functionality
            const expandAllBtn = document.getElementById('expand-all-files');
            const collapseAllBtn = document.getElementById('collapse-all-files');
            
            if (expandAllBtn && collapseAllBtn) {
                expandAllBtn.addEventListener('click', function() {
                    // Expand all file editors
                    document.querySelectorAll('.file_editor').forEach(function(editor) {
                        editor.style.display = 'block';
                    });
                    
                    // Update toggle buttons to show "expanded" state
                    document.querySelectorAll('.toggle_file_editor').forEach(function(button) {
                        const icon = button.querySelector('i');
                        if (icon) {
                            icon.classList.remove('fa-chevron-right');
                            icon.classList.add('fa-chevron-down');
                        }
                    });
                });
                
                collapseAllBtn.addEventListener('click', function() {
                    // Collapse all file editors
                    document.querySelectorAll('.file_editor').forEach(function(editor) {
                        editor.style.display = 'none';
                    });
                    
                    // Update toggle buttons to show "collapsed" state
                    document.querySelectorAll('.toggle_file_editor').forEach(function(button) {
                        const icon = button.querySelector('i');
                        if (icon) {
                            icon.classList.remove('fa-chevron-down');
                            icon.classList.add('fa-chevron-right');
                        }
                    });
                });
            }

            // Individual file toggle functionality
            document.querySelectorAll('.toggle_file_editor').forEach(function(button) {
                button.addEventListener('click', function() {
                    const wrapper = this.closest('.file_editor_wrapper');
                    const editor = wrapper.querySelector('.file_editor');
                    const icon = this.querySelector('i');
                    
                    if (editor.style.display === 'none' || editor.style.display === '') {
                        // Expand this file
                        editor.style.display = 'block';
                        icon.classList.remove('fa-chevron-right');
                        icon.classList.add('fa-chevron-down');
                    } else {
                        // Collapse this file
                        editor.style.display = 'none';
                        icon.classList.remove('fa-chevron-down');
                        icon.classList.add('fa-chevron-right');
                    }
                });
            });

            // Initialize all files as collapsed on page load
            document.querySelectorAll('.file_editor').forEach(function(editor) {
                editor.style.display = 'none';
            });

            // **CRITICAL AUTOMATION TRIGGER** - Auto-populate from filename parsing - only run on first load, not when editing
            if (!document.querySelector('input[name="project_number"]').value) {
                parseFilenamesAndPopulate();
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            // JavaScript for transmittal-level discipline/deliverable type dependency
            const transmittalDisciplineSelect = document.getElementById('transmittal_discipline');
            const transmittalDeliverableTypeSelect = document.getElementById('transmittal_deliverable_type');
            
            if (transmittalDisciplineSelect && transmittalDeliverableTypeSelect) {
                transmittalDisciplineSelect.addEventListener('change', function() {
                    const selectedDiscipline = this.value;
                    transmittalDeliverableTypeSelect.innerHTML = '<option value="">Loading...</option>';
                    transmittalDeliverableTypeSelect.disabled = true;

                    if (!selectedDiscipline) {
                        transmittalDeliverableTypeSelect.innerHTML = '<option value="">Select Discipline First</option>';
                        transmittalDeliverableTypeSelect.disabled = false;
                        return;
                    }

                    fetch(`get_deliverable_types.php?discipline=${encodeURIComponent(selectedDiscipline)}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            transmittalDeliverableTypeSelect.innerHTML = '<option value="">Select Deliverable Type</option>';

                            if (Array.isArray(data) && data.length > 0) {
                                data.forEach(item => {
                                    const option = document.createElement('option');
                                    option.value = item.value;
                                    option.textContent = item.text;
                                    transmittalDeliverableTypeSelect.appendChild(option);
                                });
                            } else {
                                transmittalDeliverableTypeSelect.innerHTML = '<option value="">No types available</option>';
                            }

                            transmittalDeliverableTypeSelect.disabled = false;
                        })
                        .catch(error => {
                            console.error('Error fetching deliverable types:', error);
                            transmittalDeliverableTypeSelect.innerHTML = '<option value="">Error loading types</option>';
                            transmittalDeliverableTypeSelect.disabled = false;
                        });
                });

                // Trigger change event on page load if discipline is already selected
                if (transmittalDisciplineSelect.value) {
                    transmittalDisciplineSelect.dispatchEvent(new Event('change'));
                }
            }

            // Enhanced manual category selection when discipline/deliverable changes
            const disciplineSelect = document.getElementById('transmittal_discipline');
            const deliverableSelect = document.getElementById('transmittal_deliverable_type');
            const categorySelect = document.getElementById('transmittal_categories');
            
            // Function to suggest category based on current discipline + deliverable selection
            function suggestCategory() {
                const discipline = disciplineSelect?.value;
                const deliverable = deliverableSelect?.value;
                
                if (discipline && deliverable && categorySelect) {
                    // Look for category option that matches the discipline + deliverable pattern
                    const options = categorySelect.querySelectorAll('option');
                    
                    for (let option of options) {
                        const optionText = option.textContent.toLowerCase();
                        const disciplineLower = discipline.toLowerCase();
                        const deliverableLower = deliverable.toLowerCase();
                        
                        // Check if option text contains both discipline and deliverable
                        if (optionText.includes(disciplineLower) && optionText.includes(deliverableLower)) {
                            if (typeof $ !== 'undefined' && $(categorySelect).data('select2')) {
                                // Select2 multi-select
                                let currentValues = $(categorySelect).val() || [];
                                if (!currentValues.includes(option.value)) {
                                    currentValues.push(option.value);
                                    $(categorySelect).val(currentValues).trigger('change');
                                }
                            } else {
                                // Standard multi-select
                                if (!option.selected) {
                                    option.selected = true;
                                }
                            }
                            break;
                        }
                    }
                }
            }
            
            // Suggest category when deliverable type changes
            if (deliverableSelect) {
                deliverableSelect.addEventListener('change', suggestCategory);
            }

            // Issue Status Override functionality
            document.querySelectorAll('.checkbox_setting_issue_override').forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    const fileIndex = this.id.replace('issue_override_checkbox_', '');
                    const overrideField = document.getElementById('issue_override_field_' + fileIndex);
                    const customStatusSelect = document.getElementById('custom_issue_status_' + fileIndex);
                    
                    if (this.checked) {
                        // Show the override field
                        overrideField.style.display = 'block';
                        customStatusSelect.required = true;
                    } else {
                        // Hide the override field and clear selection
                        overrideField.style.display = 'none';
                        customStatusSelect.value = '';
                        customStatusSelect.required = false;
                    }
                });
                
                // Check initial state on page load
                const fileIndex = checkbox.id.replace('issue_override_checkbox_', '');
                const overrideField = document.getElementById('issue_override_field_' + fileIndex);
                if (checkbox.checked) {
                    overrideField.style.display = 'block';
                }
            });

            // "Apply to All Files" button functionality
            
            // Hidden Status
            document.querySelectorAll('.copy-hidden-settings').forEach(function(button) {
                button.addEventListener('click', function() {
                    const sourceId = this.getAttribute('data-copy-from');
                    const sourceCheckbox = document.getElementById(sourceId);
                    if (!sourceCheckbox) return;
                    
                    // Apply to all hidden checkboxes
                    document.querySelectorAll('[id^="hid_checkbox_"]').forEach(function(checkbox) {
                        checkbox.checked = sourceCheckbox.checked;
                    });
                    
                    alert('Hidden status applied to all files');
                });
            });

            // Revision
            document.querySelectorAll('.copy-revision').forEach(function(button) {
                button.addEventListener('click', function() {
                    const sourceId = this.getAttribute('data-copy-from');
                    const sourceInput = document.getElementById(sourceId);
                    if (!sourceInput) return;
                    
                    const sourceValue = sourceInput.value;
                    
                    // Apply to all revision inputs
                    document.querySelectorAll('[id^="revision_number_"]').forEach(function(input) {
                        input.value = sourceValue;
                    });
                    
                    alert('Revision number applied to all files');
                });
            });

            // Custom Download Alias
            document.querySelectorAll('.copy-custom-download').forEach(function(button) {
                button.addEventListener('click', function() {
                    // Find the first custom download input as source
                    const firstCustomDownloadInput = document.querySelector('[id^="custom_download_input_1_"]');
                    if (!firstCustomDownloadInput) return;
                    
                    const sourceValue = firstCustomDownloadInput.value;
                    
                    // Apply to all custom download inputs (but with unique suffixes)
                    document.querySelectorAll('[id^="custom_download_input_"]').forEach(function(input, index) {
                        if (sourceValue && index > 0) {
                            // Add index suffix to make unique
                            input.value = sourceValue + '-' + (index + 1);
                        } else {
                            input.value = sourceValue;
                        }
                    });
                    
                    alert('Custom download alias applied to all files (with unique suffixes)');
                });
            });

            // Client Document Number
            document.querySelectorAll('.copy-client-doc-number').forEach(function(button) {
                button.addEventListener('click', function() {
                    const sourceId = this.getAttribute('data-copy-from');
                    const sourceInput = document.getElementById(sourceId);
                    if (!sourceInput) return;
                    
                    const sourceValue = sourceInput.value;
                    
                    // Apply to all client document number inputs
                    document.querySelectorAll('[id^="client_document_number_"]').forEach(function(input) {
                        input.value = sourceValue;
                    });
                    
                    alert('Client Document Number applied to all files');
                });
            });

            // Issue Status Override
            document.querySelectorAll('.copy-issue-override').forEach(function(button) {
                button.addEventListener('click', function() {
                    const sourceId = this.getAttribute('data-copy-from');
                    const sourceCheckbox = document.getElementById(sourceId);
                    const sourceFileIndex = sourceId.replace('issue_override_checkbox_', '');
                    const sourceCustomStatus = document.getElementById('custom_issue_status_' + sourceFileIndex);
                    
                    if (!sourceCheckbox) return;
                    
                    const isChecked = sourceCheckbox.checked;
                    const customStatusValue = sourceCustomStatus ? sourceCustomStatus.value : '';
                    
                    // Apply to all issue override checkboxes and custom status dropdowns
                    document.querySelectorAll('[id^="issue_override_checkbox_"]').forEach(function(checkbox) {
                        const fileIndex = checkbox.id.replace('issue_override_checkbox_', '');
                        const overrideField = document.getElementById('issue_override_field_' + fileIndex);
                        const customStatusSelect = document.getElementById('custom_issue_status_' + fileIndex);

                        // Set checkbox state
                        checkbox.checked = isChecked;

                        // Show/hide override field
                        if (isChecked) {
                            overrideField.style.display = 'block';
                            customStatusSelect.required = true;
                            if (customStatusValue) {
                                customStatusSelect.value = customStatusValue;
                            }
                        } else {
                            overrideField.style.display = 'none';
                            customStatusSelect.value = '';
                            customStatusSelect.required = false;
                        }
                    });
                    
                    alert('Issue Status Override applied to all files');
                });
            });

            // File Comments
            document.querySelectorAll('.copy-file-comments').forEach(function(button) {
                button.addEventListener('click', function() {
                    const sourceId = this.getAttribute('data-copy-from');
                    const sourceTextarea = document.getElementById(sourceId);
                    if (!sourceTextarea) return;

                    const sourceValue = sourceTextarea.value;

                    // Apply to all file comments textareas
                    document.querySelectorAll('[id^="file_comments_"]').forEach(function(textarea) {
                        textarea.value = sourceValue;
                        // If using CKEditor, update the editor content
                        if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances[textarea.id]) {
                            CKEDITOR.instances[textarea.id].setData(sourceValue);
                        }
                    });
                    
                    alert('File comments applied to all files');
                });
            });

            // Document Title
            document.querySelectorAll('.copy-document-title').forEach(function(button) {
                button.addEventListener('click', function() {
                    const sourceId = this.getAttribute('data-copy-from');
                    const sourceInput = document.getElementById(sourceId);
                    if (!sourceInput) return;

                    const sourceValue = sourceInput.value;

                    // Apply to all document title inputs
                    document.querySelectorAll('[id^="document_title_"]').forEach(function(input) {
                        input.value = sourceValue;
                    });

                    alert('Document title applied to all files');
                });
            });

            // Custom Issue Status
            document.querySelectorAll('.copy-custom-issue-status').forEach(function(button) {
                button.addEventListener('click', function() {
                    const sourceId = this.getAttribute('data-copy-from');
                    const sourceSelect = document.getElementById(sourceId);
                    if (!sourceSelect) return;

                    const sourceValue = sourceSelect.value;
                    
                    // Apply to all custom issue status selects
                    document.querySelectorAll('[id^="custom_issue_status_"]').forEach(function(select) {
                        select.value = sourceValue;
                    });
                    
                    alert('Custom Issue Status applied to all files');
                });
            });
        });

        // **CORE AUTOMATION FUNCTION** - This is the main filename parsing automation
        function parseFilenamesAndPopulate() {
            // Get all file original names from the form
            const fileInputs = document.querySelectorAll('input[name*="[original]"]');
            
            if (fileInputs.length === 0) return;
            
            // Use the first file to extract project-level data
            const firstFilename = fileInputs[0].value;
            
            // Send AJAX request to parse filename
            fetch('parse_filename.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    filename: firstFilename,
                    action: 'parse_filename'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.parsed_successfully) {
                    // Populate transmittal-level fields
                    if (data.project_number) {
                        const projectNumberField = document.getElementById('project_number');
                        if (projectNumberField && !projectNumberField.value) {
                            projectNumberField.value = data.project_number;
                        }
                    }
                    
                    // Auto-populate project name from centralized table
                    if (data.project_number) {
                        fetch(`api/get_project_name.php?project_number=${encodeURIComponent(data.project_number)}`)
                        .then(response => response.json())
                        .then(projectData => {
                            if (projectData.success && projectData.project_name) {
                                const projectNameField = document.getElementById('project_name');
                                if (projectNameField && !projectNameField.value) {
                                    projectNameField.value = projectData.project_name;
                                    showParsingMessage('success', `Project name populated: ${projectData.project_name}`);
                                }
                            }
                        })
                        .catch(error => console.error('Error fetching project name:', error));
                    }
                    
                    if (data.discipline) {
                        const disciplineField = document.getElementById('transmittal_discipline');
                        if (disciplineField && !disciplineField.value) {
                            disciplineField.value = data.discipline;
                            // Trigger change event to load deliverable types
                            disciplineField.dispatchEvent(new Event('change'));
                            
                            // Set deliverable type after a short delay to allow loading
                            if (data.deliverable_type) {
                                setTimeout(() => {
                                    const deliverableField = document.getElementById('transmittal_deliverable_type');
                                    if (deliverableField) {
                                        deliverableField.value = data.deliverable_type;
                                    }
                                }, 500);
                            }
                        }
                    }

                    // Auto-populate Project Contacts based on project number  
                    if (data.project_number) {
                        // Look up the group_id from the project_number
                        fetch(`api/get_group_id.php?project_number=${encodeURIComponent(data.project_number)}`)
                        .then(response => response.json())
                        .then(groupData => {
                            console.log('Group data received:', groupData);
                            if (groupData.success && groupData.group_id) {
                                // Now that we have the group ID, fetch the members using updated API
                                fetch(`api/get_group_members.php?group_id=${groupData.group_id}`)
                                .then(response => {
                                    console.log('Members API response status:', response.status);
                                    return response.json();
                                })
                                .then(membersData => {
                                    console.log('Members data received:', membersData);
                                    if (membersData.success && membersData.members && membersData.members.length > 0) {
                                        const clientsSelect = document.getElementById('transmittal_clients');
                                        if (clientsSelect) {
                                            // Clear existing selections if using Select2
                                            if (typeof $ !== 'undefined' && $(clientsSelect).data('select2')) {
                                                $(clientsSelect).val(null).trigger('change');
                                            }

                                            // Select the group members
                                            let selectedValues = [];
                                            membersData.members.forEach(memberId => {
                                                const option = clientsSelect.querySelector(`option[value="${memberId}"]`);
                                                if (option) {
                                                    option.selected = true;
                                                    selectedValues.push(memberId.toString());
                                                    console.log(`Selected contact: ${option.textContent} (ID: ${memberId})`);
                                                } else {
                                                    console.warn(`No option found for member ID: ${memberId}`);
                                                }
                                            });
                                            
                                            // Trigger Select2 update if available
                                            if (typeof $ !== 'undefined' && $(clientsSelect).data('select2')) {
                                                $(clientsSelect).val(selectedValues).trigger('change');
                                            }
                                            
                                            showParsingMessage('success', `Auto-selected ${membersData.count} project contacts`);
                                        }
                                    } else {
                                        console.error('Error fetching group members:', membersData.error || 'No members found');
                                        showParsingMessage('info', 'No project contacts found for this project');
                                    }
                                })
                                .catch(error => {
                                    console.error('Error fetching group members:', error);
                                    showParsingMessage('warning', 'Could not load project contacts');
                                });
                            } else {
                                console.warn('Group ID not found for project number:', data.project_number);
                                showParsingMessage('info', 'No group found for this project number');
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching group ID:', error);
                            showParsingMessage('warning', 'Could not look up project group');
                        });
                    }

                    // Auto-populate categories based on parsed discipline + deliverable
                    if (data.discipline && data.deliverable_type) {
                        setTimeout(() => {
                            const categoryField = document.getElementById('transmittal_categories');
                            const selectedDiscipline = data.discipline;
                            const selectedDeliverable = data.deliverable_type;
                            
                            if (categoryField) {
                                // Make AJAX call to find the specific category ID for this discipline + deliverable combination
                                fetch('api/get_category_by_discipline_deliverable.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        discipline: selectedDiscipline,
                                        deliverable_type: selectedDeliverable
                                    })
                                })
                                .then(response => response.json())
                                .then(categoryData => {
                                    if (categoryData.success && categoryData.category_id) {
                                        if (typeof $ !== 'undefined' && $(categoryField).data('select2')) {
                                            let currentValues = $(categoryField).val() || [];
                                            if (!currentValues.includes(categoryData.category_id.toString())) {
                                                currentValues.push(categoryData.category_id.toString());
                                                $(categoryField).val(currentValues).trigger('change');
                                            }
                                        }
                                        showParsingMessage('success', `Selected specific category: ${categoryData.category_name}`);
                                    }
                                })
                                .catch(error => {
                                    console.error('Error finding specific category:', error);
                                });
                            }
                        }, 750);
                    }

                    // Show success message
                    let message = `Filename parsed successfully! Project: ${data.project_number}, Discipline: ${data.discipline}`;
                    if (data.category_ids && data.category_ids.length > 0) {
                        message += ', Categories auto-selected';
                    }
                    showParsingMessage('success', message);

                } else {
                    // Show partial success or info message
                    let message = 'Could not fully parse filename. ';
                    if (data.project_number) {
                        message += `Found project number: ${data.project_number}`;
                    } else {
                        message += 'Please fill in project information manually.';
                    }
                    showParsingMessage('info', message);
                }
            })
            .catch(error => {
                console.error('Error parsing filename:', error);
                showParsingMessage('warning', 'Could not parse filename automatically. Please fill in fields manually.');
            });
        }

        function showParsingMessage(type, message) {
            // Create or update parsing message div
            let messageDiv = document.getElementById('filename-parsing-message');
            if (!messageDiv) {
                messageDiv = document.createElement('div');
                messageDiv.id = 'filename-parsing-message';
                messageDiv.style.margin = '10px 0';
                messageDiv.style.padding = '10px';
                messageDiv.style.borderRadius = '4px';
                
                // Insert after the first form header
                const firstHeader = document.querySelector('h3');
                if (firstHeader) {
                    firstHeader.parentNode.insertBefore(messageDiv, firstHeader.nextSibling);
                }
            }
            
            // Set message style based on type
            const styles = {
                'success': { background: '#d4edda', border: '#c3e6cb', color: '#155724' },
                'info': { background: '#d1ecf1', border: '#bee5eb', color: '#0c5460' },
                'warning': { background: '#fff3cd', border: '#ffeaa7', color: '#856404' }
            };
            
            const style = styles[type] || styles['info'];
            messageDiv.style.backgroundColor = style.background;
            messageDiv.style.borderColor = style.border;
            messageDiv.style.color = style.color;
            messageDiv.style.border = `1px solid ${style.border}`;
            
            messageDiv.innerHTML = `<i class="fa fa-info-circle"></i> ${message}`;
            
            // Auto-hide after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                }, 5000);
            }
        }
    </script>
        <?php endif; ?>
    <?php endif; ?>
</form>