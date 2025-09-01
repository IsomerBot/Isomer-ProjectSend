<?php
/**
 * This file generates the main menu for the header on the back-end
 * and also for the default template.
 */
$items = [];

/**
 * Items for system users
 */
if (current_role_in([9, 8, 7])) {
    $items["dashboard"] = [
        "nav" => "dashboard",
        "level" => [9, 8, 7],
        "main" => [
            "label" => __("Dashboard", "cftp_admin"),
            "icon" => "tachometer",
            "link" => "dashboard.php",
        ],
    ];

    $items[] = "separator";

    $items["files"] = [
        "nav" => "files",
        "level" => [9, 8, 7],
        "main" => [
            "label" => __("Files", "cftp_admin"),
            "icon" => "file",
        ],
        "sub" => [
            [
                "label" => __("Upload", "cftp_admin"),
                "link" => "upload.php",
            ],
            [
                "divider" => true,
            ],
            [
                "label" => __("Manage Files", "cftp_admin"),
                "link" => "manage-files.php",
            ],
            [
                "label" => __("Manage Downloads", "cftp_admin"),
                "link" => "manage-downloads.php",
            ],
            [
                "label" => __("Find Orphan Files", "cftp_admin"),
                "link" => "import-orphans.php",
            ],
            [
                "divider" => true,
            ],
            [
                "label" => __("Categories", "cftp_admin"),
                "link" => "categories.php",
            ],
        ],
    ];

    $items["clients"] = [
        "nav" => "clients",
        "level" => [9, 8],
        "main" => [
            "label" => tm(__("Contacts", "cftp_admin")),
            "icon" => "address-card",
            "badge" => count_account_requests(),
        ],
        "sub" => [
            [
                "label" => __("Add New", "cftp_admin"),
                "link" => "clients-add.php",
            ],
            [
                "label" => tm(__("Manage Contacts", "cftp_admin")),
                "link" => "clients.php",
            ],
            [
                "divider" => true,
            ],
        ],
    ];

    $items["groups"] = [
        "nav" => "groups",
        "level" => [9, 8],
        "main" => [
            "label" => __("Projects", "cftp_admin"),
            "icon" => "th-large",
            "badge" => count_groups_requests_for_existing_clients(),
        ],
        "sub" => [
            [
                "label" => __("Add New", "cftp_admin"),
                "link" => "groups-add.php",
            ],
            [
                "label" => __("Manage Projects", "cftp_admin"),
                "link" => "groups.php",
            ],
            [
                "divider" => true,
            ],
        ],
    ];

    $items["users"] = [
        "nav" => "users",
        "level" => [9],
        "main" => [
            "label" => __("System Users", "cftp_admin"),
            "icon" => "users",
        ],
        "sub" => [
            [
                "label" => __("Add New", "cftp_admin"),
                "link" => "users-add.php",
            ],
            [
                "label" => __("Manage System Users", "cftp_admin"),
                "link" => "users.php",
                //'badge' => COUNT_USERS_INACTIVE,
            ],
        ],
    ];

    $items[] = "separator";

    $items["templates"] = [
        "nav" => "templates",
        "level" => [9],
        "main" => [
            "label" => __("Templates", "cftp_admin"),
            "icon" => "desktop",
        ],
        "sub" => [
            [
                "label" => __("Templates", "cftp_admin"),
                "link" => "templates.php",
            ],
        ],
    ];

    $items["options"] = [
        "nav" => "options",
        "level" => [9],
        "main" => [
            "label" => __("Options", "cftp_admin"),
            "icon" => "cog",
        ],
        "sub" => [
            [
                "label" => __("General Options", "cftp_admin"),
                "link" => "options.php?section=general",
            ],

            [
                "label" => __("Privacy", "cftp_admin"),
                "link" => "options.php?section=privacy",
            ],
            [
                "label" => __("E-mail Notifications", "cftp_admin"),
                "link" => "options.php?section=email",
            ],
            [
                "label" => __("Security", "cftp_admin"),
                "link" => "options.php?section=security",
            ],
            [
                "label" => __("Branding", "cftp_admin"),
                "link" => "options.php?section=branding",
            ],
            [
                "label" => __("External Login", "cftp_admin"),
                "link" => "options.php?section=external_login",
            ],
            [
                "label" => __("Scheduled Tasks (Cron)", "cftp_admin"),
                "link" => "options.php?section=cron",
            ],
        ],
    ];

    $items["emails"] = [
        "nav" => "emails",
        "level" => [9],
        "main" => [
            "label" => __("E-mail Templates", "cftp_admin"),
            "icon" => "envelope",
        ],
        "sub" => [
            // [
            //     "label" => __("Header / Footer", "cftp_admin"),
            //     "link" => "email-templates.php?section=header_footer",
            // ],
            // [
            //     "label" => __("New File by User", "cftp_admin"),
            //     "link" => "email-templates.php?section=new_files_by_user",
            // ],
            // [
            //     "label" => __("New file by client", "cftp_admin"),
            //     "link" => "email-templates.php?section=new_files_by_client",
            // ],
            [
                "label" => __("New Client (Welcome)", "cftp_admin"),
                "link" => "email-templates.php?section=new_client",
            ],
            // [
            //     "label" => __("New client (self-registered)", "cftp_admin"),
            //     "link" => "email-templates.php?section=new_client_self",
            // ],
            // [
            //     "label" => __("Approve client account", "cftp_admin"),
            //     "link" => "email-templates.php?section=account_approve",
            // ],
            // [
            //     "label" => __("Deny client account", "cftp_admin"),
            //     "link" => "email-templates.php?section=account_deny",
            // ],
            // [
            //     "label" => __("Client updated memberships", "cftp_admin"),
            //     "link" => "email-templates.php?section=client_edited",
            // ],
            // [
            //     "label" => __("New User (Welcome)", "cftp_admin"),
            //     "link" => "email-templates.php?section=new_user",
            // ],
            [
                "label" => __("Password Reset", "cftp_admin"),
                "link" => "email-templates.php?section=password_reset",
            ],
            [
                "label" => __("Login Authorization Code", "cftp_admin"),
                "link" => "email-templates.php?section=2fa_code",
            ],
        ],
    ];

    $items[] = "separator";

    $items["tools"] = [
        "nav" => "tools",
        "level" => [9],
        "main" => [
            "label" => __("Tools", "cftp_admin"),
            "icon" => "wrench",
        ],
        "sub" => [
            [
                "label" => __("Actions Log", "cftp_admin"),
                "link" => "actions-log.php",
            ],
            [
                "label" => __("Cron Log", "cftp_admin"),
                "link" => "cron-log.php",
            ],
            [
                "label" => __("Test Email Configuration", "cftp_admin"),
                "link" => "email-test.php",
            ],
            [
                "label" => __("Unblock IP", "cftp_admin"),
                "link" => "unblock-ip.php",
            ],
            [
                "label" => __("Custom HTML/CSS/JS", "cftp_admin"),
                "link" => "custom-assets.php",
            ],
        ],
    ];
}

