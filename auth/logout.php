<?php
require __DIR__ . '/../app/bootstrap.php';

// Logging out changes state, so it requires POST with a valid CSRF token.
require_post();
if (current_user()) {
    log_activity('logout', 'user', user_id(), 'Signed out');
}
logout_user();
start_secure_session();
flash('success', 'You have signed out.');
redirect('auth/login.php');
