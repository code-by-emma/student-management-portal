<?php
/**
 * App-wide bootstrap: session, constants, error display.
 * Every entry-point page includes this file first.
 */

// --- Error handling -------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_DEBUG') === '1' ? '1' : '0');

// --- Session (secure cookie params) ---------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' => true, // enable once served over HTTPS
    ]);
    session_start();
}

// --- Session timeout (30 min idle) -----------------------------------
define('SESSION_TIMEOUT_SECONDS', 30 * 60);
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . BASE_URL . '/auth/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// --- Constants --------------------------------------------------------
// BASE_URL must be the same on every page, regardless of how deep the current
// script is nested (admin/, teacher/student/auth/ etc). We derive it from
// where this project sits on disk relative to the web server's document
// root, rather than from the current script's own folder — using the
// current script's folder breaks every asset and nav link on any page that
// isn't directly in the project root.
function resolve_base_url(): string
{
    $projectRoot = str_replace('\\', '/', dirname(__DIR__)); // .../htdocs/smp
    $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
    if ($docRoot && str_starts_with($projectRoot, $docRoot)) {
        return rtrim(substr($projectRoot, strlen($docRoot)), '/');
    }
    // Fallback (e.g. DOCUMENT_ROOT missing under CLI/some servers): derive
    // from the very first request into the app instead of the current page.
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $marker = '/' . basename($projectRoot) . '/';
    $pos = strpos($script, $marker);
    return $pos !== false ? rtrim(substr($script, 0, $pos + strlen($marker) - 1), '/') : '';
}
define('BASE_URL', resolve_base_url());
define('APP_NAME', 'Student Management Portal');
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5MB
define('ALLOWED_UPLOAD_EXT', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'xlsx', 'ppt', 'pptx']);
define('ALLOWED_IMAGE_EXT', ['jpg', 'jpeg', 'png', 'webp']);

// --- CSRF token ---------------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/payment.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
