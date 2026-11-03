<?php
/**
 * Configuration template.
 * Copy this file to config/config.php and fill in your own values.
 * config/config.php is never included in the submission ZIP, so real SMTP passwords stay private.
 */
return [
    // The barangay name, location, office hours and logo are set by the administrator
    // under Settings in the app (stored in the barangay_settings table).

    // Full public URL of the app, used in verification email links.
    // Leave empty to detect it automatically from the current request.
    'app_url'  => '',

    // Show detailed errors on screen. Keep false during the demo.
    'debug'    => false,

    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'barangay_eservices',
        'user'     => 'root',
        'password' => '',
    ],

    'mail' => [
        // 'smtp' sends real email through the SMTP server below.
        // 'log' writes each email to storage/mail/ instead (for offline testing only).
        'transport'  => 'smtp',
        'host'       => 'smtp.gmail.com',
        'port'       => 587,
        'encryption' => 'tls',          // 'tls' (STARTTLS, port 587), 'ssl' (port 465) or '' (none)
        'username'   => 'your.address@gmail.com',
        'password'   => 'your-16-character-app-password',
        'from_email' => 'your.address@gmail.com',
        'from_name'  => '',             // leave empty to use "<Barangay name> E-Services"
    ],

    'verification_ttl_minutes' => 60,   // how long a verification link stays valid
    'resend_cooldown_seconds'  => 60,   // minimum wait between resend requests
    'session_idle_minutes'     => 30,   // log out after this much inactivity
];
