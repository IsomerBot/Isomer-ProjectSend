<form action="reset-password.php?action=required_change" name="required_password_change" id="required_password_change" method="post" role="form">
    <?php addCsrf(); ?>
    <fieldset>
        <input type="hidden" name="form_type" id="form_type" value="required_change" />
        
        <div class="mb-3">
            <label for="current_password"><?php _e('Current password','cftp_admin'); ?></label>
            <input type="password" name="current_password" id="current_password" class="form-control" required />
        </div>
        
        <div class="mb-3">
            <label for="new_password"><?php _e('New password','cftp_admin'); ?></label>
            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control attach_password_toggler required" required />
            </div>
            <div class="mb-3">
                <button type="button" name="generate_password" id="generate_password" class="btn btn-light btn-sm btn_generate_password" data-ref="password" data-min="<?php echo MAX_GENERATE_PASS_CHARS; ?>" data-max="<?php echo MAX_GENERATE_PASS_CHARS; ?>"><?php _e('Generate','cftp_admin'); ?></button>
            </div>
        </div>
        
        <?php echo password_notes(); ?>
        
        <p><?php _e("Please enter a new password to continue using the system.",'cftp_admin'); ?></p>

        <div class="inside_form_buttons">
            <button type="submit" name="submit" class="btn btn-wide btn-primary"><?php _e('Change password','cftp_admin'); ?></button>
        </div>
    </fieldset>
</form>