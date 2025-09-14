<?php
/**
 * Show the form to reset the password.
 */
$allowed_levels = [9, 8, 7, 0];
require_once "bootstrap.php";

// Check if this is a required password change
$is_required_change =
    isset($_GET["action"]) && $_GET["action"] == "required_change";

$page_title = $is_required_change
    ? __("Change Password", "cftp_admin")
    : __("Lost password", "cftp_admin");

// Determine which form to show
if ($is_required_change) {
    $page_id = "reset_password_required_change";
} else {
    $page_id =
        !empty($_GET["token"]) && !empty($_GET["user"])
            ? "reset_password_enter_new"
            : "reset_password_enter_email";
}

// Use different header for required changes (user is logged in)
if ($is_required_change) {
    include_once ADMIN_VIEWS_DIR . DS . "header.php"; // Logged in header
} else {
    include_once ADMIN_VIEWS_DIR . DS . "header-unlogged.php"; // Unlogged header
}

$pass_reset = new \ProjectSend\Classes\PasswordReset();

// Process request
if ($_POST) {
    $form_type = encode_html($_POST["form_type"]);

    switch ($form_type) {
        case "required_change":
            // Handle required password change
            if (
                user_is_logged_in() &&
                isset($_POST["current_password"]) &&
                isset($_POST["password"])
            ) {
                try {
                    // Get current password hash from database
                    global $dbh;
                    $stmt = $dbh->prepare(
                        "SELECT password FROM tbl_users WHERE id = ?"
                    );
                    $stmt->execute([CURRENT_USER_ID]);
                    $db_user_data = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($db_user_data && !empty($db_user_data["password"])) {
                        // Verify current password
                        if (
                            password_verify(
                                $_POST["current_password"],
                                $db_user_data["password"]
                            )
                        ) {
                            // Hash and update new password
                            $new_hash = password_hash(
                                $_POST["password"],
                                PASSWORD_DEFAULT
                            );
                            $update_stmt = $dbh->prepare(
                                "UPDATE tbl_users SET password = ? WHERE id = ?"
                            );
                            $password_updated = $update_stmt->execute([
                                $new_hash,
                                CURRENT_USER_ID,
                            ]);

                            if ($password_updated) {
                                // Remove password change requirement
                                $meta_stmt = $dbh->prepare(
                                    "DELETE FROM tbl_user_meta WHERE user_id = ? AND name = 'require_password_change'"
                                );
                                $meta_stmt->execute([CURRENT_USER_ID]);

                                $flash->success(
                                    __(
                                        "Password changed successfully. Please log in with your new password.",
                                        "cftp_admin"
                                    )
                                );

                                // Clear session and redirect to login
                                session_destroy();
                                ps_redirect(BASE_URI . "index.php");
                                exit();
                            } else {
                                $flash->error(
                                    __(
                                        "Error updating password. Please try again.",
                                        "cftp_admin"
                                    )
                                );
                            }
                        } else {
                            $flash->error(
                                __(
                                    "Current password is incorrect",
                                    "cftp_admin"
                                )
                            );
                        }
                    } else {
                        $flash->error(
                            __("Error retrieving user data", "cftp_admin")
                        );
                    }
                } catch (Exception $e) {
                    $flash->error(
                        __(
                            "An error occurred during password change",
                            "cftp_admin"
                        )
                    );
                }
            }
            break;

        case "new_request":
            recaptcha2_validate_request();

            $get_user = get_user_by("user", "email", $_POST["email"]);
            if ($get_user) {
                $request = $pass_reset->requestNew($get_user["id"]);
                if ($request["status"] == "success") {
                    $flash->success($request["message"]);
                } else {
                    $flash->error($request["message"]);
                }
            } else {
                // Simulate that the request has been set, do not show that email exists or not on the database
                $flash->success($pass_reset->getNewRequestSuccessMessage());
            }

            ps_redirect(BASE_URI . "reset-password.php");
            break;

        case "new_password":
            $get_user = get_user_by_username($_POST["user"]);
            if (!empty($get_user["id"])) {
                $pass_reset->getByTokenAndUserId(
                    $_POST["token"],
                    $get_user["id"]
                );
                $set = $pass_reset->processRequest($_POST["password"]);
                if ($set["status"] == "success") {
                    $flash->success($set["message"]);
                    ps_redirect(BASE_URI);
                } else {
                    $flash->error($set["message"]);
                    ps_redirect(BASE_URI . "reset-password.php");
                }
            }

            exit_with_error_code(403);
            break;
    }
} else {
    if (!empty($_GET["token"]) && !empty($_GET["user"])) {
        $get_user = get_user_by_username($_GET["user"]);

        $pass_reset->getByTokenAndUserId($_GET["token"], $get_user["id"]);
        $validate = $pass_reset->validate();
        if ($validate["status"] == "error") {
            $flash->error($validate["message"]);
            ps_redirect(BASE_URI . "reset-password.php");
        }
    }
}

// Check what methods are available in the Users class
$user = new \ProjectSend\Classes\Users(CURRENT_USER_ID);
error_log("Available methods: " . implode(", ", get_class_methods($user)));
?>

<div class="row justify-content-md-center">
    <div class="col-12 col-sm-12 col-lg-4">
        <div class="white-box">
            <div class="white-box-interior">
                <?php switch ($page_id) {
                    case "reset_password_required_change":
                        include_once FORMS_DIR .
                            DS .
                            "reset-password" .
                            DS .
                            "required-change.php";
                        break;
                    case "reset_password_enter_email":
                    default:
                        include_once FORMS_DIR .
                            DS .
                            "reset-password" .
                            DS .
                            "enter-email.php";
                        break;
                    case "reset_password_enter_new":
                        include_once FORMS_DIR .
                            DS .
                            "reset-password" .
                            DS .
                            "enter-password.php";
                        break;
                } ?>

                <?php login_form_links(["homepage"]); ?>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_VIEWS_DIR . DS . "footer.php";
