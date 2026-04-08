<?php
/**
 * Register new admin user (admin only)
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db-functions.php';

startSecureSession();
requireAdmin();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrfToken)) {
        $error = 'Invalid security token.';
    } else {
        $username  = sanitizeInput($_POST['username'] ?? '');
        $email     = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $fullName  = sanitizeInput($_POST['full_name'] ?? '');
        $role      = in_array($_POST['role'] ?? '', ['admin', 'client']) ? $_POST['role'] : 'client';
        $password  = $_POST['password'] ?? '';
        $password2 = $_POST['password_confirm'] ?? '';

        if (empty($username) || empty($email) || empty($fullName) || empty($password)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
            $error = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.';
        } elseif ($password !== $password2) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $pdo  = getDBConnection();
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare(
                    "INSERT INTO users (username, email, password, role, full_name) VALUES (:u, :e, :p, :r, :f)"
                );
                $stmt->execute([
                    ':u' => $username,
                    ':e' => $email,
                    ':p' => $hash,
                    ':r' => $role,
                    ':f' => $fullName,
                ]);

                logAudit('create_user', 'users', (int)$pdo->lastInsertId(), null, ['username' => $username, 'role' => $role]);
                $success = 'User "' . e($username) . '" created successfully.';
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    $error = 'Username or email already exists.';
                } else {
                    $error = 'An error occurred. Please try again.';
                    error_log($ex->getMessage());
                }
            }
        }
    }
}

$csrfToken = generateCSRFToken();
$pageTitle = 'Register User';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="wrapper d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
        <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

        <div class="container-fluid py-4 px-4">
            <div class="row mb-3">
                <div class="col">
                    <h4 class="fw-bold"><i class="bi bi-person-plus me-2 text-primary"></i>Register New User</h4>
                    <p class="text-muted">Create admin or client accounts</p>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><i class="bi bi-check-circle-fill me-1"></i><?= $success ?></div>
                    <?php endif; ?>

                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <form method="POST" action="" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                                <div class="mb-3">
                                    <label class="form-label fw-medium">Full Name *</label>
                                    <input type="text" name="full_name" class="form-control" required
                                           value="<?= isset($_POST['full_name']) ? e(sanitizeInput($_POST['full_name'])) : '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-medium">Username *</label>
                                    <input type="text" name="username" class="form-control" required
                                           value="<?= isset($_POST['username']) ? e(sanitizeInput($_POST['username'])) : '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-medium">Email *</label>
                                    <input type="email" name="email" class="form-control" required
                                           value="<?= isset($_POST['email']) ? e(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL)) : '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-medium">Role *</label>
                                    <select name="role" class="form-select">
                                        <option value="client" <?= (($_POST['role'] ?? '') === 'client') ? 'selected' : '' ?>>Client</option>
                                        <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin (Designer)</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-medium">Password *</label>
                                    <input type="password" name="password" class="form-control"
                                           minlength="<?= MIN_PASSWORD_LENGTH ?>" required>
                                    <div class="form-text">Minimum <?= MIN_PASSWORD_LENGTH ?> characters.</div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-medium">Confirm Password *</label>
                                    <input type="password" name="password_confirm" class="form-control" required>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-person-plus me-1"></i> Create User
                                </button>
                                <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
