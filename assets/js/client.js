/**
 * client.js – Client (read-only) project table: search, sort, pagination
 * Depends on: main.js, jQuery
 * Expects globals: BASE_URL (set in the PHP page)
 */

$(document).ready(function () {

  /* ── State ───────────────────────────────────────────────── */
  var state = {
    page:    1,
    sortCol: 'p.created_at',
    sortDir: 'DESC',
    search:  '',
    status:  '',
    total:   0,
    pages:   1,
  };

  /* ── Load projects ───────────────────────────────────────── */
  function loadProjects() {
    $('#projectsBody').html(
      '<tr><td colspan="8" class="text-center py-5">' +
      '<div class="spinner-border text-primary" role="status"></div>' +
      '<p class="mt-2 text-muted mb-0">Loading…</p></td></tr>'
    );

    $.ajax({
      url:      BASE_URL + '/client/api/get-projects.php',
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

  /* ── Render table rows (read-only, no actions column) ───── */
  function renderTable(rows) {
    if (!rows || rows.length === 0) {
      $('#projectsBody').html(
        '<tr><td colspan="8" class="text-center text-muted py-5">' +
        '<i class="bi bi-inbox fs-2 d-block mb-2"></i>No projects found.</td></tr>'
      );
      return;
    }

    var html = '';
    rows.forEach(function (p) {
      var urgent    = isUrgentRow(p.remaining_days, p.status);
      var rowCls    = urgent ? 'row-urgent' : '';
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
      '</tr>';
    });

    $('#projectsBody').html(html);
    updateSortIcons();
  }

  /* ── Show error in table ─────────────────────────────────── */
  function showTableError(msg) {
    $('#projectsBody').html(
      '<tr><td colspan="8" class="text-center text-danger py-5">' +
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

    $('#pagination .page-link').on('click', function (e) {
      e.preventDefault();
      var p = parseInt($(this).data('page'), 10);
      if (!isNaN(p) && p >= 1 && p <= pages) {
        state.page = p;
        loadProjects();
      }
    });
  }

  /* ── Sort icons ──────────────────────────────────────────── */
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

  /* ── Initial load ────────────────────────────────────────── */
  loadProjects();
});
