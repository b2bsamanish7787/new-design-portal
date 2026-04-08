<?php
/**
 * Admin Dashboard
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db-functions.php';

startSecureSession();
requireAdmin();

$stats     = getDashboardStats();
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="wrapper d-flex">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
        <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

        <div class="container-fluid py-4 px-4">

            <!-- Page Heading -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-0"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h4>
                    <p class="text-muted mb-0 small">Welcome back, <?= e(getCurrentUserName()) ?>!</p>
                </div>
                <div>
                    <a href="<?= BASE_URL ?>/admin/projects.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>New Project
                    </a>
                    <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-person-plus me-1"></i>Add User
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100 stat-card stat-card-total">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                                <i class="bi bi-folder2-open fs-3"></i>
                            </div>
                            <div>
                                <p class="text-muted small mb-1 fw-medium text-uppercase">Total Projects</p>
                                <h3 class="fw-bold mb-0 counter"><?= (int)$stats['total'] ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100 stat-card stat-card-pending">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                                <i class="bi bi-hourglass-split fs-3"></i>
                            </div>
                            <div>
                                <p class="text-muted small mb-1 fw-medium text-uppercase">Pending</p>
                                <h3 class="fw-bold mb-0 counter"><?= (int)$stats['pending'] ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100 stat-card stat-card-ongoing">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="stat-icon bg-info bg-opacity-10 text-info rounded-3 p-3">
                                <i class="bi bi-arrow-repeat fs-3"></i>
                            </div>
                            <div>
                                <p class="text-muted small mb-1 fw-medium text-uppercase">Ongoing</p>
                                <h3 class="fw-bold mb-0 counter"><?= (int)$stats['ongoing'] ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100 stat-card stat-card-completed">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="stat-icon bg-success bg-opacity-10 text-success rounded-3 p-3">
                                <i class="bi bi-check-circle fs-3"></i>
                            </div>
                            <div>
                                <p class="text-muted small mb-1 fw-medium text-uppercase">Completed</p>
                                <h3 class="fw-bold mb-0 counter"><?= (int)$stats['completed'] ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Projects Preview -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3">
                    <h6 class="fw-semibold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Projects</h6>
                    <a href="<?= BASE_URL ?>/admin/projects.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Client</th>
                                    <th>Event</th>
                                    <th>Status</th>
                                    <th>End Date</th>
                                    <th>Remaining</th>
                                </tr>
                            </thead>
                            <tbody id="recentProjectsBody">
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div><!-- /container -->
    </div><!-- /main-content -->
</div><!-- /wrapper -->

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
$(document).ready(function() {
    // Load recent projects via AJAX
    $.ajax({
        url: '<?= BASE_URL ?>/admin/api/get-projects.php',
        type: 'GET',
        data: { page: 1, per_page: 5, sort_col: 'p.created_at', sort_dir: 'DESC' },
        success: function(res) {
            if (res.status === 'success' && res.data.length > 0) {
                let html = '';
                res.data.forEach(function(p) {
                    const statusBadge = getStatusBadge(p.status);
                    const remainingDays = parseInt(p.remaining_days);
                    const rowClass = (remainingDays < 3 && p.status !== 'completed') ? 'table-danger' : '';
                    const remaining = p.status === 'completed'
                        ? '<span class="text-success"><i class="bi bi-check2"></i> Done</span>'
                        : (remainingDays < 0
                            ? '<span class="text-danger fw-bold">Overdue</span>'
                            : remainingDays + ' day(s)');

                    html += `<tr class="${rowClass}">
                        <td>${escHtml(p.client_name)}</td>
                        <td>${escHtml(p.event_name)}</td>
                        <td>${statusBadge}</td>
                        <td>${formatDate(p.end_date)}</td>
                        <td>${remaining}</td>
                    </tr>`;
                });
                $('#recentProjectsBody').html(html);
            } else {
                $('#recentProjectsBody').html('<tr><td colspan="5" class="text-center text-muted py-4">No projects found.</td></tr>');
            }
        },
        error: function() {
            $('#recentProjectsBody').html('<tr><td colspan="5" class="text-center text-danger py-4">Failed to load projects.</td></tr>');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
