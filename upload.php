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

include_once ADMIN_VIEWS_DIR . DS . "header.php";
$chunk_size = get_option("upload_chunk_size");
?>

<!-- Ensure Plupload CSS is loaded -->
<link rel="stylesheet" type="text/css" href="vendor/moxiecode/plupload/js/plupload.queue.css" />

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
        <div id="uploader">
            <p>Your browser doesn't have Flash, Silverlight or HTML5 support.</p>
        </div>
        
        <style>
        /* Only hide upload control buttons */
        .plupload_start, .plupload_stop {
            display: none !important;
        }

        /* Simple remove button styling */
        .custom-remove-btn {
            display: inline-block !important;
            color: #666 !important;
            padding: 4px 8px !important;
            margin-right: 10px !important;
            cursor: pointer !important;
            font-weight: bold !important;
            font-size: 16px !important;
            text-decoration: none !important;
        }

        .custom-remove-btn:hover {
            background: #f0f0f0 !important;
            color: #333 !important;
            border-radius: 3px !important;
        }
        </style>
        
        <script type="text/javascript">
        $(document).ready(function() {
            $("#uploader").pluploadQueue({
                runtimes: 'html5',
                url: 'includes/upload.process.php',
                chunk_size: '<?php echo !empty($chunk_size)
                    ? $chunk_size
                    : "1"; ?>mb',
                rename: true,
                sortable: true,
                
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
                    PostInit: function(up) {
                        // Hide start/stop buttons
                        $('.plupload_start, .plupload_stop').hide();
                    },
                    
                    FilesAdded: function(up, files) {
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
                                        } else {
                                            $fileRow.remove();
                                        }
                                    });
                                    
                                    // Insert the remove button before the filename
                                    $fileNameSpan.before($removeBtn);
                                }
                            });
                        }, 500);
                    },
                    
                    Error: function(up, err) {
                        console.error('Upload error:', err);
                    }
                }
            });
        });
        </script>
    </div>
</div>

<?php include_once ADMIN_VIEWS_DIR . DS . "footer.php"; ?>
