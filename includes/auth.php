<?php
/**
 * Authentication + Role-Based Access Control helpers.
 */

function current_user(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'role'     => $_SESSION['role'],       // 'admin' | 'teacher' | 'student'
        'name'     => $_SESSION['name'],
        'email'    => $_SESSION['email'],
        'profile_picture' => $_SESSION['profile_picture'] ?? null,
        'linked_id' => $_SESSION['linked_id'] ?? null, // students.id or teachers.id
    ];
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/** Redirect to login if not authenticated. Call at the top of every protected page. */
function require_login(): array {
    if (!is_logged_in()) {
        redirect('/auth/login.php');
    }
    return current_user();
}

/** Restrict a page to one or more roles, e.g. require_role('admin') or require_role(['admin','teacher']). */
function require_role($roles): array {
    $user = require_login();
    $roles = (array)$roles;
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
                <h2>403 — Access denied</h2>
                <p>Your account role does not have permission to view this page.</p>
                <a href="' . BASE_URL . '/index.php">Return to dashboard</a>
             </div>');
    }
    return $user;
}

function login_user(array $userRow, ?array $linkedRow = null): void {
    session_regenerate_id(true); // prevent session fixation
    $_SESSION['user_id']  = (int)$userRow['id'];
    $_SESSION['role']     = $userRow['role_name'];
    $_SESSION['name']     = $userRow['first_name'] . ' ' . $userRow['last_name'];
    $_SESSION['email']    = $userRow['email'];
    $_SESSION['profile_picture'] = $userRow['profile_picture'];
    $_SESSION['linked_id'] = $linkedRow['id'] ?? null;
    $_SESSION['last_activity'] = time();
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
