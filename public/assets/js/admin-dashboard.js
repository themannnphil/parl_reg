/**
 * Admin Dashboard JavaScript
 * Handles admin panel functionality
 */

let currentAdmin = null;
let allEvents = [];
let allRegistrations = [];
let eventModal = null;
let regModal = null;
let dashboardToast = null;
let currentReg = null; // Stores currently viewed registration

document.addEventListener('DOMContentLoaded', async () => {
  eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
  regModal = new bootstrap.Modal(document.getElementById('regModal'));
  dashboardToast = new bootstrap.Toast(document.getElementById('dashboardToast'));

  await checkAuth();
  setupNavigation();
  await loadDashboard();
  setupLogout();
  setupEventForm();
  setupRegistrationActions();
});

/**
 * Check if user is authenticated
 */
async function checkAuth() {
  const loginUrl = (typeof APP_BASE !== 'undefined' ? APP_BASE : '') + '/admin/login';
  try {
    const response = await api.request('GET', '/auth/me');
    currentAdmin = response.data || response.user;
    
    if (!currentAdmin) {
      window.location.href = loginUrl;
      return;
    }

    document.getElementById('userName').textContent = currentAdmin.fullname || 'Admin';
  } catch (error) {
    console.log('Not authenticated, redirecting to login');
    window.location.href = loginUrl;
  }
}

/**
 * Setup navigation between sections
 */
function setupNavigation() {
  document.querySelectorAll('.nav-item[data-section]').forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      const section = item.dataset.section;
      
      // Hide all sections
      document.querySelectorAll('.content-section').forEach(s => s.style.display = 'none');
      
      // Show selected section
      document.getElementById(section).style.display = 'block';
      
      // Update active nav
      document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
      item.classList.add('active');
      
      // Update title
      const titles = {
        dashboard: 'Dashboard',
        events: 'Events Management',
        registrations: 'Registrations',
        audit: 'Audit Log'
      };
      document.getElementById('pageTitle').textContent = titles[section] || 'Dashboard';
      
      // Load section data
      if (section === 'dashboard') loadDashboard();
      if (section === 'events') loadEvents();
      if (section === 'registrations') loadAllRegistrations();
      if (section === 'audit') loadAuditLog();
    });
  });
}

/**
 * Load dashboard data
 */
async function loadDashboard() {
  try {
    // Load events for stats
    const eventsRes = await api.request('GET', '/events');
    allEvents = eventsRes.data || [];
    document.getElementById('totalEvents').textContent = allEvents.length;

    let totalRegs = 0;
    let pending = 0;
    let recentRegs = [];

    // Load registrations across events
    for (const ev of allEvents) {
      try {
        const regsRes = await api.request('GET', `/events/${ev.id}/registrations`);
        const regs = regsRes.data || [];
        totalRegs += regs.length;
        pending += regs.filter(r => r.status === 'pending').length;
        recentRegs = recentRegs.concat(regs.map(r => ({ ...r, event_name: ev.name_en })));
      } catch (e) {
        console.warn(`Could not load registrations for event ${ev.id}`);
      }
    }

    document.getElementById('totalRegistrations').textContent = totalRegs;
    document.getElementById('pendingApprovals').textContent = pending;
    document.getElementById('recentActivity').textContent = allEvents.length;

    // Show recent registrations
    const recentTable = document.getElementById('recentRegistrationsTable').querySelector('tbody');
    recentTable.innerHTML = '';
    
    recentRegs.sort((a, b) => new Date(b.created_at || b.submitted_at) - new Date(a.created_at || a.submitted_at));

    if (recentRegs.length === 0) {
      recentTable.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No recent registrations.</td></tr>';
    } else {
      recentRegs.slice(0, 5).forEach(reg => {
        const row = recentTable.insertRow();
        row.style.cursor = 'pointer';
        row.innerHTML = `
          <td><strong>${escapeHtml(reg.fullname)}</strong></td>
          <td>${escapeHtml(reg.email)}</td>
          <td>${escapeHtml(reg.event_name)}</td>
          <td><span class="status-badge status-${reg.status}">${capitalize(reg.status)}</span></td>
          <td>${formatDate(reg.created_at || reg.submitted_at)}</td>
        `;
        row.addEventListener('click', () => showRegistrationDetails(reg.event_id, reg.id));
      });
    }
  } catch (error) {
    console.error('Failed to load dashboard:', error);
  }
}

/**
 * Load all events
 */
