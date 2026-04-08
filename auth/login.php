<?php
/**
 * Login Page
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db-functions.php';

startSecureSession();

// Already logged in → redirect
if (isset($_SESSION['user_id'])) {
    $redirect = $_SESSION['user_role'] === ROLE_ADMIN
        ? BASE_URL . '/admin/dashboard.php'
        : BASE_URL . '/client/dashboard.php';
    header('Location: ' . $redirect);
    exit;
}

$error   = '';
$timeout = isset($_GET['timeout']) && $_GET['timeout'] == '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrfToken)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } elseif (!checkLoginRateLimit($username)) {
            $error = 'Too many failed attempts. Please wait 15 minutes before trying again.';
        } else {
            $user = getUserByUsername($username);
            
            if ($user && password_verify($password, $user['password'])) {
                // Success
                resetLoginAttempts($username);
                
                session_regenerate_id(true);
                $_SESSION['user_id']       = $user['id'];
                $_SESSION['user_role']     = $user['role'];
                $_SESSION['user_name']     = $user['full_name'];
                $_SESSION['username']      = $user['username'];
                $_SESSION['last_activity'] = time();
                
                updateLastLogin((int)$user['id']);
                logAudit('login', 'users', (int)$user['id']);
                
                $redirect = $user['role'] === ROLE_ADMIN
                    ? BASE_URL . '/admin/dashboard.php'
                    : BASE_URL . '/client/dashboard.php';
                
                header('Location: ' . $redirect);
                exit;
            } else {
                incrementLoginAttempt($username);
                $error = 'Invalid username or password.';
            }
        }
    }
}

$csrfToken = generateCSRFToken();
$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-light">

<div class="min-vh-100 d-flex align-items-center justify-content-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">

                <div class="text-center mb-4">
                    <i class="bi bi-layers-fill display-4 text-primary"></i>
                    <h2 class="fw-bold mt-2"><?= e(APP_NAME) ?></h2>
                    <p class="text-muted">Project Management Portal</p>
                </div>

                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4 fw-semibold">Sign In</h5>

                        <?php if ($timeout): ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="bi bi-clock-history me-1"></i>
                                Your session has expired. Please log in again.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                <?= e($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" novalidate id="loginForm">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                            <div class="mb-3">
                                <label for="username" class="form-label fw-medium">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="username" name="username"
                                           placeholder="Enter username" required autofocus
                                           value="<?= isset($_POST['username']) ? e(sanitizeInput($_POST['username'])) : '' ?>">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label fw-medium">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password"
                                           placeholder="Enter password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye" id="eyeIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                            </button>
                        </form>

                        <hr class="my-3">
                        <p class="text-center text-muted small mb-0">
                            Default credentials: <code>admin / password</code>
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePassword').addEventListener('click', function() {
    const pwd = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});
</script>
</body>
</html>
