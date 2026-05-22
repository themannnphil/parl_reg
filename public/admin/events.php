<?php
session_start();
require_once __DIR__ . '/../../app/bootstrap.php';

// TEMP DEBUG
// echo "<pre>";
// print_r($_SESSION);
// echo "</pre>";
// exit;

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Check if user is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // header("Location: ../index.php"); routing issue
    header("Location: /index.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Events — Parliamentary Services Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  
  <link rel="stylesheet" href="../assets/css/parlreg.css"/>
  <script>
    window.PARLREG_CSRF = "<?= CSRF::token() ?>";
  </script>
  <style>
    :root { --ps-green:#0b6b1b; --ps-green-dark:#046415; }
    body  { background:#f8faf8; font-family:Arial,sans-serif; }

    /* Sidebar */
    .admin-sidebar {
      width:220px; min-height:100vh; background:#0c2d17;
      position:fixed; top:0; left:0; z-index:40;
      display:flex; flex-direction:column;
    }
    .sidebar-brand { padding:20px 16px 14px; border-bottom:1px solid rgba(255,255,255,.08); }
    .sidebar-brand span { color:#fff; font-weight:700; font-size:15px; }
    .sidebar-brand small { color:rgba(255,255,255,.45); font-size:11px; display:block; margin-top:2px; }
    .nav-item-side {
      display:flex; align-items:center; gap:10px;
      padding:10px 16px; color:rgba(255,255,255,.65);
      text-decoration:none; font-size:13.5px; font-weight:500;
      transition:all .15s; border-left:3px solid transparent;
    }
    .nav-item-side:hover { color:#fff; background:rgba(255,255,255,.07); }
    .nav-item-side.active { color:#fff; background:rgba(255,255,255,.10); border-left-color:#4ade80; }
    .nav-item-side i { font-size:16px; }

    /* Main */
    .admin-main { margin-left:220px; padding:28px 32px; }

    /* Top bar */
    .admin-topbar {
      background:#fff; border-bottom:1px solid #e5e7eb;
      padding:12px 32px; position:sticky; top:0; z-index:30;
      margin-left:220px; display:flex; align-items:center; justify-content:space-between;
    }

    /* Cards */
    .stat-card {
      background:#fff; border:1px solid #e5e7eb; border-radius:12px;
      padding:18px 20px; display:flex; align-items:center; gap:14px;
    }
    .stat-icon {
      width:44px; height:44px; border-radius:10px;
      background:#edf7ef; color:var(--ps-green);
      display:flex; align-items:center; justify-content:center; font-size:20px;
    }

    /* Table */
    .events-table { background:#fff; border-radius:12px; border:1px solid #e5e7eb; overflow:hidden; }
    .events-table thead th { background:#0c2d17; color:#fff; padding:11px 16px; font-size:12.5px; font-weight:600; border:none; }
    .events-table tbody td { padding:12px 16px; font-size:13.5px; vertical-align:middle; border-bottom:1px solid #f0f0f0; }
    .events-table tbody tr:hover { background:#f8faf8; }
    .events-table tbody tr:last-child td { border-bottom:none; }

    /* Modal */
    .modal-header { background:#0c2d17; color:#fff; }
    .modal-header .btn-close { filter:invert(1); }
    .form-label { font-size:13px; font-weight:600; }
    .form-control, .form-select { font-size:13.5px; border:1.5px solid #d1e8d4; border-radius:8px; }
    .form-control:focus, .form-select:focus { border-color:var(--ps-green); box-shadow:0 0 0 3px rgba(11,107,27,.10); }

    /* Btn */
    .btn-ps { background:var(--ps-green); color:#fff; border:none; border-radius:8px; font-weight:600; font-size:13.5px; padding:8px 18px; transition:all .2s; }
    .btn-ps:hover { background:var(--ps-green-dark); color:#fff; transform:translateY(-1px); }
  </style>
</head>
<body>

<!-- ── Sidebar ────────────────────────────────────────────────────────── -->
<aside class="admin-sidebar">
  <div class="sidebar-brand">
    <span>Parliamentary Services</span>
    <small>Admin Panel</small>
  </div>
  <nav class="mt-2">
    <a href="dashboard.php" class="nav-item-side"><i class="bi bi-grid-1x2"></i> Builder</a>
    <a href="events.php"    class="nav-item-side active"><i class="bi bi-calendar3"></i> Events</a>
    <a href="registrations.php" class="nav-item-side"><i class="bi bi-people"></i> Registrations</a>
    <a href="#"     class="nav-item-side"><i class="bi bi-person-gear"></i> Users</a>
    <a href="#"  class="nav-item-side"><i class="bi bi-gear"></i> Settings</a>
    <a href="#"     class="nav-item-side"><i class="bi bi-shield-check"></i> Audit Log</a>
  </nav>
  <div class="mt-auto p-3 border-top" style="border-color:rgba(255,255,255,.08)!important">
    <a href="../portal.php" class="nav-item-side" style="font-size:12px">
      <i class="bi bi-box-arrow-up-right"></i> View Portal
    </a>
    <a href="#" class="nav-item-side" style="font-size:12px" onclick="doLogout()">
      <i class="bi bi-box-arrow-left"></i> Sign Out
    </a>
  </div>
</aside>

<!-- ── Top bar ────────────────────────────────────────────────────────── -->
<div class="admin-topbar">
  <div class="d-flex align-items-center gap-2">
    <i class="bi bi-calendar3 text-success"></i>
    <span class="fw-semibold" style="font-size:15px">Events</span>
    <span class="text-muted" style="font-size:13px">/ Manage</span>
  </div>
  <div class="d-flex align-items-center gap-2">
    <div class="bg-light border rounded-pill px-3 py-1 d-flex align-items-center gap-2" style="font-size:12px" id="userChipTop">
      <i class="bi bi-person-circle text-success"></i>
      <span id="userNameTop">Admin</span>
    </div>
    <button class="btn-ps btn" data-bs-toggle="modal" data-bs-target="#createEventModal">
      <i class="bi bi-plus-lg me-1"></i> New Event
    </button>
  </div>
</div>

<!-- ── Main ──────────────────────────────────────────────────────────── -->
<main class="admin-main">

  <!-- Stats -->
  <div class="row g-3 mb-4" id="statsRow">
    <div class="col-6 col-md-3">
      <div class="stat-card reveal">
        <div class="stat-icon"><i class="bi bi-calendar3"></i></div>
        <div><div class="fw-bold fs-4" id="statTotal">—</div><div class="text-muted small">Total Events</div></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card reveal">
        <div class="stat-icon" style="background:#d1fae5;color:#065f46"><i class="bi bi-globe"></i></div>
        <div><div class="fw-bold fs-4" id="statPublished">—</div><div class="text-muted small">Published</div></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card reveal">
        <div class="stat-icon" style="background:#fef3c7;color:#92400e"><i class="bi bi-pencil-square"></i></div>
        <div><div class="fw-bold fs-4" id="statDraft">—</div><div class="text-muted small">Drafts</div></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card reveal">
        <div class="stat-icon" style="background:#fee2e2;color:#991b1b"><i class="bi bi-archive"></i></div>
        <div><div class="fw-bold fs-4" id="statClosed">—</div><div class="text-muted small">Closed</div></div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <input type="text" class="form-control form-control-sm" style="max-width:220px" placeholder="Search events…" id="searchInput" oninput="filterEvents()"/>
    <select class="form-select form-select-sm" style="max-width:140px" id="statusFilter" onchange="filterEvents()">
      <option value="">All statuses</option>
      <option value="draft">Draft</option>
      <option value="published">Published</option>
      <option value="closed">Closed</option>
    </select>
    <span class="text-muted small ms-auto" id="countLabel"></span>
  </div>

  <!-- Table -->
  <div class="events-table">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Event Name</th>
          <th>Dates</th>
          <th>Location</th>
          <th>Status</th>
          <th>Registrants</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="eventsTableBody">
        <tr><td colspan="6" class="text-center text-muted py-5">
          <div class="spinner-border spinner-border-sm text-success me-2"></div> Loading events…
        </td></tr>
      </tbody>
    </table>
  </div>

</main>

<!-- ── Create Event Modal ─────────────────────────────────────────────── -->
<div class="modal fade" id="createEventModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 rounded-4 overflow-hidden">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-calendar-plus me-2"></i>Create New Event
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div id="modalAlert" class="alert alert-danger d-none mb-3"></div>
        <div class="row g-3">
          <!-- EN/FR tabs -->
          <div class="col-12">
            <ul class="nav nav-tabs mb-3" id="langTabs">
              <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabEN">English</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabFR">Français</button></li>
            </ul>
            <div class="tab-content">
              <div class="tab-pane fade show active" id="tabEN">
                <div class="row g-3">
                  <div class="col-12"><label class="form-label">Event Name (EN) <span class="text-danger">*</span></label><input type="text" class="form-control" id="ev_name_en" placeholder="e.g. Global Diplomatic Forum 2026" oninput="autoSlug()"/></div>
                  <div class="col-12"><label class="form-label">Location (EN) <span class="text-danger">*</span></label><input type="text" class="form-control" id="ev_location_en" placeholder="e.g. Parliament House, Accra"/></div>
                </div>
              </div>
              <div class="tab-pane fade" id="tabFR">
                <div class="row g-3">
                  <div class="col-12"><label class="form-label">Event Name (FR)</label><input type="text" class="form-control" id="ev_name_fr" placeholder="e.g. Forum Diplomatique Mondial 2026"/></div>
                  <div class="col-12"><label class="form-label">Location (FR)</label><input type="text" class="form-control" id="ev_location_fr" placeholder="e.g. Parlement, Accra"/></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12"><label class="form-label">URL Slug <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text text-muted" style="font-size:12px">/events/</span><input type="text" class="form-control" id="ev_slug" placeholder="global-diplomatic-forum-2026"/></div><div class="form-text">Auto-generated. Only lowercase letters, numbers and hyphens.</div></div>
          <div class="col-6"><label class="form-label">Start Date <span class="text-danger">*</span></label><input type="datetime-local" class="form-control" id="ev_date_start"/></div>
          <div class="col-6"><label class="form-label">End Date <span class="text-danger">*</span></label><input type="datetime-local" class="form-control" id="ev_date_end"/></div>
          <div class="col-6"><label class="form-label">Registration Deadline</label><input type="datetime-local" class="form-control" id="ev_deadline"/></div>
          <div class="col-6"><label class="form-label">Max Capacity</label><input type="number" class="form-control" id="ev_capacity" placeholder="Leave blank for unlimited" min="1"/></div>
          <div class="col-6"><label class="form-label">Approval Mode</label>
            <select class="form-select" id="ev_approval">
              <option value="auto">Auto-Approve</option>
              <option value="manual">Manual Approval</option>
            </select>
          </div>
          <div class="col-6"><label class="form-label">Theme Colour</label><input type="color" class="form-control form-control-color w-100" id="ev_color" value="#0b6b1b"/></div>
        </div>
      </div>
      <div class="modal-footer border-0 px-4 pb-4">
        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn-ps btn px-5" id="createEventBtn" onclick="createNewEvent()">
          <span id="createBtnText">Create Event</span>
          <span class="spinner-border spinner-border-sm d-none ms-2" id="createSpinner"></span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="ps-toast" id="psToast"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/api.js"></script>
<script>
  let allEvents = [];

  // ── Init ───────────────────────────────────────────────────────────────
  window.addEventListener('DOMContentLoaded', async () => {
    const user = ParlRegAPI.getUser();
    if (user) document.getElementById('userNameTop').textContent = user.fullname || 'Admin';
    await loadEvents();
    // Scroll reveal
    const obs = new IntersectionObserver(entries => entries.forEach(e => { if(e.isIntersecting){ e.target.classList.add('visible'); obs.unobserve(e.target); } }), {threshold:.05});
    document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
  });

  async function loadEvents() {
    try {
      const res = await ParlRegAPI.get('/events?page=1');
      if (res.success) {
        allEvents = res.data || [];
        updateStats();
        renderTable(allEvents);
      } else { renderError('Could not load events.'); }
    } catch { renderError('API unavailable — start the PHP server.'); }
  }

  function updateStats() {
    document.getElementById('statTotal').textContent     = allEvents.length;
    document.getElementById('statPublished').textContent = allEvents.filter(e=>e.status==='published').length;
    document.getElementById('statDraft').textContent     = allEvents.filter(e=>e.status==='draft').length;
    document.getElementById('statClosed').textContent    = allEvents.filter(e=>e.status==='closed').length;
  }

  function renderTable(events) {
    const tbody = document.getElementById('eventsTableBody');
    document.getElementById('countLabel').textContent = events.length + ' event' + (events.length===1?'':'s');
    if (!events.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5">No events found.</td></tr>';
      return;
    }
    tbody.innerHTML = events.map(ev => {
      const start = ev.date_start ? new Date(ev.date_start).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}) : '—';
      const end   = ev.date_end   ? new Date(ev.date_end).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}) : '';
      const dates = end && end !== start ? start + ' – ' + end : start;
      const badge = `<span class="status-badge status-${ev.status}">${ev.status}</span>`;
      return `<tr>
        <td><strong>${ev.name_en}</strong><br/><span class="text-muted" style="font-size:11px">/events/${ev.slug}</span></td>
        <td class="text-muted" style="font-size:13px">${dates}</td>
        <td class="text-muted" style="font-size:13px">${ev.location_en||'—'}</td>
        <td>${badge}</td>
        <td><span class="badge-ps">${ev.registrant_count||0}</span></td>
        <td>
          <div class="d-flex gap-1">
            <a href="dashboard.php" class="btn btn-sm btn-outline-success" title="Open in Builder" onclick="sessionStorage.setItem('builder_event',${ev.id})">
              <i class="bi bi-pencil"></i>
            </a>
            <a href="registrations.php?event=${ev.id}" class="btn btn-sm btn-outline-secondary" title="View Registrations">
              <i class="bi bi-people"></i>
            </a>
            ${ev.status==='draft'
              ? `<button class="btn btn-sm btn-success" onclick="publishEvent(${ev.id})" title="Publish"><i class="bi bi-globe"></i></button>`
              : `<button class="btn btn-sm btn-outline-warning" onclick="unpublishEvent(${ev.id})" title="Unpublish"><i class="bi bi-eye-slash"></i></button>`}
            <button class="btn btn-sm btn-outline-primary" onclick="cloneEvent(${ev.id})" title="Clone">
              <i class="bi bi-copy"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteEvent(${ev.id},'${ev.name_en.replace(/'/g,"\\'")}')">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </td>
      </tr>`;
    }).join('');
  }

  function renderError(msg) {
    document.getElementById('eventsTableBody').innerHTML =
      `<tr><td colspan="6" class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-2"></i>${msg}</td></tr>`;
  }

  function filterEvents() {
    const q      = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    const filtered = allEvents.filter(ev =>
      (!q      || ev.name_en.toLowerCase().includes(q) || ev.slug.toLowerCase().includes(q)) &&
      (!status || ev.status === status)
    );
    renderTable(filtered);
  }

  // ── Auto-slug ──────────────────────────────────────────────────────────
  function autoSlug() {
    const name = document.getElementById('ev_name_en').value;
    document.getElementById('ev_slug').value = name
      .toLowerCase().trim()
      .replace(/[^a-z0-9\s\-]/g,'')
      .replace(/\s+/g,'-')
      .replace(/-+/g,'-');
  }

  // ── Create event ───────────────────────────────────────────────────────
  async function createNewEvent() {
    const btn     = document.getElementById('createEventBtn');
    const txt     = document.getElementById('createBtnText');
    const spinner = document.getElementById('createSpinner');
    const alertEl = document.getElementById('modalAlert');
    alertEl.classList.add('d-none');

    const name_en = document.getElementById('ev_name_en').value.trim();
    const slug    = document.getElementById('ev_slug').value.trim();
    const start   = document.getElementById('ev_date_start').value;
    const end     = document.getElementById('ev_date_end').value;

    if (!name_en || !slug || !start || !end) {
      alertEl.textContent = 'Please fill in all required fields.';
      alertEl.classList.remove('d-none');
      return;
    }

    btn.disabled = true; txt.textContent = 'Creating…'; spinner.classList.remove('d-none');

    try {
      const res = await ParlRegAPI.post('/events', {
        name_en, slug,
        name_fr:       document.getElementById('ev_name_fr').value.trim() || null,
        date_start:    start.replace('T',' ') + ':00',
        date_end:      end.replace('T',' ')   + ':00',
        location_en:   document.getElementById('ev_location_en').value.trim(),
        location_fr:   document.getElementById('ev_location_fr').value.trim() || null,
        approval_mode: document.getElementById('ev_approval').value,
        theme_color:   document.getElementById('ev_color').value,
        capacity:      document.getElementById('ev_capacity').value || null,
        registration_deadline: document.getElementById('ev_deadline').value
          ? document.getElementById('ev_deadline').value.replace('T',' ') + ':00' : null,
      });

      if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('createEventModal')).hide();
        showToast('✅ Event created!');
        await loadEvents();
      } else {
        alertEl.textContent = res.error || (res.errors ? Object.values(res.errors)[0] : 'Create failed.');
        alertEl.classList.remove('d-none');
      }
    } catch {
      alertEl.textContent = 'Cannot reach API. Is the PHP server running?';
      alertEl.classList.remove('d-none');
    } finally {
      btn.disabled = false; txt.textContent = 'Create Event'; spinner.classList.add('d-none');
    }
  }

  // ── Actions ────────────────────────────────────────────────────────────
  async function publishEvent(id) {
    const res = await ParlRegAPI.post('/events/' + id + '/publish');
    if (res.success) { showToast('🌐 Published!'); await loadEvents(); }
    else showToast('Error: ' + res.error);
  }
  async function unpublishEvent(id) {
    if (!confirm('Unpublish this event? The portal will go offline.')) return;
    const res = await ParlRegAPI.post('/events/' + id + '/unpublish');
    if (res.success) { showToast('Unpublished.'); await loadEvents(); }
  }
  async function cloneEvent(id) {
    const res = await ParlRegAPI.post('/events/' + id + '/clone');
    if (res.success) { showToast('📋 Event cloned!'); await loadEvents(); }
    else showToast('Clone failed: ' + res.error);
  }
  async function deleteEvent(id, name) {
    if (!confirm('Delete "' + name + '"? This cannot be undone.')) return;
    const res = await ParlRegAPI.del('/events/' + id);
    if (res.success) { showToast('Deleted.'); await loadEvents(); }
    else showToast('Delete failed: ' + res.error);
  }

  async function doLogout() {
    await ParlRegAPI.logout();
    window.location.href = 'login.php';
  }

  function showToast(msg) {
    const t = document.getElementById('psToast');
    t.textContent = msg; t.classList.add('show');
    clearTimeout(t._t); t._t = setTimeout(() => t.classList.remove('show'), 2800);
  }
</script>
</body>
</html>