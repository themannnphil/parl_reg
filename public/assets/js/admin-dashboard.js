/**
 * Admin Dashboard JavaScript
 * Handles admin panel functionality
 */

let currentAdmin = null;
let allEvents = [];
let allRegistrations = [];

document.addEventListener('DOMContentLoaded', async () => {
  await checkAuth();
  setupNavigation();
  await loadDashboard();
  setupLogout();
});

/**
 * Check if user is authenticated
 */
async function checkAuth() {
  try {
    const response = await api.request('GET', '/auth/me');
    currentAdmin = response.data || response.user;
    
    if (!currentAdmin || currentAdmin.role !== 'admin') {
      window.location.href = '/login.html';
      return;
    }

    document.getElementById('userName').textContent = currentAdmin.fullname || 'Admin';
  } catch (error) {
    console.log('Not authenticated, redirecting to login');
    window.location.href = '/login.html';
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

    // Load first event's registrations for preview
    if (allEvents.length > 0) {
      const regsRes = await api.request('GET', `/events/${allEvents[0].id}/registrations`);
      const registrations = regsRes.data || [];
      
      // Calculate stats
      const totalRegs = registrations.length;
      const pending = registrations.filter(r => r.status === 'pending').length;
      
      document.getElementById('totalRegistrations').textContent = totalRegs;
      document.getElementById('pendingApprovals').textContent = pending;
      document.getElementById('recentActivity').textContent = allEvents.length;

      // Show recent registrations
      const recentTable = document.getElementById('recentRegistrationsTable').querySelector('tbody');
      recentTable.innerHTML = '';
      
      registrations.slice(0, 5).forEach(reg => {
        const row = recentTable.insertRow();
        row.innerHTML = `
          <td><strong>${reg.fullname}</strong></td>
          <td>${reg.email}</td>
          <td>${allEvents.find(e => e.id === reg.event_id)?.name_en || 'Unknown'}</td>
          <td><span class="status-badge status-${reg.status}">${capitalize(reg.status)}</span></td>
          <td>${formatDate(reg.created_at)}</td>
        `;
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

    allEvents.forEach(event => {
      const row = table.insertRow();
      row.innerHTML = `
        <td><strong>${event.name_en}</strong></td>
        <td>${formatDate(event.date_start)}</td>
        <td>${event.location_en || 'N/A'}</td>
        <td><span class="badge bg-${event.status === 'published' ? 'success' : 'secondary'}">${capitalize(event.status)}</span></td>
        <td><span class="badge bg-info">0</span></td>
        <td>
          <button class="btn btn-sm btn-outline-primary" onclick="editEvent(${event.id})">Edit</button>
        </td>
      `;
    });
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
      row.innerHTML = `
        <td><strong>${reg.fullname}</strong></td>
        <td>${reg.email}</td>
        <td>${reg.organisation || 'N/A'}</td>
        <td>${reg.event?.name_en || 'Unknown'}</td>
        <td><span class="status-badge status-${reg.status}">${capitalize(reg.status)}</span></td>
        <td>${formatDate(reg.created_at)}</td>
      `;
    });

    if (allRegs.length === 0) {
      table.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No registrations found.</td></tr>';
    }

    // Setup search
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
        <td>${log.user_email || 'System'}</td>
        <td><span class="badge bg-secondary">${log.action}</span></td>
        <td><small>${log.details || ''}</small></td>
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
 * Edit event (placeholder)
 */
function editEvent(eventId) {
  alert(`Edit event ${eventId} - Feature coming soon`);
}

/**
 * Setup logout
 */
function setupLogout() {
  document.getElementById('logoutBtn').addEventListener('click', async (e) => {
    e.preventDefault();
    try {
      await api.request('POST', '/auth/logout');
      window.location.href = '/';
    } catch (error) {
      console.error('Logout failed:', error);
    }
  });
}

/**
 * Helper: Capitalize string
 */
function capitalize(str) {
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
