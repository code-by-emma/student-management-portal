<?php
/**
 * Shared helper functions used across the app.
 */

/**
 * Renders the eye-icon button used to toggle a password field between
 * hidden and visible. Must be placed inside a `.password-field` wrapper
 * alongside the `<input type="password">` it controls — see main.js for
 * the click behavior that finds the input via that wrapper.
 */
function password_toggle_button(): string {
    return <<<'HTML'
    <button type="button" data-toggle-password tabindex="-1" aria-label="Show password"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
      <svg data-icon-eye class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
      <svg data-icon-eye-off class="h-4 w-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
      </svg>
    </button>
    HTML;
    /** Full URL (scheme + host + path) for use in emails, where a relative link is useless. */
function absolute_url(string $path): string {
    if (APP_URL !== '') return APP_URL . $path;
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host  = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https' : 'http') . '://' . $host . BASE_URL . $path;
}
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
    }
}

function flash(string $key, ?string $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function render_flashes(): string {
    $html = '';
    foreach (['success', 'error', 'info'] as $type) {
        if ($msg = flash($type)) {
            $color = ['success' => 'emerald', 'error' => 'rose', 'info' => 'sky'][$type];
            $html .= '<div class="mb-4 rounded-lg border border-' . $color . '-200 bg-' . $color . '-50 text-' . $color . '-800 px-4 py-3 text-sm" data-toast>' . e($msg) . '</div>';
        }
    }
    return $html;
}

/** Basic input sanitizer for trimming strings from $_POST */
function input(string $key, $default = '') {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

/**
 * Securely store an uploaded file under UPLOAD_DIR/$subdir.
 * Returns the relative path (to store in DB) or null on failure/absence.
 * Throws RuntimeException with a user-safe message on validation failure.
 */
function handle_upload(string $fieldName, string $subdir, array $allowedExt = ALLOWED_UPLOAD_EXT): ?string {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . $file['error'] . ').');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('File is too large. Max size is ' . (MAX_UPLOAD_BYTES / 1024 / 1024) . 'MB.');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('File type .' . $ext . ' is not allowed.');
    }
    // Re-verify actual MIME type, don't trust the client-supplied one.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = [
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/jpeg', 'image/png', 'image/webp',
    ];
    if (!in_array($mime, $allowedMimes, true)) {
        throw new RuntimeException('File content does not match an allowed type.');
    }

    $targetDir = UPLOAD_DIR . '/' . trim($subdir, '/');
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $targetDir . '/' . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Could not save uploaded file.');
    }
    return trim($subdir, '/') . '/' . $safeName;
}

/** Write an audit log entry. Never throws — logging must not break the request. */
function audit_log(?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void {
    global $pdo;
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$userId, $action, $entityType, $entityId, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {
        error_log('audit_log failed: ' . $e->getMessage());
    }
}

/** Create an in-app notification for a user. */
function notify(int $userId, string $title, string $message, string $type = 'general', ?string $link = null): void {
    global $pdo;
    try {
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, link) VALUES (?,?,?,?,?)');
        $stmt->execute([$userId, $title, $message, $type, $link]);
    } catch (Throwable $e) {
        error_log('notify failed: ' . $e->getMessage());
    }
}

/** Simple pagination helper. Returns [limit, offset, page]. */
function paginate(int $perPage = 15): array {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;
    return [$perPage, $offset, $page];
}

function pagination_links(int $page, int $totalPages, string $baseQuery = ''): string {
    if ($totalPages <= 1) return '';
    $html = '<div class="flex gap-1 mt-4 flex-wrap">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i === $page ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-100';
        $sep = $baseQuery ? '&' : '';
        $html .= "<a href=\"?{$baseQuery}{$sep}page={$i}\" class=\"px-3 py-1.5 rounded-md text-sm border border-slate-200 {$active}\">{$i}</a>";
    }
    $html .= '</div>';
    return $html;
}

function letter_grade(float $score, float $max = 100): string {
    $pct = $max > 0 ? ($score / $max) * 100 : 0;
    if ($pct >= 90) return 'A';
    if ($pct >= 80) return 'B';
    if ($pct >= 70) return 'C';
    if ($pct >= 60) return 'D';
    if ($pct >= 50) return 'E';
    return 'F';
}

function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}
