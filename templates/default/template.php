<?php
/*
Template name: Default (Clean Transmittal Filter)
*/
$ld = "cftp_template";

define("TEMPLATE_RESULTS_PER_PAGE", get_option("pagination_results_per_page"));
define("TEMPLATE_THUMBNAILS_WIDTH", "50");
define("TEMPLATE_THUMBNAILS_HEIGHT", "50");

$filter_by_category = isset($_GET["category"]) ? $_GET["category"] : null;
$filter_by_transmittal = isset($_GET["transmittal"])
    ? $_GET["transmittal"]
    : null;

$current_url = get_form_action_with_existing_parameters("index.php");

include_once ROOT_DIR . "/templates/common.php";

$window_title = __("File downloads", "cftp_template");
$page_id = "default_template";
$body_class = ["template", "default-template", "hide_title"];

// Flash errors
if (!$count) {
    if (isset($no_results_error)) {
        switch ($no_results_error) {
            case "search":
                $flash->error(
                    __(
                        "Your search keywords returned no results.",
                        "cftp_admin"
                    )
                );
                break;
            case "filter":
                $flash->error(
                    __(
                        "The filters you selected returned no results.",
                        "cftp_admin"
                    )
                );
                break;
        }
    } else {
        $flash->warning(__("There are no files available.", "cftp_admin"));
    }
}

// Header buttons - only show for logged-in users, not clients
if (current_user_can_upload() && CURRENT_USER_LEVEL != 0) {
    $header_action_buttons = [
        [
            "url" => BASE_URI . "upload.php",
            "label" => __("Upload file", "cftp_admin"),
        ],
    ];
}

