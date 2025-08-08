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
            <!-- ADMIN USERS: Show full transmittal information form -->
            <div class="row">
                <div class="col-md-6">
                    <h3><?php _e(
                        "Transmittal Information",
                        "cftp_admin"
                    ); ?></h3>

                    <!-- Transmittal Number - Auto-generated (Read-only) -->
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

                    <!-- Project Name -->
                    <div class="form-group">
                        <label for="project_name"><?php _e(
                            "Project Name",
                            "cftp_admin"
                        ); ?>*</label>
                        <input type="text" name="project_name" id="project_name" class="form-control" 
                               value="<?php echo htmlspecialchars(
                                   $existing_transmittal_data["project_name"] ??
                                       ""
                               ); ?>"
                               placeholder="<?php _e(
                                   "Enter Project Name",
                                   "cftp_admin"
                               ); ?>" required />
                    </div>

                    <!-- Project Number -->
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

                    <!-- Package Description Manual Field -->
                    <div class="form-group">
                        <label for="package_description"><?php _e(
                            "Package Description",
                            "cftp_admin"
                        ); ?>*</label>
                        <input type="text" name="package_description" id="package_description" class="form-control" 
                               value="<?php echo htmlspecialchars(
                                   $existing_transmittal_data[
                                       "package_description"
                                   ] ?? ""
                               ); ?>"
                               placeholder="<?php _e(
                                   "Enter Package Description",
                                   "cftp_admin"
                               ); ?>" required />
                    </div>

                    <!-- Issue_Status Dropdown Field -->
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

                    <!-- NEW: Transmittal-level Discipline -->
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

                    <!-- NEW: Transmittal-level Deliverable Type -->
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


                    <!-- Transmittal Comments -->

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="comments"><?php _e(
                            "Transmittal Comments",
                            "cftp_admin"
                        ); ?></label>
                        <textarea id="comments" 
                                  name="comments" 
                                  class="form-control"
                                  rows="6"
                                  placeholder="<?php _e(
                                      "Enter any additional comments",
                                      "cftp_admin"
                                  ); ?>"><?php echo htmlspecialchars(
    $existing_transmittal_data["comments"] ?? ""
); ?></textarea>
                    </div>

                    <div class="divider"></div>
                </div>
            </div>

            <!-- BCC Email -->
            <div class="form-group">
                <label for="file_bcc_addresses"><?php _e(
                    "BCC Recipients",
                    "cftp_admin"
                ); ?></label>
                <textarea name="file_bcc_addresses" id="file_bcc_addresses" class="form-control" rows="5" placeholder="<?php _e(
                    "Enter email addresses separated by commas.",
                    "cftp_admin"
                ); ?>"><?php echo htmlspecialchars(
    $existing_transmittal_data["file_bcc_addresses"] ?? ""
); ?></textarea>
                <p class="field_note form-text"><?php _e(
                    "These email addresses will receive a hidden copy of the notification for this specific transmittal.",
                    "cftp_admin"
                ); ?></p>
            </div>

            <!-- CC Email -->
            <div class="form-group">
    <label for="file_cc_addresses"><?php _e(
        "CC Recipients",
        "cftp_admin"
    ); ?></label>
    <textarea name="file_cc_addresses" id="file_cc_addresses" class="form-control" rows="5" placeholder="<?php _e(
        "Enter email addresses separated by commas.",
        "cftp_admin"
    ); ?>"><?php echo htmlspecialchars(
    $existing_transmittal_data["file_cc_addresses"] ?? ""
); ?></textarea>
</div>

            <!-- NEW: Transmittal-level Client Assignment -->
            <div class="form-group">
                <h3><?php _e("Assignments", "cftp_admin"); ?></h3>
                <label for="transmittal_clients"><?php _e(
                    "Clients",
                    "cftp_admin"
                ); ?></label>
                <select class="form-select select2 none" multiple="multiple" 
                        id="transmittal_clients" name="transmittal_clients[]" 
                        data-placeholder="<?php _e(
                            "Select clients for this transmittal. Type to search.",
                            "cftp_admin"
                        ); ?>">
                    <?php
                    // Get all clients for transmittal assignment
                    $me = new \ProjectSend\Classes\Users(CURRENT_USER_ID);
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

            <!-- NEW: Transmittal-level Groups Assignment -->
            <div class="form-group">
                <label for="transmittal_groups"><?php _e(
                    "Groups",
                    "cftp_admin"
                ); ?></label>
                <select class="form-select select2 none" multiple="multiple" 
                        id="transmittal_groups" name="transmittal_groups[]" 
                        data-placeholder="<?php _e(
                            "Select groups for this transmittal. Type to search.",
                            "cftp_admin"
                        ); ?>">
                    <?php
                    // Get all groups for transmittal assignment
                    if (
                        $me->shouldLimitUploadTo() &&
                        !empty($me->limit_upload_to)
                    ) {
                        $transmittal_groups = file_editor_get_groups_by_members(
                            $me->limit_upload_to
                        );
                    } else {
                        $transmittal_groups = file_editor_get_all_groups();
                    }

                    foreach ($transmittal_groups as $id => $name) { ?>
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
                            "Select categories for this transmittal. Type to search.",
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
            </div>

        <?php
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
                                    <p><?php echo $file->filename_original; ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- File Information Section -->
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
                                                
                                                <!-- Revision Number Manual Field -->
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

                                                <!-- Document Title-->
                                                <div class="form-group">
                                                    <label for="document_title_<?php echo $i; ?>"><?php _e(
    "Document Title",
    "cftp_admin"
); ?></label>
                                                    <input type="text" id="document_title_<?php echo $i; ?>" name="file[<?php echo $i; ?>][document_title]" class="form-control"
                                                           value="<?php echo htmlspecialchars(
                                                               $file->document_title ??
                                                                   ""
                                                           ); ?>"   
                                                           placeholder="<?php _e(
                                                               "Enter Document Title",
                                                               "cftp_admin"
                                                           ); ?>" />
                                                </div>

                                                <!-- REMOVED: Discipline Field - moved to transmittal level -->
                                                <!-- REMOVED: Deliverable Type Field - moved to transmittal level -->
                                                
                                            <?php endif; ?>

                                            <!-- File Title  -->
                                            <div class="form-group">
                                                <label><?php _e(
                                                    "File Name",
                                                    "cftp_admin"
                                                ); ?></label>
                                                <input type="text" name="file[<?php echo $i; ?>][name]" value="<?php echo $file->title; ?>" class="form-control" 
                                                       placeholder="<?php _e(
                                                           "Enter here the required file name.",
                                                           "cftp_admin"
                                                       ); ?>" />
                                            </div>

                                            <!-- Custom Download Alias  -->
                                            <?php if (
                                                CURRENT_USER_LEVEL != 0 ||
                                                current_user_can_upload_public()
                                            ) { ?>
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
                                            <?php } ?>

                                             <!-- File Comments -->
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
                                                              "Optionally, enter here a comment for the file.",
                                                              "cftp_admin"
                                                          ); ?>"><?php if (
    !empty($file->file_comments)
) {
    echo $file->file_comments;
} ?></textarea>
                                            </div>

                                            <!-- Client Document Number -->
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
                                         </div>
                                    </div>

                                    <?php // UPDATED: Removed client and group assignments - now handled at transmittal level

                    if (CURRENT_USER_LEVEL != 0) { ?>
                                        <div class="col assigns">
                                            <div class="file_data">
                                                <h3><?php _e(
                                                    "File Settings",
                                                    "cftp_admin"
                                                ); ?></h3>

                                                <!-- Hidden Status -->
                                                
                                                <div class="checkbox">
                                                    <label for="hid_checkbox_<?php echo $i; ?>">
                                                        <input type="checkbox" class="checkbox_setting_hidden" id="hid_checkbox_<?php echo $i; ?>" 
                                                               name="file[<?php echo $i; ?>][hidden]" value="1" /> 
                                                        <?php _e(
                                                            "Hidden (will not send notifications or show into the files list)",
                                                            "cftp_admin"
                                                        ); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                    
                                    <div class="col">
                                    </div>
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

                                        // // File Comments Button
                                        // $copy_buttons["file_comments"] = [
                                        //     "label" => __(
                                        //         "File Comments",
                                        //         "cftp_admin"
                                        //     ),
                                        //     "class" => "copy-file-comments",
                                        //     "data" => [
                                        //         "copy-from" =>
                                        //             "file_comments_" . $i,
                                        //     ],
                                        //     "disabled" => true, // Placeholder
                                        // ];

                                        // Client Document Number (PLACEHOLDER - to be implemented)
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
                                            "disabled" => false, // Changed from true to false
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

    <!-- Styling for the form buttons -->
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
    </style>

    <?php if (CURRENT_USER_LEVEL != 0): ?>
    <!-- JavaScript only for admin users -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // NEW: JavaScript for transmittal-level discipline/deliverable type dependency
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

        // REMOVED: File-specific discipline/deliverable type JavaScript (no longer needed)

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

        // File Comments 
        document.querySelectorAll('.copy-file-comments').forEach(function(button) {
            button.addEventListener('click', function() {
                alert('File Comments feature coming soon - database field needs to be created first');
            });
        });

        // Client Document Number (now implemented)
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
    });
    </script>
    <?php endif; ?>
<?php endif; ?>
</form>