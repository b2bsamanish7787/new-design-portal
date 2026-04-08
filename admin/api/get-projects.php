<?php
/**
 * API: Get projects list with search/sort/pagination (Admin)
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

$search   = sanitizeInput($_GET['search'] ?? '');
$sortCol  = sanitizeInput($_GET['sort_col'] ?? 'p.created_at');
$sortDir  = sanitizeInput($_GET['sort_dir'] ?? 'DESC');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = (int)($_GET['per_page'] ?? ITEMS_PER_PAGE);
$status   = sanitizeInput($_GET['status'] ?? '');

// Append status filter to search options if provided
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

// Get a single project for edit (when project_id is provided)
if (isset($_GET['project_id'])) {
    $projectId = (int)$_GET['project_id'];
    $project   = getProjectById($projectId);
    if ($project) {
        jsonResponse(['status' => 'success', 'project' => $project]);
    } else {
        jsonResponse(['status' => 'error', 'message' => 'Project not found.'], 404);
    }
}

try {
    $result = getProjectsWithStatus($options);
    jsonResponse([
        'status' => 'success',
        'data'   => $result['data'],
        'total'  => $result['total'],
        'pages'  => $result['pages'],
        'page'   => $result['page'],
    ]);
} catch (Exception $e) {
    error_log('Get projects error: ' . $e->getMessage());
    jsonResponse(['status' => 'error', 'message' => 'Failed to load projects.'], 500);
}

/**
 * Extended version that supports status filter
 */
function getProjectsWithStatus(array $options): array {
    $pdo = getDBConnection();

    $search   = $options['search'] ?? '';
    $sortCol  = $options['sort_col'] ?? 'p.created_at';
    $sortDir  = strtoupper($options['sort_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
    $page     = max(1, (int)($options['page'] ?? 1));
    $perPage  = (int)($options['per_page'] ?? ITEMS_PER_PAGE);
    $status   = $options['status'] ?? '';

    $allowedSortCols = [
        'p.client_name', 'p.event_name', 'p.start_date', 'p.end_date',
        'p.status', 'p.created_at', 'assigned_by_name', 'remaining_days',
    ];
    if (!in_array($sortCol, $allowedSortCols, true)) {
        $sortCol = 'p.created_at';
    }

    $whereClauses = [];
    $params = [];

    if ($search !== '') {
        $whereClauses[] = '(p.client_name LIKE :search OR p.event_name LIKE :search2)';
        $params[':search']  = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
    }

    if ($status !== '') {
        $whereClauses[] = 'p.status = :status';
        $params[':status'] = $status;
    }

    $whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    $countSQL = "
        SELECT COUNT(DISTINCT p.id)
        FROM projects p
        LEFT JOIN users u ON p.assigned_by = u.id
        $whereSQL
    ";
    $countStmt = $pdo->prepare($countSQL);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $pages  = max(1, (int)ceil($total / $perPage));
    $offset = ($page - 1) * $perPage;

    $orderBySQL = $sortCol === 'remaining_days'
        ? "ORDER BY DATEDIFF(p.end_date, CURDATE()) $sortDir"
        : "ORDER BY $sortCol $sortDir";

    $dataSQL = "
        SELECT
            p.id,
            p.client_name,
            p.event_name,
            p.description,
            p.start_date,
            p.end_date,
            p.status,
            p.assigned_by,
            COALESCE(u.full_name, u.username) AS assigned_by_name,
            p.created_at,
            p.updated_at,
            DATEDIFF(p.end_date, CURDATE()) AS remaining_days,
            GROUP_CONCAT(DISTINCT ud.full_name ORDER BY ud.full_name SEPARATOR ', ') AS designers
        FROM projects p
        LEFT JOIN users u ON p.assigned_by = u.id
        LEFT JOIN project_designers pd ON p.id = pd.project_id
        LEFT JOIN users ud ON pd.designer_id = ud.id
        $whereSQL
        GROUP BY p.id
        $orderBySQL
        LIMIT :limit OFFSET :offset
    ";

    $dataStmt = $pdo->prepare($dataSQL);
    foreach ($params as $k => $v) {
        $dataStmt->bindValue($k, $v);
    }
    $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    $data = $dataStmt->fetchAll();

    return [
        'data'  => $data,
        'total' => $total,
        'pages' => $pages,
        'page'  => $page,
    ];
}
