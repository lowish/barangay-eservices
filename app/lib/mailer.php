<?php
/**
 * Email sending through PHPMailer (SMTP), configured in config/config.php.
 * The "log" transport writes messages to storage/mail/ for offline testing only;
 * the live demo uses the "smtp" transport so a real email is received.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

require_once APP_ROOT . '/vendor/phpmailer/src/Exception.php';
require_once APP_ROOT . '/vendor/phpmailer/src/PHPMailer.php';
require_once APP_ROOT . '/vendor/phpmailer/src/SMTP.php';

/** Send an HTML email. Returns true on success; failures are logged, never shown with SMTP details. */
function send_mail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    $html = mail_layout($subject, $htmlBody);
    $text = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>'], "\n", $htmlBody)), ENT_QUOTES, 'UTF-8'));

    if (config('mail.transport') === 'log') {
        $dir = APP_ROOT . '/storage/mail';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        // Microsecond timestamp first, so sorting by name also sorts by time.
        $file = $dir . '/' . date('Ymd-His') . sprintf('-%06d-', (int) (fmod(microtime(true), 1) * 1e6)) . bin2hex(random_bytes(3)) . '.html';
        $meta = '<!-- To: ' . htmlspecialchars("$toName <$toEmail>") . ' | Subject: ' . htmlspecialchars($subject) . " -->\n";
        return file_put_contents($file, $meta . $html) !== false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = (string) config('mail.host');
        $mail->Port       = (int) config('mail.port', 587);
        $mail->SMTPAuth   = config('mail.username', '') !== '';
        $mail->Username   = (string) config('mail.username', '');
        $mail->Password   = (string) config('mail.password', '');
        $enc = (string) config('mail.encryption', 'tls');
        if ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }
        $mail->Timeout  = 15;
        $mail->CharSet  = 'UTF-8';
        $mail->setFrom((string) config('mail.from_email'), (string) (config('mail.from_name') ?: app_name()));
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $text;
        $mail->send();
        return true;
    } catch (MailException $e) {
        error_log('[barangay-eservices] mail failed: ' . $mail->ErrorInfo);
        return false;
    }
}

function mail_layout(string $title, string $body): string
{
    $brgy = e(app_name());
    return '<!doctype html><html><body style="margin:0;background:#f3f5f8;font-family:Arial,Helvetica,sans-serif;color:#1b2433">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px">'
        . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #dde1e7">'
        . '<tr><td style="background:#8f1d21;color:#ffffff;padding:16px 24px;font-size:16px;font-weight:bold">' . $brgy . '</td></tr>'
        . '<tr><td style="padding:24px;font-size:15px;line-height:1.55">' . $body . '</td></tr>'
        . '<tr><td style="padding:16px 24px;border-top:1px solid #dde1e7;font-size:12px;color:#5b6575">This is an automated message from ' . $brgy . '. Please do not reply.</td></tr>'
        . '</table></td></tr></table></body></html>';
}

// ---------------------------------------------------------------- Email verification

/**
 * Create a new verification token for a user and email the link.
 * Any older unused tokens for the user are invalidated, so only the latest link works.
 * The raw token is only in the email; the database keeps its SHA-256 hash.
 */
function send_verification_email(array $user): bool
{
    $token = bin2hex(random_bytes(32));
    $ttl = (int) config('verification_ttl_minutes', 60);

    db_query('UPDATE email_verifications SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [(int) $user['user_id']]);
    db_query(
        'INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))',
        [(int) $user['user_id'], hash('sha256', $token), $ttl]
    );

    $link = absolute_url('auth/verify.php?token=' . $token);
    $body = '<p>Good day, ' . e($user['first_name']) . '.</p>'
        . '<p>Please confirm your email address to activate your ' . e(app_name()) . ' account.</p>'
        . '<p style="margin:24px 0"><a href="' . e($link) . '" style="background:#8f1d21;color:#ffffff;padding:12px 20px;text-decoration:none;display:inline-block">Verify my email</a></p>'
        . '<p>Or open this link: <br><a href="' . e($link) . '">' . e($link) . '</a></p>'
        . '<p>This link expires in ' . $ttl . ' minutes and can be used only once. If you did not create this account, you can ignore this email.</p>';

    $sent = send_mail($user['email'], full_name($user), 'Verify your email address', $body);
    log_activity($sent ? 'verification_email_sent' : 'verification_email_failed', 'user', (int) $user['user_id'], 'To ' . $user['email'], (int) $user['user_id']);
    return $sent;
}
