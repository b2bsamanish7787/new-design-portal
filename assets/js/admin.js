/**
 * admin.js – Admin project management: AJAX CRUD, live search, sort, pagination
 * Depends on: main.js, jQuery, Bootstrap 5
 * Expects globals: CSRF_TOKEN, BASE_URL (set in the PHP page)
 */

$(document).ready(function () {

  /* ── State ───────────────────────────────────────────────── */
  var state = {
    page:     1,
    sortCol:  'p.created_at',
    sortDir:  'DESC',
    search:   '',
    status:   '',
    total:    0,
    pages:    1,
  };

  /* ── Init Select2 on designer selects ────────────────────── */
  function initSelect2(selector) {
    $(selector).select2({
      theme: 'bootstrap-5',
      width: '100%',
      placeholder: 'Select designers…',
      allowClear: true,
    });
  }
  initSelect2('#addDesignerSelect');
  initSelect2('#editDesignerSelect');

  /* ── Load projects ───────────────────────────────────────── */
  function loadProjects() {
    $('#projectsBody').html(
      '<tr><td colspan="9" class="text-center py-5">' +
      '<div class="spinner-border text-primary" role="status"></div>' +
      '<p class="mt-2 text-muted mb-0">Loading…</p></td></tr>'
    );

    $.ajax({
      url:      BASE_URL + '/admin/api/get-projects.php',
      type:     'GET',
      dataType: 'json',
      data: {
        page:     state.page,
        sort_col: state.sortCol,
        sort_dir: state.sortDir,
        search:   state.search,
        status:   state.status,
        per_page: 10,
      },
      success: function (res) {
        if (res.status === 'success') {
          state.total = res.total;
          state.pages = res.pages;
          renderTable(res.data);
          renderPagination(res.page, res.pages, res.total);
        } else {
          showTableError(res.message || 'Failed to load projects.');
        }
      },
      error: function () {
        showTableError('Network error. Please refresh and try again.');
      },
    });
  }

  /* ── Render table rows ───────────────────────────────────── */
  function renderTable(rows) {
    if (!rows || rows.length === 0) {
      $('#projectsBody').html(
        '<tr><td colspan="9" class="text-center text-muted py-5">' +
        '<i class="bi bi-inbox fs-2 d-block mb-2"></i>No projects found.</td></tr>'
      );
      return;
    }

    var html = '';
    rows.forEach(function (p) {
      var urgent   = isUrgentRow(p.remaining_days, p.status);
      var rowCls   = urgent ? 'row-urgent' : '';
      var remaining = getRemainingHtml(p.remaining_days, p.status);
      var designers = p.designers
        ? p.designers.split(', ').map(function (d) {
            return '<span class="tag">' + escHtml(d) + '</span>';
          }).join('')
        : '<span class="text-muted">—</span>';

      html += '<tr class="' + rowCls + '">' +
        '<td class="ps-3"><strong>' + escHtml(p.client_name) + '</strong></td>' +
        '<td>' + escHtml(p.event_name) + '</td>' +
        '<td><div class="designer-tags">' + designers + '</div></td>' +
        '<td>' + formatDate(p.start_date) + '</td>' +
        '<td>' + formatDate(p.end_date) + '</td>' +
        '<td>' + escHtml(p.assigned_by_name || '—') + '</td>' +
        '<td>' + getStatusBadge(p.status) + '</td>' +
        '<td>' + remaining + '</td>' +
        '<td class="text-center">' +
          '<button class="btn btn-sm btn-outline-warning btn-action me-1 btn-edit" ' +
            'data-id="' + p.id + '" title="Edit">' +
            '<i class="bi bi-pencil"></i></button>' +
          '<button class="btn btn-sm btn-outline-danger btn-action btn-delete" ' +
            'data-id="' + p.id + '" ' +
            'data-name="' + escHtml(p.client_name) + ' – ' + escHtml(p.event_name) + '" ' +
            'title="Delete">' +
            '<i class="bi bi-trash"></i></button>' +
        '</td>' +
      '</tr>';
    });

    $('#projectsBody').html(html);
    updateSortIcons();
  }

  /* ── Show table error ────────────────────────────────────── */
  function showTableError(msg) {
    $('#projectsBody').html(
      '<tr><td colspan="9" class="text-center text-danger py-5">' +
      '<i class="bi bi-exclamation-triangle-fill me-1"></i>' + escHtml(msg) + '</td></tr>'
    );
  }

  /* ── Pagination ──────────────────────────────────────────── */
  function renderPagination(page, pages, total) {
    var from = total === 0 ? 0 : (page - 1) * 10 + 1;
    var to   = Math.min(page * 10, total);
    $('#paginationInfo').text('Showing ' + from + '–' + to + ' of ' + total + ' project(s)');
    $('#totalCount').text(total + ' result(s)');

    var html = buildPagination(page, pages);
    $('#pagination').html(html);

    // Bind page clicks
    $('#pagination .page-link').on('click', function (e) {
      e.preventDefault();
      var p = parseInt($(this).data('page'), 10);
      if (!isNaN(p) && p >= 1 && p <= pages) {
        state.page = p;
        loadProjects();
      }
    });
  }

  /* ── Sort column icons ───────────────────────────────────── */
  function updateSortIcons() {
    $('#projectsTable th.sortable').each(function () {
      $(this).removeClass('sort-asc sort-desc');
      $(this).find('.sort-icon').removeClass('text-primary').addClass('text-muted');
    });
    var active = $('#projectsTable th.sortable[data-col="' + state.sortCol + '"]');
    if (active.length) {
      active.addClass(state.sortDir === 'ASC' ? 'sort-asc' : 'sort-desc');
      active.find('.sort-icon').removeClass('text-muted').addClass('text-primary');
    }
  }

  /* ── Sort click ──────────────────────────────────────────── */
  $(document).on('click', '#projectsTable th.sortable', function () {
    var col = $(this).data('col');
    if (state.sortCol === col) {
      state.sortDir = state.sortDir === 'ASC' ? 'DESC' : 'ASC';
    } else {
      state.sortCol = col;
      state.sortDir = 'ASC';
    }
    state.page = 1;
    loadProjects();
  });

  /* ── Live search with debounce ───────────────────────────── */
  var searchTimer;
  $('#searchInput').on('input', function () {
    clearTimeout(searchTimer);
    var val = $(this).val();
    searchTimer = setTimeout(function () {
      state.search = val;
      state.page   = 1;
      loadProjects();
    }, 350);
  });

  /* ── Status filter ───────────────────────────────────────── */
  $('#statusFilter').on('change', function () {
    state.status = $(this).val();
    state.page   = 1;
    loadProjects();
  });

  /* ── ADD PROJECT ─────────────────────────────────────────── */
  var addModal  = new bootstrap.Modal(document.getElementById('addProjectModal'));
  var editModal = new bootstrap.Modal(document.getElementById('editProjectModal'));
  var delModal  = new bootstrap.Modal(document.getElementById('deleteModal'));

  // Open add modal
  $('#btnAddProject').on('click', function () {
    $('#addProjectForm')[0].reset();
    $('#addProjectForm').removeClass('was-validated');
    $('#addModalAlert').html('');
    $('#addRemainingDays').text('— select dates above —').removeClass().addClass('form-control bg-light text-muted');
    $('#addDesignerSelect').val(null).trigger('change');
    addModal.show();
  });

  // Real-time remaining days in Add form
  $('#addProjectForm [name="start_date"], #addProjectForm [name="end_date"]').on('change', function () {
    var el  = document.getElementById('addRemainingDays');
    var s   = document.querySelector('#addProjectForm [name="start_date"]');
    var e   = document.querySelector('#addProjectForm [name="end_date"]');
    updateRemainingDisplay(el, s, e);
  });

  // Save new project
  $('#btnSaveProject').on('click', function () {
    var $form = $('#addProjectForm');
    $form.addClass('was-validated');

    // Client-side validation
    if (!$form[0].checkValidity()) return;

    var endDate   = $form.find('[name="end_date"]').val();
    var startDate = $form.find('[name="start_date"]').val();
    if (endDate && startDate && endDate < startDate) {
      showModalAlert('addModalAlert', 'End date must be on or after start date.', 'danger');
      return;
    }

    var $btn = $(this);
    setLoading($btn, '#addSpinner', '#addBtnIcon', true);

    $.ajax({
      url:      BASE_URL + '/admin/api/add-project.php',
      type:     'POST',
      dataType: 'json',
      data:     $form.serialize(),
      success: function (res) {
        setLoading($btn, '#addSpinner', '#addBtnIcon', false);
        if (res.status === 'success') {
          addModal.hide();
          $form[0].reset();
          $form.removeClass('was-validated');
          $('#addDesignerSelect').val(null).trigger('change');
          showToast(res.message || 'Project added successfully.', 'success');
          state.page = 1;
          loadProjects();
        } else {
          showModalAlert('addModalAlert', res.message || 'Failed to add project.', 'danger');
        }
      },
      error: function () {
        setLoading($btn, '#addSpinner', '#addBtnIcon', false);
        showModalAlert('addModalAlert', 'Network error. Please try again.', 'danger');
      },
    });
  });

  /* ── EDIT PROJECT ────────────────────────────────────────── */
  $(document).on('click', '.btn-edit', function () {
    var projectId = $(this).data('id');
    $('#editModalAlert').html('');
    $('#editProjectForm').removeClass('was-validated');

    // Fetch project data
    $.ajax({
      url:      BASE_URL + '/admin/api/get-projects.php',
      type:     'GET',
      dataType: 'json',
      data:     { project_id: projectId },
      success: function (res) {
        if (res.status === 'success' && res.project) {
          var p = res.project;
          $('#editProjectId').val(p.id);
          $('#editClientName').val(p.client_name);
          $('#editEventName').val(p.event_name);
          $('#editDescription').val(p.description || '');
          $('#editStartDate').val(p.start_date);
          $('#editEndDate').val(p.end_date);
          $('#editStatus').val(p.status);

          // Update remaining days display
          var el = document.getElementById('editRemainingDays');
          var s  = document.getElementById('editStartDate');
          var e  = document.getElementById('editEndDate');
          updateRemainingDisplay(el, s, e);

          // Set designer selections
          var ids = (p.designer_ids || []).map(String);
          $('#editDesignerSelect').val(ids).trigger('change');

          editModal.show();
        } else {
          showToast('Could not load project data.', 'error');
        }
      },
      error: function () {
        showToast('Network error loading project.', 'error');
      },
    });
  });

  // Real-time remaining days in Edit form
  $('#editStartDate, #editEndDate').on('change', function () {
    var el = document.getElementById('editRemainingDays');
    var s  = document.getElementById('editStartDate');
    var e  = document.getElementById('editEndDate');
    updateRemainingDisplay(el, s, e);
  });

  // Submit edit
  $('#btnUpdateProject').on('click', function () {
    var $form = $('#editProjectForm');
    $form.addClass('was-validated');
    if (!$form[0].checkValidity()) return;

    var endDate   = $('#editEndDate').val();
    var startDate = $('#editStartDate').val();
    if (endDate && startDate && endDate < startDate) {
      showModalAlert('editModalAlert', 'End date must be on or after start date.', 'danger');
      return;
    }

    var $btn = $(this);
    setLoading($btn, '#editSpinner', null, true);

    $.ajax({
      url:      BASE_URL + '/admin/api/edit-project.php',
      type:     'POST',
      dataType: 'json',
      data:     $form.serialize(),
      success: function (res) {
        setLoading($btn, '#editSpinner', null, false);
        if (res.status === 'success') {
          editModal.hide();
          showToast(res.message || 'Project updated.', 'success');
          loadProjects();
        } else {
          showModalAlert('editModalAlert', res.message || 'Update failed.', 'danger');
        }
      },
      error: function () {
        setLoading($btn, '#editSpinner', null, false);
        showModalAlert('editModalAlert', 'Network error. Please try again.', 'danger');
      },
    });
  });

  /* ── DELETE PROJECT ──────────────────────────────────────── */
  $(document).on('click', '.btn-delete', function () {
    var id   = $(this).data('id');
    var name = $(this).data('name');
    $('#deleteProjectId').val(id);
    $('#deleteProjectName').text(name);
    delModal.show();
  });

  $('#btnConfirmDelete').on('click', function () {
    var projectId = $('#deleteProjectId').val();
    var $btn = $(this);
    setLoading($btn, '#deleteSpinner', null, true);

    $.ajax({
      url:      BASE_URL + '/admin/api/delete-project.php',
      type:     'POST',
      dataType: 'json',
      data:     { project_id: projectId, csrf_token: CSRF_TOKEN },
      success: function (res) {
        setLoading($btn, '#deleteSpinner', null, false);
        delModal.hide();
        if (res.status === 'success') {
          showToast(res.message || 'Project deleted.', 'success');
          // Adjust page if we deleted the last item on it
          if (state.page > 1 && state.total - 1 <= (state.page - 1) * 10) {
            state.page--;
          }
          loadProjects();
        } else {
          showToast(res.message || 'Delete failed.', 'error');
        }
      },
      error: function () {
        setLoading($btn, '#deleteSpinner', null, false);
        showToast('Network error. Please try again.', 'error');
      },
    });
  });

  /* ── Helpers ─────────────────────────────────────────────── */
  function showModalAlert(containerId, message, type) {
    var html = '<div class="alert alert-' + type + ' alert-dismissible py-2 mb-0">' +
      '<i class="bi bi-exclamation-triangle-fill me-1"></i>' + escHtml(message) +
      '<button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button></div>';
    $('#' + containerId).html(html);
  }

  function setLoading($btn, spinnerId, iconId, loading) {
    $(spinnerId).toggleClass('d-none', !loading);
    if (iconId) $(iconId).toggleClass('d-none', loading);
    $btn.prop('disabled', loading);
  }

  /* ── Initial load ────────────────────────────────────────── */
  loadProjects();
});
