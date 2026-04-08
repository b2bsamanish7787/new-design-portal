/**
 * main.js – Shared utilities used by both admin and client pages
 */

/* ── XSS-safe HTML escape ──────────────────────────────────── */
function escHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/* ── Format ISO date to DD/MM/YYYY ────────────────────────── */
function formatDate(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr + 'T00:00:00');
  if (isNaN(d)) return escHtml(dateStr);
  const dd = String(d.getDate()).padStart(2, '0');
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  return dd + '/' + mm + '/' + d.getFullYear();
}

/* ── Status badge HTML ─────────────────────────────────────── */
function getStatusBadge(status) {
  const map = {
    pending:   { cls: 'badge-pending',   icon: 'bi-hourglass-split', label: 'Pending'   },
    ongoing:   { cls: 'badge-ongoing',   icon: 'bi-arrow-repeat',    label: 'Ongoing'   },
    completed: { cls: 'badge-completed', icon: 'bi-check-circle',    label: 'Completed' },
  };
  const s = map[status] || { cls: 'bg-secondary text-white', icon: 'bi-question', label: escHtml(status) };
  return `<span class="badge-status ${s.cls}"><i class="bi ${s.icon} me-1"></i>${s.label}</span>`;
}

/* ── Remaining days cell HTML ──────────────────────────────── */
function getRemainingHtml(remainingDays, status) {
  if (status === 'completed') {
    return '<span class="days-done"><i class="bi bi-check2-circle me-1"></i>Done</span>';
  }
  const days = parseInt(remainingDays, 10);
  if (isNaN(days)) return '—';
  if (days < 0)  return '<span class="days-overdue"><i class="bi bi-exclamation-triangle-fill me-1"></i>Overdue</span>';
  if (days < 3)  return `<span class="days-urgent"><i class="bi bi-alarm me-1"></i>${days} day(s)</span>`;
  return `<span>${days} day(s)</span>`;
}

/* ── Should the row be highlighted (urgent) ────────────────── */
function isUrgentRow(remainingDays, status) {
  if (status === 'completed') return false;
  const days = parseInt(remainingDays, 10);
  return !isNaN(days) && days < 3;
}

/* ── Toast notifications ───────────────────────────────────── */
(function () {
  // Ensure container exists
  if (!document.getElementById('toastContainer')) {
    const div = document.createElement('div');
    div.id = 'toastContainer';
    document.body.appendChild(div);
  }
}());

function showToast(message, type) {
  type = type || 'info';
  const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
  const icon  = icons[type] || icons.info;
  const container = document.getElementById('toastContainer');

  const el = document.createElement('div');
  el.className = 'toast-message toast-' + type;
  el.innerHTML = `<i class="bi ${icon}"></i><span>${escHtml(message)}</span>`;
  container.appendChild(el);

  setTimeout(function () {
    el.style.transition = 'opacity 0.4s ease';
    el.style.opacity = '0';
    setTimeout(function () { el.remove(); }, 400);
  }, 3500);
}

/* ── Calculate remaining days between two date inputs ─────── */
function calcRemainingDays(startInput, endInput) {
  const endVal = endInput ? endInput.value : '';
  if (!endVal) return null;
  const now  = new Date();
  now.setHours(0, 0, 0, 0);
  const end  = new Date(endVal + 'T00:00:00');
  if (isNaN(end)) return null;
  return Math.round((end - now) / 86400000);
}

/* ── Update a "remaining days" display element ─────────────── */
function updateRemainingDisplay(displayEl, startInput, endInput) {
  const days = calcRemainingDays(startInput, endInput);
  if (days === null) {
    displayEl.textContent = '— select end date —';
    displayEl.className = 'form-control bg-light text-muted';
    return;
  }
  if (days < 0) {
    displayEl.textContent = 'Overdue by ' + Math.abs(days) + ' day(s)';
    displayEl.className = 'form-control bg-light days-overdue';
  } else if (days < 3) {
    displayEl.textContent = days + ' day(s) remaining';
    displayEl.className = 'form-control bg-light days-urgent';
  } else {
    displayEl.textContent = days + ' day(s) remaining';
    displayEl.className = 'form-control bg-light text-success';
  }
}

/* ── Build pagination HTML ─────────────────────────────────── */
function buildPagination(currentPage, totalPages, onPageClick) {
  if (totalPages <= 1) return '';

  let html = '';

  // Prev
  html += `<li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
    <a class="page-link" href="#" data-page="${currentPage - 1}" aria-label="Previous">
      <i class="bi bi-chevron-left"></i>
    </a>
  </li>`;

  // Page numbers with ellipsis
  const delta = 2;
  const pages = [];
  for (let i = 1; i <= totalPages; i++) {
    if (i === 1 || i === totalPages || (i >= currentPage - delta && i <= currentPage + delta)) {
      pages.push(i);
    }
  }

  let last = null;
  pages.forEach(function (p) {
    if (last !== null && p - last > 1) {
      html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
    }
    html += `<li class="page-item ${p === currentPage ? 'active' : ''}">
      <a class="page-link" href="#" data-page="${p}">${p}</a>
    </li>`;
    last = p;
  });

  // Next
  html += `<li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
    <a class="page-link" href="#" data-page="${currentPage + 1}" aria-label="Next">
      <i class="bi bi-chevron-right"></i>
    </a>
  </li>`;

  return html;
}

/* ── Session timeout checker (polls every 2 minutes) ───────── */
(function () {
  var baseUrl = (document.querySelector('meta[name="base-url"]') || {}).content || '';

  function checkSession() {
    fetch(baseUrl + '/auth/check-session.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.authenticated) {
          window.location.href = baseUrl + '/auth/login.php?timeout=1';
        }
      })
      .catch(function () { /* network error – ignore */ });
  }

  // Only run when a user is on authenticated pages (not login page)
  if (!window.location.pathname.includes('/auth/login')) {
    setInterval(checkSession, 120000); // every 2 minutes
  }
}());

/* ── Sidebar toggle for mobile ─────────────────────────────── */
$(document).ready(function () {
  // Create overlay element if sidebar is present
  if ($('#sidebar').length) {
    if (!$('.sidebar-overlay').length) {
      $('body').append('<div class="sidebar-overlay"></div>');
    }

    $('#sidebarToggle').on('click', function () {
      $('#sidebar').toggleClass('show');
      $('.sidebar-overlay').toggleClass('show');
    });

    $('.sidebar-overlay').on('click', function () {
      $('#sidebar').removeClass('show');
      $('.sidebar-overlay').removeClass('show');
    });
  }
});
