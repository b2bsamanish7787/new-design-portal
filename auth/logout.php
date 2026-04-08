<?php
/**
 * Logout - destroys session and redirects to login
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/security.php';

startSecureSession();

if (isset($_SESSION['user_id'])) {
    logAudit('logout', 'users', (int)$_SESSION['user_id']);
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: ' . BASE_URL . '/auth/login.php');
exit;
