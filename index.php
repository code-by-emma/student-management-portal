<?php
require_once __DIR__ . '/config/app.php';

if (is_logged_in()) {
    redirect('/' . $_SESSION['role'] . '/dashboard.php');
}
redirect('/auth/login.php');
