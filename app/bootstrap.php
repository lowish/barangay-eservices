<?php
/**
 * Loaded at the top of every page. Sets up configuration, error handling,
 * the secure session, the database connection and the helper functions.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    $configFile = APP_ROOT . '/config/config.sample.php';
}
$GLOBALS['config'] = require $configFile;

date_default_timezone_set('Asia/Manila');

// --- Error handling: log details, show a generic message (never DB credentials) ---
ini_set('display_errors', config('debug') ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e) {
    error_log('[barangay-eservices] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $detail = config('debug') ? $e->getMessage() : null;
    require APP_ROOT . '/app/views/error500.php';
    exit;
});

require APP_ROOT . '/app/lib/helpers.php';
require APP_ROOT . '/app/lib/icons.php';
require APP_ROOT . '/app/lib/db.php';
require APP_ROOT . '/app/lib/settings.php';
require APP_ROOT . '/app/lib/security.php';
require APP_ROOT . '/app/lib/auth.php';
require APP_ROOT . '/app/lib/logger.php';
require APP_ROOT . '/app/lib/validator.php';
require APP_ROOT . '/app/lib/workflow.php';

if (PHP_SAPI !== 'cli') {
    start_secure_session();
    send_security_headers();
}

/** Read a config value using dot notation, e.g. config('db.host'). */
function config(string $key, $default = null)
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}
