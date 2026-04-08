<?php
/**
 * API: Get list of designers (Admin users)
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/db-functions.php';

header('Content-Type: application/json; charset=utf-8');

startSecureSession();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== ROLE_ADMIN) {
    jsonResponse(['status' => 'error', 'message' => 'Unauthorized.'], 403);
}

try {
    $designers = getAllDesigners();
    jsonResponse(['status' => 'success', 'designers' => $designers]);
} catch (Exception $e) {
    error_log('Get designers error: ' . $e->getMessage());
    jsonResponse(['status' => 'error', 'message' => 'Failed to load designers.'], 500);
}
