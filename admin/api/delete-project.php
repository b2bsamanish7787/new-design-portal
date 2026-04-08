<?php
/**
 * API: Delete a project (Admin only)
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'Method not allowed.'], 405);
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    jsonResponse(['status' => 'error', 'message' => 'Invalid security token.'], 403);
}

$projectId = (int)($_POST['project_id'] ?? 0);

if ($projectId <= 0) {
    jsonResponse(['status' => 'error', 'message' => 'Invalid project ID.']);
}

$existing = getProjectById($projectId);
if (!$existing) {
    jsonResponse(['status' => 'error', 'message' => 'Project not found.'], 404);
}

try {
    deleteProject($projectId);
    
    logAudit('delete_project', 'projects', $projectId, $existing, null);
    
    jsonResponse(['status' => 'success', 'message' => 'Project deleted successfully.']);
} catch (Exception $e) {
    error_log('Delete project error: ' . $e->getMessage());
    jsonResponse(['status' => 'error', 'message' => 'Failed to delete project. Please try again.'], 500);
}