async function loadEvents() {
  try {
    const response = await api.request('GET', '/events');
    allEvents = response.data || [];

    const table = document.getElementById('eventsTable').querySelector('tbody');
    table.innerHTML = '';

    for (const event of allEvents) {
      const row = table.insertRow();
      let regCount = 0;
      try {
        const regsRes = await api.request('GET', `/events/${event.id}/registrations`);
        regCount = (regsRes.data || []).length;
      } catch (e) {}

      const base = typeof APP_BASE !== 'undefined' ? APP_BASE : '';
      const formBuilderUrl = `${base}/admin/form?id=${event.id}`;
      const portalUrl = `${base}/events/${event.slug}`;

      row.innerHTML = `
        <td><strong>${escapeHtml(event.name_en)}</strong></td>
        <td>${formatDate(event.date_start)}</td>
        <td>${escapeHtml(event.location_en || 'N/A')}</td>
        <td><span class="badge bg-${event.status === 'published' ? 'success' : event.status === 'closed' ? 'danger' : 'secondary'}">${capitalize(event.status)}</span></td>
        <td><span class="badge bg-info">${regCount}</span></td>
        <td>
          <div class="btn-group gap-1">
            <button class="btn btn-sm btn-outline-primary" onclick="editEventMetadata(${event.id})">Edit</button>
            <a class="btn btn-sm btn-outline-success" href="${formBuilderUrl}">Form Builder</a>
            <button class="btn btn-sm btn-outline-warning" onclick="togglePublishStatus(${event.id}, '${event.status}')">
              ${event.status === 'published' ? 'Unpublish' : 'Publish'}
            </button>
            <button class="btn btn-sm btn-outline-info" onclick="cloneEvent(${event.id})">Clone</button>
            <a class="btn btn-sm btn-outline-secondary" href="${portalUrl}" target="_blank">View Portal</a>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteEvent(${event.id})">Delete</button>
          </div>
        </td>
      `;
    }

    if (allEvents.length === 0) {
      table.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No events found.</td></tr>';
    }
  } catch (error) {
    console.error('Failed to load events:', error);
  }
}

/**
 * Load all registrations
 */
async function loadAllRegistrations() {
  try {
    const table = document.getElementById('allRegistrationsTable').querySelector('tbody');
    table.innerHTML = '';

    if (allEvents.length === 0) {
      const eventsRes = await api.request('GET', '/events');
      allEvents = eventsRes.data || [];
    }

    let allRegs = [];
    for (const event of allEvents) {
      try {
        const regsRes = await api.request('GET', `/events/${event.id}/registrations`);
        const registrations = regsRes.data || [];
        allRegs = allRegs.concat(registrations.map(r => ({ ...r, event })));
      } catch (e) {
        console.warn(`Could not load registrations for event ${event.id}`);
      }
    }

    allRegs.forEach(reg => {
      const row = table.insertRow();
      row.style.cursor = 'pointer';
      row.innerHTML = `
        <td><strong>${escapeHtml(reg.fullname)}</strong></td>
        <td>${escapeHtml(reg.email)}</td>
        <td>${escapeHtml(reg.organisation || 'N/A')}</td>
        <td>${escapeHtml(reg.event?.name_en || 'Unknown')}</td>
        <td><span class="status-badge status-${reg.status}">${capitalize(reg.status)}</span></td>
        <td>${formatDate(reg.created_at || reg.submitted_at)}</td>
      `;
      row.addEventListener('click', () => showRegistrationDetails(reg.event_id, reg.id));
    });

    if (allRegs.length === 0) {
      table.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No registrations found.</td></tr>';
    }

    // Setup search
    const searchInput = document.getElementById('searchInput');
    searchInput.replaceWith(searchInput.cloneNode(true)); // Clear previous listeners
    document.getElementById('searchInput').addEventListener('input', (e) => {
      const query = e.target.value.toLowerCase();
      table.querySelectorAll('tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
      });
    });
  } catch (error) {
    console.error('Failed to load registrations:', error);
  }
}

/**
 * Load audit log
 */
async function loadAuditLog() {
  try {
    const response = await api.request('GET', '/audit-log?limit=50');
    const logs = response.data || [];

    const table = document.getElementById('auditTable').querySelector('tbody');
    table.innerHTML = '';

    logs.forEach(log => {
      const row = table.insertRow();
      row.innerHTML = `
        <td>${formatDateTime(log.created_at)}</td>
        <td>${escapeHtml(log.user_email || 'System')}</td>
        <td><span class="badge bg-secondary">${escapeHtml(log.action)}</span></td>
        <td><small>${escapeHtml(log.details || '')}</small></td>
      `;
    });

    if (logs.length === 0) {
      table.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No audit logs found.</td></tr>';
    }
  } catch (error) {
    console.error('Failed to load audit log:', error);
  }
}

/**
 * Setup Event Create/Edit Modal and form actions
 */
