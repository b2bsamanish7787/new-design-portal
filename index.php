<?php
/**
 * Entry point – redirect to login or appropriate dashboard
 */
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/security.php';

startSecureSession();

if (isset($_SESSION['user_id'])) {
    $redirect = $_SESSION['user_role'] === ROLE_ADMIN
        ? BASE_URL . '/admin/dashboard.php'
        : BASE_URL . '/client/dashboard.php';
    header('Location: ' . $redirect);
} else {
    header('Location: ' . BASE_URL . '/auth/login.php');
}
exit;
