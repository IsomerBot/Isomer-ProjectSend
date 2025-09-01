<?php
/**
 * Show the form to add a new client.
 */
$allowed_levels = [9, 8];
require_once "bootstrap.php";
log_in_required($allowed_levels);

$active_nav = "clients";

$page_title = __("Add contact", "cftp_admin");

$page_id = "client_form";

$new_client = new \ProjectSend\Classes\Users();

include_once ADMIN_VIEWS_DIR . DS . "header.php";

// Set checkboxes as 1 to default them to checked when first entering the form
$client_arguments = [
    "notify_upload" => 1,
    "active" => 1,
    "notify_account" => 1,
    "require_password_change" => 1,
];

if ($_POST) {
    $form_errors = false;

    try {
        /**
         * Clean the posted form values to be used on the clients actions,
         * and again on the form if validation failed.
         */
        $client_arguments = [
            "username" => $_POST["username"],
            "password" => $_POST["password"],
            "name" => $_POST["name"],
            "email" => $_POST["email"],
            "address" => isset($_POST["address"]) ? $_POST["address"] : "",
            "phone" => isset($_POST["phone"]) ? $_POST["phone"] : "",
            "contact" => isset($_POST["contact"]) ? $_POST["contact"] : "",
            "notify_upload" => isset($_POST["notify_upload"]) ? 1 : 0,
            "notify_account" => isset($_POST["notify_account"]) ? 1 : 0,
            "active" => isset($_POST["active"]) ? 1 : 0,
            "require_password_change" => isset(
                $_POST["require_password_change"]
            )
                ? true
                : false,
            "type" => "new_client",
        ];

        // Validate required fields before processing
        $required_fields = ["username", "password", "name", "email"];
        $missing_fields = [];

        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = $field;
            }
        }

        if (!empty($missing_fields)) {
            $flash->error(
                __("Please fill in all required fields.", "cftp_admin")
            );
            $form_errors = true;
        }

        // Validate email format
        if (
            !$form_errors &&
            !filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)
        ) {
            $flash->error(
                __("Please enter a valid email address.", "cftp_admin")
            );
            $form_errors = true;
        }

        // Validate username format (basic validation)
        if (
            !$form_errors &&
            !preg_match('/^[a-zA-Z0-9._-]+$/', $_POST["username"])
        ) {
            $flash->error(
                __(
                    "Username can only contain letters, numbers, dots, underscores, and hyphens.",
                    "cftp_admin"
                )
            );
            $form_errors = true;
        }

        // Check if username already exists
        if (!$form_errors) {
            $existing_user = get_user_by_username($_POST["username"]);
            if (!empty($existing_user)) {
                $flash->error(
                    __(
                        "This username is already taken. Please choose a different username.",
                        "cftp_admin"
                    )
                );
                $form_errors = true;
            }
        }

        // Check if email already exists
        if (!$form_errors) {
            global $dbh;
            $stmt = $dbh->prepare(
                "SELECT id, name, email FROM " .
                    TABLE_USERS .
                    " WHERE email = ? LIMIT 1"
            );
            $stmt->execute([$_POST["email"]]);
            $existing_email = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!empty($existing_email)) {
                $flash->error(
                    __(
                        "This email address is already in use. Please choose a different email address.",
                        "cftp_admin"
                    )
                );
                $form_errors = true;
            }
        }

        // Only proceed if no validation errors
        if (!$form_errors) {
            // Validate the information from the posted form.
            $new_client->setType("new_client");
            $new_client->set($client_arguments);

            $create = $new_client->create();

            if (empty($create) || !isset($create["id"])) {
                // Get specific validation errors
                $validation_errors = $new_client->getValidationErrors();

                if (!empty($validation_errors)) {
                    if (is_array($validation_errors)) {
                        $flash->error(
                            __("Validation failed: ", "cftp_admin") .
                                implode("; ", $validation_errors)
                        );
                    } else {
                        $flash->error(
                            __("Validation failed: ", "cftp_admin") .
                                $validation_errors
                        );
                    }
                } else {
                    $flash->error(
                        __(
                            "Failed to create client account. Please check all required fields and try again.",
                            "cftp_admin"
                        )
                    );
                }
                $form_errors = true;
            }

            if (!$form_errors) {
                // Record the action log
                $logger = new \ProjectSend\Classes\ActionsLog();
                $record = $logger->addEntry([
                    "action" => 3,
                    "owner_user" => CURRENT_USER_USERNAME,
                    "owner_id" => CURRENT_USER_ID,
                    "affected_account" => $new_client->id,
                    "affected_account_name" => $new_client->name,
                ]);

                // Handle group assignments
                $add_to_groups = !empty($_POST["groups_request"])
                    ? $_POST["groups_request"]
                    : "";
                if (!empty($add_to_groups)) {
                    try {
                        array_map("encode_html", $add_to_groups);
                        $memberships = new \ProjectSend\Classes\GroupsMemberships();
                        $memberships->clientAddToGroups([
                            "client_id" => $new_client->getId(),
                            "group_ids" => $add_to_groups,
                            "added_by" => CURRENT_USER_USERNAME,
                        ]);
                    } catch (Exception $e) {
                        $flash->warning(
                            __(
                                "Client created successfully, but there was an issue adding them to groups: ",
                                "cftp_admin"
                            ) . $e->getMessage()
                        );
                    }
                }

                // Success message
                $flash->success(
                    __("Contact created successfully", "cftp_admin")
                );
                $redirect_to =
                    BASE_URI . "clients-edit.php?id=" . $create["id"];

                // Handle email notifications
                if (isset($create["email"])) {
                    switch ($create["email"]) {
                        case 2:
                            $flash->success(
                                __(
                                    "A welcome message was not sent to the new account owner.",
                                    "cftp_admin"
                                )
                            );
                            break;
                        case 1:
                            $flash->success(
                                __(
                                    "A welcome message with login information was sent to the new account owner.",
                                    "cftp_admin"
                                )
                            );
                            break;
                        case 0:
                            $flash->warning(
                                __(
                                    "Client created successfully, but the email notification couldn't be sent.",
                                    "cftp_admin"
                                )
                            );
                            break;
                    }
                }

                ps_redirect($redirect_to);
            }
        }
    } catch (Exception $e) {
        $flash->error(
            __(
                "There was an unexpected error creating the client. Please try again.",
                "cftp_admin"
            )
        );
    }

    // If we reach here, there were form errors, so preserve the form data
    if ($form_errors) {
        $client_arguments = array_merge($client_arguments, $_POST);
    }
}
?>

<div class="row">
    <div class="col-12 col-sm-12 col-lg-6">
        <div class="white-box">
            <div class="white-box-interior">
                <?php
                // If the form was submitted with errors, show them here.
                $clients_form_type = "new_client";
                include_once FORMS_DIR . DS . "clients.php";
                ?>
            </div>
        </div>
    </div>
</div>
<?php include_once ADMIN_VIEWS_DIR . DS . "footer.php"; ?>
