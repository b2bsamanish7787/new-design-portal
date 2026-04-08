<?php
/**
 * Admin Projects Page
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db-functions.php';

startSecureSession();
requireAdmin();

$designers = getAllDesigners();
$csrfToken = generateCSRFToken();
$pageTitle = 'Projects';
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
                    <h4 class="fw-bold mb-0"><i class="bi bi-kanban me-2 text-primary"></i>Projects</h4>
                    <p class="text-muted mb-0 small">Manage all design projects</p>
                </div>
                <button class="btn btn-primary" id="btnAddProject">
                    <i class="bi bi-plus-lg me-1"></i>Add Project
                </button>
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
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="projectsBody">
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
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
    </div><!-- /main-content -->
</div><!-- /wrapper -->


<!-- ==================== ADD PROJECT MODAL ==================== -->
<div class="modal fade" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title" id="addProjectModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Add New Project
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="addModalAlert"></div>
                <form id="addProjectForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Client Name *</label>
                            <input type="text" class="form-control" name="client_name" required maxlength="100"
                                   placeholder="Enter client name">
                            <div class="invalid-feedback">Client name is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Event Name *</label>
                            <input type="text" class="form-control" name="event_name" required maxlength="150"
                                   placeholder="Enter event name">
                            <div class="invalid-feedback">Event name is required.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Description</label>
                            <textarea class="form-control" name="description" rows="3"
                                      placeholder="Optional description…"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" required>
                            <div class="invalid-feedback">Start date is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">End Date *</label>
                            <input type="date" class="form-control" name="end_date" required>
                            <div class="invalid-feedback">End date must be after start date.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Remaining Days</label>
                            <div class="form-control bg-light text-muted" id="addRemainingDays">
                                — select dates above —
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Assign Designers</label>
                            <select class="form-select" name="designer_ids[]" id="addDesignerSelect" multiple>
                                <?php foreach ($designers as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>"><?= e($d['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Hold Ctrl/Cmd to select multiple designers.</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSaveProject">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="addSpinner"></span>
                    <i class="bi bi-check2 me-1" id="addBtnIcon"></i>Save Project
                </button>
            </div>
        </div>
    </div>
</div>


<!-- ==================== EDIT PROJECT MODAL ==================== -->
<div class="modal fade" id="editProjectModal" tabindex="-1" aria-labelledby="editProjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 bg-warning">
                <h5 class="modal-title" id="editProjectModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Edit Project
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="editModalAlert"></div>
                <form id="editProjectForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="project_id" id="editProjectId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Client Name *</label>
                            <input type="text" class="form-control" name="client_name" id="editClientName" required maxlength="100">
                            <div class="invalid-feedback">Client name is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Event Name *</label>
                            <input type="text" class="form-control" name="event_name" id="editEventName" required maxlength="150">
                            <div class="invalid-feedback">Event name is required.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Description</label>
                            <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" id="editStartDate" required>
                            <div class="invalid-feedback">Start date is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">End Date *</label>
                            <input type="date" class="form-control" name="end_date" id="editEndDate" required>
                            <div class="invalid-feedback">End date must be after start date.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Status *</label>
                            <select class="form-select" name="status" id="editStatus" required>
                                <option value="pending">Pending</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Remaining Days</label>
                            <div class="form-control bg-light text-muted" id="editRemainingDays">—</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Assign Designers</label>
                            <select class="form-select" name="designer_ids[]" id="editDesignerSelect" multiple>
                                <?php foreach ($designers as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>"><?= e($d['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="btnUpdateProject">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="editSpinner"></span>
                    <i class="bi bi-check2 me-1"></i>Update Project
                </button>
            </div>
        </div>
    </div>
</div>


<!-- ==================== DELETE CONFIRM MODAL ==================== -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Delete Project</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-exclamation-triangle-fill text-danger display-5 mb-3 d-block"></i>
                <p>Are you sure you want to delete project <strong id="deleteProjectName"></strong>?</p>
                <p class="text-muted small">This action cannot be undone.</p>
                <input type="hidden" id="deleteProjectId">
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btnConfirmDelete">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="deleteSpinner"></span>
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSRF token for JS -->
<script>
const CSRF_TOKEN = '<?= e($csrfToken) ?>';
const BASE_URL   = '<?= BASE_URL ?>';
const IS_ADMIN   = true;
</script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
