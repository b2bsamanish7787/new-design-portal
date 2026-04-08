<?php
/**
 * API: Get projects for client view (read-only)
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/db-functions.php';

header('Content-Type: application/json; charset=utf-8');

startSecureSession();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['status' => 'error', 'message' => 'Unauthorized.'], 403);
}

$search  = sanitizeInput($_GET['search'] ?? '');
$sortCol = sanitizeInput($_GET['sort_col'] ?? 'p.created_at');
$sortDir = sanitizeInput($_GET['sort_dir'] ?? 'DESC');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? ITEMS_PER_PAGE);
$status  = sanitizeInput($_GET['status'] ?? '');

$options = [
    'search'   => $search,
    'sort_col' => $sortCol,
    'sort_dir' => $sortDir,
    'page'     => $page,
    'per_page' => $perPage,
];

if ($status !== '' && in_array($status, [STATUS_PENDING, STATUS_ONGOING, STATUS_COMPLETED], true)) {
    $options['status'] = $status;
}

try {
    $pdo = getDBConnection();

    $allowedSortCols = [
        'p.client_name', 'p.event_name', 'p.start_date', 'p.end_date',
        'p.status', 'p.created_at', 'assigned_by_name', 'remaining_days',
    ];
    if (!in_array($sortCol, $allowedSortCols, true)) {
        $sortCol = 'p.created_at';
    }
    $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

    $whereClauses = [];
    $params = [];

    if ($search !== '') {
        $whereClauses[] = '(p.client_name LIKE :search OR p.event_name LIKE :search2)';
        $params[':search']  = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
    }
    if ($status !== '' && in_array($status, [STATUS_PENDING, STATUS_ONGOING, STATUS_COMPLETED], true)) {
        $whereClauses[] = 'p.status = :status';
        $params[':status'] = $status;
    }

    $whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT p.id) FROM projects p LEFT JOIN users u ON p.assigned_by = u.id $whereSQL");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $pages  = max(1, (int)ceil($total / $perPage));
    $offset = ($page - 1) * $perPage;

    $orderBySQL = $sortCol === 'remaining_days'
        ? "ORDER BY DATEDIFF(p.end_date, CURDATE()) $sortDir"
        : "ORDER BY $sortCol $sortDir";

    $dataSQL = "
        SELECT p.id, p.client_name, p.event_name, p.start_date, p.end_date, p.status,
               COALESCE(u.full_name, u.username) AS assigned_by_name,
               DATEDIFF(p.end_date, CURDATE()) AS remaining_days,
               GROUP_CONCAT(DISTINCT ud.full_name ORDER BY ud.full_name SEPARATOR ', ') AS designers
        FROM projects p
        LEFT JOIN users u ON p.assigned_by = u.id
        LEFT JOIN project_designers pd ON p.id = pd.project_id
        LEFT JOIN users ud ON pd.designer_id = ud.id
        $whereSQL GROUP BY p.id $orderBySQL LIMIT :limit OFFSET :offset
    ";

    $dataStmt = $pdo->prepare($dataSQL);
    foreach ($params as $k => $v) $dataStmt->bindValue($k, $v);
    $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();

    jsonResponse(['status' => 'success', 'data' => $dataStmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page]);
} catch (Exception $e) {
    error_log('Client get-projects error: ' . $e->getMessage());
    jsonResponse(['status' => 'error', 'message' => 'Failed to load projects.'], 500);
}
