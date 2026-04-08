<?php
/**
 * Top Navigation Bar
 */
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
    <div class="container-fluid">
        <!-- Sidebar toggle for mobile -->
        <button class="btn btn-outline-light me-2 d-lg-none" id="sidebarToggle" type="button">
            <i class="bi bi-list"></i>
        </button>

        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/<?= getCurrentUserRole() === ROLE_ADMIN ? 'admin' : 'client' ?>/dashboard.php">
            <i class="bi bi-layers-fill me-1"></i><?= e(APP_NAME) ?>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTop">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarTop">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-2">
                    <span class="badge bg-light text-dark">
                        <i class="bi bi-person-fill me-1"></i>
                        <?= e(getCurrentUserName()) ?>
                        <span class="ms-1 badge <?= getCurrentUserRole() === ROLE_ADMIN ? 'bg-danger' : 'bg-success' ?> text-uppercase" style="font-size:0.65em">
                            <?= e(getCurrentUserRole()) ?>
                        </span>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/auth/logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
