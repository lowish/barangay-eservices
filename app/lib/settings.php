<?php
/**
 * The system is a general "Barangay E-Services" system. The header, footer, page titles and
 * emails always use that name. The administrator may optionally enter the barangay profile
 * (name, location, office hours, logo) under Settings; when filled in, it is shown inside pages
 * such as the dashboard banner, the landing page and printed reports. Nothing is hardcoded.
 */

const APP_NAME = 'Barangay E-Services';

const SETTINGS_DEFAULTS = [
    'barangay_name' => null,
    'municipality'  => null,
    'province'      => null,
    'office_hours'  => 'Monday to Friday, 8:00 AM to 5:00 PM',
    'logo_path'     => null,
];

const LOGO_DIR = 'uploads/logo';
const LOGO_MAX_BYTES = 2 * 1024 * 1024;
const LOGO_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

/** The settings row, read once per request. */
function barangay(bool $refresh = false): array
{
    static $settings = null;
    if ($settings === null || $refresh) {
        try {
            $row = db_one('SELECT barangay_name, municipality, province, office_hours, logo_path, updated_at FROM barangay_settings WHERE settings_id = 1');
        } catch (mysqli_sql_exception $e) {
            // The table is missing (older database): keep the site usable with neutral defaults.
            error_log('[barangay-eservices] settings unavailable: ' . $e->getMessage());
            $row = null;
        }
        $settings = array_merge(SETTINGS_DEFAULTS, $row ?? []);
    }
    return $settings;
}

/** The system name used in the header, footer, page titles and emails. */
function app_name(): string
{
    return APP_NAME;
}

/**
 * Configured barangay name, e.g. "Barangay Poblacion" for "Poblacion"; empty when not set.
 * A name already starting with Barangay/Brgy is kept as typed.
 */
function barangay_name(): string
{
    $name = trim((string) barangay()['barangay_name']);
    if ($name === '') {
        return '';
    }
    return preg_match('/^(barangay|brgy\.?)\s/i', $name) ? $name : 'Barangay ' . $name;
}

/** The configured barangay name, or the system name when no barangay is set. */
function barangay_display_name(): string
{
    return barangay_name() ?: APP_NAME;
}

/** "City or Municipality, Province" from the parts that are filled in; empty when none are. */
function barangay_location(): string
{
    $b = barangay();
    return implode(', ', array_filter([trim((string) $b['municipality']), trim((string) $b['province'])], 'strlen'));
}

/** Configured office hours, or empty. */
function office_hours(): string
{
    return trim((string) barangay()['office_hours']);
}

/** Up to two initials for the seal when no logo is uploaded, e.g. "Poblacion Norte" -> "PN"; "BE" when no name is set. */
function barangay_initials(): string
{
    if (trim((string) barangay()['barangay_name']) === '') {
        return 'BE';
    }
    $name = preg_replace('/^(barangay|brgy\.?)\s+/i', '', trim((string) barangay()['barangay_name']));
    $words = preg_split('/[\s\-]+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    $words = array_values(array_filter($words, fn ($w) => !in_array(mb_strtolower($w), ['de', 'del', 'dela', 'la', 'ng', 'of'], true)));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $w) {
        $initials .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    return $initials !== '' ? $initials : 'B';
}

/** Public URL of the uploaded logo, or null when none is set (or the file is missing). */
function logo_url(): ?string
{
    $path = barangay()['logo_path'];
    if (!$path || !is_file(APP_ROOT . '/' . $path)) {
        return null;
    }
    // Cache-busting query so a replaced logo shows right away.
    return url($path) . '?v=' . filemtime(APP_ROOT . '/' . $path);
}

/** Generic mark for the header (the same for every barangay). */
function render_app_mark(): string
{
    return '<span class="brand-seal" aria-hidden="true">BE</span>';
}

/** Barangay seal inside pages: the uploaded logo, or the initials in a circle. */
function render_seal(string $class = 'brand-seal'): string
{
    $logo = logo_url();
    if ($logo) {
        return '<img class="' . e($class) . ' brand-logo" src="' . e($logo) . '" alt="" width="40" height="40">';
    }
    return '<span class="' . e($class) . '" aria-hidden="true">' . e(barangay_initials()) . '</span>';
}

/**
 * Validate an uploaded logo and store it under uploads/logo/ with a random name.
 * Returns [relativePath, null] on success or [null, errorMessage].
 * The file type is checked from its contents (not the browser-supplied name or type),
 * and only PNG, JPEG and WebP images are accepted (SVG is refused because it can carry scripts).
 */
function store_logo_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return [null, 'The logo must be 2 MB or smaller.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return [null, 'The logo could not be uploaded. Please try again.'];
    }
    if ($file['size'] > LOGO_MAX_BYTES) {
        return [null, 'The logo must be 2 MB or smaller.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $info = @getimagesize($file['tmp_name']);
    if (!isset(LOGO_TYPES[$mime]) || $info === false) {
        return [null, 'The logo must be a PNG, JPG or WebP image.'];
    }
    if ($info[0] < 32 || $info[1] < 32 || $info[0] > 4000 || $info[1] > 4000) {
        return [null, 'The logo must be between 32 and 4000 pixels wide and tall.'];
    }
    $dir = APP_ROOT . '/' . LOGO_DIR;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        return [null, 'The server could not save the logo.'];
    }
    $relative = LOGO_DIR . '/logo-' . bin2hex(random_bytes(8)) . '.' . LOGO_TYPES[$mime];
    if (!move_uploaded_file($file['tmp_name'], APP_ROOT . '/' . $relative)) {
        return [null, 'The server could not save the logo.'];
    }
    return [$relative, null];
}

/** Delete a previously stored logo file (only files inside uploads/logo/). */
function delete_logo_file(?string $relative): void
{
    if ($relative && preg_match('#^' . preg_quote(LOGO_DIR, '#') . '/logo-[a-f0-9]{16}\.(png|jpg|webp)$#', $relative)) {
        @unlink(APP_ROOT . '/' . $relative);
    }
}