// Items for clients
else {
    if (get_option("clients_can_upload") == 1) {
    }

    $items["upload"] = [
        "nav" => "upload",
        "level" => [9, 8, 7, 0],
        "main" => [
            "label" => __("View My Files", "cftp_admin"),
            "link" => CLIENT_VIEW_FILE_LIST_URL_PATH,
            "icon" => "th-list",
        ],
    ];
}

// Build the menu
$current_filename = parse_url(basename($_SERVER["REQUEST_URI"]));
$menu_output = "
    <div class='main_side_menu'>
        <ul class='main_menu' role='menu'>\n";

foreach ($items as $item) {
    if (!is_array($item) && $item == "separator") {
        $menu_output .= '<li class="separator"></li>';
        continue;
    }

    if (current_role_in($item["level"])) {
        $current =
            !empty($active_nav) && $active_nav == $item["nav"]
                ? "current_nav"
                : "";
        $badge = !empty($item["main"]["badge"])
            ? ' <span class="badge rounded-pill text-bg-dark">' .
                $item["main"]["badge"] .
                "</span>"
            : "";
        $icon = !empty($item["main"]["icon"])
            ? '<i class="fa fa-' .
                $item["main"]["icon"] .
                ' fa-fw" aria-hidden="true"></i>'
            : "";

        /** Top level tag */
        if (!isset($item["sub"])) {
            $format =
                "<li class='%s'>\n\t<a href='%s' class='nav_top_level'>%s<span class='menu_label'>%s%s</span></a>\n</li>\n";
            $menu_output .= sprintf(
                $format,
                $current,
                BASE_URI . $item["main"]["link"],
                $icon,
                $badge,
                $item["main"]["label"]
            );
        } else {
            $first_child = $item["sub"][0];
            $top_level_link = !empty($first_child) ? $first_child["link"] : "#";
            $format =
                "<li class='has_dropdown %s'>\n\t<a href='%s' class='nav_top_level'>%s<span class='menu_label'>%s%s</span></a>\n\t<ul class='dropdown_content'>\n";
            $menu_output .= sprintf(
                $format,
                $current,
                $top_level_link,
                $icon,
                $item["main"]["label"],
                $badge
            );
            /**
             * Submenu
             */
            foreach ($item["sub"] as $subitem) {
                $badge = !empty($subitem["badge"])
                    ? ' <span class="badge rounded-pill text-bg-dark">' .
                        $subitem["badge"] .
                        "</span>"
                    : "";
                $icon = !empty($subitem["icon"])
                    ? '<i class="fa fa-' .
                        $subitem["icon"] .
                        ' fa-fw" aria-hidden="true"></i>'
                    : "";
                if (!empty($subitem["divider"])) {
                    $menu_output .= "\t\t<li class='divider'></li>\n";
                } else {
                    $sub_active =
                        $subitem["link"] == $current_filename["path"]
                            ? "current_page"
                            : "";

                    if (isset($_GET["section"])) {
                        $parse = parse_url($subitem["link"], PHP_URL_QUERY);
                        if (!empty($parse)) {
                            parse_str($parse, $subitem_query);
                            if (isset($subitem_query["section"])) {
                                if (
                                    $subitem_query["section"] ==
                                    $_GET["section"]
                                ) {
                                    $sub_active = "current_page";
                                }
                            }
                        }
                    }

                    $format =
                        "\t\t<li class='%s'>\n\t\t\t<a href='%s'>%s<span class='submenu_label'>%s%s</span></a>\n\t\t</li>\n";
                    $menu_output .= sprintf(
                        $format,
                        $sub_active,
                        BASE_URI . $subitem["link"],
                        $icon,
                        $subitem["label"],
                        $badge
                    );
                }
            }
            $menu_output .= "\t</ul>\n</li>\n";
        }
    }
}

$menu_output .= "</ul></div>\n";

$menu_output = str_replace("'", '"', $menu_output);

// Print to screen
echo $menu_output;
