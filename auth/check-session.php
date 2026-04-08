<?php
/**
 * Session check endpoint (called via AJAX to detect session expiry)
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/security.php';

startSecureSession();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['authenticated' => false]);
    exit;
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    echo json_encode(['authenticated' => false, 'timeout' => true]);
    exit;
}

$_SESSION['last_activity'] = time();
echo json_encode(['authenticated' => true, 'role' => $_SESSION['user_role']]);
