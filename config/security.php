<?php
/**
 * Security Functions
 * CSRF protection, session security, input sanitization
 */

require_once __DIR__ . '/constants.php';

/**
 * Start a secure session
 */
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $cookieParams['path'],
            'domain'   => $cookieParams['domain'],
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        
        session_start();
        
        // Regenerate session ID periodically to prevent fixation
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
    }
}

/**
 * Generate a CSRF token and store in session
 */
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token
 */
function validateCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize a string for output (XSS prevention)
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitize a string for use in HTML attributes
 */
function sanitizeInput(string $value): string {
    return trim(strip_tags($value));
}

/**
 * Check if the user is logged in; redirect to login if not
 */
function requireLogin(): void {
    startSecureSession();
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
    
    // Session timeout check
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/auth/login.php?timeout=1');
        exit;
    }
    
    $_SESSION['last_activity'] = time();
}

/**
 * Require admin role; redirect if not admin
 */
function requireAdmin(): void {
    requireLogin();
    
    if ($_SESSION['user_role'] !== ROLE_ADMIN) {
        header('Location: ' . BASE_URL . '/client/dashboard.php');
        exit;
    }
}

/**
 * Return current logged-in user's ID
 */
function getCurrentUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

/**
 * Return current logged-in user's role
 */
function getCurrentUserRole(): string {
    return $_SESSION['user_role'] ?? '';
}

/**
 * Return current logged-in user's full name
 */
function getCurrentUserName(): string {
    return $_SESSION['user_name'] ?? '';
}

/**
 * Check login rate limiting using session-based tracking
 */
function checkLoginRateLimit(string $username): bool {
    $key = 'login_attempts_' . md5($username);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $attempts = &$_SESSION[$key];
    
    // Reset if lockout period has passed
    if ((time() - $attempts['first_attempt']) > LOGIN_LOCKOUT_TIME) {
        $attempts = ['count' => 0, 'first_attempt' => time()];
    }
    
    if ($attempts['count'] >= MAX_LOGIN_ATTEMPTS) {
        return false; // Locked out
    }
    
    return true;
}

/**
 * Increment login attempt counter
 */
function incrementLoginAttempt(string $username): void {
    $key = 'login_attempts_' . md5($username);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $_SESSION[$key]['count']++;
}

/**
 * Reset login attempts on success
 */
function resetLoginAttempts(string $username): void {
    $key = 'login_attempts_' . md5($username);
    unset($_SESSION[$key]);
}

/**
 * Send a JSON response and exit
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Log an audit event
 */
function logAudit(string $action, ?string $tableName = null, ?int $recordId = null, ?array $oldValues = null, ?array $newValues = null): void {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
             VALUES (:user_id, :action, :table_name, :record_id, :old_values, :new_values, :ip_address, :user_agent)"
        );
        $stmt->execute([
            ':user_id'    => getCurrentUserId() ?: null,
            ':action'     => $action,
            ':table_name' => $tableName,
            ':record_id'  => $recordId,
            ':old_values' => $oldValues ? json_encode($oldValues) : null,
            ':new_values' => $newValues ? json_encode($newValues) : null,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (Exception $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}
