<?php
/** General helpers: escaping, URLs, redirects, flash messages, formatting. */

/** Escape output for HTML. Use for every value that came from a user or the database. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Web path of the app root, e.g. "/barangay-eservices" under XAMPP htdocs, or "" at a server root. */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
        $root   = realpath(APP_ROOT) ?: APP_ROOT;
        $rel    = str_replace('\\', '/', substr($script, strlen($root)));       // e.g. /auth/login.php
        $name   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');        // e.g. /barangay-eservices/auth/login.php
        $base   = ($rel !== '' && str_ends_with($name, $rel)) ? substr($name, 0, -strlen($rel)) : '';
        $base   = rtrim($base, '/');
    }
    return $base;
}

/** Build an app URL from a path relative to the app root. */
function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** Absolute URL (scheme + host), used in emails. */
function absolute_url(string $path = ''): string
{
    $configured = rtrim((string) config('app_url', ''), '/');
    if ($configured !== '') {
        return $configured . '/' . ltrim($path, '/');
    }
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . url($path);
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/** Store a one-time message to show on the next page. Types: success, danger, warning, info. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Keep submitted form values so the form can be re-filled after a validation error. */
function old(string $key, $default = '')
{
    return $_SESSION['old'][$key] ?? $default;
}

function remember_input(array $data): void
{
    unset($data['password'], $data['password_confirm'], $data['csrf_token']);
    $_SESSION['old'] = $data;
}

function clear_old_input(): void
{
    unset($_SESSION['old']);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Read a trimmed string from GET. */
function query_param(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Read a trimmed string from POST. */
function post_param(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function format_date(?string $datetime, string $format = 'M j, Y'): string
{
    return $datetime ? date($format, strtotime($datetime)) : '—';
}

function format_datetime(?string $datetime): string
{
    return format_date($datetime, 'M j, Y g:i A');
}

function peso($amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

function full_name(array $row, string $prefix = ''): string
{
    return trim(($row[$prefix . 'first_name'] ?? '') . ' ' . ($row[$prefix . 'last_name'] ?? ''));
}

/** Render a 404 page and stop. Used when a record does not exist or the user may not see it. */
function not_found(): never
{
    http_response_code(404);
    $pageTitle = 'Record not found';
    require APP_ROOT . '/app/views/header.php';
    echo '<div class="page-head"><h1>Record not found</h1></div>';
    echo '<p>The record you are looking for does not exist or is not available to your account.</p>';
    echo '<p><a class="btn btn-outline-primary" href="' . e(url(home_path_for_current_user())) . '">Back to dashboard</a></p>';
    require APP_ROOT . '/app/views/footer.php';
    exit;
}

/** Build a query string from the current GET params with overrides (used by filters and pagination). */
function query_with(array $overrides): string
{
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn ($v) => $v !== '' && $v !== null);
    return $params ? '?' . http_build_query($params) : '';
}

/**
 * Simple pagination helper. Returns [limit, offset, page, totalPages].
 */
function paginate(int $total, int $perPage = 15): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    return [$perPage, ($page - 1) * $perPage, $page, $totalPages];
}
