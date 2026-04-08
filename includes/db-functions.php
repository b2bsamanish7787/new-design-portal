<?php
/**
 * Reusable Database Functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Get all admin users (potential designers)
 */
function getAllDesigners(): array {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, full_name, username FROM users WHERE role = 'admin' ORDER BY full_name ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get a single user by ID
 */
function getUserById(int $id): ?array {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, username, email, full_name, role, created_at, last_login FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Get a user by username
 */
function getUserByUsername(string $username): ?array {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Update user's last login timestamp
 */
function updateLastLogin(int $userId): void {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
    $stmt->execute([':id' => $userId]);
}

/**
 * Get projects with optional search, sort, and pagination.
 * Returns ['data' => [...], 'total' => int, 'pages' => int]
 */
function getProjects(array $options = []): array {
    $pdo = getDBConnection();
    
    $search    = $options['search'] ?? '';
    $sortCol   = $options['sort_col'] ?? 'p.created_at';
    $sortDir   = strtoupper($options['sort_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
    $page      = max(1, (int)($options['page'] ?? 1));
    $perPage   = (int)($options['per_page'] ?? ITEMS_PER_PAGE);
    $clientId  = isset($options['client_id']) ? (int)$options['client_id'] : null;

    // Whitelist sortable columns
    $allowedSortCols = [
        'p.client_name', 'p.event_name', 'p.start_date', 'p.end_date',
        'p.status', 'p.created_at', 'assigned_by_name', 'remaining_days',
    ];
    if (!in_array($sortCol, $allowedSortCols, true)) {
        $sortCol = 'p.created_at';
    }

    // Build WHERE clause
    $whereClauses = [];
    $params = [];

    if ($search !== '') {
        $whereClauses[] = '(p.client_name LIKE :search OR p.event_name LIKE :search2)';
        $params[':search']  = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
    }

    if ($clientId !== null) {
        // For client view – show all projects (clients see all)
        // You can restrict by assigned_by if needed
    }

    $whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    // Count total
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

    // Special handling for remaining_days sort (computed column)
    $orderBySQL = $sortCol === 'remaining_days'
        ? "ORDER BY DATEDIFF(p.end_date, CURDATE()) $sortDir"
        : "ORDER BY $sortCol $sortDir";

    // Main query
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

/**
 * Get a single project by ID with designers
 */
function getProjectById(int $id): ?array {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            COALESCE(u.full_name, u.username) AS assigned_by_name,
            DATEDIFF(p.end_date, CURDATE()) AS remaining_days
        FROM projects p
        LEFT JOIN users u ON p.assigned_by = u.id
        WHERE p.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $project = $stmt->fetch();
    
    if (!$project) {
        return null;
    }
    
    // Get designer IDs
    $dStmt = $pdo->prepare("SELECT designer_id FROM project_designers WHERE project_id = :project_id");
    $dStmt->execute([':project_id' => $id]);
    $project['designer_ids'] = array_column($dStmt->fetchAll(), 'designer_id');
    
    return $project;
}

/**
 * Create a new project
 */
function createProject(array $data): int {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        INSERT INTO projects (client_name, event_name, description, start_date, end_date, status, assigned_by)
        VALUES (:client_name, :event_name, :description, :start_date, :end_date, :status, :assigned_by)
    ");
    $stmt->execute([
        ':client_name' => $data['client_name'],
        ':event_name'  => $data['event_name'],
        ':description' => $data['description'] ?? null,
        ':start_date'  => $data['start_date'],
        ':end_date'    => $data['end_date'],
        ':status'      => $data['status'],
        ':assigned_by' => $data['assigned_by'],
    ]);
    
    $projectId = (int)$pdo->lastInsertId();
    
    // Insert designers
    if (!empty($data['designer_ids'])) {
        assignDesigners($projectId, $data['designer_ids']);
    }
    
    return $projectId;
}

/**
 * Update an existing project
 */
function updateProject(int $id, array $data): bool {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        UPDATE projects
        SET client_name = :client_name,
            event_name  = :event_name,
            description = :description,
            start_date  = :start_date,
            end_date    = :end_date,
            status      = :status
        WHERE id = :id
    ");
    $result = $stmt->execute([
        ':client_name' => $data['client_name'],
        ':event_name'  => $data['event_name'],
        ':description' => $data['description'] ?? null,
        ':start_date'  => $data['start_date'],
        ':end_date'    => $data['end_date'],
        ':status'      => $data['status'],
        ':id'          => $id,
    ]);
    
    if ($result && isset($data['designer_ids'])) {
        // Remove old designers and re-assign
        $delStmt = $pdo->prepare("DELETE FROM project_designers WHERE project_id = :project_id");
        $delStmt->execute([':project_id' => $id]);
        
        if (!empty($data['designer_ids'])) {
            assignDesigners($id, $data['designer_ids']);
        }
    }
    
    return $result;
}

/**
 * Delete a project
 */
function deleteProject(int $id): bool {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = :id");
    return $stmt->execute([':id' => $id]);
}

/**
 * Assign designers to a project
 */
function assignDesigners(int $projectId, array $designerIds): void {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT IGNORE INTO project_designers (project_id, designer_id) VALUES (:project_id, :designer_id)");
    
    foreach ($designerIds as $designerId) {
        $stmt->execute([
            ':project_id'  => $projectId,
            ':designer_id' => (int)$designerId,
        ]);
    }
}

/**
 * Get dashboard statistics
 */
function getDashboardStats(): array {
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'ongoing') AS ongoing,
            SUM(status = 'completed') AS completed,
            SUM(status = 'pending') AS pending
        FROM projects
    ");
    
    return $stmt->fetch() ?: ['total' => 0, 'ongoing' => 0, 'completed' => 0, 'pending' => 0];
}
