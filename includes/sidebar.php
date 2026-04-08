<?php
/**
 * Admin Sidebar Navigation
 */
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebar" class="sidebar d-flex flex-column flex-shrink-0 p-3 bg-dark text-white">
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
        <i class="bi bi-layers-fill fs-4 me-2 text-primary"></i>
        <span class="fs-5 fw-bold"><?= e(APP_NAME) ?></span>
    </a>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/dashboard.php"
               class="nav-link text-white <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
                <i class="bi bi-speedometer2 me-2"></i>
                Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/projects.php"
               class="nav-link text-white <?= ($currentPage === 'projects.php') ? 'active' : '' ?>">
                <i class="bi bi-kanban me-2"></i>
                Projects
            </a>
        </li>
    </ul>
    <hr>
    <div class="dropdown">
        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle"
           data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle me-2 fs-5"></i>
            <strong><?= e(getCurrentUserName()) ?></strong>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
            <li><span class="dropdown-item-text text-muted small">
                <i class="bi bi-shield-check me-1"></i>Admin
            </span></li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item" href="<?= BASE_URL ?>/auth/logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i>Sign out
                </a>
            </li>
        </ul>
    </div>
</nav>
