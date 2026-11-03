<?php
/** Session hardening, CSRF protection and HTTP security headers. */

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    ini_set('session.use_strict_mode', '1');   // reject unknown session ids
    ini_set('session.use_only_cookies', '1');  // never accept ids from the URL
    session_name('BRGYSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() . '/',
        'secure'   => $https,
        'httponly' => true,                    // not readable from JavaScript
        'samesite' => 'Lax',
    ]);
    session_start();

    // Log out after a period of inactivity.
    $idle = (int) config('session_idle_minutes', 30) * 60;
    if (isset($_SESSION['user_id'], $_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $idle) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('warning', 'You were signed out after ' . ($idle / 60) . ' minutes of inactivity. Please sign in again.');
    }
    $_SESSION['last_activity'] = time();
}

function send_security_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'");
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to place inside every form that changes data. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify the CSRF token of a POST request. Stops with HTTP 419 on mismatch.
 * Called at the top of every POST handler.
 */
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        log_activity('csrf_rejected', null, null, 'Rejected a form submission with a missing or invalid security token');
        flash('danger', 'Your session security token expired or was invalid. Please try again.');
        $back = $_SERVER['HTTP_REFERER'] ?? '';
        // Only redirect back within this site.
        if ($back !== '' && parse_url($back, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '')) {
            header('Location: ' . $back);
            exit;
        }
        redirect('index.php');
    }
}

/** Require POST + valid CSRF token. */
function require_post(): void
{
    if (!is_post()) {
        http_response_code(405);
        exit('Method not allowed');
    }
    csrf_verify();
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 45);
}