function setupEventForm() {
  const form = document.getElementById('eventForm');
  const slugInput = document.getElementById('event_slug');
  const nameEnInput = document.getElementById('event_name_en');

  document.getElementById('createNewEventBtn').addEventListener('click', () => {
    form.reset();
    document.getElementById('event_id').value = '';
    document.getElementById('eventModalLabel').textContent = 'New Event';
    document.getElementById('eventModalAlert').style.display = 'none';
    eventModal.show();
  });

  // Auto-generate slug from name_en
  nameEnInput.addEventListener('input', () => {
    const eventId = document.getElementById('event_id').value;
    if (!eventId) {
      slugInput.value = nameEnInput.value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    document.getElementById('eventModalAlert').style.display = 'none';
    const eventId = document.getElementById('event_id').value;

    const data = {
      name_en: document.getElementById('event_name_en').value,
      name_fr: document.getElementById('event_name_fr').value || null,
      slug: document.getElementById('event_slug').value,
      theme_color: document.getElementById('event_theme_color').value,
      date_start: document.getElementById('event_date_start').value.replace('T', ' ') + ':00',
      date_end: document.getElementById('event_date_end').value.replace('T', ' ') + ':00',
      location_en: document.getElementById('event_location_en').value || null,
      location_fr: document.getElementById('event_location_fr').value || null,
      capacity: parseInt(document.getElementById('event_capacity').value) || null,
      approval_mode: document.getElementById('event_approval_mode').value
    };

    try {
      if (eventId) {
        await api.request('PUT', `/events/${eventId}`, data);
        showToast('Event updated successfully');
      } else {
        await api.request('POST', '/events', data);
        showToast('Event created successfully');
      }
      eventModal.hide();
      loadEvents();
    } catch (err) {
      document.getElementById('eventModalAlert').textContent = err.message;
      document.getElementById('eventModalAlert').style.display = 'block';
    }
  });
}

/**
 * Edit event metadata (loads details into modal)
 */
async function editEventMetadata(eventId) {
  try {
    const res = await api.request('GET', `/events/${eventId}`);
    const ev = res.data;
    if (!ev) return;

    document.getElementById('event_id').value = ev.id;
    document.getElementById('event_name_en').value = ev.name_en || '';
    document.getElementById('event_name_fr').value = ev.name_fr || '';
    document.getElementById('event_slug').value = ev.slug || '';
    document.getElementById('event_theme_color').value = ev.theme_color || '#1B3A6B';
    document.getElementById('event_date_start').value = (ev.date_start || '').substring(0, 16).replace(' ', 'T');
    document.getElementById('event_date_end').value = (ev.date_end || '').substring(0, 16).replace(' ', 'T');
    document.getElementById('event_location_en').value = ev.location_en || '';
    document.getElementById('event_location_fr').value = ev.location_fr || '';
    document.getElementById('event_capacity').value = ev.capacity || '';
    document.getElementById('event_approval_mode').value = ev.approval_mode || 'auto';

    document.getElementById('eventModalLabel').textContent = 'Edit Event';
    document.getElementById('eventModalAlert').style.display = 'none';
    eventModal.show();
  } catch (err) {
    showToast(`Error: ${err.message}`, true);
  }
}

/**
 * Toggle Event Published Status
 */
async function togglePublishStatus(eventId, currentStatus) {
  try {
    const action = currentStatus === 'published' ? 'unpublish' : 'publish';
    await api.request('POST', `/events/${eventId}/${action}`);
    showToast(`Event successfully ${action}ed`);
    loadEvents();
  } catch (err) {
    showToast(`Failed: ${err.message}`, true);
  }
}

/**
 * Clone Event
 */
async function cloneEvent(eventId) {
  if (!confirm('Are you sure you want to clone this event and its form schema?')) return;
  try {
    await api.request('POST', `/events/${eventId}/clone`);
    showToast('Event cloned successfully');
    loadEvents();
  } catch (err) {
    showToast(`Cloning failed: ${err.message}`, true);
  }
}

/**
 * Delete Event
 */
async function deleteEvent(eventId) {
  if (!confirm('Are you sure you want to delete this event? This action is permanent!')) return;
  try {
    await api.request('DELETE', `/events/${eventId}`);
    showToast('Event deleted successfully');
    loadEvents();
  } catch (err) {
    showToast(`Deletion failed: ${err.message}`, true);
  }
}

/**
 * Show Registration Details
 */
async function showRegistrationDetails(eventId, regId) {
  try {
    const res = await api.request('GET', `/events/${eventId}/registrations/${regId}`);
    const reg = res.data;
    if (!reg) return;

    currentReg = reg;

    document.getElementById('reg_fullname').textContent = reg.fullname || 'N/A';
    document.getElementById('reg_email').textContent = reg.email || 'N/A';
    document.getElementById('reg_phone').textContent = reg.phone || 'N/A';
    document.getElementById('reg_org').textContent = reg.organisation || 'N/A';
    document.getElementById('reg_country').textContent = reg.country || 'N/A';

    const statusBadge = document.getElementById('reg_status');
    statusBadge.textContent = capitalize(reg.status);
    statusBadge.className = `status-badge status-${reg.status}`;

    // Custom fields
    const customFieldsContainer = document.getElementById('reg_custom_fields');
    customFieldsContainer.innerHTML = '';
    const responses = reg.data_json || {};
    
    let count = 0;
    for (const [key, val] of Object.entries(responses)) {
      if (['fullname', 'email', 'phone', 'organisation', 'country', 'consent', 'lang'].includes(key)) continue;
      count++;
      const col = document.createElement('div');
      col.className = 'col-md-6';
      col.innerHTML = `<strong>${escapeHtml(key)}:</strong> <span>${escapeHtml(String(val))}</span>`;
      customFieldsContainer.appendChild(col);
    }
    if (count === 0) {
      customFieldsContainer.innerHTML = '<div class="col-12 text-muted">No custom form responses.</div>';
    }

    // Files
    const filesContainer = document.getElementById('reg_files');
    filesContainer.innerHTML = '';
    const files = reg.files || [];

    if (files.length === 0) {
      document.getElementById('reg_files_section').style.display = 'none';
    } else {
      document.getElementById('reg_files_section').style.display = 'block';
      files.forEach(file => {
        const item = document.createElement('div');
        item.className = 'list-group-item d-flex justify-content-between align-items-center';
        
        const sizeMb = (file.filesize / (1024 * 1024)).toFixed(2);
        const downloadUrl = (typeof APP_BASE !== 'undefined' ? APP_BASE : '') + `/api/v1/files/${file.id}`;
        
        item.innerHTML = `
          <div>
            <i class="bi bi-file-earmark-arrow-down me-2"></i>
            <strong>${escapeHtml(file.original_filename)}</strong>
            <span class="text-muted ms-2">(${sizeMb} MB - Field: ${escapeHtml(file.field_name)})</span>
          </div>
          <a href="${downloadUrl}" class="btn btn-sm btn-primary" target="_blank">Download</a>
        `;
        filesContainer.appendChild(item);
      });
    }

    regModal.show();
  } catch (err) {
    showToast(`Error: ${err.message}`, true);
  }
}

/**
 * Setup Registration Approval Actions
 */
function setupRegistrationActions() {
  document.getElementById('approveRegBtn').addEventListener('click', () => updateRegStatus('approved'));
  document.getElementById('rejectRegBtn').addEventListener('click', () => updateRegStatus('rejected'));
}

/**
 * Update current registration status
 */
async function updateRegStatus(newStatus) {
  if (!currentReg) return;
  try {
    await api.request('PUT', `/events/${currentReg.event_id}/registrations/${currentReg.id}/status`, {
      status: newStatus
    });
    showToast(`Registration status updated to ${newStatus}`);
    regModal.hide();
    
    // Reload whatever section is currently active
    const activeSection = document.querySelector('.nav-item.active').dataset.section;
    if (activeSection === 'dashboard') loadDashboard();
    if (activeSection === 'registrations') loadAllRegistrations();
  } catch (err) {
    alert(`Failed to update status: ${err.message}`);
  }
}

/**
 * Setup logout
 */
function setupLogout() {
  document.getElementById('logoutBtn').addEventListener('click', async (e) => {
    e.preventDefault();
    const loginUrl = (typeof APP_BASE !== 'undefined' ? APP_BASE : '') + '/admin/login';
    try {
      await api.request('POST', '/auth/logout');
    } catch (error) {
      console.error('Logout failed:', error);
    } finally {
      localStorage.removeItem('csrf_token');
      localStorage.removeItem('user');
      window.location.href = loginUrl;
    }
  });
}

/**
 * Helper: Show visual toast notification
 */
function showToast(message, isError = false) {
  document.getElementById('toastTitle').textContent = isError ? '⚠️ Error' : '✅ Success';
  document.getElementById('toastBody').textContent = message;
  dashboardToast.show();
}

/**
 * Helper: Capitalize string
 */
function capitalize(str) {
  if (!str) return '';
  return str.charAt(0).toUpperCase() + str.slice(1);
}

/**
 * Helper: Format date
 */
function formatDate(dateString) {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric' 
  });
}

/**
 * Helper: Format date and time
 */
function formatDateTime(dateString) {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleString('en-US', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

/**
 * Helper: Escape HTML
 */
function escapeHtml(str) {
  if (!str) return '';
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

// Make callback functions globally accessible for inline onclick handlers
window.editEventMetadata = editEventMetadata;
window.togglePublishStatus = togglePublishStatus;
window.cloneEvent = cloneEvent;
window.deleteEvent = deleteEvent;
window.showRegistrationDetails = showRegistrationDetails;