// Get available transmittals for dropdown
function get_user_transmittals_for_filter($client_id)
{
    global $dbh;

    // Get transmittals from files assigned to this client (including via groups)
    // Group by project_number + transmittal_number for unique transmittals
    $query =
        "SELECT DISTINCT f.project_number, f.transmittal_number, COUNT(*) as file_count,
                     MAX(f.transmittal_name) as transmittal_name,
                     MAX(f.project_name) as project_name
              FROM " .
        TABLE_FILES .
        " f
              LEFT JOIN " .
        TABLE_FILES_RELATIONS .
        " fr ON f.id = fr.file_id
              WHERE (f.user_id = :client_id OR fr.client_id = :client_id2)
              AND f.transmittal_number IS NOT NULL 
              AND f.transmittal_number != ''
              AND fr.hidden = '0'
              GROUP BY f.project_number, f.transmittal_number 
              ORDER BY f.project_number DESC, f.transmittal_number DESC";

    $statement = $dbh->prepare($query);
    $statement->execute([
        ":client_id" => $client_id,
        ":client_id2" => $client_id,
    ]);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

// Search + filters bar data
$search_form_action = "index.php";
$filters_form = [
    "action" => "",
    "items" => [],
];
// Add transmittal filter dropdown
$user_transmittals = get_user_transmittals_for_filter($client_info["id"]);
if (!empty($user_transmittals)) {
    $transmittal_filter = [];
    foreach ($user_transmittals as $transmittal) {
        // Use transmittal_number directly since it already contains the full identifier

        $unique_key = $transmittal["transmittal_number"];

        // Clean and simple: just the key and file count
        $display_name =
            $unique_key . " (" . $transmittal["file_count"] . " files)";

        $transmittal_filter[$unique_key] = $display_name;
    }

    $filters_form["items"]["transmittal"] = [
        "current" => $filter_by_transmittal,
        "placeholder" => [
            "value" => "",
            "label" => __("Filter by transmittal", "cftp_admin"),
        ],
        "options" => $transmittal_filter,
    ];
}

// Add category filter (always show, just like transmittal)
$selected_parent = isset($_GET["category"]) ? [$_GET["category"]] : [];
$category_filter = [];

// Try to get categories if they exist
if (!empty($cat_ids) && !empty($get_categories["arranged"])) {
    $generate_categories_options = generate_categories_options(
        $get_categories["arranged"],
        0,
        $selected_parent,
        "include",
        $cat_ids
    );
    $format_categories_options = format_categories_options(
        $generate_categories_options
    );
    foreach ($format_categories_options as $key => $category) {
        $category_filter[$category["id"]] = $category["label"];
    }
}

// ALWAYS add the category filter to the form (just like transmittal filter)
$filters_form["items"]["category"] = [
    "current" => isset($_GET["category"]) ? $_GET["category"] : null,
    "placeholder" => [
        "value" => "0",
        "label" => empty($category_filter)
            ? __("No categories available", "cftp_admin")
            : __("Filter by category", "cftp_admin"),
    ],
    "options" => $category_filter, // Will be empty array if no categories, but dropdown still shows
];

// Results count and form actions
$elements_found_count = isset($count_for_pagination)
    ? $count_for_pagination
    : 0;
$bulk_actions_items = [
    "none" => __("Select action", "cftp_admin"),
    "zip" => __("Download zipped", "cftp_admin"),
];

// Include layout files
include_once ADMIN_VIEWS_DIR . DS . "header.php";

include_once LAYOUT_DIR . DS . "search-filters-bar.php";

include_once LAYOUT_DIR . DS . "breadcrumbs.php";

include_once LAYOUT_DIR . DS . "folders-nav.php";
?>
<!-- Clean transmittal filter banner - only shows when filtering -->
<?php if (!empty($filter_by_transmittal)): ?>
<div class="alert alert-info" style="margin: 20px 0; border-radius: 5px;">
    <strong>📁 Transmittal Filter Active:</strong> 
    Showing files from transmittal <strong><?php
    // Clean the transmittal display - check if it has the duplicate pattern
    $display_transmittal = $filter_by_transmittal;
    if (
        preg_match(
            '/^([A-Z0-9]+)-\1-T-(\d+)$/',
            $filter_by_transmittal,
            $matches
        )
    ) {
        // Remove duplicate: DOM2504-DOM2504-T-0002 becomes DOM2504-T-0002
        $display_transmittal = $matches[1] . "-T-" . $matches[2];
    }
    // Also check if GLOBALS has a clean version
    if (isset($GLOBALS["TRANSMITTAL_NUMBER"])) {
        $display_transmittal = $GLOBALS["TRANSMITTAL_NUMBER"];
    }
    echo htmlspecialchars($display_transmittal);
    ?></strong>
    <?php // Just show file count - keep it simple

    foreach ($user_transmittals as $transmittal) {
        $unique_key = $transmittal["transmittal_number"];
        if ($unique_key == $display_transmittal) {
            echo " (" . $transmittal["file_count"] . " files)";
            break;
        }
    } ?>
    | <a href="index.php" class="alert-link" style="text-decoration: none;">🔄 Show All Files</a>
</div>
<?php endif; ?>


<form action="" name="files_list" method="get" class="form-inline batch_actions">
    <div class="row">
        <div class="col-12">
            <?php include_once LAYOUT_DIR . DS . "form-counts-actions.php"; ?>

            <?php if (isset($count) && $count > 0) {
                // Generate the clean table - no transmittal column clutter
                $table = new \ProjectSend\Classes\Layout\Table([
                    "id" => "files_list",
                    "class" => "footable table",
                    "origin" => CLIENT_VIEW_FILE_LIST_URL_PATH,
                ]);

                // Standard columns - clean and simple
                $thead_columns = [
                    [
                        "select_all" => true,
                        "attributes" => [
                            "class" => ["td_checkbox"],
                        ],
                    ],
                    [
                        "sortable" => true,
                        "sort_url" => "filename",
                        "content" => __("Title", "cftp_admin"),
                    ],
                    [
                        "content" => __("Type", "cftp_admin"),
                        "hide" => "phone",
                    ],
                    [
                        "sortable" => true,
                        "sort_url" => "description",
                        "content" => __("Description", "cftp_admin"),
                        "hide" => "phone",
                        "attributes" => [
                            "class" => ["description"],
                        ],
                    ],
                    [
                        "content" => __("Size", "cftp_admin"),
                        "hide" => "phone",
                    ],
                    [
                        "sortable" => true,
                        "sort_url" => "timestamp",
                        "sort_default" => true,
                        "content" => __("Date", "cftp_admin"),
                    ],
                    [
                        "content" => __("Expiry", "cftp_admin"),
                        "hide" => "phone",
                    ],
                    [
                        "content" => __("Preview", "cftp_admin"),
                        "hide" => "phone,tablet",
                    ],
                    [
                        "content" => __("Download", "cftp_admin"),
                        "hide" => "phone",
                    ],
                ];

                $table->thead($thead_columns);

                foreach ($available_files as $file_id) {
                    $file = new \ProjectSend\Classes\Files($file_id);

                    $table->addRow();

                    /** Checkbox */
                    $checkbox =
                        $file->expired == false
                            ? '<input type="checkbox" name="files[]" value="' .
                                $file->id .
                                '" class="batch_checkbox" />'
                            : null;

                    /** File title - clean, no extra links */
                    $title_content =
                        '<a href="' .
                        $file->download_link .
                        '" target="_blank">' .
                        $file->title .
                        "</a>";
                    if ($file->title != $file->filename_original) {
                        $title_content .=
                            "<br><small>" .
                            $file->filename_original .
                            "</small>";
                    }
                    if (file_is_image($file->full_path)) {
                        $dimensions = $file->getDimensions();
                        if (!empty($dimensions)) {
                            $title_content .=
                                '<br><div class="file_meta"><small>' .
                                $dimensions["width"] .
                                " x " .
                                $dimensions["height"] .
                                " px</small></div>";
                        }
                    }

                    /** Extension */
                    $extension_cell =
                        '<span class="badge bg-success label_big">' .
                        $file->extension .
                        "</span>";

                    /** Date */
                    $date = format_date($file->uploaded_date);

                    /** Expiration */
                    if ($file->expires == "1") {
                        if ($file->expired == false) {
                            $badge_class = "bg-primary";
                        } else {
                            $badge_class = "bg-danger";
                        }
                        $badge_label = date(
                            get_option("timeformat"),
                            strtotime($file->expiry_date)
                        );
                    } else {
                        $badge_class = "bg-success";
                        $badge_label = __("Never", "cftp_template");
                    }
                    $expiration_cell =
                        '<span class="badge ' .
                        $badge_class .
                        ' label_big">' .
                        $badge_label .
                        "</span>";

                    /** Thumbnail */
                    $preview_cell = "";
                    if ($file->expired == false) {
                        if ($file->isImage()) {
                            $thumbnail = make_thumbnail(
                                $file->full_path,
                                null,
                                TEMPLATE_THUMBNAILS_WIDTH,
                                TEMPLATE_THUMBNAILS_HEIGHT
                            );
                            if (!empty($thumbnail["thumbnail"]["url"])) {
                                $preview_cell =
                                    '
                                        <a href="#" class="get-preview" data-url="' .
                                    BASE_URI .
                                    "process.php?do=get_preview&file_id=" .
                                    $file->id .
                                    '">
                                            <img src="' .
                                    $thumbnail["thumbnail"]["url"] .
                                    '" class="thumbnail" alt="' .
                                    $file->title .
                                    '" />
                                        </a>';
                            }
                        } else {
                            if ($file->embeddable) {
                                $preview_cell =
                                    '<button class="btn btn-warning btn-sm btn-wide get-preview" data-url="' .
                                    BASE_URI .
                                    "process.php?do=get_preview&file_id=" .
                                    $file->id .
                                    '">' .
                                    __("Preview", "cftp_admin") .
                                    "</button>";
                            }
                        }
                    }

                    /** Download */
                    if ($file->expired == true) {
                        $download_btn_class = "btn btn-danger btn-sm disabled";
                        $download_text = __("File expired", "cftp_template");
                    } else {
                        $download_btn_class = "btn btn-primary btn-sm btn-wide";
                        $download_text = __("Download", "cftp_template");
                    }
                    $download_cell =
                        '<a href="' .
                        $file->download_link .
                        '" class="' .
                        $download_btn_class .
                        '" target="_blank">' .
                        $download_text .
                        "</a>";

                    // Clean table cells - no transmittal column clutter
                    $tbody_cells = [
                        [
                            "content" => $checkbox,
                        ],
                        [
                            "content" => $title_content,
                            "attributes" => [
                                "class" => ["file_name"],
                            ],
                        ],
                        [
                            "content" => $extension_cell,
                            "attributes" => [
                                "class" => ["extra"],
                            ],
                        ],
                        [
                            "content" => $file->description,
                            "attributes" => [
                                "class" => ["description"],
                            ],
                        ],
                        [
                            "content" => $file->size_formatted,
                        ],
                        [
                            "content" => $date,
                        ],
                        [
                            "content" => $expiration_cell,
                        ],
                        [
                            "content" => $preview_cell,
                            "attributes" => [
                                "class" => ["extra"],
                            ],
                        ],
                        [
                            "content" => $download_cell,
                            "attributes" => [
                                "class" => ["text-center"],
                            ],
                        ],
                    ];

                    foreach ($tbody_cells as $cell) {
                        $table->addCell($cell);
                    }

                    $table->end_row();
                }

                echo $table->render();
            } ?>
        </div>
    </div>
</form>
<?php
if (!empty($table)) {
    // PAGINATION
    $pagination = new \ProjectSend\Classes\Layout\Pagination();
    echo $pagination->make([
        "link" => "my_files/index.php",
        "current" => $pagination_page,
        "item_count" => $count_for_pagination,
        "items_per_page" => TEMPLATE_RESULTS_PER_PAGE,
    ]);
}

render_footer_text();

render_json_variables();

render_assets("js", "footer");
render_assets("css", "footer");

render_custom_assets("body_bottom");
?>
</body>

</html>