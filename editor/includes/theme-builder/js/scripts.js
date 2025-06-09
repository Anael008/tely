jQuery(document).ready(function($) {
    setTimeout(function() {
        jQuery('.u-password-control form').submit(function () {
            var passwordInput = jQuery('input[name="password"]');
            var passwordHashInput = jQuery('input[name="password_hash"]');
            var passwordHash = sha256.create().update(passwordInput.val()).digest().toHex();
            passwordHashInput.val(passwordHash);
            return true;
        });
    }, 0);
});