
<?php
/**
 * Mailer helper — sends transactional emails via PHPMailer/SMTP.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Send the password reset email.
 * Returns true on success and false on failure.
 */

function send_reset_email(string $toEmail, string $toName, string $resetLink): bool
{
    $mail = new PHPMailer(true);
     $mail->CharSet = 'UTF-8';
    $mail->Timeout = 15;

    try {
        // SMTP server settings
        $mail->isSMTP();
         $mail->CharSet = 'UTF-8';
         $mail->Timeout = 15;

        // TEMPORARY DEBUGGING
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = 'error_log';

        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;

        $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = MAIL_PORT;

        // Sender and recipient
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        // Email content
        $mail->isHTML(true);

        $mail->Subject = APP_NAME . ' — Password reset request';

        $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
        $safeAppName = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <p>Hi {$safeName},</p>

            <p>We received a request to reset your password for your {$safeAppName} account.</p>

            <p>
                <a href=\"{$safeLink}\"
                style=\"background:#2563EB;color:#fff;padding:10px 18px;
                border-radius:8px;text-decoration:none;display:inline-block\">
                Reset your password
                </a>
            </p>

            <p>Or copy and paste this link into your browser:</p>
            <p>{$safeLink}</p>

            <p>This link expires in 1 hour.</p>

            <p>If you didn't request this, you can safely ignore this email.</p>
        ";

        $mail->AltBody = "Reset your password: {$resetLink}

This link expires in 1 hour.
If you didn't request this, ignore this email.";

        $mail->send();

        return true;

    } catch (PHPMailerException $e) {

        error_log('PHPMailer error: ' . $mail->ErrorInfo);

        return false;
    }
}
