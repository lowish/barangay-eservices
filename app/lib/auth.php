<?php
/**
 * Authentication and authorization.
 * Every protected page calls require_role(...) at the top; the check runs on the server,
 * so hiding a menu link is never the only protection.
 */

const ROLE_ADMIN    = 'admin';
const ROLE_STAFF    = 'staff';
const ROLE_RESIDENT = 'resident';

/**
 * The signed-in user, re-read from the database on every request so that
 * deactivation or a role change takes effect immediately.
 * Pass $refresh = true right after signing in, so the cached "nobody signed in" is replaced.
 */
function current_user(bool $refresh = false): ?array
{
    static $user = false;
    if ($user !== false && !$refresh) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['user_id'])) {
        $user = db_one(
            'SELECT u.user_id, u.first_name, u.last_name, u.email, u.contact_no, u.street_address, u.purok_id,
                    u.email_verified_at, u.is_active, u.created_at, r.role_key, r.role_name
               FROM users u JOIN roles r ON r.role_id = u.role_id
              WHERE u.user_id = ?',
            [(int) $_SESSION['user_id']]
        );
        if (!$user || !$user['is_active']) {
            $user = null;
            unset($_SESSION['user_id']);
        }
    }
    return $user;
}

function user_id(): int
{
    return (int) (current_user()['user_id'] ?? 0);
}

function user_role(): ?string
{
    return current_user()['role_key'] ?? null;
}

function is_verified(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user !== null && !empty($user['email_verified_at']);
}

function home_path_for_role(?string $role): string
{
    return match ($role) {
        ROLE_ADMIN    => 'admin/dashboard.php',
        ROLE_STAFF    => 'staff/dashboard.php',
        ROLE_RESIDENT => 'resident/dashboard.php',
        default       => 'auth/login.php',
    };
}

function home_path_for_current_user(): string
{
    return home_path_for_role(user_role());
}

/** Require a signed-in user. Unverified users are sent to the verification notice. */
function require_login(bool $mustBeVerified = true): array
{
    $user = current_user();
    if (!$user) {
        $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('warning', 'Please sign in to continue.');
        redirect('auth/login.php');
    }
    if ($mustBeVerified && !is_verified($user)) {
        redirect('auth/verify-notice.php');
    }
    return $user;
}

/** Require a signed-in, verified user with one of the given roles; otherwise HTTP 403. */
function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role_key'], $roles, true)) {
        log_activity('access_denied', null, null, 'Tried to open ' . substr($_SERVER['REQUEST_URI'] ?? '', 0, 200));
        http_response_code(403);
        $pageTitle = 'Access denied';
        require APP_ROOT . '/app/views/header.php';
        echo '<div class="page-head"><h1>Access denied</h1></div>';
        echo '<p>Your account (' . e($user['role_name']) . ') does not have permission to open this page.</p>';
        echo '<p><a class="btn btn-outline-primary" href="' . e(url(home_path_for_role($user['role_key']))) . '">Go to your dashboard</a></p>';
        require APP_ROOT . '/app/views/footer.php';
        exit;
    }
    return $user;
}

/** Sign a user in: new session id (prevents session fixation), then store the id. */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token']);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Role id lookup by key. */
function role_id(string $roleKey): int
{
    return (int) db_value('SELECT role_id FROM roles WHERE role_key = ?', [$roleKey]);
}
