<?php
/**
 * Client Dashboard
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db-functions.php';

startSecureSession();
requireLogin();

// Redirect admins to their dashboard
if (getCurrentUserRole() === ROLE_ADMIN) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

$pageTitle = 'My Projects';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column min-vh-100">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid py-4 px-4 flex-grow-1">

        <!-- Heading -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-0"><i class="bi bi-folder2-open me-2 text-primary"></i>All Projects</h4>
                <p class="text-muted mb-0 small">Viewing all projects – read-only access</p>
            </div>
        </div>

        <!-- Alert placeholder -->
        <div id="alertPlaceholder"></div>

        <!-- Filters Row -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body py-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-5 col-lg-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="searchInput" class="form-control border-start-0"
                                   placeholder="Search client or event name…">
                        </div>
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <select id="statusFilter" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="col-auto ms-auto">
                        <span class="text-muted small" id="totalCount"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Projects Table -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="projectsTable">
                        <thead class="table-light">
                            <tr>
                                <th class="sortable ps-3" data-col="p.client_name">
                                    Client Name <i class="bi bi-chevron-expand ms-1 text-muted sort-icon"></i>
                                </th>
                                <th class="sortable" data-col="p.event_name">
                                    Event Name <i class="bi bi-chevron-expand ms-1 text-muted sort-icon"></i>
                                </th>
                                <th>Designers</th>
                                <th class="sortable" data-col="p.start_date">
                                    Start Date <i class="bi bi-chevron-expand ms-1 text-muted sort-icon"></i>
                                </th>
                                <th class="sortable" data-col="p.end_date">
                                    End Date <i class="bi bi-chevron-expand ms-1 text-muted sort-icon"></i>
                                </th>
                                <th class="sortable" data-col="assigned_by_name">
                                    Assigned By <i class="bi bi-chevron-expand ms-1 text-muted sort-icon"></i>
                                </th>
                                <th class="sortable" data-col="p.status">
                                    Status <i class="bi bi-chevron-expand ms-1 text-muted sort-icon"></i>
                                </th>
                                <th class="sortable" data-col="remaining_days">
                                    Remaining <i class="bi bi-chevron-expand ms-1 text-muted sort-icon"></i>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="projectsBody">
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status"></div>
                                    <p class="mt-2 text-muted">Loading projects…</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-3 px-4">
                <div class="text-muted small" id="paginationInfo"></div>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                </nav>
            </div>
        </div>

    </div><!-- /container -->

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const IS_ADMIN = false;
</script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/client.js"></script>
