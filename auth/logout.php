<?php
require_once __DIR__ . '/../config/app.php';
if (is_logged_in()) {
    audit_log($_SESSION['user_id'], 'auth.logout');
}
logout_user();
redirect('/auth/login.php');
