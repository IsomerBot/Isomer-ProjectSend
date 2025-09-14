<?php
/**
 * Contains all the functions used to validate the current logged in
 * client or user.
 */

function log_in_required($allowed_levels)
{
    // Check for an active session
    redirect_if_not_logged_in();

    // Check if the current user has permission to view this page.
    redirect_if_role_not_allowed($allowed_levels);
}

function extend_session()
{
    $_SESSION["last_call"] = time();
}

function session_expired()
{
    if (defined("SESSION_TIMEOUT_EXPIRE") && SESSION_TIMEOUT_EXPIRE == true) {
        if (
            isset($_SESSION["last_call"]) &&
            time() - $_SESSION["last_call"] > SESSION_EXPIRE_TIME
        ) {
            return true;
        }
    }

    return false;
}

/**
 * Used on header.php to check if there is an active session or valid
 * cookie before generating the content.
 * If none is found, redirect to the log in form.
 */
function redirect_if_not_logged_in()
{
    $redirect = false;
    if (!user_is_logged_in()) {
        $redirect = true;
    } else {
        if (isset($_SESSION["user_id"])) {
            $user = new \ProjectSend\Classes\Users($_SESSION["user_id"]);
            if (!$user->userExists()) {
                $redirect = true;
            }
        }
    }

    if ($redirect) {
        $_SESSION = [];
        session_destroy();
        ps_redirect(BASE_URI . "index.php");
    }
}

function user_is_logged_in()
{
    if (isset($_SESSION["user_id"])) {
        $user = new \ProjectSend\Classes\Users($_SESSION["user_id"]);
        if ($user->userExists()) {
            return true;
        }
    }

    return false;
}

/**
 * Used on header.php to check if the current logged in system user has the
 * permission to view this page.
 */
function redirect_if_role_not_allowed($allowed_levels = null)
{
    $permission = false;

    if (!empty($allowed_levels)) {
        /**
         * Check for a session, and if found see if the user
         * level is among those defined by the page.
         *
         * $allowed_levels in defined on each page before the inclusion of header.php
         */
        if (user_is_logged_in()) {
            $user = new \ProjectSend\Classes\Users($_SESSION["user_id"]);
            $user_data = $user->getProperties();

            if (
                isset($user_data["role"]) &&
                in_array($user_data["role"], $allowed_levels)
            ) {
                $permission = true;
            }
        }
        /**
         * After the checks, if the user is allowed, continue.
         * If not, show the "Not allowed message", then the footer, then die(); so the
         * actual page content is not generated.
         */
    }

    if ($permission != true) {
        exit_with_error_code(403);
    }
}

// Requires password change?
function password_change_required()
{
    global $flash;

    if (!defined("CURRENT_USER_ID")) {
        return;
    }

    $session_user = new \ProjectSend\Classes\Users(CURRENT_USER_ID);

    if ($session_user->requiresPasswordChange()) {
        $current_page = basename($_SERVER["SCRIPT_FILENAME"]);
        $current_action = isset($_GET["action"]) ? $_GET["action"] : "";

        // Debug output (remove after testing)
        error_log(
            "Password change required - Current page: $current_page, Action: $current_action"
        );

        if (
            $current_page != "reset-password.php" ||
            $current_action != "required_change"
        ) {
            $flash->info(
                __("Please set a new password to continue", "cftp_admin")
            );
            ps_redirect(BASE_URI . "reset-password.php?action=required_change");
        }
    }
}

function user_can_upload_any_file_type($user_id = CURRENT_USER_ID)
{
    $user = new \ProjectSend\Classes\Users($user_id);
    $properties = $user->getProperties();

    if (!empty(get_option("file_types_limit_to"))) {
        switch (get_option("file_types_limit_to")) {
            case "noone":
                return true;
                break;
            case "all":
                return false;
                break;
            case "clients":
                if ($properties["role"] == 0) {
                    return false;
                }
                break;
        }
    }
    unset($user);
    unset($properties);

    return true;
}

function current_user_can_view_files_list()
{
    if (defined("IS_PUBLIC_VIEW")) {
        return true;
    }

    $user = new \ProjectSend\Classes\Users(CURRENT_USER_ID);
    $props = $user->getProperties();

    if ($props["active"] == "0") {
        return false;
    }

    if (!$user->isClient()) {
        return true;
    } else {
        if ($props["username"] == CURRENT_USER_USERNAME) {
            return true;
        }
    }

    return false;
}
