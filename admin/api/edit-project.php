<?php
/**
 * API: Edit an existing project (Admin only)
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

$projectId   = (int)($_POST['project_id'] ?? 0);
$clientName  = sanitizeInput($_POST['client_name'] ?? '');
$eventName   = sanitizeInput($_POST['event_name'] ?? '');
$description = sanitizeInput($_POST['description'] ?? '');
$startDate   = $_POST['start_date'] ?? '';
$endDate     = $_POST['end_date'] ?? '';
$status      = $_POST['status'] ?? '';
$designerIds = $_POST['designer_ids'] ?? [];

$errors = [];

if ($projectId <= 0)         $errors[] = 'Invalid project ID.';
if (empty($clientName))      $errors[] = 'Client name is required.';
if (empty($eventName))       $errors[] = 'Event name is required.';
if (empty($startDate))       $errors[] = 'Start date is required.';
if (empty($endDate))         $errors[] = 'End date is required.';
if (!in_array($status, [STATUS_PENDING, STATUS_ONGOING, STATUS_COMPLETED], true)) {
    $errors[] = 'Invalid status.';
}

if (empty($errors)) {
    $start = DateTime::createFromFormat('Y-m-d', $startDate);
    $end   = DateTime::createFromFormat('Y-m-d', $endDate);
    if (!$start || !$end) {
        $errors[] = 'Invalid date format.';
    } elseif ($end < $start) {
        $errors[] = 'End date must be on or after start date.';
    }
}

if (!empty($errors)) {
    jsonResponse(['status' => 'error', 'message' => implode(' ', $errors)]);
}

// Verify project exists
$existing = getProjectById($projectId);
if (!$existing) {
    jsonResponse(['status' => 'error', 'message' => 'Project not found.'], 404);
}

$sanitizedDesignerIds = array_map('intval', (array)$designerIds);
$sanitizedDesignerIds = array_filter($sanitizedDesignerIds, fn($id) => $id > 0);

try {
    updateProject($projectId, [
        'client_name'  => $clientName,
        'event_name'   => $eventName,
        'description'  => $description ?: null,
        'start_date'   => $startDate,
        'end_date'     => $endDate,
        'status'       => $status,
        'designer_ids' => $sanitizedDesignerIds,
    ]);

    logAudit('update_project', 'projects', $projectId, $existing, [
        'client_name' => $clientName,
        'event_name'  => $eventName,
        'status'      => $status,
    ]);

    jsonResponse(['status' => 'success', 'message' => 'Project updated successfully.']);
} catch (Exception $e) {
    error_log('Edit project error: ' . $e->getMessage());
    jsonResponse(['status' => 'error', 'message' => 'Failed to update project. Please try again.'], 500);
}
