<?php
require_once 'config.php';
require_once 'auth.php';

// Check if user is logged in, if not redirect to login page
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Redirect to dashboard
header('Location: dashboard.php');
exit;
