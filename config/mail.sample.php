
 <?php
/**
 * SMTP settings for includes/mailer.php (PHPMailer).
 *
 * Gmail users: you cannot use your normal password. Turn on 2-Step
 * Verification, then create an "App Password" at
 * https://myaccount.google.com/apppasswords and use that as MAIL_PASSWORD.
 */
define('MAIL_HOST',         'smtp.gmail.com');
define('MAIL_PORT',         587);
define('MAIL_ENCRYPTION',   'tls');                  // 'tls' = port 587, 'ssl' = port 465
   define('MAIL_USERNAME',     'yourname@gmail.com');
   define('MAIL_PASSWORD',     'xxxx xxxx xxxx xxxx');
   define('MAIL_FROM_ADDRESS', 'yourname@gmail.com');
define('MAIL_FROM_NAME',    'Student Management Portal');

// Address of your site, no trailing slash. Used for the link inside the email.
// Production example: https://your-domain.com/smp
define('APP_URL', 'http://localhost/smp');

// ---- Derived: do not edit ------------------------------------------------
// True only once real credentials have been filled in (not the placeholders).
define('MAIL_ENABLED',
    MAIL_HOST !== '' && MAIL_USERNAME !== '' && MAIL_PASSWORD !== ''
    && MAIL_USERNAME !== 'yourname@gmail.com'
    && MAIL_PASSWORD !== 'PUT-YOUR-APP-PASSWORD-HERE' && MAIL_PASSWORD !== 'xxxx xxxx xxxx xxxx');

// Full URL (scheme + host + path) for links inside emails, where a relative link is useless.
if (!function_exists('absolute_url')) {
    function absolute_url(string $path): string {
        if (APP_URL !== '') return APP_URL . $path;
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host  = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        return ($https ? 'https' : 'http') . '://' . $host . BASE_URL . $path;
    }
}