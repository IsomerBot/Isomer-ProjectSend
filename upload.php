<?php
/**
 * Uploading files from computer, step 1
 * Shows the plupload form that handles the uploads and moves
 * them to a temporary folder. When the queue is empty, the user
 * is redirected to step 2, and prompted to enter the name,
 * description and client for each uploaded file.
 */
require_once "bootstrap.php";

$active_nav = "files";

$page_title = __("Upload files", "cftp_admin");

$page_id = "upload_form";

$allowed_levels = [9, 8, 7];
if (get_option("clients_can_upload") == 1) {
    $allowed_levels[] = 0;
}
log_in_required($allowed_levels);

if (LOADED_LANG != "en") {
    $plupload_lang_file =
        "vendor/moxiecode/plupload/js/i18n/" . LOADED_LANG . ".js";
    if (file_exists(ROOT_DIR . DS . $plupload_lang_file)) {
        add_asset(
            "js",
            "plupload_language",
            BASE_URI . "/" . $plupload_lang_file,
            "3.1.5",
            "footer"
        );
    }
}

message_no_clients();

// if (defined("UPLOAD_MAX_FILESIZE")) {
//     $msg =
//         __(
//             // "Click on Add files to select all the files that you want to upload, and then click continue. On the next step, you will be able to set a name and description for each uploaded file. Remember that the maximum allowed file size (in mb.) is ",
//             "cftp_admin"
//         ) .
//         " <strong>" .
//         UPLOAD_MAX_FILESIZE .
//         "</strong>";
//     $flash->info($msg);
// }

include_once ADMIN_VIEWS_DIR . DS . "header.php";
$chunk_size = get_option("upload_chunk_size");
?>

<div class="row">
    <div class="col-12">
        <div class="alert alert-info">
            <strong><?php _e("File Naming Format:", "cftp_admin"); ?></strong>
            <?php _e(
                "Please upload files in the file name format for proper automations: ",
                "cftp_admin"
            ); ?>
            <code>AAA####-AA-AAA-####</code>
            <?php if (defined("UPLOAD_MAX_FILESIZE")) { ?>
                <br><br>
                <strong><?php _e("File Size Limit:", "cftp_admin"); ?></strong>
                <?php _e(
                    "Maximum allowed file size (in mb.) is ",
                    "cftp_admin"
                ); ?>
                <strong><?php echo UPLOAD_MAX_FILESIZE; ?></strong>
            <?php } ?>
        </div>
        
        <!-- Upload form container -->
        <!-- <div id="uploader">
            <p>Your browser doesn't have Flash, Silverlight or HTML5 support.</p>
        </div> -->
        
        <style>
        /* Hide upload control buttons */
        .plupload_start, .plupload_stop {
            display: none !important;
        }

        /* Enhanced remove button styling */
        .custom-remove-btn {
            display: inline-block !important;
            color: #d9534f !important;
            background: #fff !important;
            border: 1px solid #d9534f !important;
            padding: 2px 6px !important;
            margin-right: 8px !important;
            cursor: pointer !important;
            font-weight: bold !important;
            font-size: 14px !important;
            text-decoration: none !important;
            border-radius: 3px !important;
            line-height: 1 !important;
        }

        .custom-remove-btn:hover {
            background: #d9534f !important;
            color: #fff !important;
        }

        /* Style the file list for better visibility */
        .plupload_filelist .plupload_file {
            border-bottom: 1px solid #eee;
            padding: 8px 0;
        }

        .plupload_filelist .plupload_file:hover {
            background-color: #f9f9f9;
        }
        </style>
        
        <script type="text/javascript">
        $(function() {
            var uploadedFileIds = []; // Track uploaded file IDs for redirect
            
            // Function to add remove buttons to all files
            function addRemoveButtons(up) {
                setTimeout(function() {
                    $('#uploader_filelist li[id^="o_"]').each(function() {
                        var $fileRow = $(this);
                        var fileId = $fileRow.attr('id');
                        
                        // Skip if already processed
                        if ($fileRow.find('.custom-remove-btn').length > 0) {
                            return;
                        }
                        
                        // Find the filename span
                        var $fileNameSpan = $fileRow.find('.plupload_file_name span');
                        
                        if ($fileNameSpan.length > 0) {
                            // Create remove button
                            var $removeBtn = $('<span class="custom-remove-btn" title="Remove file">×</span>');
                            
                            // Add click handler
                            $removeBtn.on('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                
                                var file = up.getFile(fileId);
                                if (file) {
                                    up.removeFile(file);
                                    
                                    // Remove from our tracking array if it was uploaded
                                    uploadedFileIds = uploadedFileIds.filter(function(id) {
                                        return true; // Keep for now, will be filtered on redirect
                                    });
                                } else {
                                    $fileRow.remove();
                                }
                                
                                // Re-add remove buttons after removal
                                addRemoveButtons(up);
                            });
                            
                            // Insert the remove button before the filename
                            $fileNameSpan.before($removeBtn);
                        }
                    });
                }, 100);
            }
            
            $("#uploader").pluploadQueue({
                runtimes: 'html5',
                url: 'includes/upload.process.php',
                chunk_size: '<?php echo !empty($chunk_size)
                    ? $chunk_size
                    : "1"; ?>mb',
                rename: true,
                dragdrop: true,
                multipart: true,
                
                filters: {
                    max_file_size: '<?php echo UPLOAD_MAX_FILESIZE; ?>mb'
                    <?php if (
                        !user_can_upload_any_file_type(CURRENT_USER_ID)
                    ) { ?>,
                        mime_types: [{
                            title: "Allowed files",
                            extensions: "<?php echo get_option(
                                "allowed_file_types"
                            ); ?>"
                        }]
                    <?php } ?>
                },
                
                init: {
                    FilesAdded: function(up, files) {
                        addRemoveButtons(up);
                    },
                    
                    FilesRemoved: function(up, files) {
                        // Files removed from queue
                    },
                    
                    FileUploaded: function(up, file, response) {
                        // Clean the response - remove any HTML that might be mixed in
                        var cleanResponse = response.response.trim();
                        
                        // Find the JSON part (should start with { and end with })
                        var jsonStart = cleanResponse.indexOf('{');
                        var jsonEnd = cleanResponse.lastIndexOf('}');
                        
                        if (jsonStart !== -1 && jsonEnd !== -1 && jsonEnd > jsonStart) {
                            cleanResponse = cleanResponse.substring(jsonStart, jsonEnd + 1);
                        }
                        
                        try {
                            var result = JSON.parse(cleanResponse);
                            
                            if (result.OK && result.info && result.info.id) {
                                uploadedFileIds.push(result.info.id);
                            }
                        } catch (e) {
                            // Handle parse error silently
                        }
                    },
                    
                   UploadComplete: function(up, files) {
    debugLog('All uploads complete. Files processed: ' + files.length);
    debugLog('Uploaded file IDs: ' + uploadedFileIds.join(','));
    
    // Simply check if we have any uploaded file IDs at all
    if (uploadedFileIds.length > 0) {
        var redirectUrl = 'files-edit.php?ids=' + uploadedFileIds.join(',');
        debugLog('Redirecting to: ' + redirectUrl);
        window.location.href = redirectUrl;
    } else {
        debugLog('ERROR: No uploaded files to process');
        alert('Error: No files were successfully uploaded. Please try again.');
    }
}
                }
            });
        });
        </script>
        
        <?php include_once FORMS_DIR . DS . "upload.php"; ?>
    </div>
</div>

<?php include_once ADMIN_VIEWS_DIR . DS . "footer.php";
?>
